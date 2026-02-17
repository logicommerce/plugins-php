<?php

define('PLUGINS_RELEASE_VERSION', '20260216-175600');

define('PLUGINS_NAMESPACE', 'Plugins\\');

if (!defined('PLUGINS_PATH')) {
    define('PLUGINS_PATH', '/local/www/plugins');
}

$path = Phar::running();
if (strlen($path) === 0) {
    $path = PLUGINS_PATH;
}
define('PLUGINS_LOAD_PATH', $path);

if (!defined('SDK_PATH')) {
    define('SDK_PATH', __DIR__ . '/../sdk');
}
