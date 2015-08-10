<?php
/**
 * Api.php
 * 
 * 微信公共平台除需要自动回复外的服务中间件
 * - 生成 access_token
 * - 发送客服消息（文本、图片、语音、视频、音乐、图文）
 *
 * @author 		gaoming13 <gaoming13@yeah.net>
 * @link 		https://github.com/gaoming13/wechat-php-sdk
 * @link 		http://me.diary8.com/
 */

namespace Gaoming13\WechatPhpSdk;

use Gaoming13\WechatPhpSdk\Utils\Http;

class Api 
{
    // 微信API域名
    const API_DOMAIN = 'https://api.weixin.qq.com/';

    // 开发者中心-配置项-AppID(应用ID)
    protected $appId;
    // 开发者中心-配置项-AppSecret(应用密钥)
    protected $appSecret;

    // 用户自定义获取access_token的方法
    protected $getTokenDiy;
    // 用户自定义保存access_token的方法
    protected $saveTokenDiy;

	/**
     * 设定配置项
     *
     * @param array $config
     */
    public function __construct($config, $getTokenDiy = FALSE, $saveTokenDiy = FALSE) {
        $this->appId            =   $config['appId'];
        $this->appSecret        =   $config['appSecret'];
        $this->getTokenDiy      =   $getTokenDiy;
        $this->saveTokenDiy     =   $saveTokenDiy;
    }

    /**
     * 校验access_token是否过期
     *
     * @param string $token
     *
     * @return bool
     */
    public function checkToken($token) {
        error_log($token->expires_in . ' ' .time());
        return $token && isset($token->expires_in) && ($token->expires_in > time() + 1200);
    }

    /**
     * 生成新的access_token
     *
     * @return mixed
     */
    public function newToken() {        
        $url = self::API_DOMAIN . 'cgi-bin/token?grant_type=client_credential&appid=' . $this->appId . '&secret=' . $this->appSecret;
        $res = Http::get($url, 'json');

        // 异常处理: 获取access_token网络错误
        if ($res === FALSE) {
            @error_log('Http Get AccessToken Error.', 0);
            return FALSE;            
        }

        // 异常处理: access_token获取失败
        if (!isset($res->access_token)) {
            @error_log('Get AccessToken Error: ' . json_encode($res), 0);
            return FALSE;
        }
        $res->expires_in += time();
        return $res;
    }

    /**
     * 获取access_token
     *
     * @return string
     */
    public function getToken() {
        $token = FALSE;
        
        if ($this->getTokenDiy !== FALSE) {
            // 调用用户自定义获取AccessToken方法
            $token = call_user_func($this->getTokenDiy);
            if ($token) {
                $token = json_decode($token);
            }
        } else {
            // 异常处理: 获取access_token方法未定义
            @error_log('Not set getTokenDiy method, AccessToken will be refreshed each time.', 0);
        }

        // 验证AccessToken是否有效
        if (!$this->checkToken($token)) {

            // 生成新的AccessToken
            $token = $this->newToken();
            if ($token === FALSE) {
                return FALSE;
            }

            // 保存新生成的AccessToken
            if ($this->saveTokenDiy !== FALSE) {                
                // 用户自定义保存AccessToken方法    
                call_user_func($this->saveTokenDiy, json_encode($token));
            } else {
                // 异常处理: 保存access_token方法未定义
                @error_log('Not set saveTokenDiy method, AccessToken will be refreshed each time.', 0);
            }
        }
        return $token->access_token;
    }
    
    /**
     * 发送客服消息（文本、图片、语音、视频、音乐、图文）
     *
     * @param string $openid     
     * @param array $msg
     *
     * @return bool
     */
    public function send ($openid, $msg) {
        // 获取消息类型
        $msg_type = '';
        if (gettype($msg)=='string') {
            $msg_type = 'text_simple';
        } elseif (gettype($msg)=='array') {         
            $msg_type = $msg['type'];
        }

        $xml = '';
        switch ($msg_type) {
            /**
             * 1.1 发送文本消息(简洁输入)
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'hello world!');
             * ```
             */
            case 'text_simple':
                $xml = sprintf('{
                        "touser":"%s",
                        "msgtype":"text",
                        "text":{
                            "content":"%s"
                        }}',
                        $openid,
                        $msg);            
                break;

            /**
             * 1.2 发送文本消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', array(
             *  'type' => 'text',
             *  'content' => 'hello world!'
             * ));
             * ```
             */         
            case 'text':
                $xml = sprintf('{
                        "touser":"%s",
                        "msgtype":"text",
                        "text":{
                            "content":"%s"
                        }%s}', 
                        $openid,
                        $msg['content'],
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 2 发送图片消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', array(
             *  'type' => 'image',
             *  'media_id' => 'Uq7OczuEGEyUu--dYjg7seTm-EJTa0Zj7UDP9zUGNkVpjcEHhl7tU2Mv8mFRiLKC'
             * ));
             * ```
             */         
            case 'image':
                $xml = sprintf('{
                        "touser":"%s",
                        "msgtype":"image",
                        "image":{
                            "media_id":"%s"
                        }%s}', 
                        $openid,                        
                        $msg['media_id'],
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 3 发送语音消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', array(
             *  'type' => 'voice',
             *  'media_id' => 'rVT43tfDwjh4p1BV2gJ5D7Zl2BswChO5L_llmlphLaTPytcGcguBAEJ1qK4cg4r_'
             *  ));
             * ```
             */         
            case 'voice':
                $xml = sprintf('{
                        "touser":"%s",
                        "msgtype":"voice",
                        "voice":{
                            "media_id":"%s"
                        }%s}', 
                        $openid,
                        $msg['media_id'],
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 4 发送视频消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', array(
             *  'type' => 'video',
             *  'media_id' => 'yV0l71NL0wtpRA8OMX0-dBRQsMVyt3fspPUzurIS3psi6eWOrb_WlEeO39jasoZ8',
             *  'thumb_media_id' => '7ct_DvuwZXIO9e9qbIf2ThkonUX_FzLAoqBrK-jzUboTYJX0ngOhbz6loS-wDvyZ',     // 可选(无效, 官方文档好像写错了)
             *  'title' => '视频消息的标题',           // 可选
             *  'description' => '视频消息的描述'      // 可选
             * ));                         
             * ```
             */         
            case 'video':
                $xml = sprintf('{
                        "touser":"%s",
                        "msgtype":"video",
                        "video":{
                            "media_id":"%s",
                            "thumb_media_id":"%s",
                            "title":"%s",
                            "description":"%s"                            
                        }%s}', 
                        $openid,
                        $msg['media_id'],
                        $msg['thumb_media_id'],
                        isset($msg['title']) ? $msg['title'] : '',
                        isset($msg['description']) ? $msg['description'] : '',
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 5 发送音乐消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', array(
             *  'type' => 'music',
             *  'title' => '音乐标题',                      //可选
             *  'description' => '音乐描述',                //可选
             *  'music_url' => 'http://me.diary8.com/data/music/2.mp3',     //可选
             *  'hqmusic_url' => 'http://me.diary8.com/data/music/2.mp3',   //可选
             *  'thumb_media_id' => 'O39wW0ZsXCb5VhFoCgibQs5PupFb6VZ2jH5A8gHUJCJz2Qmkrb7objoTue7bGTGQ',
             * ));             
             * ```
             */         
            case 'music':
                $xml = sprintf('{
                        "touser":"%s",
                        "msgtype":"music",
                        "music":{
                            "title":"%s",
                            "description":"%s",
                            "musicurl":"%s",
                            "hqmusicurl":"%s",
                            "thumb_media_id":"%s" 
                        }%s}', 
                        $openid,
                        isset($msg['title']) ? $msg['title'] : '',
                        isset($msg['description']) ? $msg['description'] : '',
                        isset($msg['music_url']) ? $msg['music_url'] : '',
                        isset($msg['hqmusic_url']) ? $msg['hqmusic_url'] : '',
                        $msg['thumb_media_id'],
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 6 发送图文消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', array(
             *  'type' => 'music',
             *  'title' => '音乐标题',                      //可选
             *  'description' => '音乐描述',                //可选
             *  'music_url' => 'http://me.diary8.com/data/music/2.mp3',     //可选
             *  'hqmusic_url' => 'http://me.diary8.com/data/music/2.mp3',   //可选
             *  'thumb_media_id' => 'O39wW0ZsXCb5VhFoCgibQs5PupFb6VZ2jH5A8gHUJCJz2Qmkrb7objoTue7bGTGQ',
             * ));             
             * ```
             */         
            case 'news':
                $articles = array();             
                foreach ($msg['articles'] as $article) {
                    array_push($articles, sprintf('{
                        "title":"%s",
                        "description":"%s",
                        "url":"%s",
                        "picurl":"%s"
                        }',
                        $article['title'],
                        $article['description'],                        
                        $article['url'],
                        $article['picurl']));
                }
                $articles = implode(",", $articles);
                $xml = sprintf('{
                        "touser":"%s",
                        "msgtype":"news",
                        "news":{"articles": [%s]}%s}',
                        $openid,
                        $articles,
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 0 异常消息处理
             *
             */ 
            default:
                @error_log("$msg_type is not a message type that can be used.", 0);
                exit();             
                break;
        }

        $url = self::API_DOMAIN . 'cgi-bin/message/custom/send?access_token=' . $this->getToken();
        $res = Http::post($url, $xml, 'json');
        // 异常处理: 获取access_token网络错误
        if ($res === FALSE) {
            @error_log("Http Send $msg_type Message Error.", 0);
            return FALSE;
        }
        // 判断是否调用成功     
        if ($res->errcode == 0) {
            return TRUE;
        } else {
            @error_log(json_encode($res), 0);
            return FALSE;
        }
    }
}