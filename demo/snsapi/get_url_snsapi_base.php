<?php
/**
 * 生成授权地址的链接（用户点击后完成授权后，跳到回调页面获取用户信息）
 * `snsapi_base` 授权方式获取用户信息（不弹出授权页面，直接跳转，只能获取用户openid）
 *
 * @author gaoming13 <gaoming13@yeah.net>
 * @link https://github.com/gaoming13/wechat-php-sdk
 */
require '../../autoload.php';

use Gaoming13\WechatPhpSdk\Api;

// 开发者中心-配置项-AppID(应用ID)
$appId = 'wx733d7f24bd29224a';
// 开发者中心-配置项-AppSecret(应用密钥)
$appSecret = 'c6d165c5785226806f42440e376a410e';

// 这是使用了Memcached来保存access_token
// 由于access_token每日请求次数有限
// 用户需要自己定义获取和保存access_token的方法
$m = new Memcached();
$m->addServer('localhost', 11211);

// api模块 - 包含各种系统主动发起的功能
$api = new Api(
    array(
        'appId' => $appId,
        'appSecret' => $appSecret,
        'get_access_token' => function() use ($m) {
            // 用户需要自己实现access_token的返回
            return $m->get('access_token');
        },
        'save_access_token' => function($token) use ($m) {
            // 用户需要自己实现access_token的保存
            $m->set('access_token', $token, 0);
        }
    )
);

header('Content-type: text/html; charset=utf-8');

$authorize_url = $api->get_authorize_url('snsapi_base',
    'http://wx.diary8.com/demo/snsapi/callback_snsapi_base.php');

echo '<a href="' . $authorize_url . '">' .$authorize_url. '</a>';