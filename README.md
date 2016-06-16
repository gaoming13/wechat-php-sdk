# wechat-php-sdk
微信公众平台php版开发包
* 支持消息加解密方式的明文模式、兼容模式、安全模式
* 支持自动接入微信公众平台（[步骤](#接入微信公众平台开发方法)） 

## 功能模块
Wechat （处理自动接入、获取与回复微信消息）([使用说明](#wechat-模块使用说明))
* [接收普通消息/事件推送](#wechat-接收普通消息事件推送)
* [被动回复（文本、图片、语音、视频、音乐、图文）](#wechat-被动回复文本图片语音视频音乐图文)
* [转发到多客服接口](#wechat-转发到多客服接口)

Api （处理需要access_token的主动接口）([使用说明](#api-模块使用说明))
* [主送发送客服消息（文本、图片、语音、视频、音乐、图文）](#api发送客服消息文本图片语音视频音乐图文) 
* [多客服功能（客服管理、多客服回话控制、获取客服聊天记录等）](#api多客服功能客服管理多客服回话控制获取客服聊天记录等) 
* [素材管理（临时素材、永久素材、素材统计）](#api素材管理临时素材永久素材素材统计) 
* [自定义菜单管理（创建、查询、删除菜单）](#api自定义菜单管理创建查询删除菜单)
* [微信JSSDK（生成微信JSSDK所需的配置信息）](#api微信jssdk生成微信jssdk所需的配置信息)
* [账号管理（生成带参数的二维码、长链接转短链接接口）](#api账号管理生成带参数的二维码长链接转短链接接口)
* [用户管理（用户分组管理、设置用户备注名、获取用户基本信息、获取用户列表、网页授权获取用户基本信息）](#api用户管理用户分组管理设置用户备注名获取用户基本信息获取用户列表网页授权获取用户基本信息)
* [微信JSAPI支付](#api微信公众号内支付)
* 数据统计接口（开发中...）

## DEMO
项目内 `demo/demo_simple.php`

```php
require 'wechat-php-sdk/autoload.php';

use Gaoming13\WechatPhpSdk\Wechat;

$wechat = new Wechat(array(		
	'appId' 		=>	'wx733d7f24bd29224a',	
	'token' 		=> 	'gaoming13',		
	'encodingAESKey' =>	'072vHYArTp33eFwznlSvTRvuyOTe5YME1vxSoyZbzaV'
));

// 获取消息
$msg = $wechat->serve();

// 回复消息
if ($msg->MsgType == 'text' && $msg->Content == '你好') {
	$wechat->reply("你也好！");
} else {
	$wechat->reply("听不懂！");
}
```

## 如何引入wechat-php-sdk
1. 手动引入

  ```php
  <?php	  
	  require "wechat-php-sdk/autoload.php";	// 引入自动加载SDK类的方法
	  
	  use Gaoming13\WechatPhpSdk\Wechat;
	  use Gaoming13\WechatPhpSdk\Api;
	  ...
  ```
            
2. 使用 `composer`

  ```shell
  #安装composer依赖
  composer require "gaoming13/wechat-php-sdk:1.0.*"
  ``` 

  ```php   
  require "vendor/autoload.php";
  use Gaoming13\WechatPhpSdk\Wechat;
  use Gaoming13\WechatPhpSdk\Api;
  ```
  
3. `ThinkPHP` 内使用

  将SDK内 `src` 文件夹重命名为 `Gaoming13`, 拷贝至 `ThinkPHP/Library/` 下即可使用 `Wechat` 和 `Api` 类库.

  Thinkphp控制器内使用SDK的DEMO:

  具体代码见: 项目内 `demo/demo_thinkPHP.php`

  ```php
  $wechat = new \Gaoming13\WechatPhpSdk\Wechat(array(		
  	'appId' => $appId,	
  	'token' => 	$token,
  	'encodingAESKey' =>	$encodingAESKey
  ));

  $api = new \Gaoming13\WechatPhpSdk\Api(
    array(
        'appId' => $appId,
        'appSecret'	=> $appSecret,
        'get_access_token' => function(){
            // 用户需要自己实现access_token的返回
            ...
        },
        'save_access_token' => function($token) {
            // 用户需要自己实现access_token的保存
            ...
        }
    )
  );
  ```

### 接入微信公众平台开发方法
[官方wiki](http://mp.weixin.qq.com/wiki/17/2d4265491f12608cd170a95559800f2d.html)

以项目中的 `demo/demo_simple.php` 为例

1. 进入自己微信公众平台 `开发者中心`, 进入修改`服务器配置`页面
2. `URL`填写`demo_simple.php`的访问地址, 比如`http://wx.diary8.com/demo/demo_simple.php`,确保外网可访问到
3. 填写`Token`和`EncodingAESKey`, `消息加解密方式`可任意选择
4. 修改`demo.php`里配置项`appId`和`token`,  `appId`为`AppID(应用ID)`,`token`为第3部填写的`token`, 如果`消息加解密方式`选择了`兼容模式`或`安全模式`,还需要填写`encodingAESKey`
5. 提交`服务器配置`表单
6. ！！！ 注意成功后还需要启用服务器配置，不然不生效


## Wechat: 模块使用说明

```php
$wechat = new Wechat(array(	
    // 开发者中心-配置项-AppID(应用ID)		
    'appId' 		=>	'wx733d7f24bd29224a',
    // 开发者中心-配置项-服务器配置-Token(令牌)
    'token' 		=> 	'gaoming13',
    // 开发者中心-配置项-服务器配置-EncodingAESKey(消息加解密密钥)
    // 可选: 消息加解密方式勾选 兼容模式 或 安全模式 需填写
    'encodingAESKey' =>	'072vHYArTp33eFwznlSvTRvuyOTe5YME1vxSoyZbzaV'
));

// 获取微信消息
$msg = $wechat->serve();

// 回复微信消息
if ($msg->MsgType == 'text' && $msg->Content == '你好') {
    $wechat->reply("你也好！");
} else {
    $wechat->reply("听不懂！");
}
```

## Wechat: 接收普通消息/事件推送

接受到的普通消息与事件推送会原样以数组对象返回，具体每种消息结构请看:

[官方wiki 接收普通消息](http://mp.weixin.qq.com/wiki/10/79502792eef98d6e0c6e1739da387346.html)
[官方wiki 接收事件推送](http://mp.weixin.qq.com/wiki/2/5baf56ce4947d35003b86a9805634b1e.html)

```php
$msg = $wechat->serve();
```

## Wechat: 被动回复（文本、图片、语音、视频、音乐、图文）

[官方wiki](http://mp.weixin.qq.com/wiki/14/89b871b5466b19b3efa4ada8e577d45e.html)

### 回复文本消息
    
```php
$wechat->reply('hello world!');
// 或者
$wechat->reply(array(
	'type' => 'text',
	'content' => '嘿嘿，呵呵~~'
));
```
    
### 回复图片消息
    
```php
$wechat->reply(array(
	'type' => 'image',
	// 通过素材管理接口上传多媒体文件，得到的id
	'media_id' => 'Uq7OczuEGEyUu--dYjg7seTm-EJTa0Zj7UDP9zUGNkVpjcEHhl7tU2Mv8mFRiLKC'
));
```
        
### 回复语音消息

```php
$wechat->reply(array(
	'type' => 'voice',
	// 通过素材管理接口上传多媒体文件，得到的id
	'media_id' => 'rVT43tfDwjh4p1BV2gJ5D7Zl2BswChO5L_llmlphLaTPytcGcguBAEJ1qK4cg4r_'
));
```
        
### 回复视频消息
    
```php
$wechat->reply(array(
	'type' => 'video',
	// 通过素材管理接口上传多媒体文件，得到的id
	'media_id' => 'yV0l71NL0wtpRA8OMX0-dBRQsMVyt3fspPUzurIS3psi6eWOrb_WlEeO39jasoZ8',
	'title' => '视频消息的标题',			//可选
	'description' => '视频消息的描述'		//可选
));
```
        
### 回复音乐消息
    
```php
$wechat->reply(array(
	'type' => 'music',
	'title' => '音乐标题',						//可选
	'description' => '音乐描述',				//可选
	'music_url' => 'http://me.diary8.com/data/music/2.mp3',		//可选
	'hqmusic_url' => 'http://me.diary8.com/data/music/2.mp3',	//可选
	'thumb_media_id' => 'O39wW0ZsXCb5VhFoCgibQs5PupFb6VZ2jH5A8gHUJCJz2Qmkrb7objoTue7bGTGQ',
));
```

### 回复图文消息
    
```php
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
```

## Wechat: 转发到多客服接口
    
```php
$wechat->reply(array(
	'type' => 'transfer_customer_service',
	'kf_account' => 'test1@test'				// 可选
));
```

## Api: 模块使用说明

### access_token需要用户自己实现缓存
由于微信的access_token请求次数有限制，

用户需要自己实现access_token的获取和保存，

否则access_token每次都会被更新，请求限额很快就用完了.


```php
$api = new Api(
    array(
        'appId' => $appId,
        'appSecret' => $appSecret,
        'get_access_token' => function() {
            // 用户需要在这里实现access_token的返回
            ...
        },
        'save_access_token' => function($token) {
            // 用户需要在这里实现access_token的保存
            ...
        }
    )
);
```

access_token可以保存在数据库、Memcached、xcache 等.

当同一个微信号被用于多个项目中，access_token需要全局维护.

以下DEMO使用了Memcached缓存access_token

具体代码见: `demo/demo_message.php`

```php
use Gaoming13\WechatPhpSdk\Wechat;
use Gaoming13\WechatPhpSdk\Api;

// AppID(应用ID)
$appId = 'wx733d7f24bd29224a';
// AppSecret(应用密钥)
$appSecret = 'c6de6zcw78522dddww8w42e403376a410e';
// Token(令牌)
$token = 'gaoming13';
// EncodingAESKey(消息加解密密钥)
$encodingAESKey = '072vHYArTp33eFwznlSvTRvuyOTe5YME1vxSoyZbzaV';

// 这是使用了Memcached来保存access_token
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
        'get_access_token' => function() use ($m) {
            // 用户需要自己实现access_token的返回
            return $m->get('access_token');
        },
        'save_access_token' => function($token) use ($m) {
            // 用户需要自己实现access_token的保存
            $m->set('access_token', $token, 0);
        },
        'get_jsapi_ticket' => function() use ($m) {
            // 可选：用户需要自己实现jsapi_ticket的返回（若使用get_jsapi_config，则必须定义）
            return $m->get('jsapi_ticket');
        },
        'save_jsapi_ticket' => function($jsapi_ticket) use ($m) {
            // 可选：用户需要自己实现jsapi_ticket的保存（若使用get_jsapi_config，则必须定义）
            $m->set('jsapi_ticket', $jsapi_ticket, 0);
        }
    )
);


// 获取微信消息
$msg = $wechat->serve();

// 被动回复用户消息
$wechat->reply('这是我被动发送的消息！');

// 主动发送文本消息
$api->send($msg->FromUserName, '这是我主动发送的消息！');
```

### Api模块接口返回值格式
所有Api模块的接口返回值格式为: `array($err, $data);`

`$err`为错误信息, `$data`为正确处理返回的数据

可用`list`接收: 

```php
list($err, $kf_list) = $api->get_kf_list();
if (is_null($err)) {
	// 接口正确返回处理
} else {
	// 接口错误返回处理
}
```

## Api：发送客服消息（文本、图片、语音、视频、音乐、图文）

[官方wiki](http://mp.weixin.qq.com/wiki/1/70a29afed17f56d537c833f89be979c9.html)

### 主动发送文本消息
    
```php
$api->send($msg->FromUserName, 'heheh');
// 或者
$api->send($msg->FromUserName, array(
	'type' => 'text',
	'content' => 'hello world!',
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));
```

### 主动发送图片消息
    
```php
$api->send($msg->FromUserName, array(
	'type' => 'image',
	'media_id' => 'Uq7OczuEGEyUu--dYjg7seTm-EJTa0Zj7UDP9zUGNkVpjcEHhl7tU2Mv8mFRiLKC',
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));
```

### 主动发送语音消息
    
```php
$api->send($msg->FromUserName, array(
	'type' => 'voice',
	'media_id' => 'rVT43tfDwjh4p1BV2gJ5D7Zl2BswChO5L_llmlphLaTPytcGcguBAEJ1qK4cg4r_',
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));
```

### 主动发送视频消息
    
```php
$api->send($msg->FromUserName, array(
	'type' => 'video',
	'media_id' => 'yV0l71NL0wtpRA8OMX0-dBRQsMVyt3fspPUzurIS3psi6eWOrb_WlEeO39jasoZ8',
	'thumb_media_id' => '7ct_DvuwZXIO9e9qbIf2ThkonUX_FzLAoqBrK-jzUboTYJX0ngOhbz6loS-wDvyZ',		// 可选(无效, 官方文档好像写错了)
	'title' => '视频消息的标题',			// 可选
	'description' => '视频消息的描述',		// 可选,
	'kf_account' => 'test1@kftest'			// 可选(指定某个客服发送, 会显示这个客服的头像)
));
```

### 主动发送音乐消息
    
```php
$api->send($msg->FromUserName, array(
	'type' => 'music',
	'title' => '音乐标题',						//可选
	'description' => '音乐描述',				//可选
	'music_url' => 'http://me.diary8.com/data/music/2.mp3',		//可选
	'hqmusic_url' => 'http://me.diary8.com/data/music/2.mp3',	//可选
	'thumb_media_id' => 'O39wW0ZsXCb5VhFoCgibQs5PupFb6VZ2jH5A8gHUJCJz2Qmkrb7objoTue7bGTGQ',
	'kf_account' => 'test1@kftest'		// 可选(指定某个客服发送, 会显示这个客服的头像)
));
```

### 主动发送图文消息
    
```php
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
```

## Api：多客服功能（客服管理、多客服回话控制、获取客服聊天记录等）

[官方wiki](http://mp.weixin.qq.com/wiki/5/ae230189c9bd07a6b221f48619aeef35.html)

### 添加客服账号
    
```php
$api->add_kf('test1234@微信号', '客服昵称', '客服密码');
```

### 设置客服信息
    
```php
$api->update_kf('test1234@微信号', '客服昵称', '客服密码');
```

### 上传客服头像
    
```php
$api->set_kf_avatar('GB2@gbchina2000', '/website/wx/demo/test.jpg');
```

### 删除客服帐号
    
```php
$api->del_kf('test1234@微信号');
```

### 获取所有客服账号
    
```php
$api->get_kf_list();
```

### 获取在线客服接待信息
    
```php
$api->get_online_kf_list();
```

### 获取客服聊天记录接口
    
```php
$api->get_kf_records(1439348167, 1439384060, 1, 10);
```

### 创建客户与客服的会话
    
```php
$api->create_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'test1@微信号', '小明请求接入会话!');
```

### 关闭客户与客服的会话
    
```php
$api->close_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'test1@微信号', '与小明的回话已关闭!');
```

### 获取客户的会话状态
    
```php
$api->get_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw');
```

### 获取客服的会话列表
    
```php
$api->get_kf_session_list('test1@微信号');
```

### 获取未接入会话列表的客户
    
```php
$api->get_waitcase_list();
```

## Api：素材管理（临时素材、永久素材、素材统计）

[官方wiki](http://mp.weixin.qq.com/wiki/5/963fc70b80dc75483a271298a76a8d59.html)

### 新增临时素材
    
```php
$api->upload_media('image', '/data/img/fighting.jpg');
$api->upload_media('voice', '/data/img/song.amr');
$api->upload_media('video', '/data/img/go.mp4');
$api->upload_media('thumb', '/data/img/sky.jpg');
```

### 获取临时素材URL
    
```php
$api->get_media('UNsNhYrHG6e0oUtC8AyjCntIW1JYoBOmmwvM4oCcxZUBQ5PDFgeB9umDhrd9zOa-');
```

### 下载临时素材
    
```php
header('Content-type: image/jpg');
list($err, $data) = $api->download_media('UNsNhYrHG6e0oUtC8AyjCntIW1JYoBOmmwvM4oCcxZUBQ5PDFgeB9umDhrd9zOa-');
echo $data;
```

### 新增永久素材
    
```php
// 新增图片素材
list($err, $res) = $api->add_material('image', '/website/me/data/img/fighting.jpg');
// 新增音频素材
list($err, $res) = $api->add_material('voice', '/data/img/song.amr');
// 新增视频素材
list($err, $res) = $api->add_material('video', '/website/me/data/video/2.mp4', '视频素材的标题', '视频素材的描述');
// 新增略缩图素材
list($err, $res) = $api->add_material('thumb', '/data/img/sky.jpg');
```

### 新增永久图文素材
    
```php
$api->add_news(array(
	array(
		'title' => '标题',
		'thumb_media_id' => '图文消息的封面图片素材id（必须是永久mediaID）',
		'author' => '作者',
		'digest' => '图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空',
		'show_cover_pic' => '是否显示封面，0为false，即不显示，1为true，即显示',
		'content' => '图文消息的具体内容，支持HTML标签，必须少于2万字符，小于1M，且此处会去除JS',
		'content_source_url' => '图文消息的原文地址，即点击“阅读原文”后的URL'
	),
	array(
		'title' => '这是图文的标题',
		'thumb_media_id' => 'BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g',
		'author' => '这是图文的作者',
		'digest' => '',
		'show_cover_pic' => true,
		'content' => '这是图文消息的具体内容',
		'content_source_url' => 'http://www.baidu.com/'
	)
));
```

### 修改永久图文素材
    
```php
list($err, $res) = $api->update_news('BZ-ih-dnjWDyNXjai6i6sZp22xhHu6twVYKNPyl77Ms', array(
	'title' => '标题',
	'thumb_media_id' => 'BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g',
	'author' => '作者',
	'digest' => '图文消息的摘要',
	'show_cover_pic' => true,
	'content' => '图文消息的具体内容',
	'content_source_url' => 'http://www.diandian.com/'
), 1); 
```

### 获取永久素材
    
```php
// 获取图片、音频、略缩图素材
// 返回素材的内容，可保存为文件或直接输出
header('Content-type: image/jpg');
list($err, $data) = $api->get_material('BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g');
echo $data;

// 获取视频素材
// 返回带down_url的json字符串
list($err, $data) = $api->get_material('BZ-ih-dnjWDyNXjai6i6sbOICualzdwwnWWBqxW39Xk');
var_dump(json_decode($data));

// 获取图文素材
// 返回图文的json字符串     
list($err, $data) = $api->get_material('BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g');
var_dump(json_decode($data));
```

### 删除永久素材
    
```php
list($err, $res) = $api->del_material('BZ-ih-dnjWDyNXjai6i6sbOICualzdwwnWWBqxW39Xk');
if (is_null($err)) {
	// 删除成功
}
```

### 获取素材总数
    
```php
$api->get_material_count();
```

### 获取素材列表
    
```php
$api->get_materials('image', 0, 20);
$api->get_materials('voice', 0, 20);
$api->get_materials('video', 0, 20);
$api->get_materials('thumb', 0, 20);
```

## Api：自定义菜单管理（创建、查询、删除菜单）

[官方wiki](http://mp.weixin.qq.com/wiki/13/43de8269be54a0a6f64413e4dfa94f39.html)

### 自定义菜单创建接口
    
```php
$api->create_menu('
{
    "button":[
        {   
          "type":"click",
          "name":"主菜单1",
          "key":"V1001_TODAY_MUSIC"
        },
        {
            "name":"主菜单2",
            "sub_button":[
                {
                    "type":"click",
                    "name":"点击推事件",
                    "key":"click_event1"
                },
                {
                    "type":"view",
                    "name":"跳转URL",
                    "url":"http://www.example.com/"
                },
                {
                    "type":"scancode_push",
                    "name":"扫码推事件",
                    "key":"scancode_push_event1"
                },
                {
                    "type":"scancode_waitmsg",
                    "name":"扫码带提示",
                    "key":"scancode_waitmsg_event1"
                }
            ]
       },
       {
            "name":"主菜单3",
            "sub_button":[
                {
                    "type":"pic_sysphoto",
                    "name":"系统拍照发图",
                    "key":"pic_sysphoto_event1"
                },
                {
                    "type":"pic_photo_or_album",
                    "name":"拍照或者相册发图",
                    "key":"pic_photo_or_album_event1"
                },
                {
                    "type":"pic_weixin",
                    "name":"微信相册发图",
                    "key":"pic_weixin_event1"
                },
                {
                    "type":"location_select",
                    "name":"发送位置",
                    "key":"location_select_event1"
                }
            ]
       }
    ]
}');
```

### 自定义菜单查询接口
    
```php
$api->get_menu();
```

### 自定义菜单删除接口
    
```php
$api->delete_menu();
```

### 获取自定义菜单配置接口
    
```php
$api->get_selfmenu();
```

## Api：微信JSSDK（生成微信JSSDK所需的配置信息）

[官方wiki](http://mp.weixin.qq.com/wiki/7/aaa137b55fb2e0456bf8dd9148dd613f.html)

### 获取JS-SDK配置需要的信息

使用前请确认，初始化Api时，已填写并实现了`get_jsapi_ticket`和`save_jsapi_ticket`

```php
$api->get_jsapi_config();
$api->get_jsapi_config('http://www.baidu.com/');

$api->get_jsapi_config('', 'json');
$api->get_jsapi_config('', 'jsonp');
$api->get_jsapi_config('', 'jsonp', 'callback');
```

## Api：账号管理（生成带参数的二维码、长链接转短链接接口）

[官方wiki](http://mp.weixin.qq.com/wiki/18/28fc21e7ed87bec960651f0ce873ef8a.html)

### 生成带参数的二维码

```php
list($err, $data) = $api->create_qrcode(1234); // 创建一个永久二维码
list($err, $data) = $api->create_qrcode(1234, 100); //创建一个临时二维码，有效期100秒
```

### 通过ticket换取二维码，返回二维码url地址

```php
$api->get_qrcode_url('gQH58DoAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xLzQweUctT2psME1lcEJPYWJkbUswAAIEApzVVQMEZAAAAA==');
```

### 通过ticket换取二维码，返回二维码图片的内容

```php
list($err, $data) = $api->get_qrcode('gQGa8ToAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xLzlVeXJZWS1seGNlODZ2SV9XMkMwAAIEo5rVVQMEAAAAAA==');
header('Content-type: image/jpg');
echo $data;
```

### 长链接转短链接接口

```php
list($err, $data) = $api->shorturl('http://me.diary8.com/category/web-front-end.html');
echo $data->short_url;
```

## Api：用户管理（用户分组管理、设置用户备注名、获取用户基本信息、获取用户列表、网页授权获取用户基本信息）

[官方wiki](http://mp.weixin.qq.com/wiki/0/56d992c605a97245eb7e617854b169fc.html)

### 用户分组管理 - 创建分组

```php
list($err, $data) = $api->create_group('新的一个分组');
echo $data->group->id;
```

### 用户分组管理 - 查询所有分组

```php
list($err, $data) = $api->get_groups();
foreach ($data->groups as $group) {
    var_dump($group);
}
```

### 用户分组管理 - 查询用户所在分组

```php
list($err, $data) = $api->get_user_group('ocNtAt0YPGDme5tJBXyTphvrQIrc');
echo $data->groupid;
```

### 用户分组管理 - 修改分组名

```php
$api->update_group(100, '自定义分组了');
```

### 用户分组管理 - 移动用户分组

```php
$api->update_user_group('ocNtAt0YPGDme5tJBXyTphvrQIrc', 100);
```

### 用户分组管理 - 批量移动用户分组

```php
$api->batchupdate_user_group(array(
    'ocNtAt0YPGDme5tJBXyTphvrQIrc',
    'ocNtAt_TirhYM6waGeNUbCfhtZoA',
    'ocNtAt_K8nRlAdmNEo_R0WVg_rRw'
    ), 100);
```

### 用户分组管理 - 删除分组

```php
$api->delete_group(102);
```

### 设置用户备注名

```php
$api->update_user_remark('ocNtAt0YPGDme5tJBXyTphvrQIrc', '用户的备注名');
```

### 获取用户基本信息

```php
$api->get_user_info('ocNtAt_K8nRlAdmNEo_R0WVg_rRw');
$api->get_user_info('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'zh_TW');
```

### 获取用户列表

```php
$api->get_user_list();
$api->get_user_list('ocNtAt_TirhYM6waGeNUbCfhtZoA');
```

### 网页授权获取用户基本信息

有两种授权类型：

0. `snsapi_base` 静默授权，用户无感知，但只能获取到`openid`
0. `snsapi_userinfo` 可以获得openid、昵称、性别、所在地等更详细的信息，但首次授权会跳转微信的一个授权页面，用户点击同意后授权成功

两种授权流程使用说明：

demo见项目内 `demo/snsapi/`

1. 通过 `get_authorize_url` 生成获取用户授权的链接，用户打开该链接后会跳转到 `回调地址页面`

    ```php
    $api->get_authorize_url('授权类型', '回调地址');
    $api->get_authorize_url('snsapi_base','http://wx.diary8.com/demo/snsapi/callback_snsapi_base.php');
    $api->get_authorize_url('snsapi_userinfo', 'http://wx.diary8.com/demo/snsapi/callback_snsapi_userinfo.php');
    ```
2. 在 `回调地址页面` 通过 `get_userinfo_by_authorize` 获取用户信息

    ```php
    list($err, $user_info) = $api->get_userinfo_by_authorize('snsapi_base');
    if ($user_info !== null) {
        var_dump($user_info);;
    } else {
        echo '授权失败！';
    }
    ```

    ```php
    list($err, $user_info) = $api->get_userinfo_by_authorize('snsapi_userinfo');
    if ($user_info !== null) {
        var_dump($user_info);;
    } else {
        echo '授权失败！';
    }
    ```

## Api：微信JSAPI支付

[官方wiki](https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=7_1)

[官方SDK](https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=11_1)

支付过程中SDK使用流程：

- 通过 网页授权获取用户基本信息 `openid`
- 调用 `wxPayUnifiedOrder` 生成预订单
- 调用 `getWxPayJsApiParameters` 生成jsapi支付的参数，作为js调用支付接口的参数

## License

MIT