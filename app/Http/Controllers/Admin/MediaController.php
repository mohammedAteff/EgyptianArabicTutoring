<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Media;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function picker(Request $request): JsonResponse
    {
        $media = Media::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Media $m) => [
                'id' => $m->id,
                'filename' => $m->filename,
                'path' => $m->path,
                'url' => $m->url(),
                'mime_type' => $m->mime_type,
                'file_size' => $m->file_size,
                'dimensions' => $m->dimensions,
                'alt_text' => $m->alt_text,
            ]);

        return response()->json($media);
    }

    public function index(): View
    {
        $media = Media::query()
            ->orderByDesc('created_at')
            ->paginate(24);

        $totalBytes = Media::sum('file_size');

        return view('admin.media.index', [
            'title' => 'Media & Asset Library',
            'media' => $media,
            'totalBytes' => $totalBytes,
            'totalBytesFormatted' => $this->formatBytes($totalBytes),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                // SVG is intentionally excluded: uploaded SVG is served from
                // a public disk and can carry active script content.
                'mimes:jpeg,jpg,png,webp,gif,pdf',
                'max:10240', // 10 MB
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $uploadedFile = $request->file('file');
        $originalFilename = $uploadedFile->getClientOriginalName();
        $path = $uploadedFile->store('media', 'public');

        $dimensions = null;
        if (str_starts_with($uploadedFile->getMimeType(), 'image/')) {
            $imageSize = @getimagesize($uploadedFile->getRealPath());
            if ($imageSize) {
                $dimensions = ['width' => $imageSize[0], 'height' => $imageSize[1]];
            }
        }

        $media = Media::create([
            'filename' => $originalFilename,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $uploadedFile->getMimeType(),
            'file_size' => $uploadedFile->getSize(),
            'dimensions' => $dimensions,
            'alt_text' => $validated['alt_text'] ?? null,
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'media_uploaded',
            'entity_type' => Media::class,
            'entity_id' => $media->id,
            'new_data' => [
                'filename' => $media->filename,
                'path' => $media->path,
                'size' => $media->file_size,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "Media asset '{$originalFilename}' uploaded successfully.");
    }

    public function destroy(Media $media): RedirectResponse
    {
        $filename = $media->filename;

        $references = $media->getReferences();
        if (! empty($references)) {
            $msg = "Cannot delete media asset '{$filename}' because it is currently referenced by: ".implode(', ', $references).'.';
            if (request()->wantsJson()) {
                abort(422, $msg);
            }

            return back()->with('error', $msg);
        }

        if (Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'media_deleted',
            'entity_type' => Media::class,
            'entity_id' => $media->id,
            'previous_data' => ['filename' => $filename],
            'created_at' => now(),
        ]);

        return back()->with('success', "Media asset '{$filename}' deleted.");
    }

    protected function formatBytes(float|int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
