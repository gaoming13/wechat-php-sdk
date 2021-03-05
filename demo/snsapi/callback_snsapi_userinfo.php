<?php
/**
 * demo/snsapi/callback_snsapi_useinfo.php
 *
 * 授权链接跳转回调页面
 * `snsapi_userinfo` 授权方式获取用户信息（弹出授权页面，可通过openid拿到昵称、性别、所在地。
 * 即使在未关注的情况下，只要用户授权，也能获取其信息）
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

list($err, $user_info) = $api->get_userinfo_by_authorize('snsapi_userinfo');
if ($user_info !== null) {
    var_dump($user_info);;
} else {
    echo '授权失败！';
}