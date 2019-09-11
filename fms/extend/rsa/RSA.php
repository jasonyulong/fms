<?php

namespace rsa;

/**
 * RSA 加解密
 * Class RSA
 * @package plugins\rsa
 */
class RSA
{
    /**
     * @var string openssl public key
     */
    private $public_key;

    /**
     * @var string openssl private key
     */
    private $private_key;

    /**
     * @var string 解密后的数据
     */
    private $decrypted;

    /**
     * @var 单例对象
     */
    private static $_instance;

    /**
     * 私有构造方法
     * RSA constructor.
     */
    private function __construct()
    {
        $this->public_key  = file_get_contents(PEM_PUBLICKEY);
        $this->private_key = file_get_contents(PEM_PRIVATEKEY);
    }

    /**
     * 私有复制方法
     */
    private function __clone()
    {
    }

    /**
     * 单例
     * @return RSA|单例对象
     */
    public static function getInstance()
    {
        if (self::$_instance instanceof self) {
            return self::$_instance;
        }
        return new self();
    }

    /**
     * 加密
     * @param  $data 加密数据
     * @return string
     */
    public function encrypt($data)
    {
        $cryptData = is_array($data) ? json_encode($data) : (string) $data;
        // 加密
        $r = openssl_private_encrypt($cryptData, $crypted, $this->private_key);
        return !$r ? null : base64_encode($crypted);
    }

    /**
     * 解密
     * @param  string $encrypt 加密过的字符串
     * @return string
     */
    public function decrypt($encrypt)
    {
        $r = openssl_public_decrypt(base64_decode($encrypt), $decrypted, $this->public_key);

        $this->decrypted = !$r ? null : $decrypted;
        return $this;
    }

    /**
     * 返回数组格式
     * @return array
     */
    public function toArray()
    {
        if (!$this->decrypted || empty($this->decrypted)) {
            return $this->decrypted;
        }

        return json_decode($this->decrypted, true);
    }

    /**
     * 返回字符串格式
     * @return string
     */
    public function toString()
    {
        return (string) $this->decrypted;
    }
}