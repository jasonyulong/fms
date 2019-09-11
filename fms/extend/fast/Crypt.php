<?php

namespace fast;

/**
 * Phalcon\Crypt
 *
 * Provides encryption facilities to Phalcon applications.
 *
 * <code>
 * use fast\Crypt;
 *
 * $crypt = new Crypt();
 *
 * $text = "The message to be encrypted";
 *
 * $encrypted = $crypt->encrypt($text, $key);
 *
 * echo $crypt->decrypt($encrypted, $key);
 * </code>
 */
class Crypt
{
    const KEY = 'Oepd1OBMamXolAQXSoAetFAhwaHxXN982D';
    const IV = 'RuI1os7upxPllCqL';

    /**
     * 加密
     * @param $string
     * @return string
     */
    public static function encrypt($string)
    {
        $encrypted = openssl_encrypt($string, 'aes-256-cbc', self::KEY, OPENSSL_RAW_DATA, self::IV);
        return rtrim(strtr(base64_encode($encrypted), "+/", "-_"), "=");
    }

    /**
     * 解密
     * @param $encrypt
     * @return string
     */
    public static function decrypt($encrypt)
    {
        $decrypted = base64_decode(strtr($encrypt, "-_", "+/") . substr("===", (strlen($encrypt) + 3) % 4));
        return openssl_decrypt($decrypted, 'aes-256-cbc', self::KEY, OPENSSL_RAW_DATA, self::IV);
    }
}