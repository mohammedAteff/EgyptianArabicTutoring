<?php

namespace Tests\Feature;

use App\Domains\System\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupAssetTraversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_deeply_nested_assets_with_source_file_directory_names_are_archived_without_omission(): void
    {
        Storage::fake('managed_backups');
        Storage::fake('local');
        $root = Storage::disk('local')->path('');
        $relative = implode('/', array_fill(0, 22, str_repeat('nested-', 8).'source.php')).'/asset.bin';
        $path = $root.'/'.$relative;
        File::ensureDirectoryExists(dirname($path));
        $contents = "Synthetic required asset\x00\xff العربية";
        File::put($path, $contents);
        $service = new class extends BackupService
        {
            public function archiveAssets(ZipArchive $zip, string $root): array
            {
                $hashes = [];
                $this->addDirectoryToZip($zip, $root, 'storage/private', $hashes, Storage::disk('managed_backups')->path(''));

                return $hashes;
            }
        };
        $archive = Storage::disk('managed_backups')->path('nested-assets.zip');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE));

        try {
            $hashes = $service->archiveAssets($zip, $root);
        } finally {
            $zip->close();
        }

        $this->assertSame(['storage/private/'.$relative => ['sha256' => hash('sha256', $contents), 'size_bytes' => strlen($contents)]], $hashes);
        $this->assertTrue($zip->open($archive));
        $this->assertSame($contents, $zip->getFromName('storage/private/'.$relative));
        $zip->close();
    }
}
