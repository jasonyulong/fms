<?php

namespace app\index\library;

use app\common\model\AccountFundDetail;
use app\common\model\AccountFundDiff;
use think\Config;
use app\common\model\Admin;
use app\common\model\Account;
use app\common\library\ToolsLib;
use app\common\library\FilterLib;
use app\common\model\AccountFund;
use app\common\model\AccountAdmin;
use app\common\model\CompanyAccountReceipt;

/**
 * 账号相关操作
 */
class AccountLib
{
    /**
     * 实例
     * @var
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
            static::$instance = new AccountLib();
        }
        return static::$instance;
    }


    /**
     * 获取所有账号的管理员
     * @author lamkakyun
     * @date 2018-11-09 20:16:58
     * @return void
     */
    public function getAllAccountAdmin()
    {
        $admin_model = new Admin();

        $where = ['status' => 1];
        $data  = $admin_model->where($where)->select()->toArray();

        return $data;
    }

    /**
     * 检查银行卡账户 是否存在
     * @author lamkakyun
     * @date 2018-11-12 17:24:51
     * @return bool
     */
    public function isExistsBankAccount($cardno, $bank_name)
    {
        $account_model = new Account();
        $where         = ['bank_name' => $bank_name, 'account' => $cardno, 'type' => '3'];
        $count         = $account_model->where($where)->count();

        return $count > 0;
    }


    /**
     * 检查 第三方账户， 和 店铺账户是否存在
     * @author lamkakyun
     * @date
     * @return void
     */
    public function isExistsAccount($account_name, $type, $type_attr)
    {
        $account_model = new Account();
        $where         = ['account' => $account_name, 'type' => $type, 'type_attr' => $type_attr];
        $count         = $account_model->where($where)->count();

        return $count > 0;
    }


    /**
     * 检查 账号名称是否存在
     * @author lamkakyun
     * @date 2018-11-13 11:53:11
     * @return bool
     */
    public function isExistsAccountTitle($title)
    {
        $account_model = new Account();
        $where         = ['title' => $title];
        $count         = $account_model->where($where)->count();

        return $count > 0;
    }


    /**
     * 获取账户状态
     * @author lamkakyun
     * @date 2018-11-13 11:42:54
     * @return array
     */
    public function getAccountStatus()
    {
        //状态 0=注销 1=正常 2=冻结
        return [
            '0' => '注销',
            '1' => '正常',
            '2' => '冻结',
        ];
    }


    /**
     * 获取账号列表
     * @author lamkakyun
     * @date
     * @return void
     */
    public function getAccountList($params)
    {
        $account_model       = new Account();
        $account_admin_model = new AccountAdmin();

        $where     = [];
        $fields    = '*';
        $typeScene = array_keys(ToolsLib::getInstance()->getTypeScene());

        if (FilterLib::isNotEmptyArr($params, 'company_id')) $where['company_id'] = ['IN', $params['company_id']];
        if (isset($params['type_scene']) && in_array($params['type_scene'], $typeScene)) $where['type_scene'] = $params['type_scene'];

        if (FilterLib::isNotEmptyArr($params, 'admin_id')) {
            $_tmp_account_ids = $account_admin_model->where(['admin_id' => ['IN', $params['admin_id']]])->column('account_id');
            $where['id']      = ['IN', $_tmp_account_ids];
        }
        if (FilterLib::isNum($params, 'account_type')) $where['type'] = $params['account_type'];
        if (FilterLib::isNum($params, 'status')) $where['status'] = $params['status'];
        if (FilterLib::isNum($params, 'type_attr')) $where['type_attr'] = $params['type_attr'];
        if (FilterLib::isNotEmptyStr($params, 'search_field') && FilterLib::isNotEmptyStr($params, 'search_value')) {
            $_search_value = preg_split('/[\s,，]+/', trim($params['search_value']));
            switch ($params['search_field']) {
                case 'account':
                case 'title':
                    $where[$params['search_field']] = ['LIKE', array_map(function ($val) {
                        return "%{$val}%";
                    }, $_search_value), 'OR'];
                    break;
            }
        }
        $start_select = ($params['p'] - 1) * $params['ps'];

        $count = $account_model->where($where)->count();
        $list  = $count ? $account_model->field($fields)->where($where)->limit($start_select, $params['ps'])->order('type_attr DESC,createtime DESC')->select()->toArray() : [];
        return ['list' => $list, 'count' => $count];
    }


    /**
     * 获取第三方收款列表
     * @author lamkakyun
     * @date 2018-11-13 14:20:07
     * @return void
     */
    public function getThirdReceiptList($params)
    {
        $start_select        = ($params['p'] - 1) * $params['ps'];
        $account_model       = new Account();
        $account_admin_model = new AccountAdmin();

        $company_account_receipt_model = new CompanyAccountReceipt();

        $where = ['type' => 2, 'type_attr' => $params['type']];

        if (FilterLib::isNotEmptyArr($params, 'admin_id')) {
            $_tmp_account_ids = $account_admin_model->where(['admin_id' => ['IN', $params['admin_id']]])->column('account_id');
            $where['id']      = ['IN', $_tmp_account_ids];
        }

        if (FilterLib::isNum($params, 'status')) $where['status'] = $params['status'];
        if (FilterLib::isNotEmptyStr($params, 'search_field') && FilterLib::isNotEmptyStr($params, 'search_value')) {
            $_search_value = preg_split('/[\s,，]+/', trim($params['search_value']));
            switch ($params['search_field']) {
                case 'title':
                case 'account':
                    $where[$params['search_field']] = ['LIKE', array_map(function ($val) {
                        return "%{$val}%";
                    }, $_search_value), 'OR'];
                    break;
            }
        }

        $count        = $account_model->where($where)->count();
        $account_list = $count ? $account_model->where($where)->limit($start_select, $params['ps'])->select()->toArray() : [];

        // todo: 合并 平台数据, 一次性查出所有 平台数据，在进行合并
        $id_list         = array_column($account_list, 'id');
        $group_info_list = $tmp_group_list = [];

        if ($id_list) $tmp_group_list = $company_account_receipt_model->field('receipt_id, platform, COUNT(*) as count')->where(['receipt_id' => ['IN', $id_list]])->group('receipt_id, platform')->select()->toArray();

        foreach ($tmp_group_list as $key => $value) {
            $group_info_list[$value['receipt_id']][$value['platform']] = $value;
        }

        foreach ($account_list as $key => $value) {
            $account_list[$key]['platform_list'] = isset($group_info_list[$value['id']]) ? $group_info_list[$value['id']] : [];

            $account_list[$key]['platform_total'] = 0;
            foreach ($account_list[$key]['platform_list'] as $v) {
                $account_list[$key]['platform_total'] += $v['count'];
            }
        }

        return ['list' => $account_list, 'count' => $count];
    }


    /**
     * 根据账户Id，获取账户管理员信息
     * @author lamkakyun
     * @date
     * @return void
     */
    public function getAdminByAccountIds($account_id_arr, $format_type = 0)
    {
        $account_admin_model = new AccountAdmin();

        $where = ['account_id' => ['IN', $account_id_arr]];
        $data  = $account_admin_model->where($where)->select()->toArray();

        // 将account id 放在 前面
        if ($format_type == 1) {
            $tmp  = $data;
            $data = [];
            foreach ($tmp as $key => $value) {
                $data[$value['account_id']][] = $value;
            }
        }

        return $data;
    }

    /**
     * 添加 账户, 涉及的时间表很多
     * 编辑账户 （不想再写一个方法，太麻烦了）
     * @author lamkakyun
     * @date 2018-11-13 19:45:45
     * @return array
     */
    public function addAccount($params, $is_edit = false, $edit_data = [])
    {
        $account_model                 = new Account();
        $account_admin_model           = new AccountAdmin();
        $admin_model                   = new Admin();
        $account_fund_model            = new AccountFund();
        $company_account_receipt_model = new CompanyAccountReceipt();

        if ($is_edit) {
            $this_account       = $edit_data['this_account'];
            $own_admin_list     = $edit_data['own_admin_list'];
            $account_admin_list = $edit_data['account_admin_list'];

            $own_admin_ids = array_column($own_admin_list, 'admin_id');

            // 当前账户 的子账户信息
            $sub_account_list  = $edit_data['sub_account_list'];
            $sub_account_names = array_column($sub_account_list, 'account');
            $sub_account_ids   = array_column($sub_account_list, 'id');

            // 还保留的 子账户 id
            $keep_sub_account_ids = $params['sub_funds_id'] ?? [];
            // 要删除的 子账户 id
            $delete_sub_account_ids = array_diff($sub_account_ids, $keep_sub_account_ids);

            // TODO: 检测这些账户是否可以 删除
            if (count($delete_sub_account_ids) > 0) {
                $_bind_count = $company_account_receipt_model->where(['receipt_id' => ['IN', $delete_sub_account_ids]])->count();
                if ($_bind_count > 0) return json(['code' => -1, 'msg' => '子账号已绑定，不能删除']);
            }
        }

        $action_str = $is_edit ? '编辑' : '添加';
        if (!FilterLib::isNotEmptyStr($params, 'title')) return json(['code' => -1, 'msg' => '账号名称不能为空']);

        // TODO: 检查账号名称是否存在
        $is_exists_title = AccountLib::getInstance()->isExistsAccountTitle($params['title']);
        if ($is_exists_title && (($is_edit && $this_account['title'] != $params['title']) || !$is_edit)) {
            return json(['code' => -1, 'msg' => '账号名称已存在']);
        }

        if (!FilterLib::isNum($params, 'company_id')) return json(['code' => '-1', 'msg' => '请选择公司']);
        if (!FilterLib::isNotEmptyArr($params, 'admin_id')) return json(['code' => -1, 'msg' => '请选择管理员']);
        if (!FilterLib::isNum($params, 'type_scene')) return json(['code' => '-1', 'msg' => '请选择使用类型']);
        if (!isset($params['account_platform']) || !in_array($params['account_platform'], array_merge(array_keys(ToolsLib::getInstance()->getThirdPayType()), ['bank_card', 'shop_account']))) return json(['code' => '-1', 'msg' => '请选择账户平台']);

        foreach ($params['sub_balance'] as $v) {
            if ($v && !FilterLib::isFloat($v)) return json(['code' => '-1', 'msg' => '请填写正确的 余额']);
        }

        if (FilterLib::isNotEmptyStr($params, 'day_quota') && !FilterLib::isFloat($params['day_quota'])) return json(['code' => '-1', 'msg' => '请填写正确的 每日转账额度']);
        if (FilterLib::isNotEmptyStr($params, 'out_rate') && !FilterLib::isFloat($params['out_rate'])) return json(['code' => '-1', 'msg' => '请填写正确的 提现费率']);
        if (FilterLib::isNotEmptyStr($params, 'fixed_fee') && !FilterLib::isFloat($params['fixed_fee'])) return json(['code' => '-1', 'msg' => '请填写正确的 固定费']);
        if (FilterLib::isNotEmptyStr($params, 'fixed_rate') && !FilterLib::isFloat($params['fixed_rate'])) return json(['code' => '-1', 'msg' => '请填写正确的 固定费率']);

        // 账户类型 以及 账户属性
        /*
        $_type = 1;
        if (in_array($params['account_platform'], array_keys(ToolsLib::getInstance()->getThirdPayType()))) $_type = 2;
        if ($params['account_platform'] == 'bank_card') $_type = 3;
        */
        $_type      = $params['type'];
        $_type_attr = ($_type == 1) ? $params['third_platform'] : ($params['account_platform'] == 'bank_card' ? '' : $params['account_platform']);

        // TODO: 假如是银行卡，需要检测 银行 归属，和 支行
        if ($params['account_platform'] == 'bank_card') {
            if (!FilterLib::isNum($params, 'bank_id')) return json(['code' => '-1', 'msg' => '请选择银行']);
            if (!FilterLib::isNum($params, 'sub_bank_id')) return json(['code' => '-1', 'msg' => '请选择银行支行']);
            if (!FilterLib::isNotEmptyStr($params, 'bank_cardno')) return json(['code' => '-1', 'msg' => '请输入银行卡号']);

            $bank_info = ToolsLib::getInstance()->getBankInfo($params['bank_id'], $params['sub_bank_id']);
            // TODO: 检查 bank_name + account + type的唯一性
            $_is_exists = AccountLib::getInstance()->isExistsBankAccount($params['bank_cardno'], $bank_info['bank_name']);
            if ($_is_exists && (($is_edit && $this_account['account'] != $params['bank_cardno']) || !$is_edit)) {
                return json(['code' => '-1', 'msg' => "银行卡号{$params['bank_cardno']}已存在，请勿重复{$action_str}"]);
            }
        } else // 检测 非 银行卡账户， 是否存在
        {
            if (!FilterLib::isNotEmptyStr($params, 'third_account')) return json(['code' => '-1', 'msg' => '请填写账户']);

            // TODO: 检查 account 表中， account + type + type_attr 的唯一性
            $_is_exists = AccountLib::getInstance()->isExistsAccount($params['third_account'], $_type, $_type_attr);
            if ($_is_exists && (($is_edit && $this_account['account'] != $params['third_account']) || !$is_edit)) {
                return json(['code' => '-1', 'msg' => "账户{$params['third_account']}已存在，请勿重复{$action_str}"]);
            }
        }

        $_account_name = ($params['account_platform'] == 'bank_card') ? $params['bank_cardno'] : $params['third_account'];
        $account_model->startTrans();
        $add_account_data = [
            'company_id'   => $params['company_id'],
            'title'        => $params['title'],
            'account'      => $_account_name,
            'account_mark' => '',
            'type'         => $_type,
            'type_attr'    => $_type_attr,
            'type_scene'   => $params['type_scene'],
            'bank_type'    => $params['bank_type'] ?? 0,
            'bank_name'    => $bank_info['bank_name'] ?? '',
            'branch_name'  => $bank_info['sub_branch_name'] ?? '',
            'province'     => $bank_info['province'] ?? '',
            'city'         => $bank_info['city'] ?? '',
            'out_rate'     => $params['out_rate'] ?? '0',
            'fixed_fee'    => $params['fixed_fee'] ?? '0',
            'fixed_rate'   => $params['fixed_rate'] ?? '0',
            'day_quota'    => $params['day_quota'] ?? '0',
            'createuser'   => $params['login_username'],
            'createtime'   => time(),
        ];
        if ($_type == 1) $add_account_data['account_mark'] = $params['ebay_site'];

        // TODO: 添加 数据 到 account 表
        if (!$is_edit) {
            $ret_update_account = $account_model->insert($add_account_data);
        } else {
            unset($add_account_data['createtime'], $add_account_data['createuser']);
            $add_account_data['uptime'] = time();
            $_where_update              = ['id' => $params['id']];
            $ret_update_account         = $account_model->where($_where_update)->update($add_account_data);
            $ret_update_account         = $ret_update_account === false ? false : true;
        }

        if (!$ret_update_account) {
            $account_model->rollback();
            return json(['code' => '-1', 'msg' => "{$action_str}失败(1)"]);
        }

        $ret_account_id = $is_edit ? $params['id'] : $account_model->getLastInsID();

        // TODO: 添加数据到 account_admin
        $add_account_admin = [];

        if ($is_edit) {
            // 找出需要删除的 admin id
            $delete_admin_ids = array_diff($own_admin_ids, $params['admin_id']);
            // 找出需要 添加的 admin id
            $add_admin_ids = array_diff($params['admin_id'], $own_admin_ids);
        }
        foreach ($params['admin_id'] as $admin_id) {
            $admin_name = $admin_model->where(['id' => $admin_id])->value('username');
            $_tmp_data  = [
                'account_id' => $ret_account_id,
                'admin_id'   => $admin_id,
                'admin_name' => $admin_name,
                'updatetime' => time(),
            ];
            if (($is_edit && in_array($admin_id, $add_admin_ids)) || !$is_edit) {
                $add_account_admin[] = $_tmp_data;
            }
        }

        $ret_add_account_admin = true;
        if ($add_account_admin) $ret_add_account_admin = $account_admin_model->insertAll($add_account_admin);

        if (!$ret_add_account_admin) {
            $account_model->rollback();
            return json(['code' => '-1', 'msg' => "{$action_str}失败(2)"]);
        }
        if ($is_edit && $delete_admin_ids) {
            $ret_delete_account_admin = $account_admin_model->where(['admin_id' => ['IN', $delete_admin_ids], 'account_id' => $params['id']])->delete();
            if (!$ret_delete_account_admin) {
                return json(['code' => '-1', 'msg' => "{$action_str}失败(10)"]);
            }
        }

        // TODO: 检测 子账号，如果成功将 添加 子账号数据 account, account_admin, account_fund
        if (isset($params['sub_account']) && count($params['sub_account']) > 0) {
            foreach ($params['sub_account'] as $key => $value) {
                if (empty($value)) $value = substr($add_account_data['account'], -4) . '_' . ($params['sub_currency_type'][$key] ?? $key);

                $_tmp_account_id      = $params['sub_account_id'][$key];
                $_tmp_funds_id        = $params['sub_funds_id'][$key];
                $_is_edit_sub_account = $_tmp_account_id ? true : false;
                $_tmp_balance         = $params['sub_balance'][$key] ?? '0';
                $_tmp_currency        = $params['sub_currency_type'][$key];

                $_ret_acc_id = $_tmp_account_id ? $_tmp_account_id : $ret_account_id;

                // TODO: 添加account_funds
                $add_acc_fund_data = [
                    'account_id'       => $_ret_acc_id,
                    'account_name'     => $_account_name,
                    'fund_name'        => $value,
                    'account_funds'    => $_tmp_balance,
                    'account_currency' => $_tmp_currency,
                    'updatetime'       => time(),
                ];

                if (!$_is_edit_sub_account) {
                    $_ret_update_fund = $account_fund_model->insert($add_acc_fund_data);
                } else {
                    $_tmp_where       = ['account_id' => $_tmp_account_id, 'id' => $_tmp_funds_id];
                    $_ret_update_fund = $account_fund_model->where($_tmp_where)->update($add_acc_fund_data);
                    $_ret_update_fund = $_ret_update_fund === false ? false : true;
                }
                if (!$_ret_update_fund) {
                    $account_model->rollback();
                    return json(['code' => '-1', 'msg' => "{$action_str}失败(6)"]);
                }
            }
        }
        $account_model->commit();
        return json(['code' => '0', 'msg' => "{$action_str}成功"]);
    }

    /**
     * 删除余额账户
     * @param $funds_id
     * @return \think\response\Json
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function delAccountFunds($funds_id)
    {
        $fundsData = AccountFund::get(['id' => $funds_id]);
        if (empty($fundsData)) {
            return json(['code' => 0, 'msg' => "请求异常"]);
        }
        AccountFund::startTrans();

        if (!$fundsData->delete()) {
            return json(['code' => 0, 'msg' => $fundsData->getError()]);
        }
        AccountFundDetail::where(['from_account_funds_id|to_account_funds_id' => $funds_id])->delete();
        AccountFundDiff::where(['account_funds_id' => $funds_id])->delete();
        AccountFund::commit();
        return json(['code' => 1, 'msg' => "操作成功"]);
    }

    /**
     * 根据账号id， 获取账户信息, 用于编辑，获取的数据表有3个
     * @author lamkakyun
     * @date
     * @return void
     */
    public function getFullAccountInfo($account_id)
    {
        $account_model       = new Account();
        $account_admin_model = new AccountAdmin();
        $account_fund_model  = new AccountFund();

        $ret_data = [];

        // 包含了子账号
        $account_list = $account_model->alias('a')->join('db_account_funds f', ' a.id=f.account_id')->field('a.*, f.account_funds, f.account_currency')->where(['a.id' => $account_id])->select()->toArray();

        $ret_data['account_list'] = $account_list;

        $all_account_ids = array_column($account_list, 'id');

        $tmp_data = $account_admin_model->where(['account_id' => ['IN', $all_account_ids]])->select()->toArray();

        $account_admin_list = [];
        foreach ($tmp_data as $value) {
            $account_admin_list[$value['account_id']][] = $value;
        }

        $ret_data['account_admin'] = $account_admin_list;

        return $ret_data;
    }

    /**
     * 根据管理员 id， 获取管理员信息
     * @author lamkakyun
     * @date 2018-11-16 09:20:36
     * @return array
     */
    public function getAdminListById($admin_ids, $format_type = 0, $fields = 'id, username')
    {
        $admin_model = new Admin();

        $ret_data = $admin_model->field($fields)->where(['id' => ['IN', $admin_ids]])->select()->toArray();

        // 将id 放到 key 上
        if ($format_type == 1) {
            $tmp      = $ret_data;
            $ret_data = [];
            foreach ($tmp as $key => $value) {
                $ret_data[$value['id']] = $value;
            }
        }

        return $ret_data;
    }


    /**
     * 根据给定的类型，获取账户列表
     * @author lamkakyun
     * @date 2018-11-19 09:58:20
     * @$type 对应 ToolsLib getAccountType  1 => '店铺账户',2 => '支付平台账户',3 => '银行账户',
     * @return array
     */
    public function getTypeAccounts($type = 0, $type_scene = false)
    {
        $account_model = new Account();
        $where         = ['status' => 1];
        if ($type) $where['type'] = $type;
        $where['type'] = ['NEQ', 1]; // 去除 店铺账户
        if ($type_scene) $where['type_scene'] = $type_scene;

        return $account_model->where($where)->select()->toArray();
    }


    /**
     * 获取第三方账号
     * @author lamkakyun
     * @date 2018-11-28 10:49:30
     * @return void
     */
    public function getThirdAccounts($params)
    {
        $account_model = new Account();
        $where         = ['status' => 1, 'type' => 2];
        if ($params['type_attr']) $where['type_attr'] = $params['type_attr'];
        return $account_model->where($where)->select()->toArray();
    }

    /**
     * 获取银行卡账号
     * @author yang
     * @date 2018-11-29
     * @return void
     */
    public function getBankAccounts()
    {
        //获取所有的银行卡账号
        $account_model = new Account();
        $where         = ['status' => 1, 'type' => 3];
        $data          = $account_model->field('id,title,account')->where($where)->order('id desc')->select()->toArray();

        //获取相应的银行卡
        $accountIdArr      = array_column($data, 'id');
        $account_funds     = new AccountFund();
        $map['account_id'] = ['in', $accountIdArr];
        $fundData          = $account_funds->field('id,account_id,account_name,fund_name,account_currency')->where($map)->select()->toArray();
        $bankData          = [];
        foreach ($fundData as $key => $val) {
            $bankData[$val['account_id']][] = $val;
        }
        foreach ($data as $key => $val) {
            $data[$key]['son'] = isset($bankData[$val['id']]) ? $bankData[$val['id']] : [];
        }
        return $data;
    }


    /**
     * 根据给定的类型，获取【余额账户】列表
     * @param bool $type_scene 使用类型
     * @param int $type 账户类型
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTypeFunds($type_scene = false, $type = false)
    {
        $account_model      = new Account();
        $account_fund_model = new AccountFund();

        $where_account = ['status' => 1];
        if ($type_scene) {
            $where_account['type_scene'] = $type_scene;
        }
        if ($type) {
            $where_account['type'] = is_array($type) ? ['IN', $type] : $type;
        }
        $account_list = $account_model->where($where_account)->select()->toArray();

        foreach ($account_list as $key => $value) {
            $_where_fund = ['account_id' => $value['id']];
            $fund_list   = $account_fund_model->where($_where_fund)->select()->toArray();

            $account_list[$key]['fund_list'] = $fund_list;
        }

        return $account_list;
    }

    /**
     * 获取余额银行付款账户
     * @author lamkakyun
     * @date 2018-11-28 17:13:49
     * @return void
     */
    public function getPayFundBankAccount()
    {
        $account_model = new Account();
        $fund_model    = new AccountFund();

        $fields = 'acc.title, acc.account, f.id as fund_id, f.fund_name,f.account_currency';
        $where  = ['acc.status' => 1, 'acc.type_scene' => 1, 'acc.type' => 3];

        $dataObj = $account_model->alias('acc')
            ->join('db_account_funds f', ' acc.id=f.account_id')
            ->field($fields)
            ->where($where)
            ->whereOr(['acc.id' => ['IN', [74, 166, 78, 94, 86]]])
            ->select();

        if (!$dataObj) return json(['code' => -1, 'msg' => 'Fail']);

        return json(['code' => 0, 'msg' => 'Success', 'data' => $dataObj->toArray()]);
    }
}