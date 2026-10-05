<?php

namespace App\Controllers;

use App\Models\RoomModel;

class RoomController extends BaseController
{
    public function index()
    {
        return view('rooms/index', [
            'title' => 'จัดการห้อง',
            'rooms' => (new RoomModel())->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function store()
    {
        $model = new RoomModel();
        $model->insert($this->roomPayload());

        return redirect()->to('/rooms')->with('success', 'เพิ่มห้องเรียบร้อยแล้ว');
    }

    public function update(int $id)
    {
        $model = new RoomModel();
        $model->update($id, $this->roomPayload());

        return redirect()->to('/rooms')->with('success', 'บันทึกข้อมูลห้องเรียบร้อยแล้ว');
    }

    public function toggle(int $id)
    {
        $model = new RoomModel();
        $room = $model->find($id);

        if ($room) {
            $model->update($id, ['is_active' => ((int) $room['is_active']) === 1 ? 0 : 1]);
        }

        return redirect()->to('/rooms')->with('success', 'อัปเดตสถานะห้องเรียบร้อยแล้ว');
    }

    private function roomPayload(): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'description' => trim((string) $this->request->getPost('description')),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }
}
