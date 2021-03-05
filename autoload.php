<?php
spl_autoload_register(function ($class) {
    if (false !== stripos($class, 'Gaoming13\WechatPhpSdk')) {
        require_once __DIR__.'/src/'.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 10)).'.class.php';
    }
});