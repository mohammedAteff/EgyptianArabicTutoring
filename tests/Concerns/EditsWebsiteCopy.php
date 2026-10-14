<?php

namespace Tests\Concerns;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Services\WebsiteCopyService;
use Illuminate\Testing\TestResponse;

trait EditsWebsiteCopy
{
    /** @param array<string, mixed> $changes */
    protected function saveWebsiteCopy(Administrator $actor, string $page, array $changes, string $action = 'publish', string $locale = 'en'): TestResponse
    {
        request()->attributes->remove('website_copy_documents');
        $copies = app(WebsiteCopyService::class);
        $data = ['page' => $page, 'locale' => $locale, 'values' => array_replace($copies->values($page, $locale, true), $changes),
            'state' => $copies->state($page, $locale), 'action' => $action, 'reviewed_source' => 1];

        return $this->actingAs($actor, 'web')->post(route('admin.website-copy.update'), $data);
    }
}
