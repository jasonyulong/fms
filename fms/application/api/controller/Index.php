<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\api\controller;

use fast\Crypt;
use think\Config;
use think\Cookie;
use app\common\model\Account;
use app\common\model\Company;
use app\index\library\FundLib;
use app\common\library\FilterLib;
use app\common\model\AccountFund;
use app\index\library\AccountLib;
use app\common\controller\ApiController;

/**
 * 首页接口
 * Class Index
 * @package app\api\controller
 */
class Index extends ApiController
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     */
    public function index()
    {
        $this->success('Success', ['version' => '1.0', 'name' => 'fms']);
    }

    /**
     * 收款账户列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function accounts()
    {
        // 收款账号类型
        $thirdAccountType = Config::get('site.third_pay_account_type');
        // 账号支持的币种
        $thirdAccountCurrency = ['USD', 'GBP', 'CAD', 'AUD', 'VND', 'EUR', 'CNY', 'JPY', 'HKD', 'SGD', 'AED'];
        sort($thirdAccountCurrency);
        // 收款账号列表
        $model        = new Account();
        $accounts     = $model->where(['type' => 2, 'type_scene' => ['IN', [1, 2, 3]], 'status' => 1])
            ->whereOr(['id' => ['in', [330]]])
            ->field('id,account,title')
            ->select();
        $accountsData = [];
        if (!empty($accounts)) {
            foreach ($accounts as $account) {
                $acc = $account->toArray();

                $acc['funds']   = $account->minifund->toArray();
                $accountsData[] = $acc;
            }
        }
        // 公司列表
        $companys = Company::column('id,company_name');

        $this->success('Success', [
            'type'     => $thirdAccountType,
            'currency' => $thirdAccountCurrency,
            'accounts' => $accountsData,
            'companys' => $companys,
        ]);
    }


    /**
     * 付款账户
     * @author lamkakyun
     * @date 2018-11-26 13:59:23
     * @return void
     */
    public function payAccounts()
    {
        return AccountLib::getInstance()->getPayFundBankAccount();
    }


    /**
     * 对接 ERP 的转账操作
     * @author lamkakyun
     * @date 2018-11-26 10:30:20
     * @return void
     */
    public function erpTransfer()
    {
        $params = input('post.', '', 'trim');
        if (!isset($params['pay_load'])) {
            return json(['code' => -1, 'msg' => '参数错误']);
        }
        $params = json_decode(Crypt::decrypt($params['pay_load']), true);

        if (!FilterLib::isNum($params, 'fund_id')) return json(['code' => -1, 'msg' => 'fund_id参数错误']);
        if ($params['fund_id'] <= 0) {
            return json(['code' => -1, 'msg' => 'fund_id必须大于0']);
        }

        if (!FilterLib::isPrice($params, 'amount')) return json(['code' => -1, 'msg' => '交易金额不正确']);
        if ($params['amount'] <= 0) return json(['code' => -1, 'msg' => '交易金额不正确']);
        if (!FilterLib::isNotEmptyStr($params, 'remarks')) return json(['code' => -1, 'msg' => '转账备注不能留空']);

        return FundLib::getInstance()->erpTransfer($params);
    }


    /**
     * 平账接口
     * @author lamkakyun
     * @date 2018-12-04 11:37:47
     * @return void
     */
    public function fixBalance()
    {
        $params = input('post.', '', 'trim');
        if (!isset($params['pay_load'])) {
            return json(['code' => -1, 'msg' => '参数错误']);
        }
        $params = json_decode(Crypt::decrypt($params['pay_load']), true);

        $fund_model = new AccountFund();

        if (!FilterLib::isNotEmptyArr($params, 'account_name')) return json(['code' => -1, 'msg' => '参数错误']);
        if (!FilterLib::isNotEmptyArr($params, 'account_currency')) return json(['code' => -1, 'msg' => '参数错误']);
        if (!FilterLib::isNotEmptyArr($params, 'true_balance')) return json(['code' => -1, 'msg' => '参数错误']);
        if (!FilterLib::isNotEmptyStr($params, 'operator')) return json(['code' => -1, 'msg' => '操作人不能为空']);

        $fund_ids = [];
        foreach ($params['account_name'] as $k => $v) {
            $_currency   = $params['account_currency'][$k];
            $_where_fund = ['account_name' => $v, 'account_currency' => $_currency];
            $_fund_id    = $fund_model->where($_where_fund)->value('id');

            if (!$_fund_id) return json(['code' => -1, 'msg' => '余额账户不存在']);

            $fund_ids[] = $_fund_id;
        }

        $params['funds_ids'] = $fund_ids;

        $params['auth_id']       = 0;
        $params['fix_reason']    = 'ERP同步平账';
        $params['auth_username'] = $params['operator'];
        return FundLib::getInstance()->fixBalance($params);
    }
}
