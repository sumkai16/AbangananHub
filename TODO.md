# AbangananHub - TODO

- [x] Updated guest auth header links in `resources/views/layouts/app.blade.php` to trigger `openAuthModal('login'/'register')` instead of navigating.
- [x] Found and fixed the remaining redirect: `resources/views/properties/index.blade.php`'s guest favorite-toggle handler hard-navigated via `window.location.href = "{{ route('login') }}"` instead of opening the modal — swapped for `openAuthModal('login')`, matching the header links.
