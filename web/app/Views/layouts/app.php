<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'AI Count People') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg: oklch(1 0 0);
            --surface: oklch(0.972 0.006 255);
            --panel: oklch(0.935 0.018 255);
            --ink: oklch(0.205 0.035 258);
            --muted: oklch(0.435 0.035 258);
            --primary: oklch(0.48 0.19 255);
            --primary-strong: oklch(0.38 0.17 255);
            --accent: oklch(0.62 0.16 205);
            --success: oklch(0.55 0.14 154);
            --danger: oklch(0.58 0.18 28);
            --border: oklch(0.88 0.015 255);
        }
        body { background: var(--surface); color: var(--ink); font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        [v-cloak] { display: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-strong); }
        .focus-ring:focus { outline: 3px solid oklch(0.80 0.10 235); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) {
            * { scroll-behavior: auto !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>
<div class="min-h-screen lg:flex">
    <aside class="bg-slate-950 text-white lg:w-72">
        <div class="flex items-center justify-between px-5 py-4 lg:block lg:px-6 lg:py-6">
            <div>
                <div class="text-lg font-semibold">AI Count People</div>
                <div class="mt-1 text-sm text-slate-300">Backoffice</div>
            </div>
            <form method="post" action="/logout" class="lg:hidden">
                <?= csrf_field() ?>
                <button class="rounded-md px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">ออก</button>
            </form>
        </div>
        <nav class="flex gap-2 overflow-x-auto px-4 pb-4 lg:block lg:space-y-1 lg:px-3">
            <a href="/" class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-slate-800 <?= uri_string() === '' ? 'bg-blue-700' : 'text-slate-200' ?>">สถิติวันนี้</a>
            <a href="/rooms" class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-slate-800 <?= str_starts_with(uri_string(), 'rooms') ? 'bg-blue-700' : 'text-slate-200' ?>">จัดการห้อง</a>
        </nav>
        <form method="post" action="/logout" class="hidden px-3 lg:block">
            <?= csrf_field() ?>
            <button class="mt-4 w-full rounded-md px-3 py-2 text-left text-sm text-slate-200 hover:bg-slate-800">ออกจากระบบ</button>
        </form>
    </aside>
    <main class="flex-1">
        <header class="border-b bg-white px-5 py-4" style="border-color: var(--border)">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal"><?= esc($title ?? 'สถิติ') ?></h1>
                    <p class="mt-1 text-sm" style="color: var(--muted)">ผู้ใช้: <?= esc(session('user_name') ?? '-') ?></p>
                </div>
                <?= $this->renderSection('actions') ?>
            </div>
        </header>
        <section class="px-5 py-6">
            <?= $this->renderSection('content') ?>
        </section>
    </main>
</div>

<?php if (session('success')): ?>
<script>Swal.fire({icon: 'success', title: 'สำเร็จ', text: <?= json_encode(session('success')) ?>, timer: 1800, showConfirmButton: false});</script>
<?php endif; ?>
<?php if (session('error')): ?>
<script>Swal.fire({icon: 'error', title: 'ไม่สำเร็จ', text: <?= json_encode(session('error')) ?>});</script>
<?php endif; ?>
</body>
</html>
