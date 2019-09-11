<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace sms;

class Mysubmail
{
    protected static $instance = null;
    private $base_url = 'https://api.mysubmail.com/';
    private $appid = '15313';
    private $appkey = '0b9445f118236f839e36955ee61b12f5';
    private $signtype = 'normal';

    /**
     * @return null|static
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * 发送短信
     * @param $to
     * @param $project
     * @param $vals
     * @return mixed
     */
    public function send($to, $vals, $project = 'JipAR4')
    {
        $request = [
            'to'        => $to,
            'vars'      => json_encode($vals),
            'project'   => $project,
            'appid'     => $this->appid,
            'timestamp' => $this->getTimestamp(),
            'sign_type' => $this->signtype,
        ];

        $request['signature'] = $this->createSignature($request);
        return $this->httpRequestSend($request);
    }

    /**
     * 请求API
     * @param $request
     * @return mixed
     */
    private function httpRequestSend($request, $method = 'post')
    {
        $url = $this->base_url . 'message/xsend.json';
        if ($method != 'get') {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-HTTP-Method-Override: $method"));
            if ($method != 'post') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
            }
        } else {
            $url = $url . '?' . http_build_query($request);
            $ch  = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        }
        $output = curl_exec($ch);
        curl_close($ch);
        $output = trim($output, "\xEF\xBB\xBF");
        return json_decode($output, true);
    }

    /**
     * Timestamp UNIX 时间戳
     * @return mixed
     */
    private function getTimestamp()
    {
        $api = $this->base_url . 'service/timestamp.json';
        $ch  = curl_init($api);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        $output    = curl_exec($ch);
        $timestamp = json_decode($output, true);

        return $timestamp['timestamp'];
    }

    private function createSignature($request)
    {
        $r = "";
        switch ($this->signtype) {
            case 'normal':
                $r = $this->appkey;
                break;
            case 'md5':
                $r = $this->buildSignature($this->argSort($request));
                break;
            case 'sha1':
                $r = $this->buildSignature($this->argSort($request));
                break;
        }
        return $r;
    }

    /**
     * 生成签名
     * @param $request
     * @return string
     */
    private function buildSignature($request)
    {
        $arg    = "";
        $app    = $this->appid;
        $appkey = $this->appkey;
        while (list ($key, $val) = each($request)) {
            $arg .= $key . "=" . $val . "&";
        }
        $arg = substr($arg, 0, count($arg) - 2);
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }

        if ($this->signType == 'sha1') {
            $r = sha1($app . $appkey . $arg . $app . $appkey);
        } else {
            $r = md5($app . $appkey . $arg . $app . $appkey);
        }
        return $r;
    }

    /**
     * 排序
     * @param $request
     * @return mixed
     */
    private function argSort($request)
    {
        ksort($request);
        reset($request);
        return $request;
    }
}