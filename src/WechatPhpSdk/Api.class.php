<?php
/**
 * Api.php
 *  
 * Api模块 （处理需要access_token的主动接口）
 * - 主送发送客服消息（文本、图片、语音、视频、音乐、图文）
 * - 多客服功能（客服管理、多客服回话控制、获取客服聊天记录...）
 * - 素材管理（临时素材、永久素材、素材统计）
 * - 自定义菜单管理（创建、查询、删除菜单）
 * - 微信JSSDK（生成微信JSSDK所需的配置信息）
 * - 账号管理（生成带参数的二维码、长链接转短链接接口）
 * - 用户管理（用户分组管理、设置用户备注名、获取用户基本信息、获取用户列表、网页授权获取用户基本信息）
 * - 数据统计接口（开发中...）
 *
 * @author 		gaoming13 <gaoming13@yeah.net>
 * @link 		https://github.com/gaoming13/wechat-php-sdk
 * @link 		http://me.diary8.com/
 */

namespace Gaoming13\WechatPhpSdk;

use Gaoming13\WechatPhpSdk\Utils\HttpCurl;
use Gaoming13\WechatPhpSdk\Utils\Error;
use Gaoming13\WechatPhpSdk\Utils\SHA1;

class Api 
{
    // 微信API域名
    const API_DOMAIN = 'https://api.weixin.qq.com/';

    // 开发者中心-配置项-AppID(应用ID)
    protected $appId;
    // 开发者中心-配置项-AppSecret(应用密钥)
    protected $appSecret;

    // 用户自定义获取access_token的方法
    protected $get_access_token_diy;
    // 用户自定义保存access_token的方法
    protected $save_access_token_diy;

    // 用户自定义获取jsapi_ticket的方法
    protected $get_jsapi_ticket_diy;
    // 用户自定义保存jsapi_ticket的方法
    protected $save_jsapi_ticket_diy;

	/**
     * 设定配置项
     *
     * @param array $config
     */
    public function __construct($config) {
        $this->appId                    =   $config['appId'];
        $this->appSecret                =   $config['appSecret'];
        $this->get_access_token_diy     =   isset($config['get_access_token']) ? $config['get_access_token'] : FALSE;
        $this->save_access_token_diy    =   isset($config['save_access_token']) ? $config['save_access_token'] : FALSE;
        $this->get_jsapi_ticket_diy     =   isset($config['get_jsapi_ticket']) ? $config['get_jsapi_ticket'] : FALSE;
        $this->save_jsapi_ticket_diy    =   isset($config['save_jsapi_ticket']) ? $config['save_jsapi_ticket'] : FALSE;
    }

    /**
     * 校验access_token是否过期
     *
     * @param string $token
     *
     * @return bool
     */
    public function valid_access_token($token) {
        return $token && isset($token->expires_in) && ($token->expires_in > time() + 1200);
    }

    /**
     * 生成新的access_token
     *
     * @return mixed
     */
    public function new_access_token() {
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
    public function get_access_token() {
        $token = FALSE;
        if ($this->get_access_token_diy !== FALSE) {
            // 调用用户自定义获取AccessToken方法
            $token = call_user_func($this->get_access_token_diy);
            if ($token) {
                $token = json_decode($token);
            }
        } else {
            // 异常处理: 获取access_token方法未定义
            @error_log('Not set get_tokenDiy method, AccessToken will be refreshed each time.', 0);
        }        
        // 验证AccessToken是否有效        
        if (!$this->valid_access_token($token)) {

            // 生成新的AccessToken
            $token = $this->new_access_token();
            if ($token === FALSE) {
                return FALSE;
            }

            // 保存新生成的AccessToken
            if ($this->save_access_token_diy !== FALSE) {
                // 用户自定义保存AccessToken方法    
                call_user_func($this->save_access_token_diy, json_encode($token));
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

        $url = self::API_DOMAIN . 'cgi-bin/message/custom/send?access_token=' . $this->get_access_token();
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
    	$url = self::API_DOMAIN . 'customservice/kfaccount/add?access_token=' . $this->get_access_token();    	
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
    	$url = self::API_DOMAIN . 'customservice/kfaccount/update?access_token=' . $this->get_access_token();    	
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
    	$url = self::API_DOMAIN . 'customservice/kfaccount/uploadheadimg?access_token=' . $this->get_access_token() . '&kf_account=' . $kf_account;        
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
    	$url = self::API_DOMAIN . 'customservice/kfaccount/del?access_token=' . $this->get_access_token() . '&kf_account=' . $kf_account;    	
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
    	$url = self::API_DOMAIN . 'cgi-bin/customservice/getkflist?access_token=' . $this->get_access_token();
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
    	$url = self::API_DOMAIN . 'cgi-bin/customservice/getonlinekflist?access_token=' . $this->get_access_token();
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
        $url = self::API_DOMAIN . 'customservice/msgrecord/getrecord?access_token=' . $this->get_access_token();
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
        $url = self::API_DOMAIN . 'customservice/kfsession/create?access_token=' . $this->get_access_token();
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
        $url = self::API_DOMAIN . 'customservice/kfsession/close?access_token=' . $this->get_access_token();
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
        $url = self::API_DOMAIN . 'customservice/kfsession/getsession?access_token=' . $this->get_access_token() . '&openid=' . $openid;
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
        $url = self::API_DOMAIN . 'customservice/kfsession/getsessionlist?access_token=' . $this->get_access_token() . '&kf_account=' . $kf_account;
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
        $url = self::API_DOMAIN . 'customservice/kfsession/getwaitcase?access_token=' . $this->get_access_token();
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
        $url = self::API_DOMAIN . 'cgi-bin/media/upload?access_token=' . $this->get_access_token() . '&type=' . $type;        
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
        return self::API_DOMAIN . 'cgi-bin/media/get?access_token=' . $this->get_access_token() . '&media_id=' . $media_id;
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
        $url = self::API_DOMAIN . 'cgi-bin/material/add_material?access_token=' . $this->get_access_token() . '&type=' . $type;                
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
        $url = self::API_DOMAIN . 'cgi-bin/material/add_news?access_token=' . $this->get_access_token();
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
        $url = self::API_DOMAIN . 'cgi-bin/material/update_news?access_token=' . $this->get_access_token();        
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
        $url = self::API_DOMAIN . 'cgi-bin/material/get_material?access_token=' . $this->get_access_token();
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
        $url = self::API_DOMAIN . 'cgi-bin/material/del_material?access_token=' . $this->get_access_token();
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
        $url = self::API_DOMAIN . 'cgi-bin/material/get_materialcount?access_token=' . $this->get_access_token();        
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
        $url = self::API_DOMAIN . 'cgi-bin/material/batchget_material?access_token=' . $this->get_access_token();
        $xml = sprintf('{"type":"%s","offset":"%s","count":"%s"}', $type, $offset, $count);
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

    /**
     * 自定义菜单创建接口        
     *     
     * @param string $json 菜单的json串，具体结构见微信公众平台文档
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象       
     *
     * Examples:
     * ```
     * $api->create_menu('
     * {
     *     "button":[
     *         {   
     *           "type":"click",
     *           "name":"主菜单1",
     *           "key":"V1001_TODAY_MUSIC"
     *         },
     *         {
     *             "name":"主菜单2",
     *             "sub_button":[
     *                 {
     *                     "type":"click",
     *                     "name":"点击推事件",
     *                     "key":"click_event1"
     *                 },
     *                 {
     *                     "type":"view",
     *                     "name":"跳转URL",
     *                     "url":"http://www.example.com/"
     *                 },
     *                 {
     *                     "type":"scancode_push",
     *                     "name":"扫码推事件",
     *                     "key":"scancode_push_event1"
     *                 },
     *                 {
     *                     "type":"scancode_waitmsg",
     *                     "name":"扫码带提示",
     *                     "key":"scancode_waitmsg_event1"
     *                 }
     *             ]
     *        },
     *        {
     *             "name":"主菜单3",
     *             "sub_button":[
     *                 {
     *                     "type":"pic_sysphoto",
     *                     "name":"系统拍照发图",
     *                     "key":"pic_sysphoto_event1"
     *                 },
     *                 {
     *                     "type":"pic_photo_or_album",
     *                     "name":"拍照或者相册发图",
     *                     "key":"pic_photo_or_album_event1"
     *                 },
     *                 {
     *                     "type":"pic_weixin",
     *                     "name":"微信相册发图",
     *                     "key":"pic_weixin_event1"
     *                 },
     *                 {
     *                     "type":"location_select",
     *                     "name":"发送位置",
     *                     "key":"location_select_event1"
     *                 }
     *             ]
     *        }
     *     ]
     * }');
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
     */    
    public function create_menu ($json) {        
        $url = self::API_DOMAIN . 'cgi-bin/menu/create?access_token=' . $this->get_access_token();        
        $res = HttpCurl::post($url, $json, 'json');
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
     * 自定义菜单查询接口        
     *     
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象       
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_menu();
     * ```
     * Result:
     * ```    
     * [
     *     null,
     *     {
     *         menu: {
     *             button: [
     *                 {
     *                     type: "click",
     *                     name: "主菜单1",
     *                     key: "V1001_TODAY_MUSIC",
     *                     sub_button: [ ]
     *                 },
     *                 {
     *                     name: "主菜单2",
     *                     sub_button: [
     *                         {
     *                             type: "click",
     *                             name: "点击推事件",
     *                             key: "click_event1",
     *                             sub_button: [ ]
     *                         },
     *                         {
     *                             type: "view",
     *                             name: "跳转URL",
     *                             url: "http://www.example.com/",
     *                             sub_button: [ ]
     *                         }
     *                     ]
     *                 }
     *             ]
     *         }
     *     }
     * ]     
     * ```
     */    
    public function get_menu () {        
        $url = self::API_DOMAIN . 'cgi-bin/menu/get?access_token=' . $this->get_access_token();        
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }        
        // 判断是否调用成功        
        if (isset($res->menu)) {
            return array(NULL, $res);
        } else {            
            return array($res, NULL);
        }
    }

    /**
     * 自定义菜单删除接口        
     *     
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象       
     *
     * Examples:
     * ```
     * list($err, $data) = $api->delete_menu();
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
     */    
    public function delete_menu () {        
        $url = self::API_DOMAIN . 'cgi-bin/menu/delete?access_token=' . $this->get_access_token();        
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
     * 获取自定义菜单配置接口        
     *     
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象       
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_selfmenu();
     * ```
     * Result:
     * ```    
     * [
     *     null,
     *     {
     *         is_menu_open: 1,
     *         selfmenu_info: {
     *             button: [
     *                 {
     *                     type: "click",
     *                     name: "主菜单1",
     *                     key: "V1001_TODAY_MUSIC"
     *                 },
     *                 {
     *                     name: "主菜单2",
     *                     sub_button: {
     *                         list: [
     *                             {
     *                                 type: "click",
     *                                 name: "点击推事件",
     *                                 key: "click_event1"
     *                             },
     *                             {
     *                                 type: "view",
     *                                 name: "跳转URL",
     *                                 url: "http://www.example.com/"
     *                             }
     *                         ]
     *                     }
     *                 }
     *             ]
     *         }
     *     }
     * ]
     * ```
     */    
    public function get_selfmenu () {        
        $url = self::API_DOMAIN . 'cgi-bin/get_current_selfmenu_info?access_token=' . $this->get_access_token();        
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }    
        // 判断是否调用成功        
        if (isset($res->is_menu_open)) {
            return array(NULL, $res);
        } else {            
            return array($res, NULL);
        }        
    }

    /**
     * JS-SDK 生成一个新的jsapi_ticket
     *
     * @return mixed
     */
    public function new_jsapi_ticket () {
        $url = self::API_DOMAIN . 'cgi-bin/ticket/getticket?access_token=' . $this->get_access_token() . '&type=jsapi';
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res->errcode == 0) {
            return (object)array(
                'ticket' => $res->ticket,
                'expires_in' => $res->expires_in + time()
            );
        } else {
            return FALSE;
        }
    }

    /**
     * JS-SDK 校验jsapi_ticket是否过期
     *
     * @param object $ticket
     * @return bool
     */
    public function valid_jsapi_ticket ($ticket) {
        return $ticket && isset($ticket->expires_in) && ($ticket->expires_in > time() + 1200);
    }

    /**
     * JS-SDK 获取jsapi_ticket
     *
     * @return string $ticket
     */
    public function get_jsapi_ticket () {
        $ticket = FALSE;
        if ($this->get_jsapi_ticket_diy !== FALSE) {
            // 调用用户自定义获取jsapi_ticket方法
            $ticket = call_user_func($this->get_jsapi_ticket_diy);
            if ($ticket) {
                $ticket = json_decode($ticket);
            }
        } else {
            // 异常处理: 获取jsapi_ticket方法未定义
            @error_log('Not set getTicketDiy method, jsapi_ticket will be refreshed each time.', 0);
        }

        // 验证jsapi_ticket是否有效
        if (!$this->valid_jsapi_ticket($ticket)) {

            // 生成新的jsapi_ticket
            $ticket = $this->new_jsapi_ticket();
            if ($ticket === FALSE) {
                return FALSE;
            }

            // 保存新生成的AccessToken
            if ($this->save_jsapi_ticket_diy !== FALSE) {
                // 用户自定义保存AccessToken方法
                call_user_func($this->save_jsapi_ticket_diy, json_encode($ticket));
            } else {
                // 异常处理: 保存access_token方法未定义
                @error_log('Not set saveTokenDiy method, jsapi_ticket will be refreshed each time.', 0);
            }
        }
        return $ticket->ticket;
    }

    /**
     * JS-SDK 获取JS-SDK配置需要的信息
     *
     * @param string $url 可选：调取JS-SDK的页面url，默认为HTTP_REFERER
     * @param string $type 可选：返回配置信息的格式 json & jsonp, 默认为对象数组
     * @param string $$jsonp_callback 可选：使用json的callback名称
     *
     * @return mixed
     *
     * Examples:
     * ```
     * $api->get_jsapi_config();
     * $api->get_jsapi_config('http://www.baidu.com/');
     * ```
     * Result:
     * ```
     * {
     *      errcode: 0,
     *      appId: "wx733d7f24bd29224a",
     *      timestamp: 1440073485,
     *      nonceStr: "5Ars5fLaLuPEXSgm",
     *      signature: "7f830aff99ff11fa931cae61b5b932b1f2c8ee10",
     *      url: "http://www.baidu.com/"
     * }
     * ```
     *
     * Examples:
     * ```
     * $api->get_jsapi_config('', 'json');
     * ```
     * Result:
     * ```
     * {"errcode":0,"appId":"wx733d7f24bd29224a","timestamp":1440073708,"nonceStr":"caFkkXnOhVrcq3Ke","signature":"1c6c08ddf6e0e3c0fd33aafcb160a9f67d6b8f94","url":null}
     * ```
     *
     * Examples:
     * ```
     * $api->get_jsapi_config('', 'jsonp');
     * $api->get_jsapi_config('', 'jsonp', 'callback');
     * ```
     * Result:
     * ```
     * ;jQuery17105012127514928579_1440073858610({"errcode":0,"appId":"wx733d7f24bd29224a","timestamp":1440073875,"nonceStr":"vsGBSM0MMiWeIJFQ","signature":"616005786e404fe0da226a6decc2730624bedbfc","url":null})
     * ```
     */
    public function get_jsapi_config ($url = '', $type = '', $jsonp_callback = 'callback') {
        $jsapi_ticket = $this->get_jsapi_ticket();
        $nonce_str = SHA1::get_random_str();
        $timestamp = time();
        if ($url == '') {
            $url = $_SERVER['HTTP_REFERER'];
        }
        $signature = SHA1::get_jsapi_signature($jsapi_ticket, $nonce_str, $timestamp, $url);

        if ($signature === FALSE) {
            $jsapi_config = array(
                'errcode' => -1,
                'errmsg' => 'get jsapi signature error.'
            );
        } else {
            $jsapi_config = array(
                'errcode' => 0,
                'appId' => $this->appId,
                'timestamp' => $timestamp,
                'nonceStr' => $nonce_str,
                'signature' => $signature,
                'url' => $url
            );
        }
        if ($type == 'json' || $type == 'jsonp') {
            $jsapi_config = json_encode($jsapi_config);
            if ($type == 'jsonp' && isset($_REQUEST[$jsonp_callback]) && !empty($_REQUEST[$jsonp_callback])) {
                $jsapi_config = ';' . $_REQUEST[$jsonp_callback] . '(' . $jsapi_config . ')';
            }
        }
        return $jsapi_config;
    }

    /**
     * 生成带参数的二维码
     *
     * @int $scene_id 场景值ID，临时二维码时为32位非0整型，永久二维码时最大值为100000（目前参数只支持1--100000）
     * @int $expire_seconds 可选：该二维码有效时间，以秒为单位。 最大不超过604800（即7天），默认为永久二维码，填写该项为临时二维码
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     *
     * Examples:
     * ```
     * list($err, $data) = $api->create_qrcode(1234); // 创建一个永久二维码
     * list($err, $data) = $api->create_qrcode(1234, 100); //创建一个临时二维码，有效期100秒
     * ```
     * Result:
     * ```
     * [
     *  null,
     *  {
     *      ticket: "gQFM8DoAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xLzlVeU83dGZsMXNldlAtQ0hmbUswAAIEQcrVVQMEZAAAAA==",
     *      expire_seconds: 100,
     *      url: "http://weixin.qq.com/q/9UyO7tfl1sevP-CHfmK0"
     *  }
     * ]
     * ```
     */
    public function create_qrcode ($scene_id, $expire_seconds = 0) {
        $url = self::API_DOMAIN . 'cgi-bin/qrcode/create?access_token=' . $this->get_access_token();
        $expire = $expire_seconds == 0 ? '' : '"expire_seconds": ' . $expire_seconds . ',';
        $action_name = $expire_seconds == 0 ? 'QR_LIMIT_SCENE' : 'QR_SCENE';
        $xml = sprintf('{%s"action_name": "%s", "action_info": {"scene": {"scene_id": %s}}}',
            $expire,
            $action_name,
            $scene_id);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res->ticket)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     * 通过ticket换取二维码，返回二维码url地址
     *
     * @string $ticket 二维码的ticket
     *
     * @return string 二维码的url地址
     *
     * Examples:
     * ```
     * echo $api->get_qrcode_url('gQH58DoAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xLzQweUctT2psME1lcEJPYWJkbUswAAIEApzVVQMEZAAAAA==');
     * ```
     * Result:
     * ```
     * https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=gQH58DoAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xLzQweUctT2psME1lcEJPYWJkbUswAAIEApzVVQMEZAAAAA==
     * ```
     */
    public function get_qrcode_url ($ticket) {
        return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $ticket;
    }

    /**
     * 通过ticket换取二维码，返回二维码图片的内容
     *
     * @string $ticket [获取到的二维码ticket]
     *
     * @return string [二维码图片的内容]
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_qrcode('gQGa8ToAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xLzlVeXJZWS1seGNlODZ2SV9XMkMwAAIEo5rVVQMEAAAAAA==');
     * header('Content-type: image/jpg');
     * echo $data;
     * ```
     */
    public function get_qrcode ($ticket) {
        $url = self::get_qrcode_url($ticket);
        $res = HttpCurl::get($url);
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_GET');
        }
        return array(NULL, $res);
    }

    /**
     * 长链接转短链接接口
     *
     * @string $long_url [需要转换的长链接，支持http://、https://、weixin://wxpay 格式的url]
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     *
     * Examples:
     * ```
     * list($err, $data) = $api->shorturl('http://me.diary8.com/category/web-front-end.html');
     * echo $data->short_url;
     * ```
     * Result:
     * ```
     * http://w.url.cn/s/ABJrkxE
     * ```
     */
    public function shorturl ($long_url) {
        $url = self::API_DOMAIN . 'cgi-bin/shorturl?access_token=' . $this->get_access_token();
        $xml = '{"action":"long2short","long_url":"' . $long_url . '"}';
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
     * 用户分组管理 - 创建分组
     *
     * Examples:
     * ```
     * $api->create_group('新的一个分组');
     * ```
     * Result:
     * ```
     * [
     *     null,
     *     {
     *         group: {
     *             id: 104,
     *             name: "新的一个分组"
     *         }
     *     }
     * ]
     * ```
     *
     * @param string $group_name [分组名字（30个字符以内）]
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function create_group ($group_name) {
        $url = self::API_DOMAIN . 'cgi-bin/groups/create?access_token=' .$this->get_access_token();
        $xml = sprintf('{"group":{"name":"%s"}}', $group_name);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res->group)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     * 用户分组管理 - 查询所有分组
     *
     * Examples:
     * ```
     * $api->get_groups();
     * ```
     * Result:
     * ```
     * [
     *     null,
     *     {
     *         groups: [
     *             {
     *                 id: 0,
     *                 name: "未分组",
     *                 count: 1
     *             },
     *             {
     *                 id: 1,
     *                 name: "黑名单",
     *                 count: 0
     *             },
     *             {
     *                 id: 100,
     *                 name: "自定义分组1",
     *                 count: 3
     *             }
     *         ]
     *     }
     * ]
     * ```
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_groups () {
        $url = self::API_DOMAIN . 'cgi-bin/groups/get?access_token=' .$this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res->groups)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     * 用户分组管理 - 查询用户所在分组
     *
     * Examples:
     * ```
     * $api->get_user_group('ocNtAt0YPGDme5tJBXyTphvrQIrc');
     * ```
     * Result:
     * ```
     * [
     *     null,
     *     {
     *         groupid: 100
     *     }
     * ]
     * ```
     *
     * @param string $open_id [用户的OpenID]
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_user_group ($open_id) {
        $url = self::API_DOMAIN . 'cgi-bin/groups/getid?access_token=' .$this->get_access_token();
        $xml = sprintf('{"openid":"%s"}', $open_id);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res->groupid)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     *  用户分组管理 - 修改分组名
     *
     * Examples:
     * ```
     * $api->update_group(100, '自定义分组了');
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
     * @param int $group_id [分组id，由微信分配]
     * @param string $group_name [分组名字（30个字符以内）]
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function update_group ($group_id, $group_name) {
        $url = self::API_DOMAIN . 'cgi-bin/groups/update?access_token=' .$this->get_access_token();
        $xml = sprintf('{"group":{"id":"%s","name":"%s"}}', $group_id, $group_name);
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
     * 用户分组管理 - 移动用户分组
     *
     * Examples:
     * ```
     * $api->update_user_group('ocNtAt0YPGDme5tJBXyTphvrQIrc', 100);
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
     * @param string $open_id [用户唯一标识符]
     * @param int $to_groupid [分组id]
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function update_user_group ($open_id, $to_groupid) {
        $url = self::API_DOMAIN . 'cgi-bin/groups/members/update?access_token=' .$this->get_access_token();
        $xml = sprintf('{"openid":"%s","to_groupid":"%s"}', $open_id, $to_groupid);
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
     * 用户分组管理 - 批量移动用户分组
     *
     * Examples:
     * ```
     * $api->update_user_group('ocNtAt0YPGDme5tJBXyTphvrQIrc', 100);
     * ```
     * Result:
     * ```
     * $api->batchupdate_user_group(array(
     *     'ocNtAt0YPGDme5tJBXyTphvrQIrc',
     *     'ocNtAt_TirhYM6waGeNUbCfhtZoA',
     *     'ocNtAt_K8nRlAdmNEo_R0WVg_rRw'
     *     ), 100);
     * ```
     *
     * @param array $open_id_arr
     * @param int $to_groupid
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function batchupdate_user_group ($open_id_arr, $to_groupid) {
        $url = self::API_DOMAIN . 'cgi-bin/groups/members/batchupdate?access_token=' .$this->get_access_token();
        $open_ids = json_encode($open_id_arr);
        $xml = sprintf('{"openid_list":%s,"to_groupid":"%s"}', $open_ids, $to_groupid);
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
     * 用户分组管理 - 删除分组
     *
     * Examples:
     * ```
     * $api->delete_group(102);
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
     * @param int $group_id
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function delete_group ($group_id) {
        $url = self::API_DOMAIN . 'cgi-bin/groups/delete?access_token=' .$this->get_access_token();
        $xml = sprintf('{"group":{"id":"%s"}}', $group_id);
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
     * 设置用户备注名
     *
     * Examples:
     * ```
     * $api->update_user_remark('ocNtAt0YPGDme5tJBXyTphvrQIrc', '用户的备注名');
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
     * @param string $open_id [用户标识]
     * @param string $remark [新的备注名，长度必须小于30字符]
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function update_user_remark ($open_id, $remark) {
        $url = self::API_DOMAIN . 'cgi-bin/user/info/updateremark?access_token=' .$this->get_access_token();
        $xml = sprintf('{"openid":"%s", "remark":"%s"}', $open_id, $remark);
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
     * 获取用户基本信息
     *
     * Examples:
     * ```
     * $api->get_user_info('ocNtAt_K8nRlAdmNEo_R0WVg_rRw');
     * $api->get_user_info('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'zh_TW');
     * ```
     * Result:
     * ```
     * [
     *     null,
     *     {
     *         subscribe: 1,
     *         openid: "ocNtAt_K8nRlAdmNEo_R0WVg_rRw",
     *         nickname: "赵利明",
     *         sex: 1,
     *         language: "zh_CN",
     *         city: "浦東新區",
     *         province: "上海",
     *         country: "中國",
     *         headimgurl: "http://wx.qlogo.cn/mmopen/eFIz8Uk9INlmmw4dAblRbUxIhjoJtPUUGGJXaWp6rd48v4vUMhmk7GvfNv2Kd0xSvRWfMk7PnOIoicz3ibMf38zvWnr7bCXNZC/0",
     *         subscribe_time: 1440150875,
     *         remark: "",
     *         groupid: 100
     *     }
     * ]
     * ```
     *
     * @param string $open_id [普通用户的标识，对当前公众号唯一]
     * @param string $lang [可选：返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语]
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_user_info ($open_id, $lang = '') {
        if ($lang != '') {
            $lang = '&lang=' . $lang;
        }
        $url = self::API_DOMAIN . 'cgi-bin/user/info?access_token=' . $this->get_access_token() . '&openid=' . $open_id . $lang;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res->openid)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }

    /**
     * 获取用户列表
     *
     * Examples:
     * ```
     * $api->get_user_list();
     * $api->get_user_list('ocNtAt_TirhYM6waGeNUbCfhtZoA');
     * ```
     * Result:
     * ```
     * [
     *     null,
     *     {
     *         total: 4,
     *         count: 2,
     *         data: {
     *             openid: [
     *                 "ocNtAt_K8nRlAdmNEo_R0WVg_rRw",
     *                 "ocNtAt9DVhWngpiMyZzPFWr4IXD0"
     *             ]
     *         },
     *         next_openid: "ocNtAt9DVhWngpiMyZzPFWr4IXD0"
     *     }
     * ]
     * ```
     *
     * @param string $next_openid [可选：第一个拉取的OPENID，不填默认从头开始拉取]
     *
     * @return array(err, data)
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_user_list ($next_openid = '') {
        if ($next_openid != '') {
            $next_openid = '&next_openid=' . $next_openid;
        }
        $url = self::API_DOMAIN . 'cgi-bin/user/get?access_token=' . $this->get_access_token() . $next_openid;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === FALSE) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res->data)) {
            return array(NULL, $res);
        } else {
            return array($res, NULL);
        }
    }
}