<?php
/**
 * FileCache 文件缓存
 */

namespace Gaoming13\WechatPhpSdk\Utils;

class FileCache {

    protected $options = [
        'expire'        => 7000,
        'cache_subdir'  => true,
        'prefix'        => '',
        'path'          => __DIR__.'/runtime/',
        'data_compress' => false,
    ];

    // 构造函数
    public function __construct($options = [])
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        if (substr($this->options['path'], -1) != '/') {
            $this->options['path'] .= '/';
        }
        $this->init();
    }

    // 初始化检查
    private function init()
    {
        // 创建项目缓存目录
        if (!is_dir($this->options['path'])) {
            if (mkdir($this->options['path'], 0755, true)) {
                return true;
            }
        }
        return false;
    }

    // 获取存储的数据
    public function get($name, $default = false)
    {
        $filename = $this->getCacheKey($name);
        if (!is_file($filename)) {
            return $default;
        }
        $content = file_get_contents($filename);
        if (false !== $content) {
            $arr = json_decode($content,true);
            if ($arr['expire'] <= time()) {
                return false;
            }
            return $arr['data'];
        }
    }

    // 设置存储的数据
    public function set($name, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        $filename = $this->getCacheKey($name);
        $json = json_encode(['data' => $value, 'expire' => time() + $expire]);
        $result = file_put_contents($filename,$json);
        if ($result) {
            return true;
        }
        return false;
    }

    // 删除存储的数据
    public function del($name)
    {
        $filename = $this->getCacheKey($name);
        @unlink($filename);
    }

    // 获取缓存文件名
    public function getCacheKey($name)
    {
        return $this->options['path']."/".$name.'_cache.php';
    }
}