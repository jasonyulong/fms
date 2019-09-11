<?php

namespace app\index\controller\funds;

use app\common\model\Account;
use app\index\library\TplLib;
use app\index\library\FundLib;
use app\common\library\ToolsLib;
use app\common\library\FilterLib;
use app\common\model\AccountFund;
use app\index\library\AccountLib;
use app\index\library\CompanyLib;
use app\common\model\AccountTemplate;
use app\common\controller\AuthController;

/**
 * 平台店铺账户
 * Class index
 * @package app\index\controller\funds
 */
class Store extends AuthController
{

    /**
     * 列表查看
     * @access auth
     * @author lamkakyun
     * @return string
     * @throws \ReflectionException
     */
    public function index()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $params['ps']   = $params['ps'] ?? 20;
        if (isset($params['is_export']) && $params['is_export'] == 1) $params['ps'] = 100000;

        $params['type'] = 1;
        
        $platform_list = ToolsLib::getInstance()->getPlatformList();

        $data = FundLib::getInstance()->getAccountBalanceList($params);

        // TODO: 导出 余额 excel
        if (isset($params['is_export']) && $params['is_export'] == 1) 
        {
            foreach ($data['list']['data'] as $k => $v)
            {
                if ($v['platform']) $data['list']['data'][$k]['platform'] = $platform_list[$v['platform']];
            }
            $headers = [
                'platform'         => '平台',
                'account'          => '账户名称',
                'site'             => '站点',
                'account_currency' => '账户币种',
                'account_funds'    => '账户余额',
            ];

            $file_name = '平台店铺账户(余额)-' . date('Ymd');
            ToolsLib::getInstance()->exportExcel($file_name, $headers, $data['list']['data']);
        }

        $this->assign('all_company', CompanyLib::getInstance()->getAllCompany());
        $this->assign('platform_list', $platform_list);
        $this->assign('all_ebay_site', ToolsLib::getInstance()->getAllEbaySite());
        $this->assign('all_currency_type', ToolsLib::getInstance()->getAllCurrencyType());
        $this->assign('list_total', $data['count']);
        $this->assign('list', $data['list']['data']);
        $this->assign('page', $data['page']);
        $this->assign('params', $params);

        return parent::fetchAuto();
    }

    /**
     * 转账
     * @access auth
     * @author lamkakyun
     * @date 2018-11-16 18:30:37
     * @return void
     */
    public function transfer()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $account_tpl_model  = new AccountTemplate();
        $account_model      = new Account();
        $account_fund_model = new AccountFund();

        if ($this->request->isGet()) {
            if (isset($params['fund_id'])) {
                $fund_info    = $account_fund_model->where(['id' => $params['fund_id']])->find();
                $account_info = $account_model->where(['id' => $fund_info['account_id']])->find()->toArray();
                $this->assign('fund_info', $fund_info);
                $is_account = false;
            }
            if (isset($params['account_id'])) {
                $account_info = $account_model->where(['id' => $params['account_id']])->find()->toArray();
                $fund_list    = $account_fund_model->where(['account_id' => $account_info['id']])->select()->toArray();
                $this->assign('fund_list', $fund_list);
                $is_account = true;
            }

            $tpl_list = $account_tpl_model->where(['createuser_id' => $this->auth->id])->select()->toArray();

            // 付款 【余额】账户
            // $store_account_list = AccountLib::getInstance()->getTypeFunds(1);
            // 收款 【余额】账户
            $all_fund_list = AccountLib::getInstance()->getTypeFunds();

            $this->assign('tpl_list', $tpl_list);
            $this->assign('account_info', $account_info);
            $this->assign('all_fund_list', $all_fund_list);
            $this->assign('is_account', $is_account);
            $this->assign('account_type_list', TplLib::$account_type_list);
            $this->assign('all_currency_type', ToolsLib::getInstance()->getAllCurrencyType());

            return parent::fetchAuto();
        }

        $params['auth_id']       = $this->auth->id;
        $params['auth_username'] = $this->auth->username;
        return FundLib::getInstance()->transfer($params);
    }


    /**
     * 换汇
     * (和转账功能差不多，只是要转换货币，和记录第三方的信息)
     * @access auth
     * @author lamkakyun
     * @date 2018-12-03 17:33:34
     * @return void
     */
    public function exchangeMoney()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $account_tpl_model  = new AccountTemplate();
        $account_model      = new Account();
        $account_fund_model = new AccountFund();

        if ($this->request->isGet()) {
            if (isset($params['fund_id'])) {
                $fund_info    = $account_fund_model->where(['id' => $params['fund_id']])->find();
                $account_info = $account_model->where(['id' => $fund_info['account_id']])->find()->toArray();
                $this->assign('fund_info', $fund_info);
                $is_account = false;
            }
            if (isset($params['account_id'])) {
                $account_info = $account_model->where(['id' => $params['account_id']])->find()->toArray();
                $fund_list    = $account_fund_model->where(['account_id' => $account_info['id']])->select()->toArray();
                $this->assign('fund_list', $fund_list);
                $is_account = true;
            }

            $tpl_list = $account_tpl_model->where(['createuser_id' => $this->auth->id, 'status' => 1])->select()->toArray();

            // 付款 【余额】账户
            // $store_account_list = AccountLib::getInstance()->getTypeFunds(1);
            // 收款 【余额】账户
            $all_fund_list = AccountLib::getInstance()->getTypeFunds();

            $this->assign('tpl_list', $tpl_list);
            $this->assign('account_info', $account_info);
            $this->assign('all_fund_list', $all_fund_list);
            $this->assign('is_account', $is_account);
            $this->assign('account_type_list', TplLib::$account_type_list);
            $this->assign('all_currency_type', ToolsLib::getInstance()->getAllCurrencyType());

            return parent::fetchAuto();
        }

        $params['auth_id']       = $this->auth->id;
        $params['auth_username'] = $this->auth->username;
        return FundLib::getInstance()->exchangeMoney($params);
    }

    /**
     * 收款
     * @access auth
     * @return string
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public function receipt()
    {
        $fund_id  = $this->request->get('fund_id', 0);
        $fundData = AccountFund::get($fund_id);

        // 处理收款
        if ($this->request->isPost()) {
            $params = $this->request->post();

            $params['auth_id']       = $this->auth->id;
            $params['auth_username'] = $this->auth->username;
            return FundLib::getInstance()->receipt($params);
        }

        $this->assign('rows', $fundData);
        return parent::fetchAuto();
    }

    /**
     * 提现
     * @access auth
     * @author lamkakyun
     * @date 2018-11-16 18:32:49
     * @return void
     */
    public function withdraw()
    {
        $account_model      = new Account();
        $account_fund_model = new AccountFund();

        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        if (!FilterLib::isNotEmptyArr($params, 'fund_id') && !FilterLib::isNum($params, 'account_id')) return json(['code' => -1, 'msg' => '参数错误']);

        if (isset($params['fund_id'])) {
            $fund_ids      = explode(',', $params['fund_id']);
            $acc_fund_list = $account_fund_model->where(['id' => ['IN', $fund_ids]])->select()->toArray();

            $is_account = false;
        }
        if (isset($params['account_id'])) {
            $account_info  = $account_model->where(['id' => $params['account_id']])->find()->toArray();
            $acc_fund_list = $account_fund_model->where(['account_id' => $account_info['id']])->select()->toArray();
            $fund_ids      = array_column($acc_fund_list, 'id');

            $is_account = true;
        }


        // todo:检测 账户 数据是否正常
        $this->assign('fund_list', $acc_fund_list);

        $currency_list = array_column($acc_fund_list, 'account_currency');

        if ($this->request->isGet()) {
            if (count($acc_fund_list) != count($fund_ids)) return $this->error('账号数据异常');
            $this->assign('acc_fund_list', $acc_fund_list);
            // 收款 【余额】账户
            $all_fund_list = AccountLib::getInstance()->getTypeFunds(2);

            $account_info = $account_model->where(['id' => $acc_fund_list[0]['account_id']])->find();
            $this->assign('all_fund_list', $all_fund_list);
            $this->assign('account_info', $account_info);
            $this->assign('is_account', $is_account);
            return parent::fetchAuto();
        }

        if (count($acc_fund_list) != count($fund_ids)) return json(['code' => -1, 'msg' => '账号数据异常']);
        $params['auth_id']       = $this->auth->id;
        $params['auth_username'] = $this->auth->username;
        return FundLib::getInstance()->withdraw($params);
    }


    /**
     * 平账
     * @access auth
     * @author lamkakyun
     * @date 2018-11-16 18:32:51
     * @return void
     */
    public function fix()
    {
        $account_fund_model = new AccountFund();
        $account_model = new Account();

        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        if (isset($params['account_id']) && !FilterLib::isNum($params, 'account_id')) return json(['code' => -1, 'msg' => '参数错误']);
        if (isset($params['fund_id']) && !FilterLib::isNotEmptyArr($params, 'fund_id')) return json(['code' => -1, 'msg' => '参数错误']);
        
        if (isset($params['fund_id']))
        {
            $fund_ids = explode(',', $params['fund_id']);
        }

        // TODO: 如果传的的是account_id,获取所有余额子账户
        if (FilterLib::isNum($params, 'account_id'))
        {
            $account_info = $account_model->where(['id' => $params['account_id']])->find();
            if (!$account_info || $account_info['status'] != 1) return json(['code' => -1, 'msg' => '账户不存在或已禁用']);

            $fund_list = $account_fund_model->field('id')->where(['account_id' => $params['account_id']])->select();
            if (!$fund_list) return json(['code' => -1, 'msg' => '余额账户不存在']);

            $fund_ids = array_column($fund_list->toArray(), 'id');
        }


        // todo:检测 账户 数据是否正常
        $acc_fund_list = $account_fund_model->where(['id' => ['IN', $fund_ids]])->select()->toArray();
        //if (count($acc_fund_list) != count($fund_ids)) return json(['code' => -1, 'msg' => '账号数据异常']);

        if ($this->request->isGet()) {
            $this->assign('acc_fund_list', $acc_fund_list);

            return parent::fetchAuto();
        }

        $params['auth_id']       = $this->auth->id;
        $params['auth_username'] = $this->auth->username;
        return FundLib::getInstance()->fixBalance($params);
    }


    /**
     * 新建模板
     * @author lamkakyun
     * @date 2018-11-17 10:21:31
     * @return void
     */
    public function newTempl()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));


        $account_type_list = TplLib::$account_type_list;

        if ($this->request->isGet()) {
            $this->assign('params', $params);
            $this->assign('account_type_list', $account_type_list);

            return parent::fetchAuto();
        }

        $params['auth_id'] = $this->auth->id;
        $params['auth_username'] = $this->auth->username;
        return TplLib::getInstance()->addNewTpl($params);
    }
}