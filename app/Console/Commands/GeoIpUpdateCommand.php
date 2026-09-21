<?php

namespace App\Console\Commands;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeoIpUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geoip:update 
                            {--url= : Direct download URL for GeoLite2-Country database or tar.gz}
                            {--path= : Local .mmdb file path to atomically install}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download or atomically install the MaxMind GeoLite2-Country database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetDir = storage_path('geoip');
        $targetFile = (string) config('services.geoip.database_path', $targetDir.'/GeoLite2-Country.mmdb');

        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true, true);
        }

        $localSourcePath = $this->option('path');
        $downloadUrl = $this->option('url') ?: config('services.geoip.download_url');
        $licenseKey = config('services.geoip.license_key');

        if ($localSourcePath) {
            if (! File::exists($localSourcePath)) {
                $this->error("Local source file does not exist: {$localSourcePath}");

                return Command::FAILURE;
            }

            return $this->atomicallyInstallFile($localSourcePath, $targetFile);
        }

        if (! $downloadUrl && $licenseKey) {
            $downloadUrl = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key={$licenseKey}&suffix=tar.gz";
        }

        if (! $downloadUrl) {
            $this->warn('No download URL, MAXMIND_LICENSE_KEY, or local --path provided.');
            $this->line('Usage examples:');
            $this->line('  php artisan geoip:update --path=/path/to/GeoLite2-Country.mmdb');
            $this->line('  php artisan geoip:update --url=https://example.com/GeoLite2-Country.mmdb');
            $this->line('Or define MAXMIND_LICENSE_KEY or GEOIP_DOWNLOAD_URL in your .env file.');

            return Command::INVALID;
        }

        $this->info('Downloading GeoLite2 database from '.$this->redactCredentials($downloadUrl).'...');

        // Keep a tar.gz suffix so PharData can reliably inspect MaxMind
        // archives on PHP installations that select archive handlers by name.
        $tempDownload = $targetDir.'/download_'.uniqid().'.tar.gz';

        $mmdbTempPath = null;

        try {
            $response = Http::timeout(120)->sink($tempDownload)->get($downloadUrl);

            if (! $response->successful()) {
                $this->error('Download failed with HTTP status: '.$response->status());
                File::delete($tempDownload);

                return Command::FAILURE;
            }

            // Check if file is tar.gz
            $mmdbTempPath = $targetDir.'/extracted_'.uniqid().'.mmdb';

            if (str_ends_with(strtolower($downloadUrl), '.tar.gz') || $this->isGzip($tempDownload)) {
                $this->info('Extracting tar.gz archive...');
                $extracted = $this->extractMmdbFromTarGz($tempDownload, $mmdbTempPath);
                File::delete($tempDownload);

                if (! $extracted) {
                    $this->error('Failed to locate GeoLite2-Country.mmdb within the downloaded archive.');

                    return Command::FAILURE;
                }
            } else {
                File::move($tempDownload, $mmdbTempPath);
            }

            return $this->atomicallyInstallFile($mmdbTempPath, $targetFile, true);
        } catch (Throwable $e) {
            $this->error('GeoIP update failed: '.$e->getMessage());
            if (File::exists($tempDownload)) {
                File::delete($tempDownload);
            }

            return Command::FAILURE;
        } finally {
            if (File::exists($tempDownload)) {
                File::delete($tempDownload);
            }
            if ($mmdbTempPath && File::exists($mmdbTempPath)) {
                File::delete($mmdbTempPath);
            }
        }
    }

    /**
     * Verify and atomically install a temporary MMDB file into target location.
     */
    protected function atomicallyInstallFile(string $sourceFile, string $targetFile, bool $isTemp = false): int
    {
        if (! File::exists($sourceFile) || (int) File::size($sourceFile) < 1024) {
            $this->error('GeoLite2 database file is missing or implausibly small.');
            if ($isTemp) {
                File::delete($sourceFile);
            }

            return Command::FAILURE;
        }

        // 1. Verify database can be parsed by Reader
        try {
            $reader = new Reader($sourceFile);
            $metadata = $reader->metadata();
            if (! str_contains(strtolower((string) $metadata->databaseType), 'country')) {
                throw new \RuntimeException('The file is not a country database.');
            }
            $this->info("Verified database: {$metadata->databaseType} (built {$metadata->buildEpoch})");
            $reader->close();
        } catch (Throwable $e) {
            $this->error('Invalid GeoLite2 database file: '.$e->getMessage());
            if ($isTemp) {
                File::delete($sourceFile);
            }

            return Command::FAILURE;
        }

        // 2. Atomic swap using temporary target in same directory
        $stagingPath = $targetFile.'.staging.'.uniqid();

        if ($isTemp) {
            File::move($sourceFile, $stagingPath);
        } else {
            File::copy($sourceFile, $stagingPath);
        }

        // Atomic rename on filesystem
        if (rename($stagingPath, $targetFile)) {
            foreach (glob($targetFile.'.staging.*') ?: [] as $obsoleteStaging) {
                if ($obsoleteStaging !== $targetFile) {
                    File::delete($obsoleteStaging);
                }
            }
            $this->info("Successfully updated GeoLite2 database: {$targetFile}");

            return Command::SUCCESS;
        }

        $this->error("Atomic swap failed when moving {$stagingPath} to {$targetFile}");
        File::delete($stagingPath);

        return Command::FAILURE;
    }

    protected function isGzip(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return false;
        }
        $header = fread($handle, 2);
        fclose($handle);

        return $header === "\x1f\x8b";
    }

    protected function redactCredentials(string $url): string
    {
        return (string) preg_replace('/([?&](?:license_key|password|token|key)=)[^&]*/i', '$1[redacted]', $url);
    }

    protected function extractMmdbFromTarGz(string $archivePath, string $outputPath): bool
    {
        try {
            $phar = new \PharData($archivePath);
            foreach (new \RecursiveIteratorIterator($phar) as $file) {
                if (str_ends_with($file->getFilename(), '.mmdb')) {
                    copy($file->getPathname(), $outputPath);

                    return true;
                }
            }
        } catch (Throwable $e) {
            $this->warn('Phar extraction error: '.$e->getMessage());
        }

        return false;
    }
}
