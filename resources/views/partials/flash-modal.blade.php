{{--
    Single source of truth for post-redirect flash notifications.
    Controllers should flash one of: 'success', 'warning', 'error' (or the
    legacy 'status' key, treated as an alias for 'success') with a
    human-readable sentence — never a raw status slug.
    Requires <x-confirm-modal /> + resources/js/modal-confirm.js to already
    be included on the page.
--}}
@php
    $flashType = null;
    $flashMessage = null;
    $flashTitle = null;

    if (session('success') || session('status')) {
        $flashType = 'success';
        $flashMessage = session('success') ?? session('status');
        $flashTitle = 'Done';
    } elseif (session('warning')) {
        $flashType = 'warning';
        $flashMessage = session('warning');
        $flashTitle = 'Heads up';
    } elseif (session('error')) {
        $flashType = 'error';
        $flashMessage = session('error');
        $flashTitle = 'Something went wrong';
    }
@endphp

@if ($flashType)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.dispatchEvent(new CustomEvent('show-modal', {
                detail: {
                    type: @json($flashType),
                    title: @json($flashTitle),
                    message: @json($flashMessage),
                }
            }));
        });
    </script>
@endif
