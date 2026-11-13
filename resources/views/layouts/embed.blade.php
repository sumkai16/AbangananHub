{{--
    A bare page for content shown inside a modal's iframe (e.g. the agreement preview on the Rentals page): the app's
    CSS and JS, no header, sidebar or chat bubble. Always light, since it is what gets printed.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'AbangananHub' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-[#060D26] antialiased">
    @yield('content')
</body>
</html>
