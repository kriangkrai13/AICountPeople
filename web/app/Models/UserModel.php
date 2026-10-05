<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'email', 'password_hash', 'role', 'is_active'];
    protected $useTimestamps = true;
    protected $returnType = 'array';
}
