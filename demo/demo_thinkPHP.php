<?php
/**
 * demo_thinkPHP.php
 *
 * thinkPHP 使用该微信SDK的方法
 *
 * thinkphp 内控制器文件名 TestController.class.php
 *
 * 使用方法:
 * - 将SDK内 `src` 文件夹重命名为 `Gaoming13`
 * - 拷贝至 `ThinkPHP/Library/` 下即可使用 `Wechat` 和 `Api` 类库.
 *
 * @author gaoming13 <gaoming13@yeah.net>
 * @link https://github.com/gaoming13/wechat-php-sdk
 */

namespace Home\Controller;
use Think\Controller;
class TestController extends BaseController {

    public function index(){
        // 这是使用了Memcached来保存access_token
        S(array(
            'type'=>'memcached',
            'host'=>'localhost',
            'port'=>'11211',
            'prefix'=>'think',
            'expire'=>0
        ));

        // 开发者中心-配置项-AppID(应用ID)
        $appId = 'wx733d7f24bd29224a';
        // 开发者中心-配置项-AppSecret(应用密钥)
        $appSecret = 'c6d165c5785226806f42440e376a410e';
        // 开发者中心-配置项-服务器配置-Token(令牌)
        $token = 'gaoming13';
        // 开发者中心-配置项-服务器配置-EncodingAESKey(消息加解密密钥)
        $encodingAESKey = '072vHYArTp33eFwznlSvTRvuyOTe5YME1vxSoyZbzaV';

        // wechat模块 - 处理用户发送的消息和回复消息
        $wechat = new \Gaoming13\WechatPhpSdk\Wechat(array(
            'appId' => $appId,
            'token' => $token,
            'encodingAESKey' => $encodingAESKey //可选
        ));
        // api模块 - 包含各种系统主动发起的功能
        $api = new \Gaoming13\WechatPhpSdk\Api(
            array(
                'appId' => $appId,
                'appSecret' => $appSecret,
                'get_access_token' => function(){
                    // 用户需要自己实现access_token的返回
                    return S('wechat_token');
                },
                'save_access_token' => function($token) {
                    // 用户需要自己实现access_token的保存
                    S('wechat_token', $token);
                }
            )
        );

        // 获取微信消息
        $msg = $wechat->serve();

        // 回复文本消息
        if ($msg->MsgType == 'text' && $msg->Content == '你好') {
            $wechat->reply("你也好！ - 这是我回复的额！");
        } else {
            $wechat->reply("听不懂！ - 这是我回复的额！");
        }

        // 主动发送
        $api->send($msg->FromUserName, '这是我主动发送的一条消息');
    }
}