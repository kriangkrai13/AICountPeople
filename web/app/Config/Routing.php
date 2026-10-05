<?php

namespace Config;

use CodeIgniter\Config\Routing as BaseRouting;

class Routing extends BaseRouting
{
    public string $defaultNamespace = 'App\Controllers';
    public string $defaultController = 'DashboardController';
    public string $defaultMethod = 'index';
    public bool $translateURIDashes = false;
    public bool $autoRoute = false;
}
