<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    public string $baseURL = 'http://localhost:8080/';
    public array $allowedHostnames = [];
    public string $indexPage = '';
    public string $uriProtocol = 'REQUEST_URI';
    public string $permittedURIChars = 'a-z 0-9~%.:_\-';
    public string $defaultLocale = 'th';
    public bool $negotiateLocale = false;
    public array $supportedLocales = ['th', 'en'];
    public string $appTimezone = 'Asia/Bangkok';
    public string $charset = 'UTF-8';
    public bool $forceGlobalSecureRequests = false;
    public array $proxyIPs = [];
    public bool $CSPEnabled = false;

    public function __construct()
    {
        parent::__construct();

        $baseURL = getenv('app.baseURL');
        if ($baseURL !== false && $baseURL !== '') {
            $this->baseURL = $baseURL;
        }
    }
}
