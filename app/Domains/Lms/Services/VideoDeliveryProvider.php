<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\VideoProviderConnection;

interface VideoDeliveryProvider
{
    public function create(VideoProviderConnection $connection, string $title): string;

    /** @return array{status:int,duration:int,has_mp4_fallback:bool} */
    public function video(VideoProviderConnection $connection, string $videoId): array;

    public function delete(VideoProviderConnection $connection, string $videoId): void;

    public function verifyProtection(VideoProviderConnection $connection, string $domain): void;

    /** @return array<string,string|int> */
    public function uploadAuthorization(VideoProviderConnection $connection, string $videoId, int $expires): array;

    public function playbackUrl(VideoProviderConnection $connection, string $videoId, int $expires): string;
}
