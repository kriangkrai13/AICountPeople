<?= $this->extend('layouts/app') ?>
<?= $this->section('actions') ?>
<button form="roomCreateForm" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">เพิ่มห้อง</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="roomsApp" class="grid gap-6 xl:grid-cols-[380px_1fr]" v-cloak>
    <section class="rounded-lg bg-white p-4">
        <h2 class="text-lg font-semibold">เพิ่มห้องใหม่</h2>
        <form id="roomCreateForm" method="post" action="/rooms" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <label class="block">
                <span class="text-sm font-medium">ชื่อห้อง</span>
                <input name="name" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </label>
            <label class="block">
                <span class="text-sm font-medium">รหัสห้อง</span>
                <input name="code" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="ROOM_01">
            </label>
            <label class="block">
                <span class="text-sm font-medium">รายละเอียด</span>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300">
                เปิดใช้งาน
            </label>
        </form>
    </section>

    <section class="overflow-hidden rounded-lg bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-lg font-semibold">ห้องทั้งหมด</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-medium">ชื่อห้อง</th>
                    <th class="px-4 py-3 font-medium">รหัส</th>
                    <th class="px-4 py-3 font-medium">รายละเอียด</th>
                    <th class="px-4 py-3 font-medium">สถานะ</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($rooms as $room): ?>
                <tr>
                    <td class="px-4 py-3 font-medium"><?= esc($room['name']) ?></td>
                    <td class="px-4 py-3"><?= esc($room['code']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= esc($room['description'] ?: '-') ?></td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium <?= (int) $room['is_active'] === 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                            <?= (int) $room['is_active'] === 1 ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <form method="post" action="/rooms/<?= (int) $room['id'] ?>/toggle">
                            <?= csrf_field() ?>
                            <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">
                                <?= (int) $room['is_active'] === 1 ? 'ปิด' : 'เปิด' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script>
Vue.createApp({}).mount('#roomsApp');
</script>
<?= $this->endSection() ?>
