<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no" class="{{ request()->routeIs('monitor') ? 'no-scrollbars' : '' }}">
    <head>

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="google" content="notranslate">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA support -->
        <meta name="theme-color" content="#ffffff">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" sizes="any" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon-180x180.png">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
