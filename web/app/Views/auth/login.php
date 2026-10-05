<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ | AI Count People</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: oklch(0.18 0.055 258); color: oklch(0.205 0.035 258); }
    </style>
</head>
<body>
<main class="grid min-h-screen place-items-center px-4">
    <section class="w-full max-w-md rounded-lg bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">AI Count People</h1>
            <p class="mt-2 text-sm text-slate-600">เข้าสู่ระบบเพื่อดูสถิติและจัดการห้อง</p>
        </div>
        <form method="post" action="/login" class="space-y-4">
            <?= csrf_field() ?>
            <label class="block">
                <span class="text-sm font-medium">อีเมล</span>
                <input name="email" type="email" required value="<?= esc(old('email') ?? 'admin@example.com') ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </label>
            <label class="block">
                <span class="text-sm font-medium">รหัสผ่าน</span>
                <input name="password" type="password" required placeholder="admin1234" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </label>
            <button class="w-full rounded-md bg-blue-700 px-4 py-2.5 font-medium text-white hover:bg-blue-800">เข้าสู่ระบบ</button>
        </form>
        <p class="mt-5 text-xs text-slate-500">บัญชีตัวอย่าง: admin@example.com / admin1234</p>
    </section>
</main>
<?php if (session('error')): ?>
<script>Swal.fire({icon: 'error', title: 'เข้าสู่ระบบไม่สำเร็จ', text: <?= json_encode(session('error')) ?>});</script>
<?php endif; ?>
</body>
</html>
