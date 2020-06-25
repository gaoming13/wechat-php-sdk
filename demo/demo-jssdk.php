<?php
    define("APPID","wx891964efedb07584");
    define("APPSECRET","1d4fb01eb8aee41eb0eb4f005cca721b");
    // 缓存目录
    define("CACHE_PATH",__DIR__."/runtime/");
    require '../autoload.php';
    use Gaoming13\WechatPhpSdk\Api;
    use Gaoming13\WechatPhpSdk\Utils\FileCache;
    // 文件缓存
    $cache =  new FileCache;
    // api模块
    $api = new Api(
        array(
            'appId' => APPID,
            'appSecret' => APPSECRET,
            'get_access_token' => function() use ($cache) {
                // 用户需要自己实现access_token的返回
                return $cache->get('access_token');
            },
            'save_access_token' => function($token) use ($cache) {
                // 用户需要自己实现access_token的保存
                $cache->set('access_token', $token, 7000);
            }
        )
    );
    // 注意 URL 一定要动态获取，不能 hardcode.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $jsapi_config =  $api->get_jsapi_config($url);
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
    appId: '<?php echo $jsapi_config['appId']; ?>', // 必填，公众号的唯一标识
    timestamp: '<?php echo $jsapi_config['timestamp']; ?>', // 必填，生成签名的时间戳
    nonceStr: '<?php echo $jsapi_config['nonceStr']; ?>', // 必填，生成签名的随机串
    signature: '<?php echo $jsapi_config['signature']; ?>',// 必填，签名，见附录1
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