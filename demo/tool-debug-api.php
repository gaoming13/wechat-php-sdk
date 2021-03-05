<?php
require '../autoload.php';

use Gaoming13\WechatPhpSdk\Api;
use Gaoming13\WechatPhpSdk\Utils\FileCache;

// 文件缓存用来暂存access_token(也可以用redis/memcached)
$cache = new FileCache([
    'path' => __DIR__.'/runtime/',
]);

// api模块
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

// 函数名称
$query = $_POST;
$function = isset($query['function']) ? $query['function'] : '';

// 获取access_token
if ($function === 'get_access_token') {
    echo $api->get_access_token();
    exit();
}

// 创建菜单
if ($function === 'create_menu') {
    list($err, $res) = $api->create_menu($query['json']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo json_encode($res);
    }
    exit();
}

// 永久公众号二维码生成
if ($function === 'qrcode_create') {
    list($err, $res) = $api->qrcode_create($query['actionName'], $query['sceneStr'], $query['expireSeconds']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo $res['url'];
    }
    exit();
}

// 发送模板消息
if ($function === 'message_template_send') {
    list($err, $res) = $api->message_template_send($query['openId'], $query['templateId'], json_encode($query['data']), '');
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo json_encode($res);
    }
    exit();
}

// 微信硬件 - 获取设备二维码
if ($function === 'device_create_qrcode') {
    list($err, $res) = $api->device_create_qrcode($query['deviceId']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo $res['ticket'];
    }
    exit();
}

// 微信硬件 - 设备授权
if ($function === 'device_authorize_device') {
    list($err, $res) = $api->device_authorize_device([
        'id' => $query['deviceId'],
        'mac' => $query['deviceMac'],
        'connect_protocol' => $query['deviceConnectProtocol'],
        'auth_key' => $query['deviceAuthKey'],
        'close_strategy' => $query['deviceCloseStrategy'],
        'conn_strategy' => $query['deviceConnStrategy'],
        'crypt_method' => $query['deviceCryptMethod'],
        'auth_ver' => $query['deviceAuthVer'],
        'manu_mac_pos' => $query['deviceManuMacPos'],
        'ser_mac_pos' => $query['deviceSerMacPos'],
    ], $query['opType'], $query['productId']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo json_encode($res);
    }
    exit();
}

// 微信硬件 - 获取用户绑定的设备列表
if ($function === 'device_get_bind_device') {
    list($err, $res) = $api->device_get_bind_device($query['openId']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo json_encode($res);
    }
    exit();
}

// 微信硬件 - 获取设备绑定的openId
if ($function === 'device_get_openid') {
    list($err, $res) = $api->device_get_openid($query['deviceId']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo json_encode($res);
    }
    exit();
}

// 微信硬件 - 设备状态查询
if ($function === 'device_get_stat') {
    list($err, $res) = $api->device_get_stat($query['deviceId']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo json_encode($res);
    }
    exit();
}

// 微信硬件 - 强制解绑用户设备
if ($function === 'device_compel_unbind') {
    list($err, $res) = $api->device_compel_unbind($query['deviceId'], $query['openId']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        // 提醒用户
        $api->message_template_send($query['openId'], 'PGENGfbcWuGjaINM0LxOuuU5vBwnUui147yBeWKwxy4', [
            'title' => ['value' => '解绑成功', 'color' => '#173177'],
            'content' => ['value' => json_encode($res), 'color' => '#173177'],
        ], '');
        echo json_encode($res);
    }
    exit();
}

// 微信硬件 - 强制绑定用户设备
if ($function === 'device_compel_bind') {
    list($err, $res) = $api->device_compel_bind($query['deviceId'], $query['openId']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        // 提醒用户
        $api->message_template_send($query['openId'], 'PGENGfbcWuGjaINM0LxOuuU5vBwnUui147yBeWKwxy4', [
            'title' => ['value' => '绑定成功', 'color' => '#173177'],
            'content' => ['value' => json_encode($res), 'color' => '#173177'],
        ], '');
        echo json_encode($res);
    }
    exit();
}


// 微信硬件 - 发送消息给设备
if ($function === 'device_transmsg') {
    list($err, $res) = $api->device_transmsg($query['deviceId'], $query['openId'], $query['content']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo json_encode($res);
    }
    exit();
}

// 微信硬件 - 发送设备状态消息给微信终端
if ($function === 'device_transmsg_device_status') {
    list($err, $res) = $api->device_transmsg_device_status($query['deviceId'], $query['openId'], $query['deviceStatus']);
    if ($err !== null) {
        echo json_encode($err);
    } else {
        echo json_encode($res);
    }
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="renderer" content="webkit">
<meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1">
<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
<style>
form { font-size: 12px; }
.tip { font-size: 12px; padding: 10px;line-height: 1.2;border: 1px dotted #785;background: #f5f5f5; }
input[type=text] { width: 80%; max-width: 300px; }
</style>
<script src="https://cdn.bootcdn.net/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
</head>
<body>
<form>
    <fieldset>
        <legend>获取access_token</legend>
        <input type="submit" value="提交"><br>
        <input name="function" type="hidden" value="get_access_token">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>设置菜单 <a href=https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Creating_Custom-Defined_Menu.html" target="_blank">【官方文档】</a></legend>
        菜单json：<input name="json" type="text" value='{"button": [{"type": "click", "name": "按钮1", "key": "button1_click"}]}'><br>
        <input type="submit" value="提交"><br>
        <input name="function" type="hidden" value="create_menu">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>公众号二维码生成 <a href="https://developers.weixin.qq.com/doc/offiaccount/Account_Management/Generating_a_Parametric_QR_Code.html" target="_blank">【官方文档】</a></legend>
        二维码类型：<input name="actionName" type="text" value='QR_LIMIT_STR_SCENE'><br>
        场景值值：<input name="sceneStr" type="text" value=''><br>
        二维码有效时间：<input name="expireSeconds" type="text" value='30'><br>
        <input type="submit" value="提交"><br>
        <input name="function" type="hidden" value="qrcode_create">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>发送模板消息 <a href="https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html#5" target="_blank">【官方文档】</a></legend>
        用户openId：<input name="openId" type="text" value="ocNtAt_K8nRlAdmNEo_R0WVg_rRw"><br>
        模版ID：<input name="templateId" type="text" value="RkkJlz1slFw_-ETPHnWkqfVSgjpX9bsCvqRzrSjI7Ek"><br>
        模版数据json：<input name="data" type="text" value='<?= json_encode(['character_string1' => ['value' => '标题1'],'thing11' => ['value' => '标题2'],'thing12' => ['value' => '标题3'],]) ?>'><br>
        链接地址：<input name="url" type="text" value="http://www.baidu.com"><br>
        <input type="submit" value="提交"><br>
        <input name="function" type="hidden" value="message_template_send">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 获取设备二维码 <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-4" target="_blank">【官方文档】</a></legend>
        deviceId：<input name="deviceId" type="text" value=""><br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_create_qrcode">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 设备授权 <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-5" target="_blank">【官方文档】</a></legend>
        opType：<input name="opType" type="text" value="0"> 0设备授权,1设备更新<br>
        产品编号：<input name="productId" type="text" value=""><br>
        设备号：<input name="deviceId" type="text" value=""><br>
        mac地址：<input name="deviceMac" type="text" value="1234567890AB"> 设备的mac地址 格式采用16进制串的方式（长度为12字节），不需要0X前缀，如：1234567890AB<br>
        连接协议：<input name="deviceConnectProtocol" type="text" value="4"> 支持以下四种连接协议：1:android classic bluetooth,2:ios classic bluetooth,3:ble,4:wifi<br>
        auth及通信的加密key：<input name="deviceAuthKey" type="text" value="1234567890ABCDEF1234567890ABCDEF"> auth及通信的加密key，第三方需要将key烧制在设备上（128bit），格式采用16进制串的方式（长度为32字节），不需要0X前缀，如：1234567890ABCDEF1234567890ABCDEF<br>
        断开策略：<input name="deviceCloseStrategy" type="text" value="2"> 1：退出公众号页面时即断开连接 2：退出公众号之后保持连接不断开<br>
        连接策略：<input name="deviceConnStrategy" type="text" value="5"> 1：（第1bit置位）在公众号对话页面，不停的尝试连接设备 4：（第3bit置位）处于非公众号页面（如主界面等），微信自动连接。当用户切换微信到前台时，可能尝试去连接设备，连上后一定时间会断开<br>
        auth加密方法：<input name="deviceCryptMethod" type="text" value="0"> 目前支持两种取值： 0：不加密 1：AES加密（CBC模式，PKCS7填充方式）<br>
        authVer：<input name="deviceAuthVer" type="text" value="0"> 0：不加密的version 1：version 1<br>
        manuMacPos：<input name="deviceManuMacPos" type="text" value="-1"> 表示mac地址在厂商广播manufature data里含有mac地址的偏移，取值如下： -1：在尾部、 -2：表示不包含mac地址 其他：非法偏移<br>
        serMacPos：<input name="deviceSerMacPos" type="text" value="-1"> 表示mac地址在厂商serial number里含有mac地址的偏移，取值如下： -1：表示在尾部 -2：表示不包含mac地址 其他：非法偏移<br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_authorize_device">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 获取用户绑定的设备列表 <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-12" target="_blank">【官方文档】</a></legend>
        用户openId：<input name="openId" type="text" value="ocNtAt_K8nRlAdmNEo_R0WVg_rRw"><br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_get_bind_device">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 获取设备绑定的openId <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-11" target="_blank">【官方文档】</a></legend>
        设备ID：<input name="deviceId" type="text" value=""><br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_get_openid">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 设备状态查询（三种状态：未授权、已授权、已绑定） <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-8" target="_blank">【官方文档】</a></legend>
        设备ID：<input name="deviceId" type="text" value=""><br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_get_stat">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 强制解绑用户设备 <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-7" target="_blank">【官方文档】</a></legend>
        设备ID：<input name="deviceId" type="text" value=""><br>
        用户openId：<input name="openId" type="text" value="ocNtAt_K8nRlAdmNEo_R0WVg_rRw"><br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_compel_unbind">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 强制绑定用户设备 <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-7" target="_blank">【官方文档】</a></legend>
        设备ID：<input name="deviceId" type="text" value=""><br>
        用户openId：<input name="openId" type="text" value="ocNtAt_K8nRlAdmNEo_R0WVg_rRw"><br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_compel_bind">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 发送消息给设备 <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-3" target="_blank">【官方文档】</a></legend>
        设备ID：<input name="deviceId" type="text" value=""><br>
        用户openId：<input name="openId" type="text" value="ocNtAt_K8nRlAdmNEo_R0WVg_rRw"><br>
        发送内容：<input name="content" type="text" value="这是发送的内容"><br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_transmsg">
        <pre class="tip"></pre>
    </fieldset>
</form>
<form>
    <fieldset>
        <legend>微信硬件 - 发送设备WIFI状态消息给微信终端 <a href="https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-13" target="_blank">【官方文档】</a></legend>
        设备ID：<input name="deviceId" type="text" value=""><br>
        用户openId：<input name="openId" type="text" value="ocNtAt_K8nRlAdmNEo_R0WVg_rRw"><br>
        设备状态：<input name="deviceStatus" type="text" value="1"> 设备状态： 0--未连接； 1--已连接<br>
        <input type="submit" value="确定"><br>
        <input name="function" type="hidden" value="device_transmsg_device_status">
        <pre class="tip"></pre>
    </fieldset>
</form>
<script>
$(function() {
    $('form').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        $('.tip', form).html('请求中...');
        var query = {};
        $('input[name]', form).each(function(index, v) {
            query[$(v).attr('name')] = $(v).val();
        });
        $.post('./tool-debug-api.php', query, function(res) {
            $('.tip', form).html(res);
        });
    });
});
</script>
</body>
</html>