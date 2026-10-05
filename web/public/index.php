<?php

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

$pathsPath = realpath(FCPATH . '../app/Config/Paths.php');

if (! $pathsPath) {
    http_response_code(500);
    echo 'CodeIgniter dependencies are not installed yet. Run composer install in web/.';
    exit;
}

require $pathsPath;
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'Boot.php';

exit(CodeIgniter\Boot::bootWeb($paths));
