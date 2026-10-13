{{-- Heart button on property cards (window.toggleFavorite). Shared by the browse page and the AI search page. --}}
<script>
    (function () {
        function toggleFavorite(button) {
            const isAuthenticated = document.querySelector('meta[name="user-authenticated"]').content === '1';

            if (!isAuthenticated) {
                openAuthModal('login');
                return;
            }

            const propertyId = button.dataset.propertyId;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const outline = button.querySelector('.heart-outline');
            const filled = button.querySelector('.heart-filled');

            fetch(`/favorites/${propertyId}/toggle`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            })
                .then(response => {
                    if (!response.ok) throw new Error('Favorite toggle failed: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    button.dataset.favorited = data.favorited ? 'true' : 'false';
                    outline.classList.toggle('hidden', data.favorited);
                    filled.classList.toggle('hidden', !data.favorited);
                })
                .catch(err => console.error(err));
        }

        window.toggleFavorite = toggleFavorite;
    })();
</script>
