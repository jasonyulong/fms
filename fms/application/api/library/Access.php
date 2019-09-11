<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\api\library;

// +----------------------------------------------------------------------
// | token 参数校验方式：
// | md5(请求参数按照ASCII码顺序用&拼接&API密钥)
// | 如： md5(a=1&b=2&c=3&API_KEY)
// +----------------------------------------------------------------------

use think\Config;

/**
 * 校验请求
 * Class Access
 * @package app\api\library
 */
class Access
{
    /**
     * @var Access response
     */
    private $request;

    /**
     * @var string token name
     */
    private $token_params = 'sign';

    /**
     * @var string time name
     */
    private $time_params = 'time';

    /**
     * @var integer time out secend
     */
    private $time_out = 60;

    /**
     * @var Access params
     */
    private $requestParams;

    /**
     * @var $this
     */
    private $_this;

    /**
     * RequestAccess constructor.
     * @param $request
     */
    public function __construct($_this, $request)
    {
        $this->request       = $request;
        $this->requestParams = $this->getParams();
        $this->time_out      = 600;
        $this->_this         = $_this;
    }

    /**
     * get access token
     * @param string $default
     * @return mixed|string
     */
    public function getToken($default = '')
    {
        return isset($this->requestParams[$this->token_params]) ? (string) $this->requestParams[$this->token_params] : $default;
    }

    /**
     * get access time
     * @param int $default
     * @return int
     */
    public function getTime($default = 0)
    {
        return isset($this->requestParams[$this->time_params]) ? intval($this->requestParams[$this->time_params]) : $default;
    }

    /**
     * validate token
     * @return bool
     */
    public function validate()
    {
        // request token
        $token = $this->getToken();
        if (empty($token)) {
            return $this->_this->error("The {$this->token_params} params cannot be null", $this->requestParams, -1);
        }

        // request time
        $time = $this->getTime();
        if ($time == 0) {
            return $this->_this->error("The time params cannot be null", null, -1);
        }
        if (time() - $time > $this->time_out) {
            return $this->_this->error("request timeout. time=" . time(), null, -1);
        }

        // 处理request params
        unset($this->requestParams[$this->token_params]);
        if (isset($this->requestParams['_url'])) {
            unset($this->requestParams['_url']);
        }

        ksort($this->requestParams);
        $md5 = md5($this->httpBuildParams() . '&' . API_KEY);

        // 校验token是否一致
        if (strtolower($md5) != strtolower($token)) {
            // debug模式返回
            //if (ENVIRONMENT != 'production') {
                return $this->_this->error("The {$this->token_params} params is error.", [
                    $this->token_params => strtolower($md5),
                    'params'            => $this->httpBuildParams() . '&' . API_KEY],
                    -1
                );
            //}
            return $this->_this->error("The {$this->token_params} params is error.", null, -1);
        }
        return true;
    }

    /**
     * 获取参数
     * @return mixed
     */
    public function getParams()
    {
        return $this->request->get();
    }

    /**
     * @param string $default
     * @return string
     */
    private function httpBuildParams($default = '')
    {
        if (empty($this->requestParams)) {
            return $default;
        }

        foreach ($this->requestParams as $key => $val) {
            $default .= $key . '=' . $val . '&';
        }
        return rtrim($default, "&");
    }
}