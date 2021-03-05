<?php
/**
 * Error 错误代码类
 */

namespace Gaoming13\WechatPhpSdk\Utils;

class Error {

    /**
     * 获取某个错误的对象数组
     *
     * @return object(err, NULL)
     *
     * Examples:
     * ```
     * Error::code('ERR_GET');
     * ```
     */
    static public function code ($code)
    {

        // 本SDK自定义错误类型
        $code_arr = [
            // 错误: get方式请求api网络错误
            'ERR_GET' => [13001, 'http get api error.'],
            // 错误: post方式请求api网络错误
            'ERR_POST' => [13002, 'http post api error.'],
            // 错误: 消息类型未定义
            'ERR_MEG_TYPE' => [13003, 'message type is not defined.'],
        ];

        return array((object)array(
                'errcode' => $code_arr[$code][0],
                'errmsg' => $code_arr[$code][1]
            ), NULL);
    }
}