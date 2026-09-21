{{-- Shared by every layout, placed in <head>. Applies the saved theme before first paint
     (no light->dark flash) and wires every [data-theme-toggle] button by delegation, so a
     layout only has to include <x-theme-toggle />. The palette remap lives in css/app.css
     under html.dark. --}}
<script>
    (function () {
        try {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-theme-toggle]')) return;
            var dark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
        });
    })();
</script>
