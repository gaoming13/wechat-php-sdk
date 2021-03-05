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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>demo-jssdk-扫码</title>
</head>
<body>
</body>
</html>
<script src="http://res.wx.qq.com/open/js/jweixin-1.2.0.js"></script>
<script>

wx.config({
    debug: false, // 开启调试模式
    appId: '<?php echo $jsapiConfig['appId']; ?>', // 必填，公众号的唯一标识
    timestamp: '<?php echo $jsapiConfig['timestamp']; ?>', // 必填，生成签名的时间戳
    nonceStr: '<?php echo $jsapiConfig['nonceStr']; ?>', // 必填，生成签名的随机串
    signature: '<?php echo $jsapiConfig['signature']; ?>',// 必填，签名，见附录1
    jsApiList: ['scanQRCode'] // 必填，需要使用的JS接口列表，所有JS接口列表见附录2
});

wx.ready(function(){
    wx.scanQRCode({
        needResult: 1, // 默认为0，扫描结果由微信处理，1则直接返回扫描结果，
        scanType: ["qrCode","barCode"], // 可以指定扫二维码还是一维码，默认二者都有
        success: function (res) {
            var result = res.resultStr; // 当needResult 为 1 时，扫码返回的结果
            alert(result);
        }
    });
});
wx.error(function(res){
    console.log(res);
});
</script>