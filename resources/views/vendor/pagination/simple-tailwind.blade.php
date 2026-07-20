@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex gap-2 items-center justify-between">

        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#64748B] bg-white border border-[#E2E8F0] cursor-not-allowed leading-5 rounded-md dark:text-[#94A3B8] dark:bg-[#1F2937] dark:border-[#64748B]">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#1F2937] bg-white border border-[#E2E8F0] leading-5 rounded-md hover:text-[#1F2937] focus:outline-none focus:ring ring-[#E2E8F0] focus:border-[#2AA7A1]/35 active:bg-[#EEF8F8] active:text-[#1F2937] transition ease-in-out duration-150 dark:bg-[#1F2937] dark:border-[#64748B] dark:text-[#94A3B8] dark:focus:border-[#156F8C] dark:active:bg-[#1F2937] dark:active:text-[#94A3B8] hover:bg-[#EEF8F8] dark:hover:bg-[#1F2937] dark:hover:text-[#94A3B8]">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#1F2937] bg-white border border-[#E2E8F0] leading-5 rounded-md hover:text-[#1F2937] focus:outline-none focus:ring ring-[#E2E8F0] focus:border-[#2AA7A1]/35 active:bg-[#EEF8F8] active:text-[#1F2937] transition ease-in-out duration-150 dark:bg-[#1F2937] dark:border-[#64748B] dark:text-[#94A3B8] dark:focus:border-[#156F8C] dark:active:bg-[#1F2937] dark:active:text-[#94A3B8] hover:bg-[#EEF8F8] dark:hover:bg-[#1F2937] dark:hover:text-[#94A3B8]">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#64748B] bg-white border border-[#E2E8F0] cursor-not-allowed leading-5 rounded-md dark:text-[#94A3B8] dark:bg-[#1F2937] dark:border-[#64748B]">
                {!! __('pagination.next') !!}
            </span>
        @endif

    </nav>
@endif
