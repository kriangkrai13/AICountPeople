<?php

namespace Config;

use CodeIgniter\Events\Events;

Events::on('pre_system', static function () {
    if (ENVIRONMENT !== 'testing') {
        ini_set('display_errors', '0');
    }
});
