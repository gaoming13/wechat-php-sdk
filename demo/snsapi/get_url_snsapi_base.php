<?php
/**
 * 生成授权地址的链接（用户点击后完成授权后，跳到回调页面获取用户信息）
 * `snsapi_base` 授权方式获取用户信息（不弹出授权页面，直接跳转，只能获取用户openid）
 */
require '../../autoload.php';

use Gaoming13\WechatPhpSdk\Api;
use Gaoming13\WechatPhpSdk\Utils\FileCache;

// 文件缓存用来暂存access_token(也可以用redis/memcached)
$cache = new FileCache([
    'path' => __DIR__.'/runtime/',
]);

// api设备
$api = new Api(
    array(
        'ghId' => 'gh_965f8b675d0e',
        'appId' => 'wx733d7f24bd29224a',
        'appSecret' => 'c6d165c5785226806f42440e376a410e',
        'get_access_token' => function() use ($cache) {
            return $cache->get('access_token');
        },
        'save_access_token' => function($token) use ($cache) {
            $cache->set('access_token', $token, 7000);
        }
    )
);

header('Content-type: text/html; charset=utf-8');

$authorize_url = $api->get_authorize_url('snsapi_base',
    'http://wx.diary8.com/demo/snsapi/callback_snsapi_base.php');

echo '<a href="' . $authorize_url . '">' .$authorize_url. '</a>';