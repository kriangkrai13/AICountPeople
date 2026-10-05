<?php

namespace App\Models;

use CodeIgniter\Model;

class DailyRoomCountModel extends Model
{
    protected $table = 'daily_room_counts';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'room_id',
        'count_date',
        'total_in',
        'total_out',
        'last_recorded_at',
        'camera_name',
        'entrance_side',
    ];
    protected $useTimestamps = true;
    protected $returnType = 'array';
}
