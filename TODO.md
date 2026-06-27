# AbangananHub - TODO

- [x] Updated guest auth header links in `resources/views/layouts/app.blade.php` to trigger `openAuthModal('login'/'register')` instead of navigating.
- [ ] Find and update remaining `login` / `register` links (likely in another navbar/partial/component) so they also open the auth modal.
- [ ] Add defensive `return false` / prevent-default behavior to modal trigger buttons.
- [ ] Investigate why click still redirects to `/login` (likely another link still exists, or a script swaps content / intercepts click incorrectly).
