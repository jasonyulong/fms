<?php

namespace app\common\library;

use fast\Http;
use fast\Crypt;
use think\Config;
use think\console\Output;
use app\common\model\Bank;
use app\common\model\Task;
use app\common\model\Account;
use think\cache\driver\Redis;
use app\common\model\Currency;
use app\common\model\AccountFund;
use app\common\library\import\OFX;
use app\common\library\import\Paypal;
use app\common\model\AccountFundDiff;
use app\common\model\ERPPaypalDetail;
use app\common\library\import\Lianlian;
use app\common\library\import\Payoneer;
use app\common\library\import\Pingpong;
use app\common\model\AccountFundDetail;
use app\index\library\FundLib;

/**
 * 工具类，比如一些缓存，组织架构，以及erp系统的基本信息等等
 * Class ToolsLib
 * @package app\common\library
 */
class ToolsLib
{

    /**
     * 实例
     * @var ToolsLib
     */
    private static $instance = null;

    /**
     * 单例：获取当前类的 实例
     * @author: Lamkakyun
     * @date: 2018-11-08 03:16:24
     */
    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = new ToolsLib();
        }
        return static::$instance;
    }

    /**
     * @var think\cache\driver\Redis
     */
    private static $redisInstance = null;

    /**
     * 获取redis 实例
     * @author lamkakyun
     * @date 2018-11-12 09:33:03
     * @return void
     */
    public function getRedis()
    {
        if (!static::$redisInstance) static::$redisInstance = new Redis(Config::get('redis'));
        return static::$redisInstance;
    }

    /**
     * 获取所有平台
     * @author: Lamkakyun
     * @date: 2018-11-08 03:13:14
     * @return array
     */
    public function getPlatformList($format_type = 0)
    {
        $ret_data = Config::get('site.platforms');

        switch ($format_type) {
            case '1': // 按顺序排序
                sort($ret_data);
                break;
        }
        return $ret_data;
    }


    /**
     * 直接从ERP中复制过来的，所有的站点
     * @author lamkakyun
     * @date 2018-11-12 20:26:03
     * @return array
     */
    public function getAllEbaySite()
    {
        return ['MY', 'US', 'ID', 'SG', 'CA', 'PH', 'MX', 'UK', 'TH', 'TW', 'FR', 'VN', 'DE', 'VN', 'IT', 'ES', 'JP', 'IN', 'MX', 'CO', 'CO', 'EG', 'CL', 'COD', 'PE', 'NG', 'AR', 'CI', 'MA', 'KE', 'FR'];
    }


    /**
     * 获取银行的所有省份
     * @author lamkakyun
     * @date 2018-11-12 09:30:09
     * @return array
     */
    public function getAllBankProvince($force_update = false)
    {
        $key  = Config::get('redis.fms_bank_province');
        $data = ToolsLib::getInstance()->getRedis()->get($key);
        if ($data && !$force_update) return $data;

        $bank_model = new Bank();

        $data = $bank_model->field('DISTINCT province_id, province')->where(['province' => ['NEQ', '']])->select()->toArray();

        ToolsLib::getInstance()->getRedis()->set($key, $data, 7 * 24 * 60 * 60);
        return $data;
    }

    /**
     * 获取银行的所有城市
     * @author lamkakyun
     * @date 2018-11-12 11:15:13
     * @return array
     */
    public function getAllBankCities($force_update = false)
    {
        $key  = Config::get('redis.fms_bank_city');
        $data = ToolsLib::getInstance()->getRedis()->get($key);
        if ($data && !$force_update) return $data;

        $bank_model = new Bank();

        $data = $bank_model->field('DISTINCT city_id, city')->select()->toArray();

        ToolsLib::getInstance()->getRedis()->set($key, $data, 7 * 24 * 60 * 60);
        return $data;
    }


    /**
     * 获取所有银行
     * @author lamkakyun
     * @date 2018-11-12 11:11:42
     * @return array
     */
    public function getAllBanks($force_update = false)
    {
        $key  = Config::get('redis.fms_banks');
        $data = ToolsLib::getInstance()->getRedis()->get($key);
        if ($data && !$force_update) return $data;

        $bank_model = new Bank();

        $data = $bank_model->field('DISTINCT bank_id, bank_name')->select()->toArray();

        ToolsLib::getInstance()->getRedis()->set($key, $data, 7 * 24 * 60 * 60);
        return $data;
    }


    /**
     * 获取所有银行 支行 (内存消耗大，不适用)
     * @author lamkakyun
     * @date 2018-11-12 11:11:42
     * @return array
     */
    public function getAllSubBanks($force_update = false)
    {
        ini_set('memory_limit', '500M'); // 消耗的内存有点多
        $key  = Config::get('redis.fms_sub_banks');
        $data = ToolsLib::getInstance()->getRedis()->get($key);
        if ($data && !$force_update) return $data;

        $bank_model = new Bank();

        $data = $bank_model->field('DISTINCT sub_branch_id, sub_branch_name')->select()->toArray();

        ToolsLib::getInstance()->getRedis()->set($key, $data, 7 * 24 * 60 * 60);
        return $data;
    }


    /**
     * 根据 bank_id, 与 sub_bank_id 获取 bank name
     * @author lamkakyun
     * @date 2018-11-12 16:37:30
     * @return string
     */
    public function getBankInfo($bank_id, $sub_bank_id)
    {
        $bank_model = new Bank();
        return $bank_model->where(['bank_id' => $bank_id, 'sub_branch_id' => $sub_bank_id])->find()->toArray();
    }


    /**
     * 获取所有币种
     * @author lamkakyun
     * @date 2018-11-12 13:34:32
     * @return array
     */
    public function getAllCurrencyType()
    {
        $currency = ['USD', 'GBP', 'CAD', 'AUD', 'VND', 'EUR', 'CNY', 'JPY', 'HKD', 'SGD', 'AED', 'MXN'];
        sort($currency);
        return $currency;
    }


    /**
     * 获取第三方支付的类型
     * @author lamkakyun
     * @date 2018-11-13 13:53:44
     * @return array
     */
    public function getThirdPayType()
    {
        return Config::get('site.third_pay_account_type');
    }

    /**
     * 获取账号类型
     * @author lamkakyun
     * @date 2018-11-13 14:04:22
     * @return array
     */
    public function getAccountType()
    {
        return Config::get('site.account_type');
    }

    /**
     * 获取转账卡类型
     * @return mixed
     */
    public function getBankType()
    {
        return Config::get('site.bank_type');
    }

    /**
     * 账户的使用场景，或说是类型
     * @author lamkakyun
     * @date 2018-11-16 11:39:39
     * @return void
     */
    public function getTypeScene()
    {
        return Config::get('site.type_scene');
    }

    /**
     * 获取转账类型
     * @author lamkakyun
     * @date 2018-11-20 14:14:45
     * @return array
     */
    public function getFundType()
    {
        return [
            '0' => '对内转账',
            '1' => '对外转账',
            '2' => '提现',
            '3' => '平账',
            '4' => '入账',
            // '5' => '换汇',
            '5' => 'HL',
        ];
    }

    /**
     * 其他货币转换为美元
     * @author: Lamkakyun
     * @date: 2018-11-20 18:22:54
     * @return float
     */
    public function convertToUSADollar($moneyAmount, $moneyType = 'USD')
    {
        $rate = $this->getCurrencyRate($moneyType);
        return round($moneyAmount * $rate, 4);
    }

    /**
     * 一种货币转换成另一种货币
     * @author lamkakyun
     * @date 2018-11-20 18:25:30
     * @return float
     */
    public function convertCurrency($from_money_amount, $from_money_type, $to_money_type)
    {
        if ($from_money_type == $to_money_type) return $from_money_amount;
        $usd = $this->convertToUSADollar($from_money_amount, $from_money_type);

        $to_rate = $this->getCurrencyRate($to_money_type);
        return round($usd / $to_rate, 4);
    }

    /**
     * 给定货币，获取 美元兑换 利率
     * @author lamkakyun
     * @date 2018-11-20 18:20:15
     * @return float
     */
    public function getCurrencyRate($moneyType = 'USD')
    {
        $redis      = $this->getRedis();
        $key        = 'erp:cache:currency:rate:' . $moneyType;
        $cache_time = 60 * 60;
        $data       = $redis->get($key);
        if ($data) return $data;

        $model = new Currency();
        $data  = $model->where(['currency' => $moneyType])->value('rates');

        $redis->set($key, $data, $cache_time);
        return $data;
    }

    /**
     * 获取导入的excel 的数据
     * @author: Lamkakyun
     * @date: 2018-11-28 14:13:07
     */
    public function getImportExcelData()
    {
        vendor('PHPExcel.PHPExcel');

        $file = $_FILES['file'];
        if ($file['error']) return ['success' => false, 'msg' => '上传文件失败'];

        $excel     = \PHPExcel_IOFactory::load($file['tmp_name']);
        $sheet     = $excel->getSheet(0);
        $excelData = $sheet->toArray();
        $fileName  = $_FILES['file']['name'];

        return ['success' => true, 'msg' => 'bingo', 'data' => $excelData, 'extra' => ['filename' => $_FILES['file']['name']], 'fileName' => $fileName];
    }

    /**
     * 获取导入的csv的数据
     * @author: Lamkakyun
     * @date: 2018-11-28 14:13:07
     */
    public function getImportCSVData()
    {
        $file = $_FILES['file'];
        if ($file['error']) return ['success' => false, 'msg' => '上传文件失败'];

        if (!$fp = fopen($file['tmp_name'], 'r')) return ['success' => false, 'msg' => '上传文件失败(2)'];

        $csv_data = [];
        while ($line = fgetcsv($fp)) {
            // foreach ($line as $k => $v)
            // {
            //     // GB2312 是简体中文，GBK包含简体和繁体，所以应该用GBK
            //     $line[$k] = mb_convert_encoding($v, 'UTF-8', 'GBK');
            // }
            $csv_data[] = $line;
        }
        fclose($fp);
        $fileName = $_FILES['file']['name'];
        return ['success' => true, 'msg' => 'bingo', 'data' => $csv_data, 'extra' => ['filename' => $_FILES['file']['name']], 'fileName' => $fileName];
    }


    /**
     * 创建导入任务
     * @author lamkakyun
     * @date 2018-11-30 15:40:04
     * @return boolean
     */
    public function buildImportTask($task_name, $task_type, $request_params, $excel_data)
    {
        $task_model = new Task();
        $redis      = $this->getRedis()->handler();

        // 导入流水的任务队列(redis的队列就是 list)
        $redis_key     = "fms:task_queue:import_flow";
        $add_task_data = [
            'task_name'     => $task_name,
            'type'          => $task_type,
            'redis_key'     => $redis_key,
            'create_time'   => time(),
            'create_userid' => $request_params['auth_id'],
        ];

        $task_model->startTrans();
        $ret_add = $task_model->insert($add_task_data);

        if (!$ret_add) {
            $task_model->rollback();
            return false;
        }

        $task_id = $task_model->getLastInsID();

        $cache_data = [
            'task_id'        => $task_id,
            'request_params' => $request_params,
            'excel_data'     => $excel_data,
        ];

        // csv 的乱码数据，json_encode 会返回false
        $json_data = json_encode($cache_data);
        if (!$json_data) {
            $task_model->rollback();
            return false;
        }

        // lpush， rpop， 先入先出的模式
        $ret_add_redis = $redis->lpush($redis_key, $json_data);
        if (!$ret_add_redis) {
            $task_model->rollback();
            return false;
        }

        $task_model->commit();
        return $ret_add_redis;
    }

    /**
     * 导出excel 文件 (从ERP 系统复制过来的代码) （PHPEXCEL 官方说 自己过时了，要采用 phpspreads，但这个方法依然使用 PHPExcel）
     * @author: Lamkakyun
     * @date: 2018-06-12 08:43:05
     * @param array $headers
     * @param array $export_data
     * @param boolean $is_seq 是否打印序号
     * @desc 使用详情参考  application\count\controller\order\index.php  的 _index_export 方法
     */
    public function exportExcel($filename, $headers, $export_data, $is_seq = true)
    {
        if ($is_seq) {
            array_unshift($headers, '序号');
        }

        $header_keys   = array_keys($headers);
        $header_values = array_values($headers);

        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
            ->setLastModifiedBy("Maarten Balliauw")
            ->setTitle("Office 2007 XLSX Test Document")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
            ->setKeywords("office 2007 openxml php")
            ->setCategory("FILE");

        $title            = $filename;
        $export_file_name = $title . ".xls";

        $header_index = [];
        $char_list    = range('A', 'Z');

        // 建立足够多的 column index
        $char_more = ['A', 'B', 'C'];
        foreach ($char_more as $char) {
            $tmp_list = range('A', 'Z');
            foreach ($tmp_list as $value) {
                $char_list[] = $char . $value;
            }
        }
        foreach ($char_list as $value) {
            if (count($header_index) >= count($header_values)) break;
            $header_index[] = $value;
        }

        foreach ($header_index as $key => $value) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($value . '1', $header_values[$key]);
        }
        $i = 2;
        foreach ($export_data as $key => $value) {
            $tmp_keys         = $header_keys;
            $tmp_header_index = $header_index;
            if ($is_seq) {
                array_shift($tmp_keys);
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue(array_shift($tmp_header_index) . $i, $key + 1);
            }
            foreach ($tmp_header_index as $v) {
                $_key = array_shift($tmp_keys);
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($v . $i, $value[$_key]);
            }
            $i++;
        }
        $objPHPExcel->getActiveSheet()->setTitle($title);
        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename={$export_file_name}");
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Transfer-Encoding: binary");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }


    /**
     * 运行任务
     * @author lamkakyun
     * @date 2018-11-30 16:48:21
     * @return void
     */
    public function runImportTask()
    {
        $task_model = new Task();
        $redis      = $this->getRedis()->handler();

        // 导入流水的任务队列(redis的队列就是 list)
        $redis_key = "fms:task_queue:import_flow";

        $redis_data = $redis->rpop($redis_key);

        if (!$redis_data) return;
        $task_data = json_decode($redis_data, true);

        $task_id    = $task_data['task_id'];
        $params     = $task_data['request_params'];
        $excel_data = $task_data['excel_data'];

        $importObj = null;
        switch ($params['type']) {
            case '1':
                $importObj = new Payoneer();
                break;
            case '2':
                $importObj = new Paypal();
                break;
            case '3':
                $importObj = new Lianlian();
                break;
            case '4':
                $importObj = new OFX();
                break;
            case '5':
                $importObj = new Pingpong();
                break;
        }

        if (!$importObj) return json(['code' => -1, 'msg' => '暂不支持该第三方支付']);
        $data = $importObj->importFlow($params['account_id'], $excel_data);

        if ($data['code'] == 0) {
            $ret_update = $task_model->where(['id' => $task_id])->update(['status' => 1, 'msg' => $data['msg']]);
            var_dump(['id' => $task_id], 'ret update: ' . $ret_update);
        }


        // $ret = $redis->lpush($redis_key, $redis_data);
        // var_dump($data['msg']);
        var_dump($data['code']);
        // var_dump($task_id);
        // var_dump($data['code'] == 0);
        // var_dump($ret);
    }


    /**
     * 拉取paypal 的数据到 本地
     * step 1：获取api数据，更新到fms的数据库
     * step 2: 将api的数据标记为 已上传
     * @author lamkakyun
     * @date 2018-12-08 09:50:53
     * @return void
     */
    public function pullERPPayaplDetail()
    {
        echo "start\n";
        $fund_detail_model = new AccountFundDetail();
        $fund_model        = new AccountFund();
        $account_model     = new Account();

        $select_count = 100;
        // $api_url = 'http://admin.test.com/t.php?s=/Api/PaypalDetailUploadToFms/getPaypalDetailList';
        // $api_url2 = 'http://admin.test.com/t.php?s=/Api/PaypalDetailUploadToFms/markAsUploaded';
        $api_url  = Config::get('site.api_url');
        $api_url2 = Config::get('site.api_url2');

        if (!$api_url || !$api_url2) die("api 地址不存在，请配置\n");

        $where_paypal_account = ['type' => 2, 'type_attr' => '2'];
        $paypal_account_list  = $account_model->where($where_paypal_account)->field('id')->select();
        $paypal_account_ids   = array_column($paypal_account_list->toArray(), 'id');
        if (!$paypal_account_ids) die("fms 中 paypal 账户不存在\n");

        do {

            $paypal_detail_data = Http::post($api_url, ['pay_load' => Crypt::encrypt(json_encode(['limit' => $select_count]))]);
            if (!$paypal_detail_data) die('请求API失败');

            $paypal_detail_data  = json_decode($paypal_detail_data, true);
            $paypal_detail_total = $paypal_detail_data['data']['total'];
            $paypal_detail_list  = $paypal_detail_data['data']['list'];

            if ($paypal_detail_total == 0) die("all done!\n");

            $ids      = array_column($paypal_detail_list, 'id');
            $tid_list = array_column($paypal_detail_list, 'tid');
            $num_list = array_map(function ($v) {
                return 'PAYPAL_' . $v;
            }, $tid_list);

            $where_detail = ['number' => ['IN', $num_list]];
            $detail_list  = $fund_detail_model->field('id, number')->where($where_detail)->select();

            $exists_number = array_column($detail_list->toArray(), 'number');

            foreach ($paypal_detail_list as $v) {
                $paydetail_id = $v['id'];
                echo "处理流水【{$paydetail_id}】\n";

                $fund_model->startTrans();

                $hash_value = 'PAYPAL_' . $v['tid'];
                if (in_array($hash_value, $exists_number)) continue;

                $gross = abs($v['gross']);
                $fee   = abs($v['fee']);
                $net   = abs($v['net']);

                if ($v['gross'] > 0) // 入账
                {
                    $where_to_fund = ['account_id' => ['IN', $paypal_account_ids], 'account_currency' => $v['currency'], 'account_name' => $v['account']];

                    $to_fund          = $fund_model->where($where_to_fund)->lock(true)->find();
                    $where_to_account = ['id' => $to_fund['account_id']];
                    $to_account       = $account_model->where($where_to_account)->find();

                    if (!$to_fund || !$to_account) {
                        echo "收款账户【{$v['account']}】不存在\n";
                        $fund_model->rollback();
                        continue;
                    }

                    $has_from = false;
                    if ($v['mail']) {
                        $where_from_fund = ['account_id' => ['IN', $paypal_account_ids], 'account_currency' => $v['currency'], 'account_name' => $v['mail']];
                        $from_fund       = $fund_model->where($where_from_fund)->lock(true)->find();

                        if ($from_fund) {
                            $where_from_account = ['id' => $from_fund['account_id']];
                            $from_account       = $account_model->where($where_from_account)->find();
                            if ($from_account) {
                                $has_from = true;
                            }
                        }
                    }

                    $add_fund_detail = [
                        'number' => $hash_value,
                        'type'   => 0, // 0=对内转账 1=对外转账 2=提现 3=平账

                        'to_account_id'        => $to_fund['account_id'],
                        'to_account_funds_id'  => $to_fund['id'],
                        'to_account'           => $to_account['account'],
                        'to_account_type'      => $to_account['type'],
                        'to_account_type_attr' => $to_account['type_attr'],
                        'to_account_funds'     => $to_fund['account_funds'] ?? '0',

                        'account_currency' => $to_fund['account_currency'],
                        'amount'           => $net,
                        'confirm_amount'   => $net,
                        'fees'             => $fee,
                        'status'           => 1, // 状态 0=待确认 1=已确认

                        'createtime'    => $v['time'],
                        'createuser_id' => 0,
                        'createuser'    => 'system',
                        'confirmtime'   => $v['time'],
                        'remarks'       => "ERP API({$paydetail_id})",
                    ];


                    $ret_save_from_fund = true;
                    if ($has_from) {
                        $add_fund_detail['from_account_id']        = $from_account['id'];
                        $add_fund_detail['from_account_funds_id']  = $from_fund['id'];
                        $add_fund_detail['from_account']           = $from_account['account'];
                        $add_fund_detail['from_account_type']      = $from_account['type'];
                        $add_fund_detail['from_account_type_attr'] = $from_account['type_attr'];
                        $add_fund_detail['from_amount']            = $gross;
                        $add_fund_detail['from_currency']          = $v['currency'];
                        $add_fund_detail['from_account_funds']     = $from_fund['account_funds'];


                        $save_from_fund_data = ['account_funds' => ($from_fund['account_funds'] - $gross)];
                        $ret_save_from_fund  = $fund_model->where(['id' => $from_fund['id']])->update($save_from_fund_data);
                    }

                    $save_to_fund_data = ['account_funds' => $to_fund['account_funds'] + $net];
                    $ret_save_to_fund  = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);

                    $ret_add_fund_detail = false;
                    try {
                        $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                    } catch (\Exception $e) {
                        echo "添加流水详情失败\n";
                        $fund_model->rollback();
                        continue;
                    }

                    if (!$ret_add_fund_detail || !$ret_save_from_fund || !$ret_save_to_fund) {
                        echo "导入API流水失败\n";
                        $fund_model->rollback();
                        continue;
                    }

                    echo "导入API流水成功\n";
                    $fund_model->commit();
                } else // 支出
                {
                    $where_from_fund    = ['account_id' => ['IN', $paypal_account_ids], 'account_currency' => $v['currency'], 'account_name' => $v['account']];
                    $from_fund          = $fund_model->where($where_from_fund)->lock(true)->find();
                    $where_from_account = ['id' => $from_fund['account_id']];
                    $from_account       = $account_model->where($where_from_account)->find();

                    if (!$from_fund || !$from_account) {
                        echo "付款账户【{$v['account']}】不存在\n";
                        $fund_model->rollback();
                        continue;
                    }

                    $has_to = false;
                    if ($v['mail']) {
                        $where_to_fund = ['account_id' => ['IN', $paypal_account_ids], 'account_currency' => $v['currency'], 'account_name' => $v['mail']];
                        $to_fund       = $fund_model->where($where_to_fund)->lock(true)->find();

                        if ($to_fund) {
                            $where_to_account = ['id' => $to_fund['account_id']];
                            $to_account       = $account_model->where($where_to_account)->find();
                            if ($to_account) {
                                $has_to = true;
                            }
                        }
                    }

                    $add_fund_detail = [
                        'number' => $hash_value,
                        'type'   => 0, // 0=对内转账 1=对外转账 2=提现 3=平账

                        'from_account_id'        => $from_fund['account_id'],
                        'from_account_funds_id'  => $from_fund['id'],
                        'from_account'           => $from_account['account'],
                        'from_account_type'      => $from_account['type'],
                        'from_account_type_attr' => $from_account['type_attr'],
                        'from_account_funds'     => $from_fund['account_funds'] ?? '0',
                        'from_amount'            => $gross,
                        'from_currency'          => $v['currency'],
                        'account_currency'       => $v['currency'],

                        'amount'         => $net,
                        'confirm_amount' => $net,
                        'fees'           => $fee,
                        'status'         => 1, // 状态 0=待确认 1=已确认

                        'createtime'    => $v['time'],
                        'createuser_id' => 0,
                        'createuser'    => 'system',
                        'confirmtime'   => $v['time'],
                        'remarks'       => "ERP API({$paydetail_id})",
                    ];

                    $ret_save_to_fund = true;
                    if ($has_to) {
                        $add_fund_detail['to_account_id']        = $to_account['id'];
                        $add_fund_detail['to_account_funds_id']  = $to_fund['id'];
                        $add_fund_detail['to_account']           = $to_account['account'];
                        $add_fund_detail['to_account_type']      = $to_account['type'];
                        $add_fund_detail['to_account_type_attr'] = $to_account['type_attr'];
                        $add_fund_detail['to_account_funds']     = $to_fund['account_funds'];

                        $save_to_fund_data = ['account_funds' => ($to_fund['account_funds'] - $gross)];
                        $ret_save_to_fund  = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);
                    }

                    $save_from_fund_data = ['account_funds' => $from_fund['account_funds'] + $net];
                    $ret_save_from_fund  = $fund_model->where(['id' => $from_fund['id']])->update($save_from_fund_data);

                    $ret_add_fund_detail = false;
                    try {
                        $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                    } catch (\Exception $e) {
                        echo "添加流水详情失败\n";
                        $fund_model->rollback();
                        continue;
                    }
                }

                if (!$ret_add_fund_detail || !$ret_save_from_fund || !$ret_save_to_fund) {
                    echo "导入API流水失败\n";
                    $fund_model->rollback();
                    continue;
                }

                echo "导入API流水成功\n";
                $fund_model->commit();
            }

            $ret_update_upload = Http::post($api_url2, ['pay_load' => Crypt::encrypt(json_encode(['ids' => $ids]))]);

        } while ($paypal_detail_total > $select_count);

        echo "bingo\n";
    }


    /**
     * 拉取 payonner 的数据到 本地
     * step 1：获取api数据，更新到fms的数据库
     * step 2: 将api的数据标记为 已上传
     * @author lamkakyun
     * @date 2019-01-16 16:41:53
     * @return void
     */
    public function pullERPPayonnerDetail()
    {
        echo "start\n";

        $fund_detail_model = new AccountFundDetail();
        $fund_model        = new AccountFund();
        $account_model     = new Account();

        $select_count = 100;

        // $api_url = 'http://admin.test.com/t.php?s=/Api/Payonner/getPayonnerDetailList';
        // $api_url2 = 'http://admin.test.com/t.php?s=/Api/Payonner/markAsUploaded';
        $api_url  = Config::get('site.api_erp_payonner_url1');
        $api_url2 = Config::get('site.api_erp_payonner_url2');

        if (!$api_url || !$api_url2) die("api 地址不存在，请配置\n");

        $where_payonner_account = ['type' => 2, 'type_attr' => '1'];
        $payonner_account_list  = $account_model->where($where_payonner_account)->field('id')->select();
        $payonner_account_ids   = array_column($payonner_account_list->toArray(), 'id');
        if (!$payonner_account_ids) die("fms 中 payonner 账户不存在\n");

        do {
            $payonner_detail_data = Http::post($api_url, ['pay_load' => Crypt::encrypt(json_encode(['limit' => $select_count]))]);
            if (!$payonner_detail_data) die('请求API失败');

            $payonner_detail_data  = json_decode($payonner_detail_data, true);
            $payonner_detail_total = $payonner_detail_data['data']['total'];
            $payonner_detail_list  = $payonner_detail_data['data']['list'];

            if ($payonner_detail_total == 0) die("all done!\n");

            $ids      = array_column($payonner_detail_list, 'id');
            $num_list = array_map(function ($v) {
                return 'PAYONNER_' . $v;
            }, $ids);

            $where_detail  = ['number' => ['IN', $num_list]];
            $detail_list   = $fund_detail_model->field('id, number')->where($where_detail)->select();
            $exists_number = array_column($detail_list->toArray(), 'number');

            $successIds = [];
            foreach ($payonner_detail_list as $v) {
                $paydetail_id = $v['id'];
                echo "处理流水【{$paydetail_id}】\n";

                $hash_value = 'PAYONNER_' . $v['id'];
                if (in_array($hash_value, $exists_number)) continue;

                $abs_amount   = abs($v['amount']);
                $abs_fee      = abs($v['d_transfer_amount'] ?: 0) + abs($v['d_fx_fee'] ?: 0);
                $currency1    = $v['amount_currency'];
                $account_name = $v['email']; // 邮箱就是账户名称

                if ($v['amount'] > 0) // 入账
                {
                    $fund_model->startTrans();
                    $where_to_fund = ['account_id' => ['IN', $payonner_account_ids], 'account_currency' => $currency1, 'account_name' => $account_name];

                    $to_fund          = $fund_model->where($where_to_fund)->lock(true)->find();
                    $where_to_account = ['id' => $to_fund['account_id']];
                    $to_account       = $account_model->where($where_to_account)->find();

                    if (!$to_fund || !$to_account) {
                        echo "收款账户【{$v['email']}】不存在\n";
                        $fund_model->rollback();
                        continue;
                    }


                    $add_fund_detail = [
                        'number' => $hash_value,
                        'type'   => 0, // 0=对内转账 1=对外转账 2=提现 3=平账

                        'to_account_id'        => $to_fund['account_id'],
                        'to_account_funds_id'  => $to_fund['id'],
                        'to_account'           => $to_account['account'],
                        'to_account_type'      => $to_account['type'],
                        'to_account_type_attr' => $to_account['type_attr'],
                        'to_account_funds'     => $to_fund['account_funds'] ?? '0',

                        'account_currency' => $to_fund['account_currency'],
                        'amount'           => $abs_amount,
                        'confirm_amount'   => $abs_amount,
                        'fees'             => $abs_fee,
                        'status'           => 1, // 状态 0=待确认 1=已确认

                        'createtime'    => $v['date_format'],
                        'createuser_id' => 0,
                        'createuser'    => 'system',
                        'confirmtime'   => $v['date_format'],
                        'remarks'       => "ERP PAYONNER API({$paydetail_id})",
                    ];

                    $save_to_fund_data = ['account_funds' => $to_fund['account_funds'] + $abs_amount];
                    $ret_save_to_fund  = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);

                    $ret_add_fund_detail = false;
                    try {
                        $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                    } catch (\Exception $e) {
                        echo "添加流水详情失败\n";
                        $fund_model->rollback();
                        continue;

                    }
                    if (!$ret_add_fund_detail || !$ret_save_to_fund) {
                        echo "导入API流水失败\n";
                        $fund_model->rollback();
                        continue;
                    }

                    $successIds[] = $paydetail_id;
                    echo "导入API流水成功 入账\n";
                    $fund_model->commit();
                } else // 支出
                {
                    $fund_model->startTrans();
                    $where_from_fund    = ['account_id' => ['IN', $payonner_account_ids], 'account_currency' => $currency1, 'account_name' => $account_name];
                    $from_fund          = $fund_model->where($where_from_fund)->lock(true)->find();
                    $where_from_account = ['id' => $from_fund['account_id']];
                    $from_account       = $account_model->where($where_from_account)->find();

                    if (!$from_fund || !$from_account) {
                        echo "付款账户【{$v['email']}】不存在\n";
                        $fund_model->rollback();
                        continue;
                    }

                    $add_fund_detail = [
                        'number' => $hash_value,
                        'type'   => 0, // 0=对内转账 1=对外转账 2=提现 3=平账

                        'from_account_id'        => $from_fund['account_id'],
                        'from_account_funds_id'  => $from_fund['id'],
                        'from_account'           => $from_account['account'],
                        'from_account_type'      => $from_account['type'],
                        'from_account_type_attr' => $from_account['type_attr'],
                        'from_account_funds'     => $from_fund['account_funds'] ?? '0',
                        'from_amount'            => $abs_amount,
                        'from_currency'          => $from_fund['account_currency'],
                        'account_currency'       => $from_fund['account_currency'],

                        'amount'         => $abs_amount,
                        'confirm_amount' => $abs_amount,
                        'fees'           => $abs_fee,
                        'status'         => 1, // 状态 0=待确认 1=已确认

                        'createtime'    => $v['date_format'],
                        'createuser_id' => 0,
                        'createuser'    => 'system',
                        'confirmtime'   => $v['date_format'],
                        'remarks'       => "ERP PAYONNER API({$paydetail_id})",
                    ];

                    $save_from_fund_data = ['account_funds' => $from_fund['account_funds'] + $abs_amount];
                    $ret_save_from_fund  = $fund_model->where(['id' => $from_fund['id']])->update($save_from_fund_data);

                    $ret_add_fund_detail = false;
                    try {
                        $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                    } catch (\Exception $e) {
                        echo "添加流水详情失败\n";
                        $fund_model->rollback();
                        continue;
                    }

                    if (!$ret_add_fund_detail || !$ret_save_from_fund) {
                        echo "导入API流水失败\n";
                        $fund_model->rollback();
                        continue;
                    }
                    $successIds[] = $paydetail_id;
                    echo "导入API流水成功 支出\n";
                    $fund_model->commit();
                }

            }

            $ret_update_upload = Http::post($api_url2, ['pay_load' => Crypt::encrypt(json_encode(['ids' => $successIds]))]);

        } while ($payonner_detail_total > $select_count);

        echo "bingo\n";
    }


    /**
     * 同步 payonner 账号信息
     * @author lamkakyun
     * @date 2019-01-21 18:04:56
     * @return void
     */
    public function syncPayonnerAccounts()
    {
        $api_url = Config::get('site.api_erp_payonner_url3');
        if (!$api_url) die("api 地址不存在，请配置\n");

        $fund_model        = new AccountFund();
        $account_model     = new Account();
        $fund_diff_model   = new AccountFundDiff();
        $fund_detail_model = new AccountFundDetail();

        $where_payonner_account = ['type' => 2, 'type_attr' => '1'];
        $payonner_account_list  = $account_model->where($where_payonner_account)->field('id')->select();
        $payonner_account_ids   = array_column($payonner_account_list->toArray(), 'id');
        if (!$payonner_account_ids) die("fms 中 payonner 账户不存在\n");

        $erp_payonner_account_data = Http::post($api_url, ['pay_load' => Crypt::encrypt(json_encode(['is_fms' => '1']))]);

        if (!$erp_payonner_account_data) die("请求API失败");

        $erp_payonner_account_data  = json_decode($erp_payonner_account_data, true);
        $erp_payonner_account_total = $erp_payonner_account_data['data']['total'];
        $erp_payonner_account_list  = $erp_payonner_account_data['data']['list'];

        foreach ($erp_payonner_account_list as $value) {
            $where_account    = ['account' => $value['email'], 'type' => '2', 'type_attr' => '1'];
            $fms_account_info = $account_model->where($where_account)->find();
            if (!$fms_account_info) {
                echo "账户" . sprintf('%25s', $value['email']) . "  不存在\n";
                continue;
            }

            if (!$value['balance_list']) continue;
            $balance_list = json_decode($value['balance_list'], true);
            $balance_list = $balance_list['result']['items'];

            foreach ($balance_list as $v) {
                // TODO 开启事务
                $fund_model->startTrans();

                $where_fund    = ['account_id' => ['IN', $payonner_account_ids], 'fund_name' => $v['id'], 'account_name' => $value['email']];
                $fms_fund_info = $fund_model->where($where_fund)->lock(true)->find();
                if (!$fms_fund_info) {
                    $where_fund    = ['account_id' => ['IN', $payonner_account_ids], 'account_currency' => $v['currency'], 'account_name' => $value['email']];
                    $fms_fund_info = $fund_model->where($where_fund)->lock(true)->find();
                    if (!$fms_fund_info) {
                        // 如果没有找到余额账户，是否应该添加新账户？？暂时不添加
                        echo "余额账户 " . sprintf("%s - %s", $value['email'], $v['currency']) . "  不存在\n";
                        $fund_model->rollback();
                        continue;
                    }
                }

                if ($v['id'] == $fms_fund_info['fund_name'] && $v['available_balance'] == $fms_fund_info['account_funds']) {
                    echo "余额信息不变 {$value['email']}-{$v['currency']}\n";
                    $fund_model->rollback();
                    continue;
                }

                $save_fund_data = [
                    'fund_name'     => $v['id'],
                    'account_funds' => $v['available_balance'],
                    'updatetime'    => time(),
                ];

                $ret_save_fund = $fund_model->where(['id' => $fms_fund_info['id']])->update($save_fund_data);
                $ret_save_fund = $ret_save_fund === false ? false : true;

                $ret_add_fund_detail = true;
                $ret_add_diff        = true;
                // 添加流水详情和平帐日志
                if ($v['available_balance'] != $fms_fund_info['account_funds']) {
                    $flow_num = FundLib::getInstance()->genFlowNumber();

                    $_diff_amount = $v['available_balance'] - $fms_fund_info['account_funds'];

                    $add_fund_detail_data = [
                        'number'                 => $flow_num,
                        'type'                   => '3', // 0=对内转账 1=对外转账 2=提现 3=平账
                        'from_account_id'        => $fms_account_info['id'],
                        'from_account_funds_id'  => $fms_fund_info['id'],
                        'from_account'           => $fms_account_info['account'],
                        'from_currency'          => $fms_fund_info['account_currency'],
                        'from_account_funds'     => $fms_fund_info['account_funds'],
                        'from_account_type'      => $fms_account_info['type'],
                        'from_account_type_attr' => $fms_account_info['type_attr'],
                        'to_account_id'          => $fms_account_info['id'],
                        'to_account'             => $fms_account_info['account'],
                        'to_account_funds_id'    => $fms_fund_info['id'],
                        // 'to_username' => '', // 平账没有这个字段
                        'to_account_type'        => $fms_account_info['type'],
                        'to_account_type_attr'   => $fms_account_info['type_attr'],
                        'account_currency'       => $fms_fund_info['account_currency'],
                        'amount'                 => $_diff_amount,
                        'fees'                   => 0,
                        'confirm_amount'         => $_diff_amount, // 确认交易金额 在 确认操作时填写
                        'status'                 => 1, // 状态 0=待确认 1=已确认
                        'createuser_id'          => 0,
                        'createuser'             => 'system',
                        'createtime'             => time(),
                        'remarks'                => '账户同步,余额账户同步',
                        'confirmuser'            => 'system',
                        'confirmuser_id'         => 0,
                        'confirmtime'            => time(),
                    ];

                    $add_diff_data = [
                        'number'           => $flow_num,
                        'account_id'       => $fms_fund_info['account_id'],
                        'account_funds_id' => $fms_fund_info['id'],
                        'currency'         => $fms_fund_info['account_currency'],
                        'amount'           => $v['available_balance'],
                        'confim_amount'    => $v['available_balance'],
                        'diff_amount'      => $_diff_amount,
                        'createuser'       => 'system',
                        'createuser_id'    => 0,
                        'createtime'       => time(),
                        'remarks'          => '账户同步,余额账户同步',
                    ];

                    $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail_data);
                    $ret_add_diff        = $fund_diff_model->insert($add_diff_data);
                }

                if (!$ret_save_fund || !$ret_add_fund_detail || !$ret_add_diff) {
                    echo "更新余额账户信息失败\n";
                    $fund_model->rollback();
                }

                echo "更新余额成功{$value['email']}-{$v['currency']}\n";
                $fund_model->commit();
            }
        }
    }
}
