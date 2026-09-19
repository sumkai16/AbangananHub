@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex gap-2 items-center justify-between">

        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#5B6A8E] bg-white border border-[#E2E4EC] cursor-not-allowed leading-5 rounded-md dark:text-[#94A3B8] dark:bg-[#060D26] dark:border-[#5B6A8E]">
                {!! __('pagination.previous') !!}
            </span>
        @else
<<<<<<< HEAD
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#060D26] bg-white border border-[#E2E4EC] leading-5 rounded-md hover:text-[#060D26] focus:outline-none focus:ring ring-[#E2E4EC] focus:border-[#DA8E77]/35 active:bg-[#ECEEF6] active:text-[#060D26] transition ease-in-out duration-150 dark:bg-[#060D26] dark:border-[#5B6A8E] dark:text-[#94A3B8] dark:focus:border-[#060D26] dark:active:bg-[#060D26] dark:active:text-[#94A3B8] hover:bg-[#ECEEF6] dark:hover:bg-[#060D26] dark:hover:text-[#94A3B8]">
=======
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#060D26] bg-white border border-[#E2E4EC] leading-5 rounded-md hover:text-[#060D26] focus:outline-none focus:ring ring-[#E2E4EC] focus:border-[#FF8A66]/35 active:bg-[#ECEEF6] active:text-[#060D26] transition ease-in-out duration-150 dark:bg-[#060D26] dark:border-[#5B6A8E] dark:text-[#94A3B8] dark:focus:border-[#060D26] dark:active:bg-[#060D26] dark:active:text-[#94A3B8] hover:bg-[#ECEEF6] dark:hover:bg-[#060D26] dark:hover:text-[#94A3B8]">
>>>>>>> 092fb1454a20ae889717d4d8b1bee67f9c0c8eaa
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
<<<<<<< HEAD
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#060D26] bg-white border border-[#E2E4EC] leading-5 rounded-md hover:text-[#060D26] focus:outline-none focus:ring ring-[#E2E4EC] focus:border-[#DA8E77]/35 active:bg-[#ECEEF6] active:text-[#060D26] transition ease-in-out duration-150 dark:bg-[#060D26] dark:border-[#5B6A8E] dark:text-[#94A3B8] dark:focus:border-[#060D26] dark:active:bg-[#060D26] dark:active:text-[#94A3B8] hover:bg-[#ECEEF6] dark:hover:bg-[#060D26] dark:hover:text-[#94A3B8]">
=======
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#060D26] bg-white border border-[#E2E4EC] leading-5 rounded-md hover:text-[#060D26] focus:outline-none focus:ring ring-[#E2E4EC] focus:border-[#FF8A66]/35 active:bg-[#ECEEF6] active:text-[#060D26] transition ease-in-out duration-150 dark:bg-[#060D26] dark:border-[#5B6A8E] dark:text-[#94A3B8] dark:focus:border-[#060D26] dark:active:bg-[#060D26] dark:active:text-[#94A3B8] hover:bg-[#ECEEF6] dark:hover:bg-[#060D26] dark:hover:text-[#94A3B8]">
>>>>>>> 092fb1454a20ae889717d4d8b1bee67f9c0c8eaa
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#5B6A8E] bg-white border border-[#E2E4EC] cursor-not-allowed leading-5 rounded-md dark:text-[#94A3B8] dark:bg-[#060D26] dark:border-[#5B6A8E]">
                {!! __('pagination.next') !!}
            </span>
        @endif

    </nav>
@endif
