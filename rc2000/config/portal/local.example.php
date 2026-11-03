<?php
/**
 * Copy this file to config/local.php and edit the values.
 * config/local.php is ignored by Git so passwords are never shared.
 * Only list the settings you want to change from config/app.php.
 */
return [
    'app_env'  => 'development',
    'base_url' => '/rc2000',
    'db_host'  => '127.0.0.1',
    'db_name'  => 'rc2000_db',
    'db_port'  => 5432,
    'db_user'  => 'postgres',
    'db_pass'  => '',
    // Optional, only if the public website runs on a different origin:
    // 'public_allowed_origins' => ['http://localhost:3000'],
];

