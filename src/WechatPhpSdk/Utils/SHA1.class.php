<?php
/**
 * SHA1 class
 *
 * 计算公众平台的消息签名接口.
 *
 * @author 		gaoming13 <gaoming13@yeah.net>
 * @link 		https://github.com/gaoming13/wechat-php-sdk
 * @link 		http://me.diary8.com/
 */

namespace Gaoming13\WechatPhpSdk\Utils;

class SHA1 {
	/**
	 * 用SHA1算法生成安全签名
	 * 生成微信消息体的签名
	 *
	 * @param string $token 票据
	 * @param string $timestamp 时间戳
	 * @param string $nonce 随机字符串
	 * @param string $encrypt 密文消息
	 */
	static function getSHA1($token, $timestamp, $nonce, $encrypt_msg) {
		//排序
		try {
			$array = array($encrypt_msg, $token, $timestamp, $nonce);
			sort($array, SORT_STRING);
			$str = implode($array);
			return sha1($str);			
		} catch (Exception $e) {
			@error_log('getSHA1 Error: ' . $e->getMessage(), 0);
			return FALSE;
		}
	}

	/**
	 * 获取微信消息的签名
	 *
	 * @param string $token 票据
	 * @param string $timestamp 时间戳
	 * @param string $nonce 随机字符串	 
	 */
	static function getSignature($token, $timestamp, $nonce) {
		//排序
		try {
			$array = array($token, $timestamp, $nonce);
			sort($array, SORT_STRING);
			$str = implode($array);
			return sha1($str);
		} catch (Exception $e) {
			@error_log('getSignature Error: ' . $e->getMessage(), 0);			
			return FALSE;
		}
	}	
}