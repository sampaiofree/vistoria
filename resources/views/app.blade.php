<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($appOrganization = auth()->user()?->organization)
    @if ($appOrganization?->isActive())
        <link rel="manifest" href="{{ route('pwa.manifest', $appOrganization, false) }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ app(\App\Services\Pwa\OrganizationAppBranding::class)->iconUrl($appOrganization) }}">
        <meta name="theme-color" content="{{ $appOrganization->primary_color ?? '#0F172A' }}">
    @endif
    <title inertia>{{ config('app.name', 'Vistoria') }}</title>
    <script>
        try {
            if (localStorage.getItem('vistoria.theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (_) {
            // The application stays in its default light theme when storage is unavailable.
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @inertia
</body>
</html>
