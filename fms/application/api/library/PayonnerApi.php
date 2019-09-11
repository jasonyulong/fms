<?php
namespace app\api\library;

use think\Config;
class PayonnerApi
{
    // api 的配置，应该从配置文件中获取，暂时这样写
    private $redirect_url = 'http://www.oobest.com';
    private $sandbox_url = 'https://api.sandbox.payoneer.com';
    private $program_id = "100070840";
    private $api_username = 'Zhuoshi0840';
    private $api_pwd = 'fc8oxBV1007';
    private $api_url_base = null;
    private $api_header = [];

    public function __construct()
    {
        $this->api_url_base = "https://api.sandbox.payoneer.com/v2/programs/{$this->program_id}/";
        $this->api_header =  [$this->_buildAuthHeader()];
    }

    /**
     * 实例
     * @var
     */
    private static $instance = null;

    /**
     * 单例：获取当前类的 实例
     * @author: Lamkakyun
     * @date: 2018-11-16 15:12:08
     */
    public static function getInstance()
    {
        if (!static::$instance) {

            static::$instance = new PayonnerApi();
        }
        return static::$instance;
    }
    

    /**
     * 创建验证头信息
     * @author lamkakyun
     * @date 2018-12-28 11:35:22
     * @return void
     */
    private function _buildAuthHeader()
    {
        $base = "{$this->api_username}:{$this->api_pwd}";
        $auth = base64_encode($base);
        return 'authorization: Basic ' . $auth;
    }

    /**
     * 调用echo api
     * @author lamkakyun
     * @date 2018-12-28 11:29:40
     * @return void
     */
    public function echo()
    {
        $api_url = $this->api_url_base . "echo";
        return curl_get($api_url, $this->api_header);
    }

    /**
     * 调用 api-version api
     * @author lamkakyun
     * @date 2018-12-28 11:37:29
     * @return void
     */
    public function apiVersion()
    {
        $api_url = $this->api_url_base . "api-version";
        return curl_get($api_url, $this->api_header);
    }


    /**
     * 调用登陆连接 api
     * @author lamkakyun
     * @date 2018-12-28 13:35:29
     * @return void
     */
    public function loginLink($payee_id = '')
    {
        $api_url = $this->api_url_base . "payees/login-link";
        $post_data = [
            'payee_id' => 'qPM5TXBgSlgOrfR',
        ];
        
        $http_body = json_encode($post_data);
        // echo '<pre>';var_dump($api_url, $http_body);echo '</pre>';

        return curl_post($api_url, $this->api_header, $http_body);
    }

    /**
     * 调用api Get Payee Status
     * @author lamkakyun
     * @date 2018-12-28 14:11:06
     * @cmd curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/payees/qPM5TXBgSlgOrfR/status -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc=" 
     * @return void
     */
    public function status()
    {
        $payee_id = 'qPM5TXBgSlgOrfR';
        $api_url = $this->api_url_base . "payees/{$payee_id}/status";
        return curl_get($api_url, $this->api_header);
    }


    /**
     * API: Get Receiving Accounts
     * @author lamkakyun
     * @date 2018-12-28 14:16:38
     * @cmd curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/payees/qPM5TXBgSlgOrfR/receiving-accounts -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc=" 
     * @return void
     */
    public function receivingAccounts()
    {
        $payee_id = 'qPM5TXBgSlgOrfR';
        $api_url = $this->api_url_base . "payees/{$payee_id}/receiving-accounts";
        return curl_get($api_url, $this->api_header);
    }


    /**
     * API: Get Single Payee Report
     * @author lamkakyun
     * @date 2018-12-28 14:27:13
     * @cmd curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/reports/payee_details?payee_id=qPM5TXBgSlgOrfR -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc=" 
     * @return void
     */
    public function payeeReport()
    {
        // $payee_id = 'qPM5TXBgSlgOrfR';
        // $payee_id = 'unique Zhuoshi Payee ID';
        // $payee_id = '4Xi6OvWf5S86gxR8RF';
        // $payee_id = 'NyTFN2gMKm2dfTvfyFIjhXD8';
        $payee_id = '001';
        $api_url = $this->api_url_base . "reports/payee_details?payee_id={$payee_id}";
        return curl_get($api_url, $this->api_header);
    }


    /**
     * API: Get Payees Status Report
     * @author lamkakyun
     * @date 2018-12-28 14:28:52
     * @cmd curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/reports/payees_status?start_date=2018-10-01&end_date=2018-11-01 -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc="
     * @return void
     */
    public function payeeStatusReport()
    {

    }


    /**
     * API: Get Payee Details
     * @author lamkakyun
     * @date 2018-12-28 14:32:18
     * @cmd curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/payees/qPM5TXBgSlgOrfR/details -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc="
     * @return void
     */
    public function payeeDetail()
    {

    }


    /**
     * API: Get Balances
     * @author lamkakyun
     * @date 2018-12-28 14:43:35
     * @cmd curl -X GET https://api.sandbox.payoneer.com/v2/programs/100070840/payees/qPM5TXBgSlgOrfR/balances -H "authorization: Basic Wmh1b3NoaTA4NDA6ZmM4b3hCVjEwMDc="
     * @return void
     */
    public function payeeBalance()
    {

    }
}