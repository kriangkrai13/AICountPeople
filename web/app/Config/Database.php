<?php

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
    public string $defaultGroup = 'default';

    public array $default = [
        'DSN' => '',
        'hostname' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'ai_count_people',
        'DBDriver' => 'MySQLi',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug' => true,
        'charset' => 'utf8mb4',
        'DBCollat' => 'utf8mb4_unicode_ci',
        'swapPre' => '',
        'encrypt' => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port' => 3306,
    ];

    public function __construct()
    {
        parent::__construct();

        $this->default['hostname'] = $this->readEnv(['AICOUNT_DB_HOST', 'database.default.hostname'], $this->default['hostname']);
        $this->default['database'] = $this->readEnv(['AICOUNT_DB_DATABASE', 'database.default.database'], $this->default['database']);
        $this->default['username'] = $this->readEnv(['AICOUNT_DB_USER', 'database.default.username'], $this->default['username']);
        $this->default['password'] = $this->readEnv(['AICOUNT_DB_PASSWORD', 'database.default.password'], $this->default['password']);
        $this->default['DBDriver'] = $this->readEnv(['database.default.DBDriver'], $this->default['DBDriver']);
        $this->default['port'] = (int) $this->readEnv(['AICOUNT_DB_PORT', 'database.default.port'], (string) $this->default['port']);
    }

    private function readEnv(array $keys, string $default): string
    {
        foreach ($keys as $key) {
            $value = getenv($key);

            if ($value !== false && $value !== '') {
                return $value;
            }
        }

        return $default;
    }
}
