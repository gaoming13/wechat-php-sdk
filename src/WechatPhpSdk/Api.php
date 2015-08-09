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
        return $token && isset($token->expires_in) && ($token->expires_in > time());        
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

    public function send ($msg) {
        var_dump($this->getToken());
    }
}