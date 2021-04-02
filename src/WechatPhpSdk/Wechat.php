<?php
/**
 * Wechat模块（处理获取微信消息与被动回复）
 * - 接收普通消息/事件推送
 * - 被动回复（文本、图片、语音、视频、音乐、图文）
 * - 转发到多客服接口
 * - 支持消息加解密方式的明文模式、兼容模式、安全模式
 * - 支持接入微信公众平台
 */

namespace Gaoming13\WechatPhpSdk;

use Gaoming13\WechatPhpSdk\Utils\Prpcrypt;
use Gaoming13\WechatPhpSdk\Utils\SHA1;

class Wechat
{
    // 开发者中心-配置项-AppID(应用ID)
    protected $appId;
    // 开发者中心-配置项-服务器配置-Token(令牌)
    protected $token;
    // 开发者中心-配置项-服务器配置-EncodingAESKey(消息加解密密钥)
    protected $encodingAESKey;

    // 消息的加密签名
    protected $signature;
    // 消息的时间戳
    protected $timestamp;
    // 消息的随机数
    protected $nonce;
    // 消息的随机字符串
    protected $echostr;
    // 消息的加密类型
    protected $encrypt_type;
    // 消息体的签名
    protected $msg_signature;
    // 消息对象数组
    protected $message;

    /**
     * 设定配置项
     *
     * @param array $config
     */
    public function __construct($config)
    {
        $this->appId = $config['appId'];
        $this->token = $config['token'];
        $this->encodingAESKey = isset($config['encodingAESKey']) && !empty($config['encodingAESKey']) ? $config['encodingAESKey'] : false;
    }

    /**
     * 微信消息处理主入口
     * - 自动处理服务器配置验证
     *
     * @return string
     */
    public function serve()
    {
        // 微信消息GET参数处理
        $this->checkParams();
        // 处理微信URL接入
        $this->accessAuth();
        // 获取微信消息
        return $this->getMessage();
    }

    /**
     * 被动回复微信消息
     *
     * @param $msg array
     *
     */
    public function reply($msg)
    {
        // 获取消息类型
        $msg_type = '';
        if (gettype($msg)=='string') {
            $msg_type = 'text_simple';
        } elseif (gettype($msg)=='array') {
            $msg_type = $msg['type'];
        }

        switch ($msg_type) {
            /**
             * 1.1 回复文本消息(简洁输入)
             *
             * Examples:
             * ```
             * $wechat->reply('hello world!');
             * ```
             */
            case 'text_simple':
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[text]]></MsgType>'.
                        '<Content><![CDATA[%s]]></Content>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        $msg);
                break;

            /**
             * 1.2 回复文本消息
             *
             * Examples:
             * ```
             * $wechat->reply([
             *    'type' => 'text',
             *    'content' => '嘿嘿，呵呵~~'
             * ]);
             * ```
             */
            case 'text':
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[text]]></MsgType>'.
                        '<Content><![CDATA[%s]]></Content>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        $msg['content']);
                break;

            /**
             * 2 回复图片消息
             *
             * Examples:
             * ```
             * $wechat->reply([
             *      'type' => 'image',
             *      'media_id' => 'Uq7OczuEGEyUu--dYjg7seTm-EJTa0Zj7UDP9zUGNkVpjcEHhl7tU2Mv8mFRiLKC'
             * ]);
             * ```
             */
            case 'image':
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[image]]></MsgType>'.
                        '<Image><MediaId><![CDATA[%s]]></MediaId></Image>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        $msg['media_id']);
                break;

            /**
             * 3 回复语音消息
             *
             * Examples:
             * ```
             * $wechat->reply([
             *    'type' => 'voice',
             *    'media_id' => 'rVT43tfDwjh4p1BV2gJ5D7Zl2BswChO5L_llmlphLaTPytcGcguBAEJ1qK4cg4r_'
             * ]);
             * ```
             */
            case 'voice':
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[voice]]></MsgType>'.
                        '<Voice><MediaId><![CDATA[%s]]></MediaId></Voice>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        $msg['media_id']);
                break;

            /**
             * 4 回复视频消息
             *
             * Examples:
             * ```
             * $wechat->reply([
            *    'type' => 'video',
            *    'media_id' => 'yV0l71NL0wtpRA8OMX0-dBRQsMVyt3fspPUzurIS3psi6eWOrb_WlEeO39jasoZ8',
            *    'title' => '视频消息的标题',        //可选
            *    'description' => '视频消息的描述'   //可选
             * ]);
             * ```
             */
            case 'video':
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[video]]></MsgType>'.
                        '<Video>'.
                        '<MediaId><![CDATA[%s]]></MediaId>'.
                        '<Title><![CDATA[%s]]></Title>'.
                        '<Description><![CDATA[%s]]></Description>'.
                        '</Video>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        $msg['media_id'],
                        isset($msg['title']) ? $msg['title'] : '',
                        isset($msg['description']) ? $msg['description'] : '');
                break;

            /**
             * 5 回复音乐消息
             *
             * Examples:
             * ```
             * $wechat->reply([
              *    'type' => 'music',
              *    'title' => '音乐标题',           //可选
              *    'description' => '音乐描述',     //可选
              *    'music_url' => 'http://me.diary8.com/data/music/2.mp3',      //可选
              *    'hqmusic_url' => 'http://me.diary8.com/data/music/2.mp3',    //可选
              *    'thumb_media_id' => 'O39wW0ZsXCb5VhFoCgibQs5PupFb6VZ2jH5A8gHUJCJz2Qmkrb7objoTue7bGTGQ',
             * ]);
             * ```
             */
            case 'music':
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[music]]></MsgType>'.
                        '<Music>'.
                        '<Title><![CDATA[%s]]></Title>'.
                        '<Description><![CDATA[%s]]></Description>'.
                        '<MusicUrl><![CDATA[%s]]></MusicUrl>'.
                        '<HQMusicUrl><![CDATA[%s]]></HQMusicUrl>'.
                        '<ThumbMediaId><![CDATA[%s]]></ThumbMediaId>'.
                        '</Music>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        isset($msg['title']) ? $msg['title'] : '',
                        isset($msg['description']) ? $msg['description'] : '',
                        isset($msg['music_url']) ? $msg['music_url'] : '',
                        isset($msg['hqmusic_url']) ? $msg['hqmusic_url'] : '',
                        $msg['thumb_media_id']);
                break;

            /**
             * 6 回复图文消息
             *
             * Examples:
             * ```
             * $wechat->reply([
             *    'type' => 'news',
             *    'articles' => [
             *        [
             *            'title' => '图文消息标题1',                                //可选
             *            'description' => '图文消息描述1',                          //可选
             *            'picurl' => 'http://me.diary8.com/data/img/demo1.jpg',   //可选
             *            'url' => 'http://www.example.com/'                       //可选
             *        ],
             *        [
             *            'title' => '图文消息标题2',
             *            'description' => '图文消息描述2',
             *            'picurl' => 'http://me.diary8.com/data/img/demo2.jpg',
             *            'url' => 'http://www.example.com/'
             *        ],
             *        [
             *            'title' => '图文消息标题3',
             *            'description' => '图文消息描述3',
             *            'picurl' => 'http://me.diary8.com/data/img/demo3.jpg',
             *            'url' => 'http://www.example.com/'
             *        ],
             *     ],
             * ]);
             * ```
             */
            case 'news':
                $articles = '';
                foreach ($msg['articles'] as $article) {
                    $articles .= sprintf('<item>'.
                                    '<Title><![CDATA[%s]]></Title>'.
                                    '<Description><![CDATA[%s]]></Description>'.
                                    '<PicUrl><![CDATA[%s]]></PicUrl>'.
                                    '<Url><![CDATA[%s]]></Url>'.
                                '</item>',
                                $article['title'],
                                $article['description'],
                                $article['picurl'],
                                $article['url']);
                }
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[news]]></MsgType>'.
                        '<ArticleCount>%s</ArticleCount>'.
                        '<Articles>%s</Articles>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        count($msg['articles']),
                        $articles);
                break;

            /**
             * 7 转发到多客服接口
             *
             * Examples:
             * ```
             * $wechat->reply([
             *    'type' => 'transfer_customer_service',
             *    'kf_account' => 'test1@test'            // 可选
             * ]);
             * ```
             */
            case 'transfer_customer_service':
                $xml_transinfo = '';
                if (isset($msg['kf_account']) && !empty($msg['kf_account'])) {
                    $xml_transinfo = sprintf('<TransInfo>'.
                            '<KfAccount><![CDATA[%s]]></KfAccount>'.
                            '</TransInfo>',
                            $msg['kf_account']);
                }
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[transfer_customer_service]]></MsgType>%s'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        $xml_transinfo);
                break;

             /**
             * 8 回复设备消息
             *
             * Examples:
             * ```
             * $wechat->reply([
              *    'type' => 'device_text',
              *    'device_type' => '设备类型，目前为“公众账号原始ID”',
              *    'device_id' => '设备ID，第三方提供',
              *    'session_id' => '微信客户端生成的session id',
              *    'content' => '消息内容，BASE64编码'
             * ]);
             * ```
             */
            case 'device_text':
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[device_text]]></MsgType>'.
                        '<DeviceType><![CDATA[%s]]></DeviceType>'.
                        '<DeviceID><![CDATA[%s]]></DeviceID>'.
                        '<SessionID>%s</SessionID>'.
                        '<Content><![CDATA[%s]]></Content>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        isset($msg['device_type']) ? $msg['device_type'] : '',
                        isset($msg['device_id']) ? $msg['device_id'] : '',
                        isset($msg['session_id']) ? $msg['session_id'] : '',
                        base64_encode(isset($msg['content']) ? $msg['content'] : ''));
                break;

            /**
             * 8 回复设备连接状态消息
             *
             * Examples:
             * ```
             * $wechat->reply([
              *    'type' => 'device_status',
              *    'device_type' => '设备类型，目前为“公众账号原始ID”',
              *    'device_id' => '设备ID，第三方提供',
              *    'deviceStatus' => '设备状态:0未连接,1已连接'
             * ]);
             * ```
             */
            case 'device_status':
                $xml = sprintf('<xml>'.
                        '<ToUserName><![CDATA[%s]]></ToUserName>'.
                        '<FromUserName><![CDATA[%s]]></FromUserName>'.
                        '<CreateTime>%s</CreateTime>'.
                        '<MsgType><![CDATA[device_status]]></MsgType>'.
                        '<DeviceType><![CDATA[%s]]></DeviceType>'.
                        '<DeviceID><![CDATA[%s]]></DeviceID>'.
                        '<DeviceStatus>%s</DeviceStatus>'.
                        '</xml>',
                        $this->message['FromUserName'],
                        $this->message['ToUserName'],
                        time(),
                        isset($msg['device_type']) ? $msg['device_type'] : '',
                        isset($msg['device_id']) ? $msg['device_id'] : '',
                        isset($msg['device_status']) ? $msg['device_status'] : ''
                    );
                break;


            /**
             * 0 异常消息处理
             *
             */
            default:
                @error_log("[wechat-php-sdk]$msg_type is not a message type that can be used.", 0);
                exit();
                break;
        }

        // 对消息加解密方式 兼容模式\安全模式 - 加密消息
        if ($this->encrypt_type == 'aes' && $xml != '') {

            // 异常处理: 未配置encodingAESKey
            if ($this->encodingAESKey === false) {
                @error_log('[wechat-php-sdk]EncodingAESKey Not Defined.', 0);
                exit();
            }

            $reply_timestamp = time();

            // 消息加密
            $pc = new Prpcrypt($this->encodingAESKey);
            $reply_encrypt = $pc->encrypt($xml, $this->appId);
            // 消息加密: 异常处理
            if ($reply_encrypt === false) {
                @error_log('[wechat-php-sdk]Encrypt Message Error.', 0);
                exit();
            }

            // 生成安全签名
            $reply_signature = SHA1::getSHA1($this->token, $reply_timestamp, $this->nonce, $reply_encrypt);
            // 生成安全签名: 异常处理
            if ($reply_signature===false) {
                @error_log('[wechat-php-sdk]Get Message Signature Error.', 0);
                exit();
            }

            // 生成发送的xml
            $xml = sprintf('<xml>'.
                        '<Encrypt><![CDATA[%s]]></Encrypt>'.
                        '<MsgSignature><![CDATA[%s]]></MsgSignature>'.
                        '<TimeStamp>%s</TimeStamp>'.
                        '<Nonce><![CDATA[%s]]></Nonce>'.
                        '</xml>',
                        $reply_encrypt,
                        $reply_signature,
                        $reply_timestamp,
                        $this->nonce);
        }
        echo $xml;
    }

    /**
     * 微信消息GET参数处理
     *
     */
    private function checkParams()
    {
        $this->signature = isset($_GET['signature']) && !empty($_GET['signature']) ? $_GET['signature'] : false;
        $this->timestamp = isset($_GET['timestamp']) && !empty($_GET['timestamp']) ? $_GET['timestamp'] : false;
        $this->nonce = isset($_GET['nonce']) && !empty($_GET['nonce']) ? $_GET['nonce'] : false;
        $this->echostr = isset($_GET['echostr']) && !empty($_GET['echostr']) ? $_GET['echostr'] : false;
        $this->encrypt_type = isset($_GET['encrypt_type']) && !empty($_GET['encrypt_type']) ? $_GET['encrypt_type'] : false;
        $this->msg_signature = isset($_GET['msg_signature']) && !empty($_GET['msg_signature']) ? $_GET['msg_signature'] : false;
    }

    /**
     * 检查signature是否正确
     *
     * @return bool
     */
    private function checkSignature()
    {
        if ($this->signature !== false && $this->timestamp !== false && $this->nonce !== false) {
            $tmp_signature = SHA1::getSignature($this->token, $this->timestamp, $this->nonce);
            if ($tmp_signature === false) {
                error_log('[wechat-php-sdk]Validate signature error.', 0);
            } else if ($tmp_signature === $this->signature) {
                return true;
            }
        }
        return false;
    }

    /**
     * 处理服务器配置URL验证
     *
     */
    private function accessAuth()
    {
        // 处理服务器配置URL验证成功
        if ($this->echostr !== false) {
            if (! $this->checkSignature()) {
                // 验证失败
                @error_log('[wechat-php-sdk]accessAuth Error.', 0);
            }
            // 返回echostr给微信服务器
            exit($this->echostr);
        }
    }

    /**
     * 获取用户发送的消息
     * - 已集成消息解密
     *
     * @return array
     */
    private function getMessage()
    {
        if ($this->echostr === false && $this->checkSignature()) {

            // 获取微信原始消息体
            if (@file_get_contents('php://input') !== false) {
                $xml_input = file_get_contents('php://input');
            } else {
                $xml_input = $GLOBALS['HTTP_RAW_POST_DATA'];
            }
            if (!empty($xml_input)) {

                // XML解析微信消息体
                libxml_disable_entity_loader(true);
                $xml_obj = simplexml_load_string($xml_input, 'SimpleXMLElement', LIBXML_NOCDATA);

                // 微信 兼容模式/安全模式 信息解密
                if ($this->encrypt_type == 'aes') {
                    // 异常处理: 未配置encodingAESKey
                    if ($this->encodingAESKey===false) {
                        @error_log('[wechat-php-sdk]EncodingAESKey Not Defined.', 0);
                        exit();
                    }
                    // 验证用户消息的安全签名
                    $msg_signature = SHA1::getSHA1($this->token, $this->timestamp, $this->nonce, $xml_obj->Encrypt);
                    if ($msg_signature === false) {
                        @error_log('[wechat-php-sdk]getSHA1 Error.', 0);
                        exit();
                    }
                    if ($msg_signature != $this->msg_signature){
                        @error_log('[wechat-php-sdk]MsgSignature Not Match.', 0);
                        exit();
                    }
                    // 解密用户消息
                    $pc = new Prpcrypt($this->encodingAESKey);
                    $xml_input1 = $pc->decrypt($xml_obj->Encrypt, $this->appId);
                    if ($xml_input1 === false) {
                        @error_log('[wechat-php-sdk]Decode Message Error.', 0);
                        exit();
                    }
                    $xml_obj = simplexml_load_string($xml_input1, 'SimpleXMLElement', LIBXML_NOCDATA);
                }
                $this->message = json_decode(json_encode($xml_obj), true);
                return $this->message;
            }
        }
        exit();
    }
}
