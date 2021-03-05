<?php
require '../autoload.php';

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

$jsapiConfig = $api->get_jsapi_config('http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
<meta charset="utf-8">
<meta name="renderer" content="webkit">
<meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1">
<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
<title>调用事件页面</title>
<script src="http://res.wx.qq.com/open/js/jweixin-1.0.0.js"></script>
<script src= "http://libs.baidu.com/jquery/2.0.0/jquery.js" ></script>
<style>
#tip { font-size: 12px; padding: 10px;line-height: 1.2;border: 1px dotted #785;background: #f5f5f5; }
.item { border: 2px dotted #785; margin-top: 4px; padding: 4px; }
</style>
</head>
<body>
    <div id="tip"></div>
    <div class="item">
        <input id="configWXDeviceWiFiKey" type="text" value="" placeholder="AirKiss的密钥:可选" />
        <button id="configWXDeviceWiFi" class="btn">给设备配网</button>
    </div>
    <div class="item">
        <button id="startScanWXDevice" class="btn">开始扫描设备</button>
    </div>
    <div class="item">
        <button id="stopScanWXDevice" class="btn">停止扫描设备</button>
    </div>
    <div class="item">
        <input id="connectWXDeviceDeviceId" type="text" value="" placeholder="deviceId（必填）：设备id" />
        <button id="connectWXDevice" class="btn">连接设备</button>
    </div>
    <div class="item">
        <input id="disconnectWXDeviceDeviceId" type="text" value="" placeholder="deviceId（必填）：设备id" />
        <button id="disconnectWXDevice" class="btn">断开设备连接</button>
    </div>
    <div class="item">
        <button id="getWXDeviceInfos" class="btn">获取设备信息</button>
    </div>
    <div class="item">
        <input id="sendDataToWXDeviceDeviceId" type="text" value="" placeholder="deviceId（必填）：设备id" />
        <input id="sendDataToWXDeviceBase64Data" type="text" value="" placeholder="数据（必填）" />
        <button id="sendDataToWXDevice" class="btn">给设备发送数据</button>
    </div>
</body>
<script type="text/javascript">
$(function() {
    var addTip = function(msg) {
        $('#tip').append(msg + '<br>');
    };
    wx.config({
        beta: true,
        debug: true,
        appId: '<?= $jsapiConfig['appId'] ?>',
        timestamp: <?= $jsapiConfig['timestamp'] ?>,
        nonceStr: '<?= $jsapiConfig['nonceStr'] ?>',
        signature: '<?= $jsapiConfig['signature'] ?>',
        jsApiList : [
            'configWXDeviceWiFi',
            'openWXDeviceLib',
            'getWXDeviceInfos',
            'sendDataToWXDevice',
            'startScanWXDevice',
            'stopScanWXDevice',
            'connectWXDevice',
            'disconnectWXDevice',
            'getWXDeviceTicket',
            'onWXDeviceBindStateChange',
            'onWXDeviceStateChange',
            'onScanWXDeviceResul',
            'onWXDeviceLanStateChange',
        ],
    });
    wx.ready(() => {
        // 初始化设备库
        addTip('初始化设备库:openWXDeviceLib');
        wx.invoke('openWXDeviceLib', {'connType':'lan', 'brandUserName': 'gh_965f8b675d0e'}, function(res) {
            console.log('openWXDeviceLib',res);
        });
        // 微信客户端设备绑定状态改变事件
        wx.on('onWXDeviceBindStateChange', res => {
            addTip('监听到：微信客户端设备绑定状态改变事件：' + JSON.stringify(res));
        });
        // 设备连接状态变化
        wx.on('onWXDeviceStateChange', res => {
            addTip('监听到：设备连接状态变化事件：' + JSON.stringify(res));
        });
        // 接收到设备数据
        wx.on('onReceiveDataFromWXDevice', res => {
            addTip('监听到：接收到设备数据：' + JSON.stringify(res));
        });
        // 扫描到某个设备
        wx.on('onScanWXDeviceResult', res => {
            addTip('监听到：扫描到某个设备：' + JSON.stringify(res));
        });
        // 手机WIFI状态改变事件
        wx.on('onWXDeviceLanStateChange', res => {
            addTip('监听到：手机WIFI状态改变事件：' + JSON.stringify(res));
        });
    });
    // 给设备配网
    $('#configWXDeviceWiFi').on('click', function() {
        var query = {};
        var key = $('#configWXDeviceWiFiKey').val();
        addTip('开始configWXDeviceWiFi, 密钥：' + key);
        if (key !== '') query['key'] = window.btoa(key);
        wx.invoke('configWXDeviceWiFi', query, res => {
            addTip(JSON.stringify(res));
        });
    });
    // 开始扫描设备
    $('#startScanWXDevice').on('click', function() {
        addTip('开始扫描设备:startScanWXDevice');
        var query = {};
        query.connType = 'lan';
        wx.invoke('startScanWXDevice', query, function(res) {
            addTip(JSON.stringify(res));
        });
    });
    // 停止扫描设备
    $('#stopScanWXDevice').on('click', function() {
        addTip('停止扫描设备:stopScanWXDevice');
        var query = {};
        query.connType = 'lan';
        wx.invoke('stopScanWXDevice', query, function(res) {
            addTip(JSON.stringify(res));
        });
    });
    // 连接设备
    $('#connectWXDevice').on('click', function() {
        var query = {};
        query.deviceId = $('#connectWXDeviceDeviceId').val();
        query.connType = 'lan';
        addTip('开始连接设备：connectWXDevice：' + query.deviceId);
        wx.invoke('connectWXDevice', query, res => {
            addTip(JSON.stringify(res));
        });
    });
    // 断开设备连接
    $('#disconnectWXDevice').on('click', function() {
        var query = {};
        query.deviceId = $('#disconnectWXDeviceDeviceId').val();
        query.connType = 'lan';
        addTip('断开设备连接：disconnectWXDevice：' + query.deviceId);
        wx.invoke('disconnectWXDevice', query, res => {
            addTip(JSON.stringify(res));
        });
    });
    // 获取设备信息
    $('#getWXDeviceInfos').on('click', function() {
        addTip('获取设备信息:getWXDeviceInfos');
        var query = {};
        query.connType = 'lan';
        wx.invoke('getWXDeviceInfos', query, function(res) {
            addTip(JSON.stringify(res));
        });
    });
    // 发送数据给设备
    $('#sendDataToWXDevice').on('click', function() {
        addTip('开始发送数据给设备:sendDataToWXDevice');
        var query = {};
        query.deviceId = $('#sendDataToWXDeviceDeviceId').val();
        query.base64Data = window.btoa($('#sendDataToWXDeviceBase64Data').val());
        query.connType = 'lan';
        wx.invoke('sendDataToWXDevice', query, function(res) {
            addTip(JSON.stringify(res));
        });
    });
});
</script>
</html>