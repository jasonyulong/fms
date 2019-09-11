<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\command\Api;

use app\common\model\AccountAdmin;
use app\common\model\CompanyAccount;
use app\common\model\CompanyAccountReceipt;
use fast\Http;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class AccountFunds
{
    /**
     * 导入账号资金数据
     * @param Input $input
     * @param Output $output
     */
    public function import(Input $input, Output $output)
    {
        $options = $input->getOptions();

        $accountModel = new \app\common\model\Account();
        $accountFunds = new \app\common\model\AccountFund();
        $model        = new \app\common\model\CompanyAccountReceipt();

        $accounts = $model->alias('receipt')->join('CompanyAccount account', 'account.id=receipt.account_id')->where(['account.status' => 1])->select();
        if (empty($accounts)) {
            return $output->info('未找到账号数据');
        }
        $platforms = array_flip(Config::get('site.platforms'));

        foreach ($accounts as $account) {
            if ($account->platform == 'joom') continue;
            if (!isset($platforms[$account->platform])) continue;
            $saveData  = [
                'company_id'   => $account->company_id,
                'title'        => $account->account,
                'account'      => $account->account,
                'account_mark' => $account->site,
                'type'         => 1,
                'type_attr'    => $platforms[$account->platform],
                'type_scene'   => 2,
                'status'       => 1,
                'createuser'   => 'system',
                'createtime'   => time(),
                'uptime'       => time(),
            ];
            $accountId = $accountModel->where(['type' => 1, 'account' => $account->account, 'type_scene' => 2, 'type_attr' => $saveData['type_attr']])->column('id');
            if (!empty($accountId)) {
                continue;
            }
            $accountModel->insert($saveData);
            $accountId = $accountModel->getLastInsID();
            // 添加管理员
            $accountAdminId = AccountAdmin::get(['account_id' => $accountId]);
            if (empty($accountAdminId)) {
                AccountAdmin::insert(['account_id' => $accountId, 'admin_id' => 36, 'admin_name' => '王庆', 'updatetime' => time()]);
            }

            $rent_currency = [$account->rent_currency];
            if ($account->platform == 'aliexpress') {
                $rent_currency = [$account->rent_currency, 'CNY'];
            }
            foreach ($rent_currency as $currency) {
                $accountSaveData = [
                    'account_id'   => $accountId,
                    'account_name' => $account->account,
                    'fund_name'    => $account->platform_account_id,
                    'updatetime'   => time(),
                ];
                $accountFundsId  = $accountFunds->where(['account_id' => $accountId, 'account_name' => $account->account, 'account_currency' => $currency])->column('id');
                if (empty($accountFundsId)) {
                    $accountFunds->insert($accountSaveData);
                } else {
                    $accountFundsId = $accountFundsId[0];
                    $accountFunds->update($accountSaveData, ['id' => $accountFundsId]);
                }
            }
            echo $account->account . " success\n";
        }
        $output->writeln("success");
    }

}