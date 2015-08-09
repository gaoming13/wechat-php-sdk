<?php

require '../autoload.php';

use Gaoming13\WechatPhpSdk\Api;

$m = new Memcached();
$m->addServer('localhost', 11211);

$api = new Api(
	array(
		'appId'		=> 	'wx733d7f24bd29224a',
		'appSecret'	=>	'c6d165c5785226806f42440e376a410e'
	),
	function(){
		global $m;		
		return $m->get('wechat_token');
	}, 
	function($token) {
		global $m;
		$m->set('wechat_token', $token, 0);
	}
);

$api->send('heheh');