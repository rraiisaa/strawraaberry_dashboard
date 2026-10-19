<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Strawraaberry Journey</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/logo_pink.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/logo_pink.png') }}">
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
        ['partners.overview', 'Titip Jual', 'store', 'bg-mint', ['partners.*', 'restocks.*', 'reports.*', 'payments.*']],
        ['vendors.index', 'Vendor', 'truck', 'bg-butter', 'vendors.*'],
        ['cashflow.index', 'Aliran Dana', 'wallet', 'bg-lav', 'cashflow.*'],
    ];
@endphp

<header class="sticky top-0 z-30 flex items-center justify-between bg-white/90 px-4 py-3 backdrop-blur md:hidden">
    <span class="font-display text-lg font-semibold text-berry"> Strawraaberry Journey</span>
    <button x-on:click.stop="nav = !nav" class="rounded-xl bg-lav p-2" aria-label="Menu"><i data-lucide="menu" class="h-5 w-5"></i></button>
</header>

<aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-white p-5 shadow-lav transition md:translate-x-0"
       :class="{ 'translate-x-0': nav }" x-on:click.outside="nav = false">
    <a href="{{ route('dashboard') }}" class="mb-8 block">
        <div class="font-display text-2xl font-semibold leading-tight text-berry"> Strawraaberry</div>
        <div class="mt-1 text-xs font-semibold text-cocoa/50">silly things grow here</div>
    </a>
    <nav class="space-y-2">
        @foreach ($menu as [$route, $label, $icon, $bg, $pattern])
            <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-bold transition {{ request()->routeIs(...(array) $pattern) ? $bg . ' shadow-pastel' : 'hover:bg-cloud' }}">
                <i data-lucide="{{ $icon }}" class="h-5 w-5"></i> {{ $label }}
            </a>
        @endforeach
    </nav>
    <div class="mt-10 rounded-2xl bg-butter p-4 text-xs font-semibold"> Catat setiap titipan & pengeluaran di hari yang sama biar saldo kas selalu akurat.</div>
</aside>

<main class="p-4 md:ml-64 md:p-8">
    @yield('content')
</main>

<script type="application/json" id="flash-data">{!! json_encode(['success' => session('success'), 'errors' => $errors->all()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

<style>
    @keyframes toast-shrink { from { width: 100%; } to { width: 0; } }
    @keyframes pop-in { 0% { transform: scale(.6); opacity: 0; } 70% { transform: scale(1.08); } 100% { transform: scale(1); opacity: 1; } }
    .pop-in { animation: pop-in .35s ease-out both; }
</style>

<div x-data="uiDialogs()" x-init="boot()" class="contents">
    {{-- Toast --}}
    <div class="pointer-events-none fixed right-4 top-4 z-[90] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div x-show="t.show" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-8 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
                 x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-x-8 opacity-0"
                 class="pointer-events-auto overflow-hidden rounded-2xl bg-white shadow-pastel ring-1 ring-berry-soft/50">
                <div class="flex items-start gap-3 p-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full pop-in bg-mint text-emerald-700">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                    </div>
                    <div class="flex-1"><div class="font-display font-semibold">Berhasil</div><div class="text-sm font-semibold text-cocoa/70" x-text="t.msg"></div></div>
                    <button type="button" x-on:click="dismiss(t)" class="rounded-lg p-1 text-cocoa/50 hover:bg-cloud" aria-label="Tutup"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                <div class="h-1 bg-mint"><div class="h-1 bg-berry" :style="`animation: toast-shrink ${t.ms}ms linear forwards`"></div></div>
            </div>
        </template>
    </div>

    {{-- Dialog konfirmasi / error --}}
    <div x-show="dlg.open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-cocoa/50 p-4 backdrop-blur-sm"
         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         x-on:keydown.escape.window="close()" role="dialog" aria-modal="true">
        <div x-show="dlg.open" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="scale-90 opacity-0" x-transition:enter-end="scale-100 opacity-100"
             x-on:click.outside="close()" class="w-full max-w-sm rounded-3xl bg-white p-6 text-center shadow-pastel">
            <div class="pop-in mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full" :class="{ 'bg-berry-soft/60 text-berry': dlg.type==='danger' || dlg.type==='error', 'bg-butter text-cocoa': dlg.type==='primary' }">
                <svg x-show="dlg.type==='danger'" viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>
                <svg x-show="dlg.type==='error'" viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>
                <svg x-show="dlg.type==='primary'" viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.82 1c0 2-3 3-3 3M12 17h.01"/></svg>
            </div>
            <h3 class="font-display text-xl font-semibold" x-text="dlg.title"></h3>
            <div class="mt-2 space-y-1 text-sm font-semibold text-cocoa/70"><template x-for="m in dlg.messages" :key="m"><p x-text="m"></p></template></div>
            <div class="mt-6 flex gap-2">
                <button type="button" x-show="dlg.type!=='error'" x-on:click="close()" class="btn-soft flex-1">Batal</button>
                <button type="button" x-ref="okBtn" x-on:click="accept()" class="btn flex-1" :class="dlg.type==='danger' && '!bg-berry'" x-text="dlg.ok"></button>
            </div>
        </div>
    </div>
</div>

<script>
    function uiDialogs() {
        return {
            toasts: [], seq: 0, form: null,
            dlg: { open: false, type: 'primary', title: '', messages: [], ok: 'Ya, lanjutkan' },
            boot() {
                document.addEventListener('submit', (e) => {
                    const f = e.target;
                    if (!f.dataset || !f.dataset.confirm) return;
                    if (f.dataset.confirmed === '1') { delete f.dataset.confirmed; return; }
                    e.preventDefault(); e.stopImmediatePropagation();
                    this.form = f;
                    this.dlg = { open: true, type: f.dataset.confirmType || 'primary', title: f.dataset.confirmTitle || 'Lanjutkan tindakan ini?',
                                 messages: [f.dataset.confirm], ok: f.dataset.confirmOk || 'Ya, lanjutkan' };
                    this.$nextTick(() => this.$refs.okBtn && this.$refs.okBtn.focus());
                }, true);
                window.toast = (msg, ms) => this.toast(msg, ms);
                try {
                    const flash = JSON.parse(document.getElementById('flash-data').textContent);
                    if (flash.success) this.toast(flash.success);
                    if (flash.errors && flash.errors.length) this.dlg = { open: true, type: 'error', title: 'Belum bisa disimpan', messages: flash.errors, ok: 'Mengerti' };
                } catch (e) {}
            },
            toast(msg, ms = 4500) {
                this.toasts.push({ id: ++this.seq, msg, ms, show: false });
                const t = this.toasts[this.toasts.length - 1]; // versi reaktif
                this.$nextTick(() => { t.show = true; });
                setTimeout(() => this.dismiss(t), ms);
            },
            dismiss(t) { t.show = false; setTimeout(() => { this.toasts = this.toasts.filter(x => x.id !== t.id); }, 250); },
            close() { this.dlg.open = false; this.form = null; },
            accept() {
                if (this.dlg.type === 'error' || !this.form) { this.close(); return; }
                const f = this.form; this.dlg.open = false; this.form = null;
                f.dataset.confirmed = '1';
                f.requestSubmit();
            },
        };
    }
    document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
</script>
@stack('scripts')
</body>
</html>