<!DOCTYPE html>
{{-- .dark is unconditional: Gotham is dark-only (plan Section 15.1), and the
     starter kit's `dark:` variant styles only apply when the class is present.
     Deriving it from the visitor's OS preference, as the starter did, left a
     light-mode visitor with Gotham surfaces but light-mode variant styles.
     The appearance switcher in Settings is inert while this is hard-coded. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Paints the void before any stylesheet loads, so there is no white
             flash on a cold cache. Hard-coded rather than tokenised because a
             CSS custom property is not available this early. Keep in step with
             --surface-void in resources/css/gotham.css. --}}
        <style>
            html {
                background-color: #0B1220;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
