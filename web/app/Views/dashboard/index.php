<?= $this->extend('layouts/app') ?>
<?= $this->section('actions') ?>
<form class="flex items-center gap-2" method="get">
    <input type="date" name="date" value="<?= esc($date) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
    <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">ดูข้อมูล</button>
</form>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="dashboardApp" v-cloak>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg bg-white p-4">
            <p class="text-sm text-slate-600">จำนวนห้องทั้งหมด</p>
            <p class="mt-2 text-3xl font-semibold"><?= number_format($summary['rooms']) ?></p>
        </div>
        <div class="rounded-lg bg-white p-4">
            <p class="text-sm text-slate-600">ห้องที่เปิดใช้งาน</p>
            <p class="mt-2 text-3xl font-semibold"><?= number_format($summary['active_rooms']) ?></p>
        </div>
        <div class="rounded-lg bg-white p-4">
            <p class="text-sm text-slate-600">เข้า</p>
            <p class="mt-2 text-3xl font-semibold text-blue-700"><?= number_format($summary['total_in']) ?></p>
        </div>
        <div class="rounded-lg bg-white p-4">
            <p class="text-sm text-slate-600">ออก</p>
            <p class="mt-2 text-3xl font-semibold text-cyan-700"><?= number_format($summary['total_out']) ?></p>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
            <h2 class="text-lg font-semibold">รายการห้องประจำวันที่ <?= esc($date) ?></h2>
            <input v-model="keyword" class="rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="ค้นหาห้อง">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-medium">ห้อง</th>
                    <th class="px-4 py-3 font-medium">เข้า</th>
                    <th class="px-4 py-3 font-medium">ออก</th>
                    <th class="px-4 py-3 font-medium">อัปเดตล่าสุด</th>
                    <th class="px-4 py-3 font-medium">กล้อง</th>
                    <th class="px-4 py-3 font-medium">สถานะ</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <tr v-for="row in filteredRows" :key="row.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ row.name }}</div>
                        <div class="text-xs text-slate-500">{{ row.code }}</div>
                    </td>
                    <td class="px-4 py-3 text-lg font-semibold text-blue-700">{{ row.total_in }}</td>
                    <td class="px-4 py-3 text-lg font-semibold text-cyan-700">{{ row.total_out }}</td>
                    <td class="px-4 py-3">{{ row.last_recorded_at || 'ยังไม่มีข้อมูล' }}</td>
                    <td class="px-4 py-3">{{ row.camera_name || '-' }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="row.is_active == 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">
                            {{ row.is_active == 1 ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                        </span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
const { createApp } = Vue;
createApp({
    data() {
        return {
            keyword: '',
            rows: <?= json_encode($rows, JSON_UNESCAPED_UNICODE) ?>
        };
    },
    computed: {
        filteredRows() {
            const q = this.keyword.trim().toLowerCase();
            if (!q) return this.rows;
            return this.rows.filter(row => `${row.name} ${row.code}`.toLowerCase().includes(q));
        }
    }
}).mount('#dashboardApp');
</script>
<?= $this->endSection() ?>
