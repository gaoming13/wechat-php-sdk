<?php
/**
 * Api模块 （处理需要access_token的主动接口）
 * - 主送发送客服消息（文本、图片、语音、视频、音乐、图文）
 * - 多客服功能（客服管理、多客服回话控制、获取客服聊天记录...）
 * - 素材管理（临时素材、永久素材、素材统计）
 * - 自定义菜单管理（创建、查询、删除菜单）
 * - 微信JSSDK（生成微信JSSDK所需的配置信息）
 * - 账号管理（生成带参数的二维码、长链接转短链接接口）
 * - 用户管理（用户分组管理、设置用户备注名、获取用户基本信息、获取用户列表、网页授权获取用户基本信息）
 */

namespace Gaoming13\WechatPhpSdk;

use Gaoming13\WechatPhpSdk\Utils\HttpCurl;
use Gaoming13\WechatPhpSdk\Utils\Error;
use Gaoming13\WechatPhpSdk\Utils\SHA1;
use Gaoming13\WechatPhpSdk\Utils\Xml;

class Api
{
    // 微信API域名
    const API_DOMAIN = 'https://api.weixin.qq.com/';
    //页面授权
    const SNSAPI_BASE = "snsapi_base";
    const SNSAPI_USERINFO = "snsapi_userinfo";

    // 公众账号原始ID
    protected $ghId;
    // 开发者中心-配置项-AppID(应用ID)
    protected $appId;
    // 开发者中心-配置项-AppSecret(应用密钥)
    protected $appSecret;
    // 微信支付商户号，商户申请微信支付后，由微信支付分配的商户收款账号
    protected $mchId;
    // API密钥,微信商户平台(pay.weixin.qq.com)-->账户设置-->API安全-->密钥设置
    protected $key;

    /** @var callable $get_access_token_diy 用户自定义获取access_token的方法 */
    protected $get_access_token_diy;
    /** @var callable $save_access_token_diy 用户自定义保存access_token的方法 */
    protected $save_access_token_diy;

    /** @var callable $get_jsapi_ticket_diy 用户自定义获取jsapi_ticket的方法 */
    protected $get_jsapi_ticket_diy;
    /** @var callable $save_jsapi_ticket_diy 用户自定义保存jsapi_ticket的方法 */
    protected $save_jsapi_ticket_diy;

    /**
     * 设定配置项
     *
     * @param array $config
     */
    public function __construct($config)
    {
        $this->ghId                  = isset($config['ghId']) ? $config['ghId'] : '';
        $this->appId                 = $config['appId'];
        $this->appSecret             = $config['appSecret'];
        $this->mchId                 = isset($config['mchId']) ? $config['mchId'] : false;
        $this->key                   = isset($config['key']) ? $config['key'] : false;
        $this->get_access_token_diy  = isset($config['get_access_token']) ? $config['get_access_token'] : false;
        $this->save_access_token_diy = isset($config['save_access_token']) ? $config['save_access_token'] : false;
        $this->get_jsapi_ticket_diy  = isset($config['get_jsapi_ticket']) ? $config['get_jsapi_ticket'] : false;
        $this->save_jsapi_ticket_diy = isset($config['save_jsapi_ticket']) ? $config['save_jsapi_ticket'] : false;
    }

    /**
     * 校验access_token是否过期
     *
     * @param string $token
     *
     * @return bool
     */
    public function valid_access_token($token)
    {
        return $token && isset($token['expires_in']) && ($token['expires_in'] > time() + 1200);
    }

    /**
     * 生成新的access_token
     *
     * @return mixed
     */
    public function new_access_token()
    {
        $url = self::API_DOMAIN . 'cgi-bin/token?grant_type=client_credential&appid=' . $this->appId . '&secret=' . $this->appSecret;
        $res = HttpCurl::get($url, 'json');

        // 异常处理: 获取access_token网络错误
        if ($res === false) {
            @error_log('[wechat-php-sdk]Http Get AccessToken Error.', 0);
            return false;
        }

        // 异常处理: access_token获取失败
        if (!isset($res['access_token'])) {
            @error_log('[wechat-php-sdk]Get AccessToken Error: ' . json_encode($res), 0);
            return false;
        }
        $res['expires_in'] += time();
        return $res;
    }

    /**
     * 获取access_token
     *
     * @return string
     */
    public function get_access_token()
    {
        $token = false;
        if ($this->get_access_token_diy !== false) {
            // 调用用户自定义获取AccessToken方法
            $token = call_user_func($this->get_access_token_diy);
            if ($token) {
                $token = json_decode($token, true);
            }
        } else {
            // 异常处理: 获取access_token方法未定义
            @error_log('[wechat-php-sdk]Not set get_tokenDiy method, AccessToken will be refreshed each time.', 0);
        }
        // 验证AccessToken是否有效
        if (! $this->valid_access_token($token)) {

            // 生成新的AccessToken
            $token = $this->new_access_token();
            if ($token === false) {
                return false;
            }

            // 保存新生成的AccessToken
            if ($this->save_access_token_diy !== false) {
                // 用户自定义保存AccessToken方法
                call_user_func($this->save_access_token_diy, json_encode($token));
            } else {
                // 异常处理: 保存access_token方法未定义
                @error_log('[wechat-php-sdk]Not set saveTokenDiy method, AccessToken will be refreshed each time.', 0);
            }
        }
        return $token['access_token'];
    }

    /**
     * 发送客服消息（文本、图片、语音、视频、音乐、图文）
     *
     * @param string $openid
     * @param array $msg
     *
     * @return [err, data]
     */
    public function send($openid, $msg)
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
             * 1.1 发送文本消息(简洁输入)
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'hello world!');
             * ```
             */
            case 'text_simple':
                $xml = sprintf('{'.
                        '"touser":"%s",'.
                        '"msgtype":"text",'.
                        '"text":{'.
                            '"content":"%s"'.
                        '}}',
                        $openid,
                        $msg);
                break;

            /**
             * 1.2 发送文本消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', [
             *  'type' => 'text',
             *  'content' => 'hello world!'
             * ]);
             * ```
             */
            case 'text':
                $xml = sprintf('{'.
                        '"touser":"%s",'.
                        '"msgtype":"text",'.
                        '"text":{'.
                            '"content":"%s"'.
                        '}%s}',
                        $openid,
                        $msg['content'],
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 2 发送图片消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', [
             *  'type' => 'image',
             *  'media_id' => 'Uq7OczuEGEyUu--dYjg7seTm-EJTa0Zj7UDP9zUGNkVpjcEHhl7tU2Mv8mFRiLKC'
             * ]);
             * ```
             */
            case 'image':
                $xml = sprintf('{'.
                        '"touser":"%s",'.
                        '"msgtype":"image",'.
                        '"image":{'.
                            '"media_id":"%s"'.
                        '}%s}',
                        $openid,
                        $msg['media_id'],
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 3 发送语音消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', [
             *  'type' => 'voice',
             *  'media_id' => 'rVT43tfDwjh4p1BV2gJ5D7Zl2BswChO5L_llmlphLaTPytcGcguBAEJ1qK4cg4r_'
             *  ]);
             * ```
             */
            case 'voice':
                $xml = sprintf('{'.
                        '"touser":"%s",'.
                        '"msgtype":"voice",'.
                        '"voice":{'.
                            '"media_id":"%s"'.
                        '}%s}',
                        $openid,
                        $msg['media_id'],
                        isset($msg['kf_account']) ? ',"customservice":{"kf_account": "'.$msg['kf_account'].'"}' : '');
                break;

            /**
             * 4 发送视频消息
             *
             * Examples:
             * ```
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', [
             *  'type' => 'video',
             *  'media_id' => 'yV0l71NL0wtpRA8OMX0-dBRQsMVyt3fspPUzurIS3psi6eWOrb_WlEeO39jasoZ8',
             *  'thumb_media_id' => '7ct_DvuwZXIO9e9qbIf2ThkonUX_FzLAoqBrK-jzUboTYJX0ngOhbz6loS-wDvyZ',  // 可选(无效, 官方文档好像写错了)
             *  'title' => '视频消息的标题',       // 可选
             *  'description' => '视频消息的描述'  // 可选
             * ]);
             * ```
             */
            case 'video':
                $xml = sprintf('{'.
                        '"touser":"%s",'.
                        '"msgtype":"video",'.
                        '"video":{'.
                            '"media_id":"%s",'.
                            '"thumb_media_id":"%s",'.
                            '"title":"%s",'.
                            '"description":"%s"'.
                        '}%s}',
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
             * $api->send('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', [
             *  'type' => 'music',
             *  'title' => '音乐标题',                      //可选
             *  'description' => '音乐描述',                //可选
             *  'music_url' => 'http://me.diary8.com/data/music/2.mp3',     //可选
             *  'hqmusic_url' => 'http://me.diary8.com/data/music/2.mp3',   //可选
             *  'thumb_media_id' => 'O39wW0ZsXCb5VhFoCgibQs5PupFb6VZ2jH5A8gHUJCJz2Qmkrb7objoTue7bGTGQ',
             * ]);
             * ```
             */
            case 'music':
                $xml = sprintf('{'.
                        '"touser":"%s",'.
                        '"msgtype":"music",'.
                        '"music":{'.
                            '"title":"%s",'.
                            '"description":"%s",'.
                            '"musicurl":"%s",'.
                            '"hqmusicurl":"%s",'.
                            '"thumb_media_id":"%s"'.
                        '}%s}',
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
             * $api->send($msg['FromUserName'], [
             *  'type' => 'news',
             *  'articles' => [
             *      [
             *          'title' => '图文消息标题1',                           //可选
             *          'description' => '图文消息描述1',                     //可选
             *          'picurl' => 'http://me.diary8.com/data/img/demo1.jpg',  //可选
             *          'url' => 'http://www.example.com/'                      //可选
             *      ],
             *      [
             *          'title' => '图文消息标题2',
             *          'description' => '图文消息描述2',
             *          'picurl' => 'http://me.diary8.com/data/img/demo2.jpg',
             *          'url' => 'http://www.example.com/'
             *      ],
             *      [
             *          'title' => '图文消息标题3',
             *          'description' => '图文消息描述3',
             *          'picurl' => 'http://me.diary8.com/data/img/demo3.jpg',
             *          'url' => 'http://www.example.com/'
             *      ],
             *  ],
             *  'kf_account' => 'test1@kftest'      // 可选(指定某个客服发送, 会显示这个客服的头像)
             * ]);
             * ```
             */
            case 'news':
                $articles = [];
                foreach ($msg['articles'] as $article) {
                    array_push($articles, sprintf('{'.
                        '"title":"%s",'.
                        '"description":"%s",'.
                        '"url":"%s",'.
                        '"picurl":"%s"'.
                        '}',
                        $article['title'],
                        $article['description'],
                        $article['url'],
                        $article['picurl']));
                }
                $articles = implode(",", $articles);
                $xml = sprintf('{'.
                        '"touser":"%s",'.
                        '"msgtype":"news",'.
                        '"news":{"articles": [%s]}%s}',
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
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, true];
        } else {
            return [$res, null];
        }
    }


    /**
     * 添加客服账号
     *
     * @param string $kf_account
     * @param string $nickname
     * @param string $password
     *
     * @return [err, res]
     *
     * Examples:
     * ```
     * list($err, $res) = $api->add_kf('test1234@微信号', '客服昵称', '客服密码');
     * ```
     */
    public function add_kf ($kf_account, $nickname, $password)
    {
        $password = md5($password);
        $xml = sprintf('{'.
                '"kf_account" : "%s",'.
                '"nickname" : "%s",'.
                '"password" : "%s"}',
                $kf_account,
                $nickname,
                md5($password));
        $url = self::API_DOMAIN . 'customservice/kfaccount/add?access_token=' . $this->get_access_token();
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, true];
        } else {
            return [$res, null];
        }
    }

    /**
     * 设置客服信息
     *
     * @param string $kf_account
     * @param string $nickname
     * @param string $password
     *
     * @return [err, res]
     *
     * Examples:
     * ```
     * list($err, $res) = $api->update_kf('test1234@微信号', '客服昵称', '客服密码');
     * ```
     */
    public function update_kf($kf_account, $nickname, $password)
    {
        $password = md5($password);
        $xml = sprintf('{'.
                '"kf_account" : "%s",'.
                '"nickname" : "%s",'.
                '"password" : "%s"}',
                $kf_account,
                $nickname,
                md5($password));
        $url = self::API_DOMAIN . 'customservice/kfaccount/update?access_token=' . $this->get_access_token();
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, true];
        } else {
            return [$res, null];
        }
    }

    /**
     * 上传客服头像
     *
     * @param string $kf_account
     * @param string $path
     *
     * @return [err, res]
     *
     * Examples:
     * ```
     * list($err, $res) = $api->set_kf_avatar('GB2@gbchina2000', '/website/wx/demo/test.jpg');
     * ```
     */
    public function set_kf_avatar($kf_account, $path)
    {
        $url = self::API_DOMAIN . 'customservice/kfaccount/uploadheadimg?access_token=' . $this->get_access_token() . '&kf_account=' . $kf_account;
        $res = HttpCurl::post($url, ['media' => '@'.$path], 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, true];
        } else {
            return [$res, null];
        }
    }

    /**
     * 删除客服帐号
     *
     * @param string $kf_account
     *
     * @return [err, res]
     *
     * Examples:
     * ```
     * list($err, $res) = $api->del_kf('test1234@微信号');
     * ```
     */
    public function del_kf($kf_account)
    {
        $url = self::API_DOMAIN . 'customservice/kfaccount/del?access_token=' . $this->get_access_token() . '&kf_account=' . $kf_account;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, true];
        } else {
            return [$res, null];
        }
    }

    /**
     * 获取所有客服账号
     *
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $kf_list) = $api->get_kf_list();
     * ```
     */
    public function get_kf_list()
    {
        $url = self::API_DOMAIN . 'cgi-bin/customservice/getkflist?access_token=' . $this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['kf_list'])) {
            return [null, $res['kf_list']];
        } else {
            return [$res, null];
        }
    }

    /**
     * 获取在线客服接待信息
     *
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $kf_list) = $api->get_online_kf_list();
     * ```
     */
    public function get_online_kf_list ()
    {
        $url = self::API_DOMAIN . 'cgi-bin/customservice/getonlinekflist?access_token=' . $this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['kf_online_list'])) {
            return [null, $res['kf_online_list']];
        } else {
            return [$res, null];
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
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $record_list) = $api->get_kf_records(1439348167, 1439384060, 1, 10);
     * ```
     */
    public function get_kf_records($starttime, $endtime, $pageindex, $pagesize)
    {
        $url = self::API_DOMAIN . 'customservice/msgrecord/getrecord?access_token=' . $this->get_access_token();
        $xml = sprintf('{'.
                    '"endtime" : %s,'.
                    '"pageindex" : %s,'.
                    '"pagesize" : %s,'.
                    '"starttime" : %s}',
                    $endtime,
                    $pageindex,
                    $pagesize,
                    $starttime);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['recordlist'])) {
            return [null, $res['recordlist']];
        } else {
            return [$res, null];
        }
    }

    /**
     * 创建客户与客服的会话
     *
     * @param string $kf_account
     * @param string $openid
     * @param string $text (可选)
     *
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $res) = $api->create_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'test1@微信号', '小明请求接入会话!');
     * ```
     */
    public function create_kf_session($openid, $kf_account, $text='')
    {
        $url = self::API_DOMAIN . 'customservice/kfsession/create?access_token=' . $this->get_access_token();
        $xml = sprintf('{'.
                    '"kf_account" : "%s",'.
                    '"openid" : "%s",'.
                    '"text" : "%s"}',
                    $kf_account,
                    $openid,
                    $text);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, true];
        } else {
            return [$res, null];
        }
    }

    /**
     * 关闭客户与客服的会话
     *
     * @param string $kf_account
     * @param string $openid
     * @param string $text (可选)
     *
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $res) = $api->close_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw', 'test1@微信号', '与小明的回话已关闭!');
     * ```
     */
    public function close_kf_session($openid, $kf_account, $text='')
    {
        $url = self::API_DOMAIN . 'customservice/kfsession/close?access_token=' . $this->get_access_token();
        $xml = sprintf('{'.
                    '"kf_account" : "%s",'.
                    '"openid" : "%s",'.
                    '"text" : "%s"}',
                    $kf_account,
                    $openid,
                    $text);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, true];
        } else {
            return [$res, null];
        }
    }

    /**
     * 获取客户的会话状态
     *
     * @param string $openid
     *
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_kf_session('ocNtAt_K8nRlAdmNEo_R0WVg_rRw');
     * ```
     */
    public function get_kf_session($openid)
    {
        $url = self::API_DOMAIN . 'customservice/kfsession/getsession?access_token=' . $this->get_access_token() . '&openid=' . $openid;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 获取客服的会话列表
     *
     * @param string $kf_account
     *
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_kf_session_list('test1@微信号');
     * ```
     */
    public function get_kf_session_list($kf_account)
    {
        $url = self::API_DOMAIN . 'customservice/kfsession/getsessionlist?access_token=' . $this->get_access_token() . '&kf_account=' . $kf_account;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['sessionlist'])) {
            return [null, $res['sessionlist']];
        } else {
            return [$res, null];
        }
    }

    /**
     * 获取未接入会话列表的客户
     *
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_waitcase_list();
     * ```
     */
    public function get_waitcase_list()
    {
        $url = self::API_DOMAIN . 'customservice/kfsession/getwaitcase?access_token=' . $this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['waitcaselist'])) {
            return [null, $res['waitcaselist']];
        } else {
            return [$res, null];
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
     * @return [err, res]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function upload_media($type, $path)
    {
        $url = self::API_DOMAIN . 'cgi-bin/media/upload?access_token=' . $this->get_access_token() . '&type=' . $type;
        $res = HttpCurl::post($url, ['media' => '@'.$path], 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['media_id'])) {
            return [null, $res];
        } else {
            return [$res, null];
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
    public function get_media($media_id)
    {
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
     * @return [err, res]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function download_media($media_id)
    {
        $url = $this->get_media($media_id);
        $res = HttpCurl::get($url);
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        return [null, $res];
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
     * @return [err, res]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function add_material($type, $path, $title='', $introduction='')
    {
        $url = self::API_DOMAIN . 'cgi-bin/material/add_material?access_token=' . $this->get_access_token() . '&type=' . $type;
        $post_data = ['media' => '@'.$path];
        if ($type == 'video') {
            $post_data['description'] = sprintf('{"title":"%s","introduction":"%s"}', $title, $introduction);
        }
        $res = HttpCurl::post($url, $post_data, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res['media_id'])) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 新增永久图文素材
     *
     * @param array $articles
     *
     * @return [err, res]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     *
     * Examples:
     * ```
     * list($err, $res) = $api->add_news([
     *     [
     *         'title' => '标题',
     *         'thumb_media_id' => '图文消息的封面图片素材id（必须是永久mediaID）',
     *         'author' => '作者',
     *         'digest' => '图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空',
     *         'show_cover_pic' => '是否显示封面，0为false，即不显示，1为true，即显示',
     *         'content' => '图文消息的具体内容，支持HTML标签，必须少于2万字符，小于1M，且此处会去除JS',
     *         'content_source_url' => '图文消息的原文地址，即点击“阅读原文”后的URL'
     *     ],
     *     [
     *         'title' => '这是图文的标题',
     *         'thumb_media_id' => 'BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g',
     *         'author' => '这是图文的作者',
     *         'digest' => '',
     *         'show_cover_pic' => true,
     *         'content' => '这是图文消息的具体内容',
     *         'content_source_url' => 'http://www.baidu.com/'
     *     ],
     * ]);
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
    public function add_news($articles)
    {
        $url = self::API_DOMAIN . 'cgi-bin/material/add_news?access_token=' . $this->get_access_token();
        $articles1 = [];
        foreach ($articles as $article) {
            array_push($articles1, sprintf('{'.
                '"title":"%s",'.
                '"thumb_media_id":"%s",'.
                '"digest":"%s",'.
                '"show_cover_pic":"%s",'.
                '"content":"%s",'.
                '"content_source_url":"%s"}',
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
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res['media_id'])) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 修改永久图文素材
     *
     * Examples:
     * ```
     * list($err, $res) = $api->update_news('BZ-ih-dnjWDyNXjai6i6sZp22xhHu6twVYKNPyl77Ms', [
     *     'title' => '标题',
     *     'thumb_media_id' => 'BZ-ih-dnjWDyNXjai6i6sdvxOoXOHr9wO0pgMhcZR8g',
     *     'author' => '作者',
     *     'digest' => '图文消息的摘要',
     *     'show_cover_pic' => true,
     *     'content' => '图文消息的具体内容',
     *     'content_source_url' => 'http://www.diandian.com/'
     * ], 1);
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
     * @return [err, res]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function update_news($media_id, $article, $index = 0)
    {
        $url = self::API_DOMAIN . 'cgi-bin/material/update_news?access_token=' . $this->get_access_token();
        $xml = sprintf('{'.
            '"media_id":"%s",'.
            '"index":"%s",'.
            '"articles": {'.
                '"title": "%s",'.
                '"thumb_media_id": "%s",'.
                '"author": "%s",'.
                '"digest": "%s",'.
                '"show_cover_pic": "%s",'.
                '"content": "%s",'.
                '"content_source_url": "%s"'.
            '}}',
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
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, res]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_material($media_id)
    {
        $url = self::API_DOMAIN . 'cgi-bin/material/get_material?access_token=' . $this->get_access_token();
        $xml = '{"media_id":"' . $media_id . '"}';
        $res = HttpCurl::post($url, $xml);
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        return [null, $res];
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
     * @return [err, res]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function del_material($media_id)
    {
        $url = self::API_DOMAIN . 'cgi-bin/material/del_material?access_token=' . $this->get_access_token();
        $xml = '{"media_id":"' . $media_id . '"}';
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 获取素材总数
     *
     * @return [err, data]
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
    public function get_material_count()
    {
        $url = self::API_DOMAIN . 'cgi-bin/material/get_materialcount?access_token=' . $this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (!property_exists($res, 'errcode')) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 获取素材列表
     *
     * @param string $type 素材的类型，图片（image）、视频（video）、语音 （voice）、图文（news）
     * @param string $offset 从全部素材的该偏移位置开始返回，0表示从第一个素材 返回
     * @param string $count 返回素材的数量，取值在1到20之间
     *
     * @return [err, data]
     *
     * Examples:
     * ```
     * list($err, $data) = $api->get_materials('image', 0, 20);
     * list($err, $data) = $api->get_materials('voice', 0, 20);
     * list($err, $data) = $api->get_materials('video', 0, 20);
     * list($err, $data) = $api->get_materials('thumb', 0, 20);
     * ```
     */
    public function get_materials($type, $offset, $count)
    {
        $url = self::API_DOMAIN . 'cgi-bin/material/batchget_material?access_token=' . $this->get_access_token();
        $xml = sprintf('{"type":"%s","offset":"%s","count":"%s"}', $type, $offset, $count);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (!property_exists($res, 'errcode')) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 自定义菜单创建接口
     *
     * @param string $json 菜单的json串，具体结构见微信公众平台文档
     *
     * @return [err, data]
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
    public function create_menu($json)
    {
        $url = self::API_DOMAIN . 'cgi-bin/menu/create?access_token=' . $this->get_access_token();
        $res = HttpCurl::post($url, $json, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 自定义菜单查询接口
     *
     * @return [err, data]
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
    public function get_menu()
    {
        $url = self::API_DOMAIN . 'cgi-bin/menu/get?access_token=' . $this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['menu'])) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 自定义菜单删除接口
     *
     * @return [err, data]
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
    public function delete_menu()
    {
        $url = self::API_DOMAIN . 'cgi-bin/menu/delete?access_token=' . $this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 获取自定义菜单配置接口
     *
     * @return [err, data]
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
    public function get_selfmenu()
    {
        $url = self::API_DOMAIN . 'cgi-bin/get_current_selfmenu_info?access_token=' . $this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['is_menu_open'])) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * JS-SDK 生成一个新的jsapi_ticket
     *
     * @return mixed
     */
    public function new_jsapi_ticket()
    {
        $url = self::API_DOMAIN . 'cgi-bin/ticket/getticket?access_token=' . $this->get_access_token() . '&type=jsapi';
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [
                'ticket' => $res['ticket'],
                'expires_in' => $res['expires_in'] + time()
            ];
        } else {
            return false;
        }
    }

    /**
     * JS-SDK 校验jsapi_ticket是否过期
     *
     * @param object $ticket
     * @return bool
     */
    public function valid_jsapi_ticket($ticket)
    {
        return $ticket && isset($ticket['expires_in']) && ($ticket['expires_in'] > time() + 1200);
    }

    /**
     * JS-SDK 获取jsapi_ticket
     *
     * @return string $ticket
     */
    public function get_jsapi_ticket()
    {
        $ticket = false;
        if ($this->get_jsapi_ticket_diy !== false) {
            // 调用用户自定义获取jsapi_ticket方法
            $ticket = call_user_func($this->get_jsapi_ticket_diy);
            if ($ticket) {
                $ticket = json_decode($ticket, true);
            }
        } else {
            // 异常处理: 获取jsapi_ticket方法未定义
            @error_log('[wechat-php-sdk]Not set getTicketDiy method, jsapi_ticket will be refreshed each time.', 0);
        }

        // 验证jsapi_ticket是否有效
        if (!$this->valid_jsapi_ticket($ticket)) {

            // 生成新的jsapi_ticket
            $ticket = $this->new_jsapi_ticket();
            if ($ticket === false) {
                return false;
            }

            // 保存新生成的AccessToken
            if ($this->save_jsapi_ticket_diy !== false) {
                // 用户自定义保存AccessToken方法
                call_user_func($this->save_jsapi_ticket_diy, json_encode($ticket));
            } else {
                // 异常处理: 保存access_token方法未定义
                @error_log('[wechat-php-sdk]Not set saveTokenDiy method, jsapi_ticket will be refreshed each time.', 0);
            }
        }
        return $ticket['ticket'];
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
    public function get_jsapi_config($url = '', $type = '', $jsonp_callback = 'callback')
    {
        $jsapi_ticket = $this->get_jsapi_ticket();
        $nonce_str = SHA1::get_random_str();
        $timestamp = time();
        if ($url == '') {
            $url = $_SERVER['HTTP_REFERER'];
        }
        $signature = SHA1::get_jsapi_signature($jsapi_ticket, $nonce_str, $timestamp, $url);

        if ($signature === false) {
            $jsapi_config = [
                'errcode' => -1,
                'errmsg' => 'get jsapi signature error.'
            ];
        } else {
            $jsapi_config = [
                'errcode' => 0,
                'appId' => $this->appId,
                'timestamp' => $timestamp,
                'nonceStr' => $nonce_str,
                'signature' => $signature,
                'url' => $url
            ];
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
     * @return [err, data]
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
    public function create_qrcode($scene_id, $expire_seconds = 0)
    {
        $url = self::API_DOMAIN . 'cgi-bin/qrcode/create?access_token=' . $this->get_access_token();
        $expire = $expire_seconds == 0 ? '' : '"expire_seconds": ' . $expire_seconds . ',';
        $action_name = $expire_seconds == 0 ? 'QR_LIMIT_SCENE' : 'QR_SCENE';
        $xml = sprintf('{%s"action_name": "%s", "action_info": {"scene": {"scene_id": %s}}}',
            $expire,
            $action_name,
            $scene_id);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        // 判断是否调用成功
        if (isset($res['ticket'])) {
            return [null, $res];
        } else {
            return [$res, null];
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
    public function get_qrcode_url($ticket)
    {
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
    public function get_qrcode($ticket)
    {
        $url = $this->get_qrcode_url($ticket);
        $res = HttpCurl::get($url);
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_GET');
        }
        return [null, $res];
    }

    /**
     * 长链接转短链接接口
     *
     * @string $long_url [需要转换的长链接，支持http://、https://、weixin://wxpay 格式的url]
     *
     * @return [err, data]
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
    public function shorturl($long_url)
    {
        $url = self::API_DOMAIN . 'cgi-bin/shorturl?access_token=' . $this->get_access_token();
        $xml = '{"action":"long2short","long_url":"' . $long_url . '"}';
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function create_group($group_name)
    {
        $url = self::API_DOMAIN . 'cgi-bin/groups/create?access_token=' .$this->get_access_token();
        $xml = sprintf('{"group":{"name":"%s"}}', $group_name);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res['group'])) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_groups()
    {
        $url = self::API_DOMAIN . 'cgi-bin/groups/get?access_token=' .$this->get_access_token();
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res['groups'])) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_user_group($open_id)
    {
        $url = self::API_DOMAIN . 'cgi-bin/groups/getid?access_token=' .$this->get_access_token();
        $xml = sprintf('{"openid":"%s"}', $open_id);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res['groupid'])) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function update_group($group_id, $group_name)
    {
        $url = self::API_DOMAIN . 'cgi-bin/groups/update?access_token=' .$this->get_access_token();
        $xml = sprintf('{"group":{"id":"%s","name":"%s"}}', $group_id, $group_name);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function update_user_group($open_id, $to_groupid)
    {
        $url = self::API_DOMAIN . 'cgi-bin/groups/members/update?access_token=' .$this->get_access_token();
        $xml = sprintf('{"openid":"%s","to_groupid":"%s"}', $open_id, $to_groupid);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 用户分组管理 - 批量移动用户分组
     *
     * Examples:
     * ```
     * $api->batchupdate_user_group([
     *     'ocNtAt0YPGDme5tJBXyTphvrQIrc',
     *     'ocNtAt_TirhYM6waGeNUbCfhtZoA',
     *     'ocNtAt_K8nRlAdmNEo_R0WVg_rRw'
     * ], 100);
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
     * @param array $open_id_arr
     * @param int $to_groupid
     *
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function batchupdate_user_group($open_id_arr, $to_groupid)
    {
        $url = self::API_DOMAIN . 'cgi-bin/groups/members/batchupdate?access_token=' .$this->get_access_token();
        $open_ids = json_encode($open_id_arr);
        $xml = sprintf('{"openid_list":%s,"to_groupid":"%s"}', $open_ids, $to_groupid);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function delete_group($group_id)
    {
        $url = self::API_DOMAIN . 'cgi-bin/groups/delete?access_token=' .$this->get_access_token();
        $xml = sprintf('{"group":{"id":"%s"}}', $group_id);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function update_user_remark($open_id, $remark)
    {
        $url = self::API_DOMAIN . 'cgi-bin/user/info/updateremark?access_token=' .$this->get_access_token();
        $xml = sprintf('{"openid":"%s", "remark":"%s"}', $open_id, $remark);
        $res = HttpCurl::post($url, $xml, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if ($res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_user_info($open_id, $lang = '')
    {
        if ($lang != '') {
            $lang = '&lang=' . $lang;
        }
        $url = self::API_DOMAIN . 'cgi-bin/user/info?access_token=' . $this->get_access_token() . '&openid=' . $open_id . $lang;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res['openid'])) {
            return [null, $res];
        } else {
            return [$res, null];
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
     * @return [err, data]
     * - `err`, 调用失败时得到的异常
     * - `res`, 调用正常时得到的对象
     */
    public function get_user_list($next_openid = '')
    {
        if ($next_openid != '') {
            $next_openid = '&next_openid=' . $next_openid;
        }
        $url = self::API_DOMAIN . 'cgi-bin/user/get?access_token=' . $this->get_access_token() . $next_openid;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return Error::code('ERR_POST');
        }
        // 判断是否调用成功
        if (isset($res['data'])) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    /**
     * 得到获取用户授权需要打开的页面链接
     *
     * !!! 跳转后若提示`微信redirect_uri参数错误`
     * 很大可能是微信号的 `网页授权获取用户基本信息` 无权限，或 `授权回调页面域名` 填写不正确
     *
     * Examples:
     * ```
     * $api->get_authorize_url('snsapi_base', 'http://wx.diary8.com/demo/snsapi/callback_snsapi_base.php');
     * $api->get_authorize_url('snsapi_userinfo', 'http://wx.diary8.com/demo/snsapi/callback_snsapi_userinfo.php');
     * ```
     *
     * @param string $scope 应用授权作用域
     *  `snsapi_base` 不弹出授权页面，直接跳转，只能获取用户openid
     *  `snsapi_userinfo` 弹出授权页面，可通过openid拿到昵称、性别、所在地。即使在未关注的情况下，只要用户授权，也能获取其信息
     * @param string $redirect_uri 授权后要跳转到的地址
     * @param string $state 非必须, 重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值，最多128字节
     *
     * @return string
     */
    public function get_authorize_url($scope, $redirect_uri, $state = '')
    {
        $redirect_uri = urlencode($redirect_uri);
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->appId .
            '&redirect_uri=' . $redirect_uri . '&response_type=code&scope=' . $scope .
            '&state=' . $state . '#wechat_redirect';
        return $url;
    }

    /**
     * 获取用户授权后回调页面根据获取到的code，获取用户信息
     * 注：本函数将获取access_token和拉取用户信息集成在了一起，未对获取到的access_token进行保存
     *
     * Examples:
     * ```
     * $api->get_userinfo_by_authorize('snsapi_base', $_GET['code']);
     * $api->get_userinfo_by_authorize('snsapi_userinfo', $_GET['code']);
     * ```
     *
     * @param $scope `get_authorize_url`时使用的授权类型
     * @param string $lang 可选，返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语
     *
     * @return array|object
     */
    public function get_userinfo_by_authorize($scope, $lang = 'zh_CN')
    {
        if (isset($_GET['code']) && !empty($_GET['code'])) {
            $code = $_GET['code'];
            // 1. 通过code换取网页授权access_token
            $url = self::API_DOMAIN . 'sns/oauth2/access_token?appid=' . $this->appId . '&secret=' . $this->appSecret .
                '&code=' . $code . '&grant_type=authorization_code';
            $res = HttpCurl::get($url, 'json');
            // 异常处理: 获取时网络错误
            if ($res === false) {
                return Error::code('ERR_POST');
            }
            // 判断是否调用成功
            if (isset($res['access_token'])) {
                if ($scope == 'snsapi_userinfo') {
                    // 2.1 `snsapi_userinfo` 继续通过access_token和openid拉取用户信息
                    $url = self::API_DOMAIN . 'sns/userinfo?access_token=' . $res['access_token'] .
                        '&openid=' . $res['openid'] . '&lang=' . $lang;
                    $res = HttpCurl::get($url, 'json');
                    // 异常处理: 获取时网络错误
                    if ($res === false) {
                        return Error::code('ERR_POST');
                    }
                    // 判断是否调用成功
                    if (isset($res['openid'])) {
                        return [null, $res];
                    } else {
                        return [$res, null];
                    }
                } else {
                    // 2.2 `snsapi_base` 不弹出授权页面，直接跳转，只能获取用户openid
                    return [null, $res];
                }
            } else {
                return [$res, null];
            }
        } else {
            return ['授权失败', null];
        }
    }

    /**
     * 微信支付 - 统一下单 - 生成预订单
     * 包含
     * 1. 公众号支付 wiki:https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     * 2. App支付 wiki:https://pay.weixin.qq.com/wiki/doc/api/app/app.php?chapter=9_1
     * @param array $conf 配置数组
     * @return bool|mixed
     */
    public function wxPayUnifiedOrder($conf = [])
    {

        // [必填]公众账号ID、应用ID
        $conf['appid'] = $this->appId;

        // [必填]商户号、
        $conf['mch_id'] = $this->mchId;

        // 设备号
        // - device_info

        // [必填]nonce_str 随机字符串
        $conf['nonce_str'] = SHA1::get_random_str(32);

        // 签名类型
        $conf['sign_type'] = 'MD5';

        // [必填]商品描述
        // - body

        // 商品详情
        // - detail

        // 附加数据
        // - attach

        // [必填]商户订单号
        // - out_trade_no

        // 货币类型
        // - fee_type

        // [必填]总金额
        // - total_fee

        // [必填]终端IP
        $conf['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];

        // 交易起始时间
        // - time_start

        // 交易结束时间
        // - time_expire

        // 商品标记
        // - goods_tag

        // [必填]通知地址
        // - notify_url

        // [必填]交易类型
        // - trade_type

        // 指定支付方式
        // - limit_pay

        // 用户标识, trade_type=JSAPI时（即公众号支付），此参数必传
        // - openid

        // [必填]签名
        $conf['sign'] = SHA1::getSign2($conf, 'key='.$this->key);

        // 生成xml
        $xml = Xml::toXml($conf);

        // 调用接口
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        try {
            $res = HttpCurl::post($url, $xml);
            libxml_disable_entity_loader(true);
            return json_decode(json_encode(simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 公众号支付 - 获取jsapi支付的参数
     * 用于直接填入js函数作为参数
     * @param string $prepayId 预生成订单ID
     * @return string
     */
    public function getWxPayJsApiParameters($prepayId)
    {
        // 获取jsapi支付的参数
        $input = [
            //微信分配的公众账号ID WxPayConfig::APPID
            'appId' => $this->appId,
            //设置支付时间戳
            'timeStamp' => (string)time(),
            //随机字符串
            'nonceStr' => SHA1::get_random_str(32),
            //订单详情扩展字符串
            'package' => 'prepay_id='.$prepayId,
            //签名方式
            'signType' => 'MD5',
        ];
        // 签名
        $input['paySign'] = SHA1::getSign2($input, 'key='.$this->key);
        return json_encode($input);
    }

    /**
     * App支付 - 获取App支付的参数
     * 用于移动客户端调用移动端SDK调起微信支付
     * @param string $prepayId 预生成订单ID
     * @return array
     */
    public function getWxPayAppApiParameters($prepayId)
    {
        // 获取App支付的参数
        $input = [
            // 应用ID
            'appid' => $this->appId,
            // 商户号
            'partnerid' => $this->mchId,
            // 预支付交易会话ID
            'prepayid' => $prepayId,
            // 扩展字段
            'package' => 'Sign=WXPay',
            // 随机字符串
            'noncestr' => SHA1::get_random_str(32),
            // 时间戳
            'timestamp' => (string)time(),
        ];
        // 签名
        $input['paySign'] = SHA1::getSign2($input, 'key='.$this->key);
        return $input;
    }

    /**
     * 处理微信支付异步通知
     * 包含
     * 1. 公众号支付 wiki:https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     * 2. App支付 wiki:https://pay.weixin.qq.com/wiki/doc/api/app/app.php?chapter=9_1
     * @return array [是否支付成功, 异步通知的原始数据, 回复微信异步通知的数据]
     */
    public function progressWxPayNotify()
    {
        // PHP7移除了HTTP_RAW_POST_DATA
        $xml = file_get_contents('php://input');
        try {
            libxml_disable_entity_loader(true);
            $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            if (! is_array($data)) {
                return [false, [], [
                    'return_code' => 'FAIL',
                    'return_msg' => ''
                ]];
            }
            // 格式是否正确
            if (! array_key_exists('return_code', $data)) {
                return [false, $data, [
                    'return_code' => 'FAIL',
                    'return_msg' => 'return_code is not set'
                ]];
            }
            // 是否支付成功
            if ($data['return_code'] != 'SUCCESS') {
                return [false, $data, [
                    'return_code' => 'FAIL',
                    'return_msg' => 'return_code is '.$data['return_code']
                ]];
            }
            // 签名是否正确
            $sign1 = SHA1::getSign2($data, 'key='.$this->key);
            if ($sign1 != $data['sign']) {
                return [false, $data, [
                    'return_code' => 'FAIL',
                    'return_msg' => '签名验证失败'
                ]];
            }
            // 支付成功
            return [true, $data, [
                'return_code' => 'SUCCESS',
                'return_msg' => 'OK'
            ]];
        } catch (\Exception $e) {
            return [false, [], [
                'return_code' => 'FAIL',
                'return_msg' => $e->getMessage()
            ]];
        }
    }

    /**
     * 回复微信异步通知
     * 包含
     * 1. 公众号支付 wiki:https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     * 2. App支付 wiki:https://pay.weixin.qq.com/wiki/doc/api/app/app.php?chapter=9_1
     * @param array $info 回复内容数组
     */
    public static function replyWxPayNotify($info)
    {
        echo Xml::toXml($info);
    }

    // 创建二维码ticket
    // https://developers.weixin.qq.com/doc/offiaccount/Account_Management/Generating_a_Parametric_QR_Code.html
    public function qrcode_create($actionName, $sceneStr = '', $expireSeconds = 30)
    {
        $url = self::API_DOMAIN . 'cgi-bin/qrcode/create?access_token=' . $this->get_access_token();
        $query = [
            'action_name' => $actionName,
            'action_info' => [
                'scene' => ['scene_str' => $sceneStr]
            ],
        ];
        if ($actionName === 'QR_SCENE' || $actionName === 'QR_STR_SCENE') {
            $query['expire_seconds'] = $expireSeconds;
        }
        $res = HttpCurl::post($url, json_encode($query), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['url'])) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    // 发送模板消息
    // https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html#5
    public function message_template_send($openId, $templateId, $data = [], $url = '', $miniprogramAppId = '', $miniprogramPagePath = '')
    {
        $apiUri = self::API_DOMAIN . 'cgi-bin/message/template/send?access_token=' . $this->get_access_token();
        $query = [
            'touser' => $openId,
            'template_id' => $templateId,
            "data" => $data,
        ];
        if ($url !== '') {
            $query['url'] = $url;
        }
        if ($miniprogramAppId !== '') {
            $query['miniprogram'] = [
                'appid' => $miniprogramAppId,
                'pagepath' => $miniprogramPagePath,
            ];
        }
        $res = HttpCurl::post($apiUri, json_encode($query), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['ret']) && $res['ret'] == 0) {
            return array(null, $res);
        } else {
            return array($res, null);
        }
    }

    // 微信硬件 - 获取设备二维码
    // 第三方公众账号通过设备id从公众平台批量获取设备二维码
    public function device_create_qrcode($deviceId)
    {
        $url = self::API_DOMAIN . 'device/create_qrcode?access_token=' . $this->get_access_token();
        $res = HttpCurl::post($url, json_encode([
            'device_num' => '1',
            'device_id_list' => [$deviceId],
        ]), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['errcode']) && $res['errcode'] == 0) {
            return [null, $res['code_list'][0]];
        } else {
            return [$res, null];
        }
    }

    // 微信硬件 - 设备授权
    // 第三方公众账号将设备id及其属性信息提交公众平台进行授权
    // https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-5
    // opType 0设备授权,1设备更新
    // productId 产品编号
    public function device_authorize_device($device, $opType = 0, $productId = '')
    {
        $url = self::API_DOMAIN . 'device/authorize_device?access_token=' . $this->get_access_token();

        $query = [
            'device_num' => '1',
            'device_list' => [$device],
            'op_type' => $opType,
        ];
        if ($opType === 0) {
            $query['product_id'] = $productId;
        }
        $res = HttpCurl::post($url, json_encode($query), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['errcode']) && $res['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    // 微信硬件 - 通过openid获取用户绑定的deviceid
    // 通过openid获取用户在当前devicetype下绑定的deviceid列表
    // https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-12
    public function device_get_bind_device($openId)
    {
        $url = self::API_DOMAIN . 'device/get_bind_device?access_token=' . $this->get_access_token() . '&openid='.$openId;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['device_list'])) {
            return [null, $res['device_list']];
        } else {
            return [$res, null];
        }
    }

    // 微信硬件 - 获取设备绑定openID
    // 通过device type和device id获取设备主人的openid
    // https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-11
    public function device_get_openid($deviceId)
    {
        $url = self::API_DOMAIN . 'device/get_openid?access_token=' . $this->get_access_token() . '&device_type=' . $this->ghId . '&device_id='.$deviceId;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['open_id'])) {
            return [null, $res['open_id']];
        } else {
            return [$res, null];
        }
    }

    // 微信硬件 - 设备状态查询
    // 第三方公众账号通过设备id查询该id的状态（三种状态：未授权、已授权、已绑定）
    // https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-8
    public function device_get_stat($deviceId)
    {
        $url = self::API_DOMAIN . 'device/get_stat?access_token=' . $this->get_access_token() . '&device_id='.$deviceId;
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['status_info'])) {
            return [null, $res['status_info']];
        } else {
            return [$res, null];
        }
    }

    // 微信硬件 - 强制绑定用户和设备
    // 第三方强制绑定用户和设备
    // https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-7
    public function device_compel_bind($deviceId, $openId)
    {
        $url = self::API_DOMAIN . 'device/compel_bind?access_token=' . $this->get_access_token();
        $res = HttpCurl::post($url, json_encode([
            'device_id' => $deviceId,
            'openid' => $openId,
        ]), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if ($res['base_resp']['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    // 微信硬件 - 强制解绑用户和设备
    // 第三方强制解绑用户和设备
    // https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-7
    public function device_compel_unbind($deviceId, $openId)
    {
        $url = self::API_DOMAIN . 'device/compel_unbind?access_token=' . $this->get_access_token();
        $res = HttpCurl::post($url, json_encode([
            'device_id' => $deviceId,
            'openid' => $openId,
        ]), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if ($res['base_resp']['errcode'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    // 微信硬件 - 主动发送消息给设备
    // 第三方发送消息给设备主人的微信终端，并最终送达设备
    // https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-3
    public function device_transmsg($deviceId, $openId, $content)
    {
        $url = self::API_DOMAIN . 'device/transmsg?access_token=' . $this->get_access_token();
        $res = HttpCurl::post($url, json_encode([
            'device_type' => $this->ghId,
            'device_id' => $deviceId,
            'open_id' => $openId,
            'content' => base64_encode($content),
        ]), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['ret']) && $res['ret'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    // 微信硬件 - 第三方主动发送设备状态消息给微信终端
    // https://iot.weixin.qq.com/wiki/new/index.html?page=3-4-13
    public function device_transmsg_device_status($deviceId, $openId, $deviceStatus)
    {
        $url = self::API_DOMAIN . 'device/transmsg?access_token=' . $this->get_access_token();
        $res = HttpCurl::post($url, json_encode([
            'device_type' => $this->ghId,
            'device_id' => $deviceId,
            'open_id' => $openId,
            'msg_type' => 2,
            // 设备状态:0未连接,1已连接
            'device_status' => $deviceStatus,
        ]), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['ret']) && $res['ret'] == 0) {
            return [null, $res];
        } else {
            return [$res, null];
        }
    }

    // 小程序.临时登录凭证code获取openId
    // https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/login/auth.code2Session.html
    public function sns_jscode2session($code)
    {
        $url = self::API_DOMAIN . 'sns/jscode2session?appid='.$this->appId.'&secret='.$this->appSecret.'&js_code='.$code.'&grant_type=authorization_code';
        $res = HttpCurl::get($url, 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['openid']) && $res['openid'] == 0) {
            return array(null, $res);
        } else {
            return array($res, null);
        }
    }

    // 小程序.发送订阅消息
    // https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/subscribe-message/subscribeMessage.send.html
    public function message_subscribe_send($openId, $templateId, $page, $data = [])
    {
        $url = self::API_DOMAIN . 'cgi-bin/message/subscribe/send?access_token=' . $this->get_access_token();
        $query = [
            'touser' => $openId,
            'template_id' => $templateId,
            'page' => $page,
            "data" => $data,
        ];
        $res = HttpCurl::post($url, json_encode($query), 'json');
        // 异常处理: 获取时网络错误
        if ($res === false) {
            return ['ERR_POST', null];
        }
        // 判断是否调用成功
        if (isset($res['ret']) && $res['ret'] == 0) {
            return array(null, $res);
        } else {
            return array($res, null);
        }
    }
}
