<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Strawraaberry Journey</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { display: ['Fredoka', 'sans-serif'], sans: ['Nunito', 'sans-serif'] },
            colors: {
                berry:  { DEFAULT: '#FF4D6D', soft: '#FFB7C5' },
                butter: '#FFF3B0', mint: '#D8F3DC', lav: '#EDE9FE', cloud: '#F8F9FA', cocoa: '#2D2A32',
            },
            boxShadow: { pastel: '0 8px 24px -8px rgba(255,77,109,.25)', lav: '0 8px 24px -8px rgba(139,120,220,.25)' },
        } } }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@0.441.0"></script>
    <style type="text/tailwindcss">
        @layer components {
            .card   { @apply bg-white rounded-2xl p-5 shadow-pastel border border-berry-soft/30; }
            .pill   { @apply inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold; }
            .btn    { @apply inline-flex items-center justify-center gap-2 rounded-xl bg-berry px-4 py-2 text-sm font-bold text-white shadow-pastel transition hover:brightness-105 active:scale-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-berry; }
            .btn-soft { @apply inline-flex items-center justify-center gap-2 rounded-xl bg-berry-soft/40 px-4 py-2 text-sm font-bold text-cocoa transition hover:bg-berry-soft/70; }
            .input  { @apply w-full rounded-xl border border-lav bg-cloud px-3 py-2 text-sm text-cocoa focus:border-berry focus:ring-2 focus:ring-berry-soft focus:outline-none; }
            .label  { @apply mb-1 block text-xs font-bold text-cocoa/70; }
            .th     { @apply px-3 py-2 text-left text-xs font-bold text-cocoa/60; }
            .td     { @apply px-3 py-2.5 text-sm; }
        }
        [x-cloak] { display: none !important; }
        body { background: radial-gradient(circle at 10% 0%, #FFF3B0 0, transparent 35%), radial-gradient(circle at 100% 20%, #EDE9FE 0, transparent 40%), #F8F9FA; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen font-sans text-cocoa antialiased" x-data="{ nav: false }">

@php
    $menu = [
        ['dashboard', 'Dashboard', 'layout-dashboard', 'bg-berry-soft', 'dashboard'],
        ['products.index', 'Produk & Stok', 'package', 'bg-butter', 'products.*'],
        ['consignments.index', 'Titip Jual', 'store', 'bg-mint', 'consignments.*'],
        ['vendors.index', 'Vendor', 'truck', 'bg-butter', 'vendors.*'],
        ['cashflow.index', 'Aliran Dana', 'wallet', 'bg-lav', 'cashflow.*'],
    ];
@endphp

<header class="sticky top-0 z-30 flex items-center justify-between bg-white/90 px-4 py-3 backdrop-blur md:hidden">
    <span class="font-display text-lg font-semibold text-berry"> Strawraaberry Journey</span>
    <button @click.stop="nav = !nav" class="rounded-xl bg-lav p-2" aria-label="Menu"><i data-lucide="menu" class="h-5 w-5"></i></button>
</header>

<aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-white p-5 shadow-lav transition md:translate-x-0"
       :class="{ 'translate-x-0': nav }" @click.outside="nav = false">
    <a href="{{ route('dashboard') }}" class="mb-8 block">
        <div class="font-display text-2xl font-semibold leading-tight text-berry"> Strawraaberry<br>Journey</div>
        <div class="mt-1 text-xs font-semibold text-cocoa/50">craft studio dashboard</div>
    </a>
    <nav class="space-y-2">
        @foreach ($menu as [$route, $label, $icon, $bg, $pattern])
            <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-bold transition {{ request()->routeIs($pattern) ? $bg . ' shadow-pastel' : 'hover:bg-cloud' }}">
                <i data-lucide="{{ $icon }}" class="h-5 w-5"></i> {{ $label }}
            </a>
        @endforeach
    </nav>
    <div class="mt-10 rounded-2xl bg-butter p-4 text-xs font-semibold"> Catat setiap titipan & pengeluaran di hari yang sama biar saldo kas selalu akurat.</div>
</aside>

<main class="p-4 md:ml-64 md:p-8">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition
             class="mb-5 rounded-2xl bg-mint px-4 py-3 text-sm font-bold shadow-pastel">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-2xl bg-berry-soft/40 px-4 py-3 text-sm font-bold">
            @foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
    @endif
    @yield('content')
</main>

<script>document.addEventListener('DOMContentLoaded', () => lucide.createIcons());</script>
@stack('scripts')
</body>
</html>