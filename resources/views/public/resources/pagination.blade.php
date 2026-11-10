<nav class="resources-pager" aria-label="{{ website_text('resources', 'Resource pages') }}">
    @if($resources->onFirstPage())
        <span aria-disabled="true">{{ website_text('resources', 'Previous') }}</span>
    @else
        <a href="{{ $resources->previousPageUrl() }}" rel="prev">{{ website_text('resources', 'Previous') }}</a>
    @endif
    <span aria-current="page">{{ strtr(website_text('resources', 'Page {page} of {pages}'), ['{page}' => $resources->currentPage(), '{pages}' => $resources->lastPage()]) }}</span>
    @if($resources->hasMorePages())
        <a href="{{ $resources->nextPageUrl() }}" rel="next">{{ website_text('resources', 'Next') }}</a>
    @else
        <span aria-disabled="true">{{ website_text('resources', 'Next') }}</span>
    @endif
</nav>
