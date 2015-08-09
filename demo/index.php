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
$appSecret = 'c6d165c5785226806f42440e376a410e';
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
	array('appId' => $appId,'appSecret'	=> $appSecret),
	function(){
		// 用户需要自己实现access_token的返回
		global $m;		
		return $m->get('wechat_token');
	}, 
	function($token) {
		// 用户需要自己实现access_token的保存
		global $m;
		$m->set('wechat_token', $token, 0);
	}
);


// 获取微信消息
$msg = $wechat->serve();


// 默认消息
$default_msg = "/微笑  欢迎关注本测试号:\n 回复1: 回复文本消息\n 回复2: 回复图片消息\n 回复3: 回复语音消息\n 回复4: 回复视频消息\n 回复5: 回复音乐消息\n 回复6: 回复图文消息\n 回复7: 主动回复";

// 用户关注微信号后 - 回复用户普通文本消息
if ($msg->MsgType == 'event' && $msg->Event == 'subscribe') {

	$wechat->reply($default_msg);
	exit();	
}

// 用户回复1 - 回复文本消息
if ($msg->MsgType == 'text' && $msg->Content == '1') {

	$wechat->reply("hello world!");
	/* 也可使用这种数组方式回复
	$wechat->reply(array(
		'type' => 'text',
		'content' => 'hello world!'
	));
	*/
	exit();
}

// 用户回复2 - 回复图片消息
if ($msg->MsgType == 'text' && $msg->Content == '2') {

	$wechat->reply(array(
		'type' => 'image',
		// 通过素材管理接口上传多媒体文件，得到的id
		'media_id' => 'Uq7OczuEGEyUu--dYjg7seTm-EJTa0Zj7UDP9zUGNkVpjcEHhl7tU2Mv8mFRiLKC'
	));
	exit();
}

// 用户回复3 - 回复语音消息
if ($msg->MsgType == 'text' && $msg->Content == '3') {

	$wechat->reply(array(
		'type' => 'voice',
		// 通过素材管理接口上传多媒体文件，得到的id
		'media_id' => 'rVT43tfDwjh4p1BV2gJ5D7Zl2BswChO5L_llmlphLaTPytcGcguBAEJ1qK4cg4r_'
	));
	exit();
}

// 用户回复4 - 回复视频消息
if ($msg->MsgType == 'text' && $msg->Content == '4') {

	$wechat->reply(array(
		'type' => 'video',
		// 通过素材管理接口上传多媒体文件，得到的id
		'media_id' => 'yV0l71NL0wtpRA8OMX0-dBRQsMVyt3fspPUzurIS3psi6eWOrb_WlEeO39jasoZ8',
	 	'title' => '视频消息的标题',			//可选
	 	'description' => '视频消息的描述'		//可选
	));
	exit();
}

// 用户回复5 - 回复音乐消息
if ($msg->MsgType == 'text' && $msg->Content == '5') {

	$wechat->reply(array(
		'type' => 'music',
		'title' => '音乐标题',						//可选
		'description' => '音乐描述',				//可选
		'music_url' => 'http://me.diary8.com/data/music/2.mp3',		//可选
		'hqmusic_url' => 'http://me.diary8.com/data/music/2.mp3',	//可选
		'thumb_media_id' => 'O39wW0ZsXCb5VhFoCgibQs5PupFb6VZ2jH5A8gHUJCJz2Qmkrb7objoTue7bGTGQ',
	));
	exit();
}

// 用户回复6 - 回复图文消息
if ($msg->MsgType == 'text' && $msg->Content == '6') {

	$wechat->reply(array(
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
		)
	));
	exit();
}

// 用户回复7 - 发送主动消息
if ($msg->MsgType == 'text' && $msg->Content == '7') {
	// 被动回复
	$wechat->reply("这是被动回复的一条消息");
	// 主动发送
	$api->send($msg->FromUserName, '这是主动发送的一条消息');
	// 主动发送
	$api->send($msg->FromUserName, array(
		'type' => 'text',
		'content' => '这是一个客服主动发送的一条消息!',
		'kf_account' => 'test1@kftest'
	));	
	// 主动发送
	$api->send($msg->FromUserName, '您的openid是: ' . $msg->FromUserName);
	exit();
}

// 默认回复默认信息
$wechat->reply($default_msg);