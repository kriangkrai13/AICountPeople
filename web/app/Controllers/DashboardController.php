<?php

namespace App\Controllers;

use App\Models\RoomModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $date = $this->request->getGet('date') ?: date('Y-m-d');
        $db = db_connect();

        $rows = $db->table('rooms r')
            ->select('r.id, r.name, r.code, r.is_active, COALESCE(c.total_in, 0) AS total_in, COALESCE(c.total_out, 0) AS total_out, c.last_recorded_at, c.camera_name, c.entrance_side')
            ->join('daily_room_counts c', 'c.room_id = r.id AND c.count_date = ' . $db->escape($date), 'left')
            ->orderBy('r.name', 'ASC')
            ->get()
            ->getResultArray();

        $summary = [
            'rooms' => count($rows),
            'active_rooms' => count(array_filter($rows, static fn ($row) => (int) $row['is_active'] === 1)),
            'total_in' => array_sum(array_map(static fn ($row) => (int) $row['total_in'], $rows)),
            'total_out' => array_sum(array_map(static fn ($row) => (int) $row['total_out'], $rows)),
        ];

        return view('dashboard/index', [
            'title' => 'สถิติวันนี้',
            'date' => $date,
            'rows' => $rows,
            'summary' => $summary,
            'rooms' => (new RoomModel())->orderBy('name', 'ASC')->findAll(),
        ]);
    }
}
