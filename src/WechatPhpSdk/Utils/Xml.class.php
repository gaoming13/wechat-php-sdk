<?php
/**
 * Xml class
 *
 * Xml处理
 *
 * @author 		gaoming13 <gaoming13@yeah.net>
 * @link 		https://github.com/gaoming13/wechat-php-sdk
 * @link 		http://me.diary8.com/
 */

namespace Gaoming13\WechatPhpSdk\Utils;

class Xml
{
    /**
     * 生成xml字符串
     * @param array $params 数据数组
     * @return string
     */
    public static function toXml($params)
    {
        $xml = '<xml>';
        foreach ($params as $key => $val) {
            if (is_numeric($val)) {
                $xml .= '<'.$key.'>'.$val.'</'.$key.'>';
            } else {
                $xml .= '<'.$key.'><![CDATA['.$val.']]></'.$key.'>';
            }
        }
        $xml .= '</xml>';
        return $xml;
    }
}