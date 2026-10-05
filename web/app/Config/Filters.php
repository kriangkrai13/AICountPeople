<?php

namespace Config;

use App\Filters\AuthFilter;
use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    public array $aliases = [
        'auth' => AuthFilter::class,
    ];

    public array $globals = [
        'before' => [],
        'after' => [],
    ];

    public array $methods = [];
    public array $filters = [];
}
