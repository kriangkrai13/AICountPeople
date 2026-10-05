<?php

namespace App\Models;

use CodeIgniter\Model;

class RoomModel extends Model
{
    protected $table = 'rooms';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'code', 'description', 'is_active'];
    protected $useTimestamps = true;
    protected $returnType = 'array';
}
