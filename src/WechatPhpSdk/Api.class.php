<?php
/**
 * Api.php
 *  
 * Api模块 （处理需要access_token的主动接口）
 * - 主送发送客服消息（文本、图片、语音、视频、音乐、图文）
 * - 多客服功能（客服管理、多客服回话控制、获取客服聊天记录...）
 * - 素材管理（临时素材、永久素材、素材统计）
 * - 自定义菜单管理（开发中...）
 *
 * @author 		gaoming13 <gaoming13@yeah.net>
 * @link 		https://github.com/gaoming13/wechat-php-sdk
 * @link 		http://me.diary8.com/
 */

namespace Gaoming13\WechatPhpSdk;

use Gaoming13\WechatPhpSdk\Utils\HttpCurl;
use Gaoming13\WechatPhpSdk\Utils\Error;

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
        $res = HttpCurl::get($url, 'json');

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
     * @return array(err, data)
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
             * $api->send($msg->FromUserName, array(
             *  'type' => 'news',
             *  'articles' => array(
             *      array(
             *          'title' => '图文消息标题1',                           //可选
             *          'description' => '图文消息描述1',                     //可选
             *          'picurl' => 'http://me.diary8.com/data/img/demo1.jpg',  //可选
             *          'url' => 'http://www.example.com/'                      //可选
             *      ),
             *      array(
             *          'title' => '图文消息标题2',
             *          'description' => '图文消息描述2',
             *          'picurl' => 'http://me.diary8.com/data/img/demo2.jpg',
             *          'url' => 'http://www.example.com/'
             *      ),
             *      array(
             *          'title' => '图文消息标题3',
             *          'description' => '图文消息描述3',
             *          'picurl' => 'http://me.diary8.com/data/img/demo3.jpg',
             *          'url' => 'http://www.example.com/'
             *      )
             *  ),
             *  'kf_account' => 'test1@kftest'      // 可选(指定某个客服发送, 会显示这个客服的头像)
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
            	return Error::code('ERR_MEG_TYPE');                
                break;
        }

        $url = self::API_DOMAIN . 'cgi-bin/message/custom/send?access_token=' . $this->getToken();
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取access_token网络错误
        if ($res === FALSE) {
        	return Error::code('ERR_GET');
        }
        // 判断是否调用成功     
        if ($res->errcode == 0) {
        	return array(NULL, TRUE);            
        } else {
        	return array($res, NULL);            
        }
    }


    /**
     * 添加客服账号
     *
     * @param string $kf_account
     * @param string $nickname
     * @param string $password
	 *
     * @return array(err, res)
     * 
	 * Examples:
	 * ```	 
	 * list($err, $res) = $api->add_kf('test1234@微信号', '客服昵称', '客服密码');
	 * ```               
     */
    public function add_kf ($kf_account, $nickname, $password) {
    	$password = md5($password);
    	$xml = sprintf('{
    			"kf_account" : "%s",
    			"nickname" : "%s",
    			"password" : "%s"}',
				$kf_account,
				$nickname,
				md5($password));
    	$url = self::API_DOMAIN . 'customservice/kfaccount/add?access_token=' . $this->getToken();    	
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
        	return Error::code('ERR_GET');
        }        
        // 判断是否调用成功        
        if ($res->errcode == 0) {
        	return array(NULL, TRUE);
        } else {
        	return array($res, NULL);
        }
    }

    /**
     * 设置客服信息
     *
     * @param string $kf_account
     * @param string $nickname
     * @param string $password
	 *
     * @return array(err, res)
     * 
	 * Examples:
	 * ```	 
	 * list($err, $res) = $api->update_kf('test1234@微信号', '客服昵称', '客服密码');
	 * ```               
     */
    public function update_kf ($kf_account, $nickname, $password) {
    	$password = md5($password);
    	$xml = sprintf('{
    			"kf_account" : "%s",
    			"nickname" : "%s",
    			"password" : "%s"}',
				$kf_account,
				$nickname,
				md5($password));
    	$url = self::API_DOMAIN . 'customservice/kfaccount/update?access_token=' . $this->getToken();    	
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
        	return Error::code('ERR_GET');
        }        
        // 判断是否调用成功        
        if ($res->errcode == 0) {
        	return array(NULL, TRUE);
        } else {
        	return array($res, NULL);
        }
    }

    /**
     * 上传客服头像
     *
     * @param string $kf_account
     * @param string $path     
	 *
     * @return array(err, res)
     * 
	 * Examples:
	 * ```	 
	 * list($err, $res) = $api->set_kf_avatar('GB2@gbchina2000', '/website/wx/demo/test.jpg');
	 * ```               
     */
    public function set_kf_avatar ($kf_account, $path) {
    	$url = self::API_DOMAIN . 'customservice/kfaccount/uploadheadimg?access_token=' . $this->getToken() . '&kf_account=' . $kf_account;        
        $res = HttpCurl::post($url, array('media' => '@'.$path), 'json');        
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
        	return Error::code('ERR_GET');
        }        
        // 判断是否调用成功        
        if ($res->errcode == 0) {
        	return array(NULL, TRUE);
        } else {
        	return array($res, NULL);
        }        
    }

    /**
     * 删除客服帐号
     *
     * @param string $kf_account
     *
     * @return array(err, res)
     *
	 * Examples:
	 * ```	 
	 * list($err, $res) = $api->del_kf('test1234@微信号');
	 * ```               
     */
    public function del_kf ($kf_account) {    	
    	$url = self::API_DOMAIN . 'customservice/kfaccount/del?access_token=' . $this->getToken() . '&kf_account=' . $kf_account;    	
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
        	return Error::code('ERR_GET');
        }        
        // 判断是否调用成功
        if ($res->errcode == 0) {
        	return array(NULL, TRUE);
        } else {
        	return array($res, NULL);
        }
    }

    /**
     * 获取所有客服账号
     *
     * @return array(err, data)
     *
	 * Examples:
	 * ```	 
	 * list($err, $kf_list) = $api->get_kf_list();
	 * ```               
     */
    public function get_kf_list () {    		
    	$url = self::API_DOMAIN . 'cgi-bin/customservice/getkflist?access_token=' . $this->getToken();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
        	return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res->kf_list)) {
        	return array(NULL, $res->kf_list);
        } else {        	
        	return array($res, NULL);
        }
    }

    /**
     * 获取在线客服接待信息
     *
     * @return array(err, data)
     *
	 * Examples:
	 * ```
	 * list($err, $kf_list) = $api->get_online_kf_list();
	 * ```               
     */
    public function get_online_kf_list () {    	
    	$url = self::API_DOMAIN . 'cgi-bin/customservice/getonlinekflist?access_token=' . $this->getToken();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
        	return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res->kf_online_list)) {
        	return array(NULL, $res->kf_online_list);
        } else {        	
        	return array($res, NULL);
        }
    }

    /**
     * 获取客服聊天记录接口
     *
     * @param int $starttime
     * @param int $endtime
     * @param int $pageindex
     * @param int $pagesize
     *
     * @return array(err, data)
     *
     * Examples:
     * ```
     * list($err, $record_list) = $api->get_kf_records(1439348167, 1439384060, 1, 10);
     * ```
     */
    public function get_kf_records ($starttime, $endtime, $pageindex, $pagesize) {        
        $url = self::API_DOMAIN . 'customservice/msgrecord/getrecord?access_token=' . $this->getToken();
        $xml = sprintf('{
                    "endtime" : %s,
                    "pageindex" : %s,
                    "pagesize" : %s,
                    "starttime" : %s}',
                    $endtime,
                    $pageindex,
                    $pagesize,
                    $starttime);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res->recordlist)) {
            return array(NULL, $res->recordlist);
        } else {            
            return array($res, NULL);
        }
    }

    /**
     * 创建客户与客服的会话
     *
     * @param string $kf_account
     * @param string $openid
     * @param string $text (可选)
     *
     * @return array(err, data)
     *
     * Examples:
     * ```
     * list($err, $res) = $api->create_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'test1@微信号', '小明请求接入会话!');
     * ```
     */
    public function create_kf_session ($openid, $kf_account, $text='') {        
        $url = self::API_DOMAIN . 'customservice/kfsession/create?access_token=' . $this->getToken();
        $xml = sprintf('{
                    "kf_account" : "%s",
                    "openid" : "%s",
                    "text" : "%s"}',
                    $kf_account,
                    $openid,
                    $text);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res->errcode == 0) {
            return array(NULL, TRUE);
        } else {            
            return array($res, NULL);
        }
    }

    /**
     * 关闭客户与客服的会话
     *
     * @param string $kf_account
     * @param string $openid
     * @param string $text (可选)
     *
     * @return array(err, data)
     *
     * Examples:
     * ```
     * list($err, $res) = $api->close_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'test1@微信号', '与小明的回话已关闭!');
     * ```
     */
    public function close_kf_session ($openid, $kf_account, $text='') {
        $url = self::API_DOMAIN . 'customservice/kfsession/close?access_token=' . $this->getToken();
        $xml = sprintf('{
                    "kf_account" : "%s",
                    "openid" : "%s",
                    "text" : "%s"}',
                    $kf_account,
                    $openid,
                    $text);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res->errcode == 0) {
            return array(NULL, TRUE);
        } else {            
            return array($res, NULL);
        }
    }

    /**
     * 获取客户的会话状态
     *     
     * @param string $openid
     *
     * @return array(err, data)
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw');
     * ```
     */
    public function get_kf_session ($openid) {        
        $url = self::API_DOMAIN . 'customservice/kfsession/getsession?access_token=' . $this->getToken() . '&openid=' . $openid;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res->errcode == 0) {
            return array(NULL, $res);
        } else {            
            return array($res, NULL);
        }
    }

    /**
     * 获取客服的会话列表
     *     
     * @param string $kf_account
     *
     * @return array(err, data)
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_kf_session_list('test1@微信号');
     * ```
     */
    public function get_kf_session_list ($kf_account) {        
        $url = self::API_DOMAIN . 'customservice/kfsession/getsessionlist?access_token=' . $this->getToken() . '&kf_account=' . $kf_account;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res->sessionlist)) {
            return array(NULL, $res->sessionlist);
        } else {            
            return array($res, NULL);
        }
    }

    /**
     * 获取未接入会话列表的客户        
     *
     * @return array(err, data)
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_waitcase_list();
     * ```
     */
    public function get_waitcase_list () {        
        $url = self::API_DOMAIN . 'customservice/kfsession/getwaitcase?access_token=' . $this->getToken();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }    
        // 判断是否调用成功
        if (isset($res->waitcaselist)) {
            return array(NULL, $res->waitcaselist);
        } else {            
            return array($res, NULL);
        }        
    }


    /**
     * 新增临时素材
     *
     * Examples:
     * ```
     * list($err, $res) = $api->upload_media('image', '/data/img/fighting.jpg');
     * list($err, $res) = $api->upload_media('voice', '/data/img/song.amr');
     * list($err, $res) = $api->upload_media('video', '/data/img/go.mp4');
     * list($err, $res) = $api->upload_media('thumb', '/data/img/sky.jpg');
     * ```
     * Result:
     * ```
     * [
     *     null,
     *     {
     *         type: "image",
     *         media_id: "CVS_UPz62LKIfDwc7bUWtI250x_KBLhOuYgkHr1GjVxJCP8N9rOYfgIKXSY5Wg9n",
     *         created_at: 1439623233
     *     }
     * ]
     * ```
     *
     * @param string $type 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb，主要用于视频与音乐格式的缩略图）
     * @param string $path 素材的绝对路径
     *
     * @return array(err, res)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function upload_media ($type, $path) {        
        $url = self::API_DOMAIN . 'cgi-bin/media/upload?access_token=' . $this->getToken() . '&type=' . $type;        
        $res = HttpCurl::post($url, array('media' => '@'.$path), 'json');        
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }        
        // 判断是否调用成功
        if (isset($res->media_id)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     * 获取临时素材URL
     *
     * Examples:
     * ```   
     * $url = $api->get_media('UNsNhYrHG6e0oUtC8AyjCntIW1JYoBOmmwvM4oCcxZUBQ5PDFgeB9umDhrd9zOa-');
     * ```
     * Result:
     * ```
     * https://api.weixin.qq.com/cgi-bin/media/get?access_token=egpGMhgnhbrqOo77wkUS7HmEFp40bITkRZNJk1gCGTH8i-BiVxai9zs0CcWk223dz6LiypGprpLHBRL9upjKQLqPgtAnqUeK9qznUyDsNXg&media_id=CVS_UPz62LKIfDwc7bUWtI250x_KBLhOuYgkHr1GjVxJCP8N9rOYfgIKXSY5Wg9n  
     * ```     
     *     
     * @param string $media_id 媒体文件ID
     *
     * @return string $url 媒体文件的URL     
     */
    public function get_media ($media_id) {        
        return self::API_DOMAIN . 'cgi-bin/media/get?access_token=' . $this->getToken() . '&media_id=' . $media_id;
    }

    /**
     * 下载临时素材
     *
     * Examples:
     * ```   
     * header('Content-type: image/jpg');
     * list($err, $data) = $api->download_media('UNsNhYrHG6e0oUtC8AyjCntIW1JYoBOmmwvM4oCcxZUBQ5PDFgeB9umDhrd9zOa-');
     * echo $data;
     * ```
     *
     * @param string $media_id 媒体文件ID
     *
     * @return array(err, res)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象        
     */
    public function download_media ($media_id) {
        $url = $this->get_media($media_id);
        $res = HttpCurl::get($url);
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        return array(NULL, $res);
    }

    /**
     * 新增永久素材
     *
     * Examples:
     * ```
     * // 新增图片素材
     * list($err, $res) = $api->add_material('image', '/website/me/data/img/fighting.jpg');
     * // 新增音频素材
     * list($err, $res) = $api->add_material('voice', '/data/img/song.amr');
     * // 新增视频素材
     * list($err, $res) = $api->add_material('video', '/website/me/data/video/2.mp4', '视频素材的标题', '视频素材的描述');
     * // 新增略缩图素材
     * list($err, $res) = $api->add_material('thumb', '/data/img/sky.jpg');
     * ```
     * Result:
     * ```    
     * [
     *     null,
     *     {
     *         media_id: "BZ-ih-dnjWDyNXjai6i6sbK8hTy_bs-PHtnLn8C-IAs",
     *         url: "https://mmbiz.qlogo.cn/mmbiz/InxuM0bx4ZWgxicicoy2tLibV2hyO5hWT4VlHNI6LticmppBiaG12cJ8icDoSR83zFSKDAz8qnY1miatZiaX8pZKUaIt7w/0?wx_fmt=jpeg"
     *     }
     * ]
     * ```   
     *
     * @param string $type 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     * @param string $path 要上传文件的绝对路径
     * @param string $title 可选: 视频素材的标题（video）
     * @param string $introduction 可选: 视频素材的描述（video）
     *
     * @return array(err, res)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function add_material ($type, $path, $title='', $introduction='') {        
        $url = self::API_DOMAIN . 'cgi-bin/material/add_material?access_token=' . $this->getToken() . '&type=' . $type;                
        $post_data = array('media' => '@'.$path);
        if ($type == 'video') {
            $post_data['description'] = sprintf('{"title":"%s","introduction":"%s"}', $title, $introduction);
        }
        $res = HttpCurl::post($url, $post_data, 'json');        
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res->media_id)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     * 新增永久图文素材
     *
     * @param string $articles     
     *
     * @return array(err, res)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象 
     *
     * Examples:
     * ```     
     * list($err, $res) = $api->add_news(array(
     *     array(
     *         'title' => '标题',
     *         'thumb_media_id' => '图文消息的封面图片素材id（必须是永久mediaID）',
     *         'author' => '作者',
     *         'digest' => '图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空',
     *         'show_cover_pic' => '是否显示封面，0为false，即不显示，1为true，即显示',
     *         'content' => '图文消息的具体内容，支持HTML标签，必须少于2万字符，小于1M，且此处会去除JS',
     *         'content_source_url' => '图文消息的原文地址，即点击“阅读原文”后的URL'
     *     ),
     *     array(
     *         'title' => '这是图文的标题',
     *         'thumb_media_id' => 'BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g',
     *         'author' => '这是图文的作者',
     *         'digest' => '',
     *         'show_cover_pic' => true,
     *         'content' => '这是图文消息的具体内容',
     *         'content_source_url' => 'http://www.baidu.com/'
     *     )
     * ));
     * ```
     * Result:
     * ```    
     * [
     *     null,
     *     {
     *         media_id: "BZ-ih-dnjWDyNXjai6i6sbK8hTy_bs-PHtnLn8C-IAs"     
     *     }
     * ]
     * ```
     */
    public function add_news ($articles) {        
        $url = self::API_DOMAIN . 'cgi-bin/material/add_news?access_token=' . $this->getToken();
        $articles1 = array();             
        foreach ($articles as $article) {
            array_push($articles1, sprintf('{
                "title":"%s",
                "thumb_media_id":"%s",
                "digest":"%s",
                "show_cover_pic":"%s",
                "content":"%s",
                "content_source_url":"%s"}',
                $article['title'],
                $article['thumb_media_id'],                        
                $article['digest'],
                $article['show_cover_pic'],
                $article['content'],                
                $article['content_source_url']));
        }
        $articles1 = implode(",", $articles1);
        $xml = sprintf('{"articles": [%s]}', $articles1);
        $res = HttpCurl::post($url, $xml, 'json');        
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res->media_id)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     * 修改永久图文素材
     *
     * Examples:
     * ```     
     * list($err, $res) = $api->update_news('BZ-ih-dnjWDyNXjai6i6sZp22xhHu6twVYKNPyl77Ms', array(
     *     'title' => '标题',
     *     'thumb_media_id' => 'BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g',
     *     'author' => '作者',
     *     'digest' => '图文消息的摘要',
     *     'show_cover_pic' => true,
     *     'content' => '图文消息的具体内容',
     *     'content_source_url' => 'http://www.diandian.com/'
     * ), 1); 
     * ```
     * Result:
     * ```    
     * [
     *     null,
     *     {
     *         errcode: 0,
     *         errmsg: "ok"
     *     }
     * ]
     * ``` 
     *     
     * @param string $media_id 要修改的图文消息的id
     * @param string $article 
     * @param string $index 要更新的文章在图文消息中的位置（多图文消息时，此字段才有意义），第一篇为0
     *
     * @return array(err, res)        
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象      
     */
    public function update_news ($media_id, $article, $index = 0) {        
        $url = self::API_DOMAIN . 'cgi-bin/material/update_news?access_token=' . $this->getToken();        
        $xml = sprintf('{
            "media_id":"%s",
            "index":"%s",
            "articles": {
                "title": "%s",
                "thumb_media_id": "%s",
                "author": "%s",
                "digest": "%s",
                "show_cover_pic": "%s",
                "content": "%s",
                "content_source_url": "%s"
            }}',
            $media_id, 
            $index,
            $article['title'],
            $article['thumb_media_id'],
            $article['author'],
            $article['digest'],
            $article['show_cover_pic'],
            $article['content'],                
            $article['content_source_url']);        
        $res = HttpCurl::post($url, $xml, 'json');        
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res->errcode == 0) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     * 获取永久素材
     *
     * Examples:
     * ```   
     * // 获取图片、音频、略缩图素材
     * // 返回素材的内容，可保存为文件或直接输出
     * header('Content-type: image/jpg');
     * list($err, $data) = $api->get_material('BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g');
     * echo $data;
     *
     * // 获取视频素材
     * // 返回带down_url的json字符串
     * list($err, $data) = $api->get_material('BZ-ih-dnjWDyNXjai6i6sbOICualzdwwnWWBqxW39Xk');
     * var_dump(json_decode($data));
     *
     * // 获取图文素材
     * // 返回图文的json字符串     
     * list($err, $data) = $api->get_material('BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g');
     * var_dump(json_decode($data));
     * ```   
     *     
     * @param string $media_id 要获取的素材的media_id
     *
     * @return array(err, res)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_material ($media_id) {                
        $url = self::API_DOMAIN . 'cgi-bin/material/get_material?access_token=' . $this->getToken();
        $xml = '{"media_id":"' . $media_id . '"}';
        $res = HttpCurl::post($url, $xml);
        // 异常处理: 获取时网络错误        
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        return array(NULL, $res);
    }

    /**
     * 删除永久素材
     *
     * Examples:
     * ```   
     * list($err, $res) = $api->del_material('BZ-ih-dnjWDyNXjai6i6sbOICualzdwwnWWBqxW39Xk');
     * if (is_null($err)) {
     *  // 删除成功
     * }
     * ```
     * Result:
     * ```    
     * [
     *     null,
     *     {
     *         errcode: 0,
     *         errmsg: "ok"
     *     }
     * ]
     * ``` 
     *     
     * @param string $media_id 要删除的素材的media_id
     *
     * @return array(err, res)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象                 
     */
    public function del_material ($media_id) {        
        $url = self::API_DOMAIN . 'cgi-bin/material/del_material?access_token=' . $this->getToken();
        $xml = '{"media_id":"' . $media_id . '"}';
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误        
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res->errcode == 0) {
            return array(NULL, $res);
        } else {            
            return array($res, NULL);
        }        
    }

    /**
     * 获取素材总数        
     *     
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象       
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_material_count();
     * ```
     * Result:
     * ```    
     * [
     *     null,
     *     {
     *         voice_count: 0,
     *         video_count: 0,
     *         image_count: 2858,
     *         news_count: 278
     *     }
     * ]
     * ```
     */    
    public function get_material_count () {        
        $url = self::API_DOMAIN . 'cgi-bin/material/get_materialcount?access_token=' . $this->getToken();        
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }    
        // 判断是否调用成功
        if ($res->errcode == 0) {
            return array(NULL, $res);
        } else {            
            return array($res, NULL);
        }
    }

    /**
     * 获取素材列表        
     *
     * @param string $type 素材的类型，图片（image）、视频（video）、语音 （voice）、图文（news）
     * @param string $offset 从全部素材的该偏移位置开始返回，0表示从第一个素材 返回
     * @param string $count 返回素材的数量，取值在1到20之间
     *
     * @return array(err, data)
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_materials('image', 0, 20);
     * list($err, $data) = $api->get_materials('voice', 0, 20);
     * list($err, $data) = $api->get_materials('video', 0, 20);
     * list($err, $data) = $api->get_materials('thumb', 0, 20);
     * ```
     */    
    public function get_materials ($type, $offset, $count) {        
        $url = self::API_DOMAIN . 'cgi-bin/material/batchget_material?access_token=' . $this->getToken();
        $xml = sprintf('{
                    "type":"%s",
                    "offset":"%s",
                    "count":"%s"}',
                    $type,
                    $offset,
                    $count);        
        $res = HttpCurl::post($url, $xml, 'json');    
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }    
        // 判断是否调用成功
        if ($res->errcode == 0) {
            return array(NULL, $res);
        } else {            
            return array($res, NULL);
        }        
    }
}