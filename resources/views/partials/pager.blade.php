@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-hidden="true"><x-icon name="back" :size="16" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">
                <x-icon name="back" :size="16" />
            </a>
        @endif

        @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            @if ($page == $paginator->currentPage())
                <span class="is-current" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">
                <x-icon name="next" :size="16" />
            </a>
        @else
            <span class="is-disabled" aria-hidden="true"><x-icon name="next" :size="16" /></span>
        @endif
    </nav>
@endif
