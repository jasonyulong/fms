<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\account;

use think\Config;
use app\common\model\Bank;
use app\common\model\Admin;
use app\common\model\Account;
use app\common\library\ToolsLib;
use app\common\library\FilterLib;
use app\common\model\AccountFund;
use app\index\library\AccountLib;
use app\index\library\CompanyLib;
use app\common\model\AccountAdmin;
use app\common\controller\AuthController;
use app\common\model\CompanyAccountReceipt;

/**
 * 账户管理
 * Class index
 * @package app\index\controller\account
 */
class index extends AuthController
{

    /**
     * 查看
     * @access auth
     * @author lamkakyun
     * @date 2018-11-09 17:34:09
     * @return void
     */
    public function index()
    {
        $account_model = new Account();

        $params       = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $params['p']  = $params['p'] ?? 1;
        $params['ps'] = $params['ps'] ?? 50;
        $start_select = ($params['p'] - 1) * $params['ps'];
        $type_scene   = $params['type_scene'] ?? '';
        if (!empty($type_scene)) {
            $params['account_type'] = 2;
        }

        $data  = AccountLib::getInstance()->getAccountList($params);
        $count = $data['count'];

        $all_company = CompanyLib::getInstance()->getAllCompany();

        $account_list       = $data['list'];
        $account_admin_list = AccountLib::getInstance()->getAdminByAccountIds(array_column($account_list, 'id'), 1);

        foreach ($account_list as $key => $value) {
            $account_list[$key]['admins'] = isset($account_admin_list[$value['id']]) ? implode(',', array_column($account_admin_list[$value['id']], 'admin_name')) : '';
        }

        $this->_assignPagerData($this, $params, $count);
        $this->assign('account_list', $account_list);
        $this->assign('account_type', ToolsLib::getInstance()->getAccountType());
        $this->assign('list_total', $count);
        $this->assign('params', $params);
        $this->assign('account_status', AccountLib::getInstance()->getAccountStatus());
        $this->assign('all_company', $all_company);
        $this->assign('type_scene', ToolsLib::getInstance()->getTypeScene());
        $this->assign('admin_list', AccountLib::getInstance()->getAllAccountAdmin());
        $this->assign('third_type_attr', ToolsLib::getInstance()->getThirdPayType());
        $this->assign('platforms', Config::get('site.platforms'));

        return parent::fetchAuto();
    }

    private function _sendAddData()
    {
        $this->assign('bank_type', ToolsLib::getInstance()->getBankType());
        $this->assign('account_type', ToolsLib::getInstance()->getAccountType());
        $this->assign('type_scene', ToolsLib::getInstance()->getTypeScene());
        $this->assign('third_type_attr', ToolsLib::getInstance()->getThirdPayType());
        $this->assign('company_list', CompanyLib::getInstance()->getAllCompany(1));
        $this->assign('admin_list', AccountLib::getInstance()->getAllAccountAdmin());
        $this->assign('platform_list', ToolsLib::getInstance()->getPlatformList(1));
        $this->assign('third_pay_account_type', ToolsLib::getInstance()->getThirdPayType());
        $this->assign('all_province', ToolsLib::getInstance()->getAllBankProvince());
        $this->assign('all_cities', ToolsLib::getInstance()->getAllBankCities());
        $this->assign('all_banks', ToolsLib::getInstance()->getAllBanks());
        $this->assign('all_currency_type', ToolsLib::getInstance()->getAllCurrencyType());
        $this->assign('all_ebay_site', ToolsLib::getInstance()->getAllEbaySite());
    }

    /**
     * 新增账户
     * @access auth
     * @author lamkakyun
     * @date 2018-11-09 18:07:40
     * @return void
     */
    public function add($params = [])
    {
        $params = $params ?: array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        // 登录用户
        $params['login_username'] = $this->auth->username;

        if ($this->request->isGet()) {
            $this->_sendAddData();
            return $this->fetchAuto('add');
        }

        return AccountLib::getInstance()->addAccount($params);
    }

    /**
     * 编辑账户
     * @access auth
     * @author lamkakyun
     * @date 2018-11-13 19:50:27
     * @return void
     */
    public function edit()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $params['login_username'] = $this->auth->username;

        if (!FilterLib::isNum($params, 'id')) return $this->error('参数错误');
        $account_info = AccountLib::getInstance()->getFullAccountInfo($params['id']);

        $this_account       = null;
        $sub_account_list   = [];
        $account_admin_list = $account_info['account_admin'];
        if (!$account_admin_list) return $this->error('账户数据异常');
        $own_admin_list = $account_admin_list[$params['id']];

        foreach ($account_info['account_list'] as $value) {
            $this_account = $value;
        }

        $sub_account_list = AccountFund::all(['account_id' => $params['id']])->toArray();

        $all_admin_id = array_column($own_admin_list, 'admin_id');
        // 转换为字符串
        $all_admin_id = array_map(function ($val) {
            return $val . '';
        }, $all_admin_id);

        $edit_account_type = empty($this_account['type_attr']) ? 'bank_card' : $this_account['type_attr'];

        if ($edit_account_type == 'bank_card') {
            $bank_model = new Bank();
            $bank_info  = $bank_model->where(['sub_branch_name' => $this_account['branch_name']])->find();
            $this->assign('bank_info', $bank_info);
        }

        $this->assign('this_account', $this_account);
        $this->assign('sub_account_list', $sub_account_list);
        $this->assign('all_admin_id', $all_admin_id);
        $this->assign('edit_account_type', $edit_account_type);
        $this->assign('is_edit', 1);

        if ($this->request->isGet()) {
            $this->_sendAddData();
            return $this->fetchAuto('add');
        }

        // TODO: post 操作 ===================================================================
        // 将需要编辑的数据，穿给 Lib 的方法， 减小查询,加快处理速度
        $edit_data = [
            'this_account'       => $this_account,
            'sub_account_list'   => $sub_account_list,
            'own_admin_list'     => $own_admin_list,
            'account_admin_list' => $account_admin_list,
            'edit_account_type'  => $edit_account_type,
        ];
        return AccountLib::getInstance()->addAccount($params, $is_edit = true, $edit_data = $edit_data);
    }

    /**
     * 删除余额
     * @access auth
     */
    public function del()
    {
        if (!$this->request->isPost()) {
            return $this->error(__('请求异常'));
        }
        $funds_id = $this->request->post('funds_id', 0);

        return AccountLib::getInstance()->delAccountFunds($funds_id);
    }


    /**
     * 第三方收款账户
     * @access auth
     * @return string
     */
    public function receipt()
    {
        $params         = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $params['p']    = $params['p'] ?? 1;
        $params['ps']   = $params['ps'] ?? 50;
        $params['type'] = $params['type'] ?? 1;

        $account_type_list = ToolsLib::getInstance()->getThirdPayType();
        if (!isset($params['type']) || !in_array($params['type'], array_keys($account_type_list))) return $this->error('参数错误');

        $data = AccountLib::getInstance()->getThirdReceiptList($params);

        $count        = $data['count'];
        $account_list = $data['list'];

        // TODO: 获取账号管理员数据
        if ($count > 0) {
            $account_admin_list = AccountLib::getInstance()->getAdminByAccountIds(array_column($account_list, 'id'), 1);

            foreach ($account_list as $key => $value) {
                $account_list[$key]['admins'] = isset($account_admin_list[$value['id']]) ? implode(',', array_column($account_admin_list[$value['id']], 'admin_name')) : '';
            }
        }

        $platform_list = ToolsLib::getInstance()->getPlatformList(1);

        $this->_assignPagerData($this, $params, $count);
        $this->assign('list_total', $count);
        $this->assign('platform_list', $platform_list);
        $this->assign('account_type_list', $account_type_list);
        $this->assign('params', $params);
        $this->assign('account_list', $account_list);
        $this->assign('admin_list', AccountLib::getInstance()->getAllAccountAdmin());
        $this->assign('third_pay_account_type', ToolsLib::getInstance()->getThirdPayType());
        $this->assign('account_status', AccountLib::getInstance()->getAccountStatus());
        return parent::fetchAuto();
    }


    /**
     * 收款账号详情
     * @access auth
     * @author lamkakyun
     * @date 2018-11-13 15:55:09
     * @return void
     */
    public function receiptAccountDetail()
    {
        $params       = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $params['p']  = $params['p'] ?? 1;
        $params['ps'] = $params['ps'] ?? 50;
        $start_select = ($params['p'] - 1) * $params['ps'];

        $account_model                 = new Account();
        $company_account_receipt_model = new CompanyAccountReceipt();

        if (!FilterLib::isNum($params, 'account_id')) return $this->error(__('参数错误'));

        $where = ['receipt_id' => $params['account_id']];
        if (FilterLib::isNotEmptyStr($params, 'platform')) $where['platform'] = $params['platform'];

        $receipt_account = $account_model->where(['id' => $params['account_id']])->find()->toArray();

        $count = $company_account_receipt_model->where($where)->count();
        $list  = $count ? $company_account_receipt_model->where($where)->limit($start_select, $params['ps'])->select()->toArray() : [];

        if ($count > 0) {
            $_tmp_list = CompanyLib::getInstance()->getCompanyAccountByIds(array_column($list, 'account_id'), 1);
            foreach ($list as $key => $value) {
                $list[$key]['account_info'] = $_tmp_list[$value['account_id']] ?? [];
            }
        }

        $this->assign('list_total', $count);
        $this->assign('list', $list);
        $this->assign('receipt_account', $receipt_account);
        return parent::fetchAuto();
    }


    /**
     * 子账户详情
     * @access auth
     * @author lamkakyun
     * @date 2018-11-13 16:56:49
     * @return void
     */
    public function subAccountDetail()
    {
        $params       = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $params['p']  = $params['p'] ?? 1;
        $params['ps'] = $params['ps'] ?? 50;
        $start_select = ($params['p'] - 1) * $params['ps'];

        if (!FilterLib::isNum($params, 'account_id')) return $this->error(__('参数错误'));

        $account_fund_model = new AccountFund();

        $where = ['account_id' => $params['account_id']];

        $count = $account_fund_model->where($where)->count();
        $list  = $account_fund_model->where($where)->paginate($params['ps']);

        $this->assign('total', $count);
        $this->assign('list', $list->toArray());
        $this->assign('page', $list->render());
        return parent::fetchAuto();
    }


    /**
     * 修改账户管理员
     * @author lamkakyun
     * @date 2018-11-15 17:27:03
     * @return void
     */
    public function updateAccountAdmin()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $account_admin_model = new AccountAdmin();

        if ($this->request->isGet()) {
            $this->assign('admin_list', AccountLib::getInstance()->getAllAccountAdmin());
            return parent::fetchAuto();
        }

        if (!FilterLib::isNotEmptyStr($params, 'account_id')) return json(['code' => -1, 'msg' => '参数错误']);
        if (!FilterLib::isNotEmptyArr($params, 'admin_id')) return json(['code' => -1, 'msg' => '请选择管理员']);

        $update_admin_ids = $params['admin_id'];
        $account_ids      = explode(',', $params['account_id']);

        $all_account_admin_list = AccountLib::getInstance()->getAdminByAccountIds($account_ids, 1);


        // 开启事务
        $account_admin_model->startTrans();

        foreach ($account_ids as $account_id) {
            $account_admin_list = $all_account_admin_list[$account_id] ?? [];

            $has_admin_ids = array_column($account_admin_list, 'admin_id');

            $all_admin_id     = array_merge($has_admin_ids, $update_admin_ids);
            $delete_admin_ids = array_diff($has_admin_ids, $update_admin_ids);
            $add_admin_ids    = array_diff($update_admin_ids, $has_admin_ids);

            $all_admin_list = AccountLib::getInstance()->getAdminListById($all_admin_id, 1);

            $add_account_admin_data = [];
            foreach ($add_admin_ids as $_admin_id) {
                $_tmp                     = [
                    'account_id' => $account_id,
                    'admin_id'   => $_admin_id,
                    'admin_name' => $all_admin_list[$_admin_id]['username'],
                ];
                $add_account_admin_data[] = $_tmp;
            }

            $ret_add    = $account_admin_model->insertAll($add_account_admin_data);
            $ret_delete = $account_admin_model->where(['account_id' => $account_id, 'admin_id' => ['IN', $delete_admin_ids]])->delete();


            if (!$ret_add && $ret_delete === false) {
                $account_admin_model->rollback();
                return json(['code' => -1, 'msg' => '操作失败']);
            }
        }

        $account_admin_model->commit();
        return json(['code' => 0, 'msg' => '操作成功']);
    }


    /**
     * 修改账户状态
     * @author lamkakyun
     * @date 2018-11-15 17:27:19
     * @return void
     */
    public function updateAccountStatus()
    {
        $params        = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $account_model = new Account();

        $status_list = AccountLib::getInstance()->getAccountStatus();
        if ($this->request->isGet()) {
            $this->assign('account_status', $status_list);
            return parent::fetchAuto();
        }

        if (!FilterLib::isNotEmptyStr($params, 'account_id')) return json(['code' => -1, 'msg' => '参数错误']);

        $account_ids = explode(',', $params['account_id']);

        if (!isset($params['account_status']) || !in_array($params['account_status'], array_keys($status_list))) return json(['code' => -1, 'msg' => '操作失败']);

        $save_data  = ['status' => $params['account_status']];
        $ret_update = $account_model->where(['id' => ['IN', $account_ids]])->update($save_data);

        if ($ret_update === false) return json(['code' => -1, 'msg' => '操作失败(2)']);

        return json(['code' => 0, 'msg' => '操作成功']);
    }


    /**
     * 修改提款费率
     * @author lamkakyun
     * @date 2018-11-15 17:27:30
     * @return void
     */
    public function updateOutRate()
    {
        $params        = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $account_model = new Account();

        if ($this->request->isGet()) {
            return parent::fetchAuto();
        }

        if (!FilterLib::isNotEmptyStr($params, 'account_id')) return json(['code' => -1, 'msg' => '参数错误']);

        $account_ids = explode(',', $params['account_id']);

        if (!FilterLib::isPrice($params, 'out_rate')) return json(['code' => -1, 'msg' => '参数错误(2)']);

        $save_data  = ['out_rate' => $params['out_rate']];
        $ret_update = $account_model->where(['id' => ['IN', $account_ids]])->update($save_data);

        if ($ret_update === false) return json(['code' => -1, 'msg' => '操作失败(2)']);

        return json(['code' => 0, 'msg' => '操作成功']);
    }
}