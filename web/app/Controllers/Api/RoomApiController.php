<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\RoomModel;

class RoomApiController extends BaseController
{
    public function index()
    {
        $rooms = (new RoomModel())
            ->select('id, name, code, description')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->response->setJSON(['data' => $rooms]);
    }
}
