<?php

namespace App\Domains\Booking\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Resources\Models\Resource;
use App\Rules\SafeLessonUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class LessonMaterialService
{
    public function __construct(private AuditLogService $audits) {}

    /** @param array<string, mixed> $data */
    public function attach(Booking $booking, array $data, int $actorId): LessonMaterial
    {
        $path = null;
        try {
            return DB::transaction(function () use ($booking, $data, $actorId, &$path): LessonMaterial {
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                if (! $locked->student()->whereNull('merged_into_student_id')->exists()) {
                    throw ValidationException::withMessages(['title' => 'This lesson needs an active student record before materials can be attached.']);
                }
                if ($data['kind'] === 'resource') {
                    Resource::query()->published()->lockForUpdate()->findOrFail((int) $data['resource_id']);
                }
                if ($data['kind'] === 'private_file') {
                    /** @var UploadedFile $file */
                    $file = $data['file'];
                    $stored = $file->storeAs('lesson-materials', Str::uuid().'.pdf', 'local');
                    if (! is_string($stored)) {
                        throw ValidationException::withMessages(['file' => 'The PDF could not be saved. Please retry.']);
                    }
                    $path = $stored;
                }
                $material = $locked->lessonMaterials()->create([
                    'created_by' => $actorId, 'kind' => $data['kind'], 'title' => $data['title'],
                    'description' => $data['description'] ?? null, 'student_visible' => $data['student_visible'],
                    'sort_order' => $data['sort_order'], 'resource_id' => $data['kind'] === 'resource' ? $data['resource_id'] : null,
                    'url' => in_array($data['kind'], ['external_link', 'recording'], true) ? $data['url'] : null,
                    'disk' => $path ? 'local' : null, 'path' => $path,
                ]);
                $this->audit($material, $material->kind === 'recording' ? 'lesson_recording_attached' : 'lesson_material_attached', $actorId);

                return $material;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Booking $booking, LessonMaterial $material, array $data, int $actorId): void
    {
        DB::transaction(function () use ($booking, $material, $data, $actorId): void {
            Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $locked = $this->lockedMaterial($booking, $material);
            abort_if($locked->withdrawn_at !== null, 404);
            $previous = $locked->only(['student_visible', 'sort_order']);
            $locked->update($data);
            $this->audit($locked, $previous['student_visible'] !== $locked->student_visible
                ? 'lesson_material_visibility_changed' : 'lesson_material_updated', $actorId, $previous);
        });
    }

    public function withdraw(Booking $booking, LessonMaterial $material, int $actorId): bool
    {
        $locked = DB::transaction(function () use ($booking, $material, $actorId): LessonMaterial {
            Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $locked = $this->lockedMaterial($booking, $material);
            if (! $locked->withdrawn_at) {
                $locked->update(['withdrawn_at' => now('UTC'), 'student_visible' => false, 'url' => null, 'resource_id' => null]);
                $this->audit($locked, $locked->kind === 'recording' ? 'lesson_recording_withdrawn' : 'lesson_material_withdrawn', $actorId);
            }

            return $locked;
        });

        return $this->cleanup($locked);
    }

    /** Booking locks are already held by the privacy service.
     * @param  list<int>  $bookingIds
     */
    public function eraseForBookings(array $bookingIds, ?int $actorId): void
    {
        LessonMaterial::query()->whereIn('booking_id', $bookingIds)->orderBy('id')->lockForUpdate()->get()
            ->each(function (LessonMaterial $material) use ($actorId): void {
                $material->update([
                    'title' => 'Redacted lesson material', 'description' => null, 'url' => null, 'resource_id' => null,
                    'student_visible' => false, 'withdrawn_at' => $material->withdrawn_at ?? now('UTC'),
                ]);
                $this->audit($material, 'lesson_material_privacy_erased', $actorId);
                DB::afterCommit(function () use ($material): void {
                    if (! $this->cleanup($material)) {
                        Log::warning('Lesson PDF privacy cleanup pending.', ['material_id' => $material->id]);
                    }
                });
            });
    }

    public function cleanup(LessonMaterial $material): bool
    {
        if (! $material->withdrawn_at || $material->path === null) {
            return true;
        }
        if ($material->disk !== 'local' || ! $this->isLessonPath($material->path)) {
            return false;
        }
        try {
            $file = $this->privatePath($material->path, 'lesson-materials');
            if ($file !== null && ! Storage::disk('local')->delete($material->path)) {
                return false;
            }
            if ($file === null && Storage::disk('local')->exists($material->path)) {
                return false;
            }
            $material->update(['disk' => null, 'path' => null]);

            return true;
        } catch (Throwable) {
            Log::warning('Lesson PDF cleanup pending.', ['material_id' => $material->id]);

            return false;
        }
    }

    public function open(LessonMaterial $material): BinaryFileResponse|RedirectResponse
    {
        if (in_array($material->kind, ['external_link', 'recording'], true)) {
            return $this->external($material->url);
        }
        $path = $material->path;
        $directory = 'lesson-materials';
        $extension = 'pdf';
        if ($material->kind === 'resource') {
            $resource = $material->resource;
            abort_unless($resource && $resource->isPublished(), 404);
            if ($resource->external_url) {
                return $this->external($resource->external_url);
            }
            $path = $resource->file_path;
            $directory = 'resources';
            $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
            abort_unless(in_array($extension, ['pdf', 'zip', 'doc', 'docx', 'mp3', 'wav', 'm4a'], true), 404);
        } else {
            abort_unless($material->disk === 'local' && is_string($path) && $this->isLessonPath($path), 404);
        }
        $file = is_string($path) ? $this->privatePath($path, $directory) : null;
        abort_if($file === null, 404);

        return response()->download($file, (Str::slug($material->title) ?: 'lesson-material').'.'.$extension, [
            'Content-Type' => $extension === 'pdf' ? 'application/pdf' : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function external(?string $url): RedirectResponse
    {
        abort_unless(is_string($url) && SafeLessonUrl::isSafe($url), 404);

        return redirect()->away($url, 302, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    private function isLessonPath(string $path): bool
    {
        return preg_match('~\Alesson-materials/[0-9a-f-]{36}\.pdf\z~D', $path) === 1;
    }

    private function privatePath(string $path, string $directory): ?string
    {
        if (! str_starts_with($path, $directory.'/') || str_contains($path, '\\') || str_contains($path, "\0")
            || preg_match('~(?:^|/)\.\.?(?:/|$)~', $path)) {
            return null;
        }
        $root = realpath(Storage::disk('local')->path($directory));
        $file = realpath(Storage::disk('local')->path($path));
        if ($root === false || $file === false || ! is_file($file)) {
            return null;
        }
        $normalizedRoot = str_replace('\\', '/', $root).'/';
        $normalizedFile = str_replace('\\', '/', $file);

        return str_starts_with($normalizedFile, $normalizedRoot) ? $file : null;
    }

    private function lockedMaterial(Booking $booking, LessonMaterial $material): LessonMaterial
    {
        return $booking->lessonMaterials()->whereKey($material->id)->lockForUpdate()->firstOrFail();
    }

    /** @param array<string, mixed>|null $previous */
    private function audit(LessonMaterial $material, string $action, ?int $actorId, ?array $previous = null): void
    {
        $this->audits->log($action, LessonMaterial::class, $material->id, $previous,
            ['booking_id' => $material->booking_id, 'kind' => $material->kind, 'student_visible' => $material->student_visible, 'sort_order' => $material->sort_order], $actorId);
    }
}
