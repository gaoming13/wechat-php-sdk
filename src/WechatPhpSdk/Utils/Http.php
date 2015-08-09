<?php
/**
 * Http 工具类
 * 
 *
 * @author      gaoming13 <gaoming13@yeah.net>
 * @link        https://github.com/gaoming13/wechat-php-sdk
 * @link        http://me.diary8.com/
 */

namespace Gaoming13\WechatPhpSdk\Utils;

class Http {

    /**
     * GET 请求
     * @param string $url
     */
    static public function get($url, $type='text') {
        $oCurl = curl_init();
        if(stripos($url, 'https://') !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus['http_code']) == 200) {
            if ($type == 'json') {
                return json_decode($sContent);
            } else {
                return $sContent;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * POST 请求
     * @param string $url
     * @param array $param     
     * @return string content
     */
    static public function post($url, $param, $type='text') {
        $oCurl = curl_init();
        if(stripos($url, 'https://')!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        $strPOST = '';
        if (gettype($param)=='array') {
            $aPOST = array();
            foreach($param as $key=>$val) {
                $aPOST[] = $key.'='.urlencode($val);
            }
            $strPOST = join('&', $aPOST);
        } else {
            $strPOST = $param;
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus['http_code']) == 200) {
            if ($type == 'json') {
                return json_decode($sContent);
            } else {
                return $sContent;
            }            
        } else {
            return FALSE;
        }
    }
}