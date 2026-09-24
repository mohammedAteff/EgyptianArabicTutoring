<?php

namespace App\Domains\Booking\Services;

use App\Domains\CMS\Models\Setting;
use Illuminate\Support\Str;

class MeetingLinkService
{
    public function current(): ?string
    {
        $url = Setting::get('video_meeting_url');

        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return $url !== '' && Str::isUrl($url, ['https']) ? $url : null;
    }
}
