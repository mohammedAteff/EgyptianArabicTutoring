<?php

namespace App\View\Components\Lms;

use App\Domains\Lms\Services\LmsContentService;
use Illuminate\View\Component;
use Illuminate\View\View;

class RichTextEditor extends Component
{
    public string $html;

    public function __construct(LmsContentService $content, public string $id, string $value = '')
    {
        $this->html = $content->sanitizeText($value);
    }

    public function render(): View
    {
        return view('components.lms.rich-text-editor');
    }
}
