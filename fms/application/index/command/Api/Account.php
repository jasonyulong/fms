<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\command\Api;

use app\common\model\CompanyAccount;
use app\common\model\CompanyAccountReceipt;
use app\common\model\Currency;
use fast\Http;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Account
{
    /**
     * 导入账号数据
     * @param Input $input
     * @param Output $output
     */
    public function import(Input $input, Output $output)
    {
        $options = $input->getOptions();

        $platform = $options['platform'];
        // 获取平台账户信息
        $url = '/Api/Account/getAccountInfo';

        $platforms = !empty($platform) ? [$platform] : Config::get('site.platforms');

        foreach ($platforms as $platform) {
            $params = [
                'platform' => $platform,
                //'time'     => time(),
            ];
            ksort($params);
            // 生成签名
            $params['sign'] = strtolower(md5($this->httpBuildParams($params) . '&' . API_KEY));

            $output->writeln(sprintf('Api Key:%s', API_KEY));
            $output->writeln(sprintf('request params: %s', json_encode($params)));
            $output->writeln(sprintf('request url: %s', Config::get('app.api_url') . $url));

            $request = Http::get(Config::get('app.api_url') . $url, $params);
            $request = !empty($request) ? json_decode($request, false) : [];
            if (empty($request) || empty($request->data)) {
                $output->writeln("请求发回数据为空");
                continue;
            }
            $this->saveResponse($request->data);
            $output->writeln($platform . " success");
        }
    }

    /**
     * 汇率获取
     * @param Input $input
     * @param Output $output
     */
    public function currencys(Input $input, Output $output)
    {
        $options = $input->getOptions();
        // 获取汇率信息
        $url = '/Api/Account/currencys';

        $params = [];
        ksort($params);
        // 生成签名
        $params['sign'] = strtolower(md5($this->httpBuildParams($params) . '&' . API_KEY));

        $output->writeln(sprintf('Api Key:%s', API_KEY));
        $output->writeln(sprintf('request params: %s', json_encode($params)));
        $output->writeln(sprintf('request url: %s', Config::get('app.api_url') . $url));

        $request = Http::get(Config::get('app.api_url') . $url, $params);
        $output->writeln($request);

        $request = !empty($request) ? json_decode($request, false) : [];

        if (empty($request) || empty($request->data)) {
            $output->writeln("请求发回数据为空");
            return;
        }
        $this->saveCurrencysResponse($request->data);
        $output->writeln(" success");
    }

    /**
     * 保存数据
     * @param $response
     * @return bool
     */
    private function saveResponse($response)
    {
        $model        = new CompanyAccount();
        $modelReceipt = new CompanyAccountReceipt();

        foreach ($response as $value) {
            $saveData  = [
                'id'                  => $value->id,
                'account'             => $value->account,
                'platform'            => $value->platform,
                'platform_account_id' => $value->platform_account_id,
                'company_id'          => $value->company_id,
                'storeid'             => $value->storeid ?? 0,
                'rent'                => $value->rent,
                'rent_currency'       => $value->rent_currency,
                'site'                => $value->site,
                'has_account'         => !empty($value->receipt_serialize) ? 1 : 0,
                'status'              => $value->status,
                'createtime'          => strtotime($value->createtime),
                'createuser'          => $value->createuser,
            ];
            $accountId = $model->where(['id' => $value->id])->column('id');
            if (empty($accountId)) {
                $accountId = $model->insert($saveData);
            } else {
                $accountId = $accountId[0];
                $model->save($saveData, ['id' => $accountId]);
            }
            if (empty($value->receipt_serialize)) continue;

            $receipt_id = [];
            foreach ($value->receipt_serialize as $account) {
                $receipt_id[]     = $account->receipt_id;
                $accountSaveData  = [
                    'platform'         => $account->platform,
                    'account_id'       => $account->account_id,
                    'account_funds_id' => $account->account_funds_id,
                    'receipt_type'     => $account->receipt_type,
                    'receipt_id'       => $account->receipt_id,
                    'receipt_currency' => $account->receipt_currency,
                    'updatetime'       => time(),
                ];
                $receiptAccountId = $modelReceipt->where(['account_id' => $account->account_id])->column('id');
                if (empty($receiptAccountId)) {
                    $receiptAccountId = $modelReceipt->insert($accountSaveData);
                } else {
                    $modelReceipt->save($accountSaveData, ['account_id' => $account->account_id]);
                }
            }
            // 清除非当前绑定关系的其它账户
            $modelReceipt->where(['account_id' => $account->account_id])->whereNotIn('receipt_id', $receipt_id)->delete();

            echo $value->account . " success\n";
        }

        return true;
    }

    /**
     * 保存汇率
     * @param $response
     * @return bool
     * @throws \think\exception\DbException
     */
    private function saveCurrencysResponse($response)
    {
        foreach ($response as $val) {
            $saveData = [
                'id'         => $val->id,
                'currency'   => $val->currency,
                'rates'      => $val->rates,
                'country'    => $val->country,
                'updatetime' => time(),
            ];

            $has = Currency::get(['id' => $val->id]);
            if (!empty($has)) {
                Currency::update($saveData, ['id' => $val->id]);
            } else {
                Currency::create($saveData);
            }
        }
        return true;
    }

    /**
     * 根据参数为签名做好准备
     * @param string $default
     * @return string
     */
    private function httpBuildParams($params, $default = '')
    {
        if (empty($params)) {
            return $default;
        }

        foreach ($params as $key => $val) {
            $default .= $key . '=' . $val . '&';
        }
        return rtrim($default, "&");
    }
}