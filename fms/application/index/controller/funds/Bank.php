<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\funds;

use think\Config;
use app\index\library\FundLib;
use app\common\library\ToolsLib;
use app\common\library\FilterLib;
use app\common\model\AccountFund;
use app\index\library\AccountLib;
use app\index\library\CompanyLib;
use app\common\controller\AuthController;
use think\View;

/**
 * 转账卡账户
 * Class index
 * @package app\index\controller\funds
 */
class Bank extends AuthController
{
    /**
     * 查看
     * @access auth
     * @return string
     * @throws \ReflectionException
     */
    public function index()
    {
        $fund_model = new AccountFund();

        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $params['type'] = 3;
        $params['ps']   = $params['ps'] ?? 20;
        if (isset($params['is_export']) && $params['is_export'] == 1) $params['ps'] = 100000;

        $type_scene             = ToolsLib::getInstance()->getTypeScene();
        $third_pay_account_type = ToolsLib::getInstance()->getThirdPayType();
        $data                   = FundLib::getInstance()->getAccountBalanceList($params);

        // TODO: 导出 余额 excel
        if (isset($params['is_export']) && $params['is_export'] == 1) {
            foreach ($data['list']['data'] as $k => $v) {
                $data['list']['data'][$k]['fund_name']  = "【{$v['account'] }】{$v['fund_name']}";
                $data['list']['data'][$k]['type_scene'] = $type_scene[$v['type_scene']];
                $data['list']['data'][$k]['platform']   = $third_pay_account_type[$v['platform']] ?? $v['bank_name'];
            }

            $headers = [
                'title'            => '账户名称',
                'fund_name'        => '账户',
                'platform'         => '账户类型',
                'type_scene'       => '使用类型',
                'account_currency' => '账户币种',
                'account_funds'    => '账户余额',
            ];

            $file_name = '转账卡账户(余额)-' . date('Ymd');
            ToolsLib::getInstance()->exportExcel($file_name, $headers, $data['list']['data']);
        }

        $this->assign('all_company', CompanyLib::getInstance()->getAllCompany());
        $this->assign('all_currency_type', ToolsLib::getInstance()->getAllCurrencyType());
        $this->assign('third_pay_account_type', $third_pay_account_type);
        $this->assign('admin_list', AccountLib::getInstance()->getAllAccountAdmin());
        $this->assign('type_scene', $type_scene);
        $this->assign('third_type_attr', ToolsLib::getInstance()->getThirdPayType());
        $this->assign('list_total', $data['count']);
        $this->assign('list', $data['list']['data']);
        $this->assign('page', $data['page']);
        $this->assign('params', $params);

        return parent::fetchAuto();
    }


    /**
     * 流水明细
     * @access auth
     * @author lamkakyun
     * @date 2018-11-20 13:38:18
     * @return void
     */
    public function flowDetail()
    {
        $fund_model   = new AccountFund();
        $params       = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $params['p']  = $params['p'] ?? 1;
        $params['ps'] = $params['ps'] ?? 20;

        if (!isset($params['fund_id']) && !isset($params['account_id'])) {
            $this->error("请求参数有误,请返回首页重新进入", url('/'));
        }

        $data = FundLib::getInstance()->getFlowList($params);

        if (isset($params['fund_id'])) {
            $fund_info = $fund_model->where(['id' => $params['fund_id']])->find();
            $this->assign('account_id', $fund_info['account_id']);
        }
        if (isset($params['account_id'])) {
            $this->assign('account_id', $params['account_id']);
        }

        foreach ($data['list']['data'] as $k => $v) {
            $money_type = '';
            if (isset($fund_info)) {
                if ($fund_info['id'] == $v['from_account_funds_id']) $money_type = '支出';
                elseif ($fund_info['id'] == $v['to_account_funds_id']) $money_type = '收入';
            } elseif (isset($params['account_id'])) {
                if ($params['account_id'] == $v['from_account_id']) $money_type = '支出';
                elseif ($params['account_id'] == $v['to_account_id']) $money_type = '收入';
            }
            if ($money_type == '支出') {
                if (empty($v['from_currency'])) $data['list']['data'][$k]['from_currency'] = $v['account_currency'];
                if ($v['from_amount'] == 0) $data['list']['data'][$k]['from_amount'] = $v['amount'];
            }
            $data['list']['data'][$k]['money_type'] = $money_type;
        }

        $this->assign('list_total', $data['count']);
        $this->assign('list', $data['list']['data']);
        $this->assign('page', $data['page']);
        $this->assign('params', $params);
        $this->assign('ruletitle', '流水明细');
        $this->assign('fund_type', ToolsLib::getInstance()->getFundType());

        return parent::fetchAuto();
    }


    /**
     * 转账卡收支表 (转账卡 = 第三方 + 银行卡)
     * @access auth
     * @author lamkakyun
     * @date 2019-02-13 10:46:47
     * @return void
     */
    public function inout()
    {
        $fund_model = new AccountFund();

        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $params['p']    = $params['p'] ?? 1;
        $params['ps']   = $params['ps'] ?? 20;
        $params['type'] = 3; // 银行卡类型
        if (!isset($params['start_time']) || empty($params['start_time'])) $params['start_time'] = date('Y-m-01');
        if (!isset($params['end_time']) || empty($params['end_time'])) $params['end_time'] = date('Y-m-d');

        // 获取所有的银行卡账户
        $all_bank_accounts = AccountLib::getInstance()->getBankAccounts();

        // 第三方账户类型
        $third_pay_account_type = ToolsLib::getInstance()->getThirdPayType();

        // 所有银行类型
        $all_banks      = ToolsLib::getInstance()->getAllBanks();
        $all_bank_names = array_column($all_banks, 'bank_name');

        // 第三方账户类型 + 银行类型
        $all_types = $third_pay_account_type + $all_bank_names;

        // 获取账户 和 余额战虎
        $data = FundLib::getInstance()->getAccountBalanceList($params);


        $all_fund_ids = array_column($data['list']['data'], 'f_id');

        $income_data = FundLib::getInstance()->getFundsIncomeAndExpend($all_fund_ids, $params['start_time'], $params['end_time'], 'income');

        $expend_data = FundLib::getInstance()->getFundsIncomeAndExpend($all_fund_ids, $params['start_time'], $params['end_time'], 'expend');


        // 获取 余额账户的 收入、支出 和最新余额 
        $total_income  = 0.00;
        $total_expend  = 0.00;
        $total_balance = 0.00;
        foreach ($data['list']['data'] as $key => $fund_info) {
            $fund_id = $fund_info['f_id'];
            $income  = $income_data[$fund_id]['sum_amount'] ?? '0.00';
            $expend  = $expend_data[$fund_id]['sum_amount'] ?? '0.00';
            $balance = FundLib::getInstance()->getFundBalance($fund_id, $params['start_time'], $params['end_time']);

            $data['list']['data'][$key]['income']  = $income;
            $data['list']['data'][$key]['expend']  = $expend;
            $data['list']['data'][$key]['balance'] = $balance;

            // 需要转换货币
            $total_income  += ToolsLib::getInstance()->convertCurrency($income, $fund_info['account_currency'], 'CNY');
            $total_expend  += ToolsLib::getInstance()->convertCurrency($expend, $fund_info['account_currency'], 'CNY');
            $total_balance += ToolsLib::getInstance()->convertCurrency($balance, $fund_info['account_currency'], 'CNY');;
        }

        $this->assign('total_income', $total_income);
        $this->assign('total_expend', $total_expend);
        $this->assign('total_balance', $total_balance);
        $this->assign('all_bank_accounts', $all_bank_accounts);
        $this->assign('all_types', $all_types);
        $this->assign('third_type_attr', $third_pay_account_type);
        $this->assign('all_currency_type', ToolsLib::getInstance()->getAllCurrencyType());
        $this->assign('admin_list', AccountLib::getInstance()->getAllAccountAdmin());
        $this->assign('type_scene', ToolsLib::getInstance()->getTypeScene());
        $this->assign('list_total', $data['count']);
        $this->assign('list', $data['list']['data']);
        $this->assign('page', $data['page']);
        $this->assign('params', $params);

        $this->assign('ruletitle', '转账卡收支表');

        return parent::fetchAuto();
    }
}