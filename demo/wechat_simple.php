<?php
/**
 * 例子：接收用户发送消息并回复(简单版本)
 */

require '../autoload.php';

use Gaoming13\WechatPhpSdk\Wechat;

$wechat = new Wechat(array(
    // 开发者中心-配置项-AppID(应用ID)
    'appId' => 'wx733d7f24bd29224a',
    // 开发者中心-配置项-服务器配置-Token(令牌)
    'token' => 'gaoming13',
    // 开发者中心-配置项-服务器配置-EncodingAESKey(消息加解密密钥)
    // 可选: 消息加解密方式勾选 兼容模式 或 安全模式 需填写
    'encodingAESKey' => '072vHYArTp33eFwznlSvTRvuyOTe5YME1vxSoyZbzaV'
));

// 获取微信消息
$msg = $wechat->serve();

// 回复微信消息
if ($msg['MsgType'] == 'text' && $msg['Content'] == '你好') {
    $wechat->reply("你也好！");
} else {
    $wechat->reply("听不懂！");
}