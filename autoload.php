<?php

require_once __DIR__ . '/vendor/autoload.php';

$sdkFile = '/autoload.php';
if (defined('USE_PHARS') && USE_PHARS) {
    if (defined('USE_LC_SDK_PHAR') && USE_LC_SDK_PHAR) {
        $sdkFile = '/sdk.phar';
    }
}

require_once SDK_PATH . $sdkFile;
