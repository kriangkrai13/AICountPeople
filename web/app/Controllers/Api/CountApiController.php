<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class CountApiController extends BaseController
{
    public function sync()
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $required = ['room_id', 'count_date', 'total_in', 'total_out', 'last_recorded_at'];

        foreach ($required as $field) {
            if (! isset($payload[$field])) {
                return $this->response->setStatusCode(422)->setJSON(['message' => "Missing {$field}"]);
            }
        }

        $db = db_connect();
        $roomId = (int) $payload['room_id'];
        $countDate = (string) $payload['count_date'];
        $data = [
            'room_id' => $roomId,
            'count_date' => $countDate,
            'total_in' => max(0, (int) $payload['total_in']),
            'total_out' => max(0, (int) $payload['total_out']),
            'last_recorded_at' => (string) $payload['last_recorded_at'],
            'camera_name' => $payload['camera_name'] ?? null,
            'entrance_side' => in_array(($payload['entrance_side'] ?? 'right'), ['left', 'right'], true) ? $payload['entrance_side'] : 'right',
        ];

        $existing = $db->table('daily_room_counts')
            ->where('room_id', $roomId)
            ->where('count_date', $countDate)
            ->get()
            ->getRowArray();

        if ($existing) {
            $db->table('daily_room_counts')->where('id', $existing['id'])->update($data);
        } else {
            $db->table('daily_room_counts')->insert($data);
        }

        $db->table('count_sync_logs')->insert([
            'room_id' => $roomId,
            'count_date' => $countDate,
            'total_in' => $data['total_in'],
            'total_out' => $data['total_out'],
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->response->setJSON(['message' => 'synced', 'data' => $data]);
    }
}
