<?php
/**
 * demo_message.php
 * 主送发送客服消息 DEMO
 * 
 * wechat-php-sdk DEMO
 *
 * @author 		gaoming13 <gaoming13@yeah.net>
 * @link 		https://github.com/gaoming13/wechat-php-sdk
 * @link 		http://me.diary8.com/
 */

require '../autoload.php';

use Gaoming13\WechatPhpSdk\Wechat;
use Gaoming13\WechatPhpSdk\Api;

// 开发者中心-配置项-AppID(应用ID)
$appId = 'wx733d7f24bd29224a';
// 开发者中心-配置项-AppSecret(应用密钥)
$appSecret = 'c6de6zcw78522dddww8w42e403376a410e';
// 开发者中心-配置项-服务器配置-Token(令牌)
$token = 'gaoming13';
// 开发者中心-配置项-服务器配置-EncodingAESKey(消息加解密密钥)
$encodingAESKey = '072vHYArTp33eFwznlSvTRvuyOTe5YME1vxSoyZbzaV';

// 这是使用了Memcached来保存access_token
// 由于access_token每日请求次数有限
// 用户需要自己定义获取和保存access_token的方法
$m = new Memcached();
$m->addServer('localhost', 11211);

// wechat模块 - 处理用户发送的消息和回复消息
$wechat = new Wechat(array(		
	'appId' => $appId,	
	'token' => 	$token,
	'encodingAESKey' =>	$encodingAESKey
));

// api模块 - 包含各种系统主动发起的功能
$api = new Api(
    array(
        'appId' => $appId,
        'appSecret'	=> $appSecret,
        'get_access_token' => function() {
            // 用户需要自己实现access_token的返回
            global $m;
            return $m->get('access_token');
        },
        'save_access_token' => function($token) {
            // 用户需要自己实现access_token的保存
            global $m;
            $m->set('access_token', $token, 0);
        }
    )
);


// 获取微信消息
$msg = $wechat->serve();

// 回复用户消息
$wechat->reply('hehhe!');

// 主动发送文本消息 - 简洁模式
$api->send($msg->FromUserName, 'heheh');

// 主动发送文本消息
$api->send($msg->FromUserName, array(
	'type' => 'text',
	'content' => 'hello world!',
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));

// 主动发送图片消息
$api->send($msg->FromUserName, array(
	'type' => 'image',
	'media_id' => 'Uq7OczuEGEyUu--dYjg7seTm-EJTa0Zj7UDP9zUGNkVpjcEHhl7tU2Mv8mFRiLKC',
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));

// 主动发送语音消息
$api->send($msg->FromUserName, array(
	'type' => 'voice',
	'media_id' => 'rVT43tfDwjh4p1BV2gJ5D7Zl2BswChO5L_llmlphLaTPytcGcguBAEJ1qK4cg4r_',
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));

// 主动发送视频消息
$api->send($msg->FromUserName, array(
	'type' => 'video',
	'media_id' => 'yV0l71NL0wtpRA8OMX0-dBRQsMVyt3fspPUzurIS3psi6eWOrb_WlEeO39jasoZ8',
	'thumb_media_id' => '7ct_DvuwZXIO9e9qbIf2ThkonUX_FzLAoqBrK-jzUboTYJX0ngOhbz6loS-wDvyZ',		// 可选(无效, 官方文档好像写错了)
	'title' => '视频消息的标题',			// 可选
	'description' => '视频消息的描述',		// 可选,
	'kf_account' => 'test1@kftest'			// 可选(指定某个客服发送, 会显示这个客服的头像)
));

// 主动发送音乐消息
$api->send($msg->FromUserName, array(
	'type' => 'music',
	'title' => '音乐标题',						//可选
	'description' => '音乐描述',				//可选
	'music_url' => 'http://me.diary8.com/data/music/2.mp3',		//可选
	'hqmusic_url' => 'http://me.diary8.com/data/music/2.mp3',	//可选
	'thumb_media_id' => 'O39wW0ZsXCb5VhFoCgibQs5PupFb6VZ2jH5A8gHUJCJz2Qmkrb7objoTue7bGTGQ',
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));

// 主动发送图文消息
$api->send($msg->FromUserName, array(
	'type' => 'news',
	'articles' => array(
		array(
			'title' => '图文消息标题1',								//可选
			'description' => '图文消息描述1',						//可选
			'picurl' => 'http://me.diary8.com/data/img/demo1.jpg',	//可选
			'url' => 'http://www.example.com/'						//可选
		),
		array(
			'title' => '图文消息标题2',
			'description' => '图文消息描述2',
			'picurl' => 'http://me.diary8.com/data/img/demo2.jpg',
			'url' => 'http://www.example.com/'
		),
		array(
			'title' => '图文消息标题3',
			'description' => '图文消息描述3',
			'picurl' => 'http://me.diary8.com/data/img/demo3.jpg',
			'url' => 'http://www.example.com/'
		)
	),
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));