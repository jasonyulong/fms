<?php

namespace app\index\library;

use think\Config;
use app\common\model\Account;
use app\common\library\ToolsLib;
use app\common\library\FilterLib;
use app\common\model\AccountFund;
use app\common\model\AccountAdmin;
use app\common\model\AccountFundDiff;
use app\common\model\AccountTemplate;
use app\common\model\AccountFundDetail;
use think\response\Json;

/**
 * 余额相关操作
 */
class FundLib
{
    use \traits\controller\Jump;
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

            static::$instance = new FundLib();
        }
        return static::$instance;
    }

    /**
     * 根据账户来查找月账户
     * @param $params
     * @return array
     * @throws \think\exception\DbException
     */
    public function getAccountThirdList($params)
    {
        $account_model = new Account();
        $auth          = Auth::instance();
        // 第三方付款账户
        $where = ['acc.type' => $params['type'] ?? 1];

        if (FilterLib::isNum($params, 'status')) $where['acc.status'] = $params['status'];

        if (FilterLib::isNotEmptyArr($params, 'company_id')) $where['acc.company_id'] = ['IN', $params['company_id']];
        if (FilterLib::isNotEmptyArr($params, 'platform')) $where['acc.type_attr'] = ['IN', $params['platform']];
        if (FilterLib::isNotEmptyArr($params, 'ebay_site')) $where['acc.account_mark'] = ['IN', $params['ebay_site']];

        // 这个是search value
        if (FilterLib::isNotEmptyStr($params, 'account_name')) {
            $_tmp_arr = preg_split('/[\s,，]+/', trim($params['account_name']));

            $where['acc.account|acc.title'] = ['LIKE', array_map(function ($val) {
                return "%{$val}%";
            }, $_tmp_arr), 'OR'];

        }
        if (FilterLib::isNotEmptyStr($params, 'type_scene')) $where['acc.type_scene'] = $params['type_scene'];
        if (FilterLib::isNum($params, 'type_attr')) $where['acc.type_attr'] = $params['type_attr'];
        // 账户管理员
        if (FilterLib::isNotEmptyStr($params, 'admin_id')) {
            // 管理员管理的所有账户
            $adminAccountId = AccountAdmin::where(['admin_id' => ['IN', $params['admin_id']]])->column('account_id');
            if (!empty($adminAccountId)) $where['acc.id'] = ['IN', $adminAccountId];
        } // 如果是非超级管理员, 只能看见自己管理的卡
        elseif (!$auth->isSuperAdmin()) {
            $adminAccountId = AccountAdmin::where(['admin_id' => ['IN', $auth->id]])->column('account_id');
            if (!empty($adminAccountId)) $where['acc.id'] = ['IN', $adminAccountId];
        }

        $fields = 'acc.*';
        $count  = $account_model->alias('acc')->where($where)->count();
        if ($count <= 0) {
            return ['list' => [], 'count' => $count, 'page' => ''];
        }

        $list = $account_model->alias('acc')->field($fields)->where($where)->order('acc.id desc')->paginate($params['ps']);
        if (request()->get('debug') == 'sql') {
            echo $account_model->getLastSql() . PHP_EOL;
        }

        $returnData = [];
        foreach ($list as $val) {
            $funds   = $val->funds ? $val->funds->toArray() : [];
            $retData = $val->toArray();
            if (!empty($funds)) {
                $accountFunds = [];
                foreach ($funds as $fund) {
                    $accountFunds[$fund['account_currency']][] = $fund;
                }
                $retData['funds'] = $accountFunds;
            } else {
                $retData['funds'] = [];
            }
            $returnData[] = $retData;
        }

        return ['list' => $returnData, 'count' => $count, 'page' => $list->render()];
    }

    /**
     * 获取店铺账户余额列表
     * @author lamkakyun
     * @date 2018-11-16 15:17:10
     * @return array
     */
    public function getAccountBalanceList($params)
    {
        $account_model      = new Account();
        $account_fund_model = new AccountFund();

        $auth = Auth::instance();

        $where = ['acc.type' => $params['type'] ?? 1, 'status' => 1];

        $funds_start = $params['funds_start'] ?? '';
        $funds_end   = $params['funds_end'] ?? '';

        if (FilterLib::isNotEmptyArr($params, 'company_id')) $where['acc.company_id'] = ['IN', $params['company_id']];
        if (FilterLib::isNotEmptyArr($params, 'account_ids')) $where['acc.id'] = ['IN', $params['account_ids']];
        if (FilterLib::isNotEmptyArr($params, 'platform')) $where['acc.type_attr'] = ['IN', $params['platform']];
        if (FilterLib::isNotEmptyArr($params, 'ebay_site')) $where['acc.account_mark'] = ['IN', $params['ebay_site']];
        if (FilterLib::isNotEmptyArr($params, 'currency_type')) $where['f.account_currency'] = ['IN', $params['currency_type']];

        if (FilterLib::isNotEmptyArr($params, 'account_type')) {
            $third_pay_account_type      = ToolsLib::getInstance()->getThirdPayType();
            $third_pay_account_type_flip = array_flip($third_pay_account_type);
            $_tmp_keys1                  = array_values($third_pay_account_type);

            $all_banks      = ToolsLib::getInstance()->getAllBanks();
            $all_bank_names = array_column($all_banks, 'bank_name');

            $_type_attr = [];
            $_bank_name = [];

            foreach ($params['account_type'] as $v) {
                if (in_array($v, $_tmp_keys1)) {
                    $_type_attr[] = $third_pay_account_type_flip[$v];
                }
                if (in_array($v, $all_bank_names)) {
                    $_bank_name[] = $v;
                }
            }

            if (!empty($_type_attr)) $where['type_attr|bank_name'] = ['IN', array_merge($_type_attr, $_bank_name)];
        }


        // 这个是search value
        if (FilterLib::isNotEmptyStr($params, 'account_name')) {
            $_tmp_arr                       = preg_split('/[\s,，]+/', trim($params['account_name']));
            $where['acc.account|acc.title'] = ['LIKE', array_map(function ($val) {
                return "%{$val}%";
            }, $_tmp_arr), 'OR'];
        }
        if (FilterLib::isNotEmptyStr($params, 'type_scene')) $where['acc.type_scene'] = $params['type_scene'];

        // 余额区间
        if ($funds_start != '') $where['f.account_funds'] = ['EGT', $funds_start];
        if ($funds_end != '') $where['f.account_funds'] = ['ELT', $funds_end];
        if ($funds_start != '' && $funds_end != '') $where['f.account_funds'] = [['EGT', $funds_start], ['ELT', $funds_end]];

        // 账户管理员
        if (FilterLib::isNotEmptyStr($params, 'admin_id')) {
            // 管理员管理的所有账户
            $adminAccountId = AccountAdmin::where(['admin_id' => ['IN', $params['admin_id']]])->column('account_id');
            if (!empty($adminAccountId)) {
                if (isset($where['acc.id'])) {
                    $where['acc.id'] = ['IN', array_intersect($adminAccountId, $where['acc.id'][1])];
                } else {
                    $where['acc.id'] = ['IN', $adminAccountId];
                }
            }
        } // 如果是非超级管理员, 只能看见自己管理的卡
        elseif (!$auth->isSuperAdmin()) {
            $adminAccountId = AccountAdmin::where(['admin_id' => ['IN', $auth->id]])->column('account_id');
            if (!empty($adminAccountId)) {
                if (isset($where['acc.id'])) {
                    $where['acc.id'] = ['IN', array_intersect($adminAccountId, $where['acc.id'][1])];
                } else {
                    $where['acc.id'] = ['IN', $adminAccountId];
                }
            }
        }


        $fields   = 'acc.id, f.id as f_id, acc.type_attr as platform, acc.title, acc.account, acc.type_scene, acc.account_mark as site, f.account_currency, f.into_confirm, f.out_confirm, f.account_funds,acc.bank_name, f.fund_name';
        $join_arr = [['db_account_funds f', 'f.account_id = acc.id']];

        $count = $account_model->alias('acc')->join($join_arr)->where($where)->count();

        if (request()->get('debug') == 'sql') {
            echo $account_model->getLastSql() . PHP_EOL;
        }

        if ($count <= 0) {
            return ['list' => ['data' => []], 'count' => 0, 'page' => ''];
        }
        $list = $account_model->alias('acc')->join($join_arr)->field($fields)->where($where)->order('acc.id desc')->paginate($params['ps']);
        if (request()->get('debug') == 'sql') {
            echo $account_model->getLastSql() . PHP_EOL;
        }

        return ['list' => $list->toArray(), 'count' => $count, 'page' => $list->render()];
    }


    /**
     * 资金流水号
     * @author lamkakyun
     * @date 2018-11-19 11:05:51
     * @return string
     */
    public function genFlowNumber()
    {
        // 时间紧急，毫秒时间+6位随机数
        $id = date('Ymd') . intval(microtime(true) * 1000) . random_int(100000, 999999);
        return $id;

        // 为了保证生成的id 的唯一性，我们将  id 放到 cache， 保存1秒或者以上(比如放到redis 的set 中)
        // 然后，检测是否重复，重复则，重新生成
    }


    /**
     * 转账操作
     * @author lamkakyun
     * @date 2018-11-19 11:20:06
     * @return array
     */
    public function transfer($params)
    {
        $account_model             = new Account();
        $account_fund_model        = new AccountFund();
        $account_fund_detail_model = new AccountFundDetail();

        // todo： 检测
        $from_fund    = $account_fund_model->where(['id' => $params['title']])->find();
        $from_account = $account_model->where(['id' => $from_fund['account_id']])->find();

        if (isset($params['money_amount'])) {
            $params['money_amount'] = replace_money($params['money_amount']);
        } else {
            return json(['code' => -1, 'msg' => '请填写转账金额']);
        }

        if (!$from_account || !$from_fund) return json(['code' => -1, 'msg' => '请选择付款的余额账户']);
        if (!FilterLib::isNum($params, 'transfer_type')) return json(['code' => -1, 'msg' => '请选择转账类型']);
        if (!FilterLib::isNum($params, 'title')) return json(['code' => -1, 'msg' => '请选择付款账户名称']);
        if (!FilterLib::isPrice($params, 'money_amount') || $params['money_amount'] <= 0) return json(['code' => -1, 'msg' => '转账金额格式错误']);
        // if (!isset($params['currency']) || !in_array($params['currency'], ToolsLib::getInstance()->getAllCurrencyType())) return json(['code' => -1, 'msg' => '请选择货币']);
        if (isset($params['transaction_fee']) && empty($params['transaction_fee'])) $params['transaction_fee'] = 0;
        if (!FilterLib::isPrice($params, 'transaction_fee') || $params['transaction_fee'] < 0) return json(['code' => -1, 'msg' => '请填写正确的手续费']);
        if ($params['money_amount'] <= $params['transaction_fee']) return json(['code' => -1, 'msg' => '手续费不能大于等于转账金额']);

        if ($params['transfer_type'] == '1') {
            if (!FilterLib::isNum($params, 'r_title')) return json(['code' => -1, 'msg' => '请选择收款账户名称']);
            if ($params['r_title'] == $params['title']) return json(['code' => -1, 'msg' => '付款账户和收款账户不能是同一个']);
            if (!FilterLib::isNotEmptyStr($params, 'r_remark')) return json(['code' => -1, 'msg' => '请填写收款备注']);
        } else if ($params['transfer_type'] == '2') {
            if (!FilterLib::isNum($params, 't_account_type')) return json(['code' => -1, 'msg' => '请选择账户类型']);
            if (!FilterLib::isNotEmptyStr($params, 'receipt_username')) return json(['code' => -1, 'msg' => '请填写收款姓名']);
            if (!FilterLib::isNotEmptyStr($params, 'receipt_account')) return json(['code' => -1, 'msg' => '请填写收款账户']);
            if (!FilterLib::isNotEmptyStr($params, 't_remark')) return json(['code' => -1, 'msg' => '请填写收款备注(2)']);
        }


        // 1 => '付款' 2 => '收款'
        // if ($from_account['type_scene'] == 2) return json(['code' => -1, 'msg' => "{$from_account['title']}是收款账户，不能转账"]);

        //if ($params['money_amount'] > $from_account['day_quota']) return json(['code' => -1, 'msg' => "超额！账户【{$from_account['title']}】每日转账额度为{$from_account['day_quota']}{$from_fund['account_currency']}"]);

        // $usd_money_amount = ToolsLib::getInstance()->convertToUSADollar($params['money_amount'], $params['currency']);
        // $usd_trans_fee = ToolsLib::getInstance()->convertToUSADollar($params['transaction_fee'], $params['currency']);

        // if ($from_fund['account_currency'] != $params['currency']) return json(['code' => -1, 'msg' => '账户余额币种不对应']);

        if ($params['money_amount'] > $from_fund['account_funds']) return json(['code' => -1, 'msg' => '账户余额不足']);

        // TODO: 1.update余额 2.update 转出待确认 3.update 转入待确认 4.add account_funds_detail
        $account_fund_model->startTrans();

        if ($params['transfer_type'] == '1') {
            $to_fund    = $account_fund_model->where(['id' => $params['r_title']])->find();
            $to_account = $account_model->where(['id' => $to_fund['account_id']])->find();

            if ($from_fund['account_currency'] != $to_fund['account_currency']) return json(['code' => -1, 'msg' => '付款和收款账户余额的货币不一致']);

            // todo: 将转账金额转换成 收款账户的 货币的 对应金额
            $target_money_amount = ToolsLib::getInstance()->convertCurrency($params['money_amount'], $from_fund['account_currency'], $to_fund['account_currency']);

            if (!$to_account || !$to_fund) return json(['code' => -1, 'msg' => '收款账户异常']);

            $add_fund_detail_data = [
                'number'                 => $this->genFlowNumber(),
                'type'                   => '0', // 0=对内转账 1=对外转账
                'from_account_id'        => $from_fund['account_id'],
                'from_account_funds_id'  => $from_fund['id'],
                'from_account'           => $from_account['account'],
                'from_currency'          => $from_fund['account_currency'],
                'from_account_funds'     => $from_fund['account_funds'],
                'from_account_type'      => $from_account['type'],
                'from_account_type_attr' => $from_account['type_attr'],
                'from_amount'            => $params['money_amount'],
                'to_account_id'          => $to_fund['account_id'],
                'to_account_funds_id'    => $to_fund['id'],
                'to_account'             => $to_account['account'],
                'to_username'            => '', // 内部转账没有这个字段
                'to_account_type'        => $to_account['type'],
                'to_account_type_attr'   => $to_account['type_attr'],
                'account_currency'       => $to_fund['account_currency'],
                'amount'                 => $target_money_amount, // 金额是，经过货币转换后的金额
                'fees'                   => $params['transaction_fee'],
                'confirm_amount'         => 0, // 确认交易金额 在 确认操作时填写
                'status'                 => 0, // 状态 0=待确认 1=已确认
                'createuser_id'          => $params['auth_id'],
                'createuser'             => $params['auth_username'],
                'createtime'             => time(),
                'remarks'                => $params['r_remark'],
            ];

            // $ret_add = $account_fund_detail_model->insert($add_fund_detail_data);
            $where_to_save = ['id' => $from_fund['id']];
            $save_data     = [
                // 'account_funds' => $from_fund['account_funds'] - $params['money_amount'], // 转账和提现是 确认时才扣这个余额
                'out_confirm' => $from_fund['out_confirm'] + $params['money_amount'],
            ];


            $where_to_save_2 = ['id' => $to_fund['id']];
            $save_data_2     = [
                'into_confirm' => $to_fund['into_confirm'] + $target_money_amount,
            ];

            $ret_add_fund_detail = $account_fund_detail_model->insert($add_fund_detail_data);
            $ret_update_fund     = $account_fund_model->where($where_to_save)->update($save_data);
            $ret_update_fund_2   = $account_fund_model->where($where_to_save_2)->update($save_data_2);

        } elseif ($params['transfer_type'] == '2') {
            // 对外转账，默认就是确认的
            $add_fund_detail_data = [
                'number'                 => $this->genFlowNumber(),
                'type'                   => '1', // 0=对内转账 1=对外转账 2=提现 3=平账 4=收款 5=换汇
                'from_account_id'        => $from_fund['account_id'],
                'from_account_funds_id'  => $from_fund['id'],
                'from_account'           => $from_account['account'],
                'from_currency'          => $from_fund['account_currency'],
                'from_account_funds'     => $from_fund['account_funds'],
                'from_account_type'      => $from_account['type'],
                'from_account_type_attr' => $from_account['type_attr'],
                'from_amount'            => $params['money_amount'],
                'to_account'             => $params['receipt_account'],
                'to_username'            => $params['receipt_username'], // 内部转账没有这个字段
                'to_account_type'        => $params['t_account_type'],
                'account_currency'       => $from_fund['account_currency'],
                'amount'                 => $params['money_amount'],
                'fees'                   => $params['transaction_fee'],
                'confirm_amount'         => $params['money_amount'] - $params['transaction_fee'], // 确认交易金额 在 确认操作时填写
                'status'                 => 1, // 状态 0=待确认 1=已确认
                'createuser_id'          => $params['auth_id'],
                'createuser'             => $params['auth_username'],
                'createtime'             => time(),
                'remarks'                => $params['t_remark'],
                'confirmuser'            => $params['auth_username'],
                'confirmuser_id'         => $params['auth_id'],
                'confirmtime'            => time(),
            ];

            $where_to_save = ['id' => $from_fund['id']];
            $save_data     = [
                'account_funds' => $from_fund['account_funds'] - $params['money_amount'],
                'out_totals'    => $from_fund['out_totals'] + $params['money_amount'],
            ];

            $ret_add_fund_detail = $account_fund_detail_model->insert($add_fund_detail_data);
            $ret_update_fund     = $account_fund_model->where($where_to_save)->update($save_data);
            $ret_update_fund_2   = true;

            // todo: 添加对外转账模板
            $tpl_model = new AccountTemplate();
            $tpl_count = $tpl_model->where(['account' => $params['receipt_account'], 'account_user' => $params['receipt_username'], 'type' => $params['t_account_type']])->count();
            if (!$tpl_count) {
                $_add_tpl = [
                    'title'         => $params['receipt_username'] . "({$params['receipt_account']})",
                    'account'       => $params['receipt_account'],
                    'account_user'  => $params['receipt_username'],
                    'type'          => $params['t_account_type'],
                    'type_attr'     => $params['bank_name'] ?? '',
                    'createuser_id' => $params['auth_id'],
                    'createuser'    => $params['auth_username'],
                    'createtime'    => time(),
                ];

                $tpl_model->insert($_add_tpl);
            }

        }

        if (!$ret_update_fund || !$ret_update_fund_2 || !$ret_add_fund_detail) {
            $account_fund_model->rollback();
            return json(['code' => -1, 'msg' => '操作失败']);
        }

        $account_fund_model->commit();
        return json(['code' => 0, 'msg' => '操作成功']);
    }


    /**
     * 换汇(和转账功能差不多，只是要转换货币，和记录第三方的信息)
     * @author lamkakyun
     * @date 2018-12-03 17:33:34
     * @return array
     */
    public function exchangeMoney($params)
    {
        $account_model             = new Account();
        $account_fund_model        = new AccountFund();
        $account_fund_detail_model = new AccountFundDetail();

        // todo： 检测
        $from_fund    = $account_fund_model->where(['id' => $params['title']])->lock(true)->find();
        $from_account = $account_model->where(['id' => $from_fund['account_id']])->find();

        if (FilterLib::isNotEmptyStr($params, 'money_amount')) {
            $params['money_amount'] = replace_money($params['money_amount']);
        } else {
            return json(['code' => -1, 'msg' => '请填写转账金额']);
        }

        if (!FilterLib::isPrice($params, 'rates') || $params['rates'] < 0) return json(['code' => -1, 'msg' => '汇率不正确']);
        if (!FilterLib::isNotEmptyStr($params, 'third_name')) return json(['code' => -1, 'msg' => '第三方名称不能为空']);

        if (!$from_account || !$from_fund) return json(['code' => -1, 'msg' => '请选择付款的余额账户']);
        if (!FilterLib::isNum($params, 'title')) return json(['code' => -1, 'msg' => '请选择付款账户名称']);
        if (!FilterLib::isPrice($params, 'money_amount') || $params['money_amount'] <= 0) return json(['code' => -1, 'msg' => '转账金额格式错误']);
        if (isset($params['transaction_fee']) && empty($params['transaction_fee'])) $params['transaction_fee'] = 0;
        if (!FilterLib::isPrice($params, 'transaction_fee') || $params['transaction_fee'] < 0) return json(['code' => -1, 'msg' => '手续费不正确']);
        if ($params['money_amount'] <= $params['transaction_fee']) return json(['code' => -1, 'msg' => '手续费不能大于等于转账金额']);

        if (!FilterLib::isNum($params, 'r_title')) return json(['code' => -1, 'msg' => '请选择收款账户名称']);
        if ($params['r_title'] == $params['title']) return json(['code' => -1, 'msg' => '付款账户和收款账户不能是同一个']);
        if (!FilterLib::isNotEmptyStr($params, 'r_remark')) return json(['code' => -1, 'msg' => '请填写收款备注']);
        if ($params['money_amount'] > $from_fund['account_funds']) return json(['code' => -1, 'msg' => '账户余额不足']);


        // TODO: 1.update余额 2.update 转出待确认 3.update 转入待确认 4.add account_funds_detail
        $account_fund_model->startTrans();

        $to_fund    = $account_fund_model->where(['id' => $params['r_title']])->lock(true)->find();
        $to_account = $account_model->where(['id' => $to_fund['account_id']])->find();

        // todo: 将转账金额转换成 收款账户的 货币的 对应金额
        // $to_note = "HL金额:{$params['money_amount']} {$from_fund['account_currency']},汇率:{$params['rates']},手续费:{$params['transaction_fee']} {$from_fund['account_currency']}";

        $to_note             = "HL:{$params['third_name']}";
        $target_money_amount = round(($params['money_amount'] - $params['transaction_fee']) * $params['rates'], 4);

        if (!$to_account || !$to_fund) {
            $account_fund_model->rollback();
            return json(['code' => -1, 'msg' => '收款账户异常']);
        }

        $add_fund_detail_data = [
            'number'                 => $this->genFlowNumber(),
            'type'                   => '5', // 0=对内转账 1=对外转账
            'from_account_id'        => $from_fund['account_id'],
            'from_account_funds_id'  => $from_fund['id'],
            'from_account'           => $from_account['account'],
            'from_currency'          => $from_fund['account_currency'],
            'from_account_funds'     => $from_fund['account_funds'],
            'from_account_type'      => $from_account['type'],
            'from_account_type_attr' => $from_account['type_attr'],
            'from_amount'            => $params['money_amount'],
            'to_account_funds'       => $to_fund['account_funds'],
            'to_account_id'          => $to_fund['account_id'],
            'to_account_funds_id'    => $to_fund['id'],
            'to_account'             => $to_account['account'],
            'to_username'            => '', // 内部转账没有这个字段
            'to_account_type'        => $to_account['type'],
            'to_account_type_attr'   => $to_account['type_attr'],
            'to_note'                => $to_note,
            'account_currency'       => $to_fund['account_currency'],
            'amount'                 => $target_money_amount, // 金额是，经过货币转换后的金额
            'rates'                  => $params['rates'],
            'fees'                   => $params['transaction_fee'],
            'confirm_amount'         => 0, // 确认交易金额 在 确认操作时填写
            'status'                 => 0, // 状态 0=待确认 1=已确认
            'createuser_id'          => $params['auth_id'],
            'createuser'             => $params['auth_username'],
            'createtime'             => time(),
            'remarks'                => $params['r_remark'],
        ];

        // 转出待确认
        $where_from_save = ['id' => $from_fund['id']];
        $save_from_data  = [
            'out_confirm' => $from_fund['out_confirm'] + $params['money_amount'],
        ];

        // 转入待确认
        $where_to_save = ['id' => $to_fund['id']];
        $save_to_data  = [
            'into_confirm' => $to_fund['into_confirm'] + $target_money_amount,
        ];

        $ret_add_fund_detail = $account_fund_detail_model->insert($add_fund_detail_data);
        $ret_update_fund     = $account_fund_model->where($where_from_save)->update($save_from_data);
        $ret_update_fund_2   = $account_fund_model->where($where_to_save)->update($save_to_data);

        if (!$ret_update_fund || !$ret_update_fund_2 || !$ret_add_fund_detail) {
            $account_fund_model->rollback();
            return json(['code' => -1, 'msg' => '操作失败']);
        }

        $account_fund_model->commit();
        return json(['code' => 0, 'msg' => '操作成功']);
    }


    /**
     * 对接ERP转账
     * @author lamkakyun
     * @date
     * @return void
     */
    public function erpTransfer($params)
    {
        $account_model     = new Account();
        $fund_model        = new AccountFund();
        $fund_detail_model = new AccountFundDetail();

        $fund_model->startTrans();

        // TODO: 检测 FMS 对应 账户是否存在
        $where_fund = ['id' => $params['fund_id']];
        $fund_info  = $fund_model->where($where_fund)->find();
        if (!$fund_info) return json(['code' => -1, 'msg' => '余额账户异常']);

        $fund_info    = $fund_info->toArray();
        $account_info = $account_model->where(['id' => $fund_info['account_id']])->find()->toArray();

        if ($params['account_currency'] == 'RMB') $params['account_currency'] = 'CNY';

        if (!$fund_info || !$account_info) return json(['code' => -1, 'msg' => '余额账户异常']);
        if ($fund_info['account_currency'] != $params['account_currency']) return json(['code' => -1, 'msg' => '货币类型不一致', 'more_data' => $params]);

        // 如果账户不是转账卡, 则不同步流水, 只要同步转账卡的流水就可以
        if ($account_info['type'] != 3 && in_array(intval($account_info['type_attr']), [1, 2, 3, 4, 5, 7])) {
            return json(['code' => 0, 'msg' => '第三方转账卡不同步流水']);
        }

        // 查找重复性
        $where  = [
            'type'                  => 1,
            'from_account_id'       => $fund_info['account_id'],
            'from_account_funds_id' => $fund_info['id'],
            'amount'                => $params['amount'],
            'to_account'            => $params['to_account'] ?? '',
            'remarks'               => $params['remarks'],
            'createuser'            => 'system',
        ];
        $hasOne = $fund_detail_model->where($where)->find();
        if (!empty($hasOne)) {
            $fund_model->rollback();
            return json(['code' => 0, 'msg' => '重复流水', 'data' => $where]);
        }

        // TODO: 1.添加流水数据 2.更新账号余额
        $save_fund_data  = ['account_funds' => ($fund_info['account_funds'] - $params['amount'])];
        $ret_update_fund = $fund_model->where($where_fund)->update($save_fund_data);
        $ret_update_fund = $ret_update_fund === false ? false : true;

        if (!$ret_update_fund) {
            $fund_model->rollback();
            return json(['code' => -1, 'msg' => '更新账户余额失败']);
        }

        $pay_time = $params['pay_time'] ?? 0;

        $add_fund_detail = [
            'number'                 => $this->genFlowNumber(),
            'type'                   => 1, // 0=对内转账 1=对外转账 2=提现 3=平账
            'from_account_id'        => $fund_info['account_id'],
            'from_account_funds_id'  => $fund_info['id'],
            'from_account'           => $fund_info['account_name'],
            'from_currency'          => $fund_info['account_currency'],
            'from_account_funds'     => $fund_info['account_funds'],
            'from_amount'            => $params['amount'],
            'from_account_type'      => $account_info['type'],
            'from_account_type_attr' => $account_info['type_attr'],
            'to_account'             => $params['to_account'] ?? '', // 可能为空，允许为空
            'to_username'            => $params['to_username'] ?? '', // 可能为空，允许为空
            'account_currency'       => $fund_info['account_currency'],
            'amount'                 => $params['amount'],
            'confirm_amount'         => $params['amount'],
            'status'                 => 1,
            'createuser'             => 'system',
            'createuser_id'          => 1,
            'createtime'             => $pay_time > 0 ? $pay_time : time(),
            'confirmuser'            => 'system',
            'confirmuser_id'         => 1,
            'confirmtime'            => $pay_time > 0 ? $pay_time : time(),
            'remarks'                => $params['remarks'],
        ];

        $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);

        if (!$ret_add_fund_detail || !$ret_update_fund) {
            $fund_model->rollback();
            return json(['code' => -1, 'msg' => '操作失败']);
        }

        $fund_model->commit();
        return json(['code' => 0, 'msg' => '操作成功 ']);
    }

    /**
     * 收款
     * @param $params
     * @return \think\response\Json
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function receipt($params)
    {
        $account_model             = new Account();
        $account_fund_model        = new AccountFund();
        $account_fund_detail_model = new AccountFundDetail();

        $amount     = replace_money($params['amount']);
        $remarks    = trim($params['remarks']);
        $fee        = 0;
        $funds_id   = $params['funds_id'];
        $account_id = $params['account_id'];

        if (!FilterLib::isFloat($amount) || $amount <= 0) {
            return $this->error('收款金额格式有误(1)');
        }
        $fundsData = AccountFund::get($funds_id);
        if (empty($fundsData)) {
            return $this->error('收款账户异常(2)');
        }
        $accountData = Account::get($account_id);
        if (empty($accountData)) {
            return $this->error('收款账户异常(3)');
        }
        $account_fund_model->startTrans();

        $account_funds = $fundsData->account_funds + $amount;
        $into_totals   = $fundsData->into_totals + $amount;
        // 增加账户资金
        if (!$fundsData->save(['account_funds' => $account_funds, 'into_totals' => $into_totals])) {
            $account_fund_model->rollback();
            return $this->error(sprintf("更新异常:%s", $fundsData->getError()));
        }
        // 增加流水而且是已经确认的
        $fundsDetail = [
            'number'                 => $this->genFlowNumber(),
            'type'                   => 4, // 0=对内转账 1=对外转账 2=提现 3=平账 4=收款
            'from_account_id'        => 0,
            'from_account_funds_id'  => 0,
            'from_account'           => '',
            'from_account_type'      => 0,
            'from_account_type_attr' => 0,

            'to_account_id'        => $account_id,
            'to_account'           => $accountData->account,
            'to_account_funds_id'  => $funds_id,
            'to_account_type'      => $accountData->type,
            'to_account_type_attr' => $accountData->type_attr,
            'account_currency'     => $fundsData->account_currency,
            'amount'               => $amount,
            'fees'                 => $fee,
            'confirm_amount'       => $amount, // 确认交易金额 在 确认操作时填写
            'status'               => 1,       // 状态 0=待确认 1=已确认
            'createuser_id'        => $params['auth_id'],
            'createuser'           => $params['auth_username'],
            'createtime'           => time(),
            'remarks'              => $remarks,
            'confirmuser'          => $params['auth_username'],
            'confirmuser_id'       => $params['auth_id'],
            'confirmtime'          => time(),
        ];
        if (!$account_fund_detail_model->insert($fundsDetail)) {
            $account_fund_model->rollback();
            return $this->error(sprintf("更新异常:%s", $account_fund_detail_model->getError()));
        }
        $account_fund_model->commit();
        return $this->success('操作成功');
    }

    /**
     * 平账
     * @author lamkakyun
     * @date 2018-11-19 16:00:56
     * @return array
     */
    public function fixBalance($params)
    {
        $account_model             = new Account();
        $account_fund_model        = new AccountFund();
        $account_fund_detail_model = new AccountFundDetail();

        // TODO: 需求，如果true_balance 为空,不提示金额错误，跳过，不对其进行平帐
        $tmp_params = $params;
        foreach ($tmp_params['true_balance'] as $key => $value) {
            if (trim($value) === '') {
                unset($params['funds_ids'][$key]);
                unset($params['true_balance'][$key]);
                unset($params['account_ids'][$key]);
            }
        }

        $fund_ids = $params['funds_ids'];

        if (!FilterLib::isNotEmptyArr($params, 'true_balance')) return json(['code' => -1, 'msg' => '参数错误']);

        foreach ($params['true_balance'] as &$v) {
            $v = replace_money($v);
            if (!FilterLib::isFloat($v)) return json(['code' => -1, 'msg' => '实际余额错误']);
        }

        if (!FilterLib::isNotEmptyStr($params, 'fix_reason')) return json(['code' => -1, 'msg' => '请填写平账原因']);

        // TODO: 1.update account 2.add fund detail
        $account_fund_model->startTrans();

        foreach ($fund_ids as $k => $fund_id) {
            $tmp_where = ['id' => $fund_id];

            $_account_fund_info = $account_fund_model->where($tmp_where)->find();
            $_account_info      = $account_model->where(['id' => $_account_fund_info['account_id']])->find();

            if (!$_account_info || !$_account_fund_info) {
                $account_fund_model->rollback();
                return json(['code' => -1, 'msg' => '账户信息异常']);
            }

            $_true_balance = $params['true_balance'][$k];

            $tmp_save_data = ['account_funds' => $_true_balance];

            $_ret_update_fund = $account_fund_model->where($tmp_where)->update($tmp_save_data);
            if ($_ret_update_fund === false) {
                $account_fund_model->rollback();
                return json(['code' => -1, 'msg' => '操作失败']);
            }

            $_diff_amount = $_true_balance - $_account_fund_info['account_funds'];

            // 平账就相当于自己给自己转账，而且是已经确认的
            $tmp_add_fund_detail_data = [
                'number'                 => $this->genFlowNumber(),
                'type'                   => '3', // 0=对内转账 1=对外转账 2=提现 3=平账
                'from_account_id'        => $_account_info['id'],
                'from_account_funds_id'  => $_account_fund_info['id'],
                'from_account'           => $_account_info['account'],
                'from_currency'          => $_account_fund_info['account_currency'],
                'from_account_funds'     => $_account_fund_info['account_funds'],
                'from_account_type'      => $_account_info['type'],
                'from_account_type_attr' => $_account_info['type_attr'],
                'to_account_id'          => $_account_info['id'],
                'to_account'             => $_account_info['account'],
                'to_account_funds_id'    => $_account_fund_info['id'],
                // 'to_username' => '', // 平账没有这个字段
                'to_account_type'        => $_account_info['type'],
                'to_account_type_attr'   => $_account_info['type_attr'],
                'account_currency'       => $_account_fund_info['account_currency'],
                'amount'                 => $_diff_amount,
                'fees'                   => 0,
                'confirm_amount'         => $_diff_amount, // 确认交易金额 在 确认操作时填写
                'status'                 => 1, // 状态 0=待确认 1=已确认
                'createuser_id'          => $params['auth_id'],
                'createuser'             => $params['auth_username'],
                'createtime'             => time(),
                'remarks'                => $params['fix_reason'],
                'confirmuser'            => $params['auth_username'],
                'confirmuser_id'         => $params['auth_id'],
                'confirmtime'            => time(),
            ];

            $ret_add_detail = $account_fund_detail_model->insert($tmp_add_fund_detail_data);
            if (!$ret_add_detail) {
                $account_fund_model->rollback();
                return json(['code' => -1, 'msg' => '操作失败(2)']);
            }
        }

        $account_fund_model->commit();
        return json(['code' => 0, 'msg' => '操作成功']);
    }


    /**
     * 提现
     * @author lamkakyun
     * @date 2018-11-19 17:00:49
     * @return array
     */
    public function withdraw($params)
    {
        $account_model             = new Account();
        $account_fund_model        = new AccountFund();
        $account_fund_detail_model = new AccountFundDetail();

        $fund_ids = explode(',', $params['fund_id']);

        if (!FilterLib::isNotEmptyArr($params, 'withdraw_money')) return json(['code' => -1, 'msg' => '参数错误(2)']);

        foreach ($params['withdraw_money'] as &$v) {
            $v = replace_money($v);
            if (!FilterLib::isFloat($v)) return json(['code' => -1, 'msg' => '提现金额错误']);
        }

        if (!FilterLib::isNotEmptyStr($params, 'withdraw_remark')) return json(['code' => -1, 'msg' => '请填写备注']);

        // todo:检测 账户 数据是否正常
        $acc_fund_list = $account_fund_model->where(['id' => ['IN', $fund_ids]])->select()->toArray();
        if (count($acc_fund_list) != count($fund_ids)) return json(['code' => -1, 'msg' => '账号数据异常']);

        $currency_list = array_column($acc_fund_list, 'account_currency');

        if (count(array_unique($currency_list)) > 1) return json(['code' => -1, 'msg' => '提现失败，账户的货币类型不一致']);

        if (!FilterLib::isNum($params, 'withdraw_to')) return json(['code' => -1, 'msg' => '请选择目标账户']);

        if (in_array($params['withdraw_to'], $fund_ids)) return json(['code' => -1, 'msg' => '目标账户和当前账户重合']);

        $withdraw_account_fund_info = $account_fund_model->where(['id' => $params['withdraw_to']])->find()->toArray();
        $withdraw_account_info      = $account_model->where(['id' => $withdraw_account_fund_info['account_id']])->find()->toArray();

        if (!$withdraw_account_info || !$withdraw_account_fund_info) return json(['code' => -1, 'msg' => '目标账户数据异常']);

        if ($currency_list[0] != $withdraw_account_fund_info['account_currency']) return json(['code' => -1, 'msg' => '转账账户 与 目标账户货币类型不一致']);

        $account_fund_model->startTrans();

        foreach ($fund_ids as $k => $fund_id) {
            $tmp_where = ['id' => $fund_id];

            $_account_fund_info         = $account_fund_model->where($tmp_where)->find();
            $_account_info              = $account_model->where(['id' => $_account_fund_info['account_id']])->find();
            $withdraw_account_fund_info = $account_fund_model->where(['id' => $params['withdraw_to']])->find()->toArray();

            if (!$_account_info || !$_account_fund_info) {
                $account_fund_model->rollback();
                return json(['code' => -1, 'msg' => '账户信息异常']);
            }

            $_withdraw_money = $params['withdraw_money'][$k];
            $_withdraw_fee   = $_withdraw_money * $_account_info['out_rate'];

            if ($_withdraw_money > $_account_fund_info['account_funds']) {
                $account_fund_model->rollback();
                return json(['code' => -1, 'msg' => "账户{$_account_info['account']}提现余额不足"]);
            }
            if ($_withdraw_money <= 0) {
                $account_fund_model->rollback();
                return json(['code' => -1, 'msg' => "账户{$_account_info['account']}提现余额不正确"]);
            }

            // TODO: 1.update account balance 2.update fund 3.add fund detail
            // 更新 付款账户，（无需转换货币）
            $tmp_where     = ['id' => $fund_id];
            $tmp_save_data = [
                // 'account_funds' => ($_account_fund_info['account_funds'] - $_withdraw_money), 
                'out_confirm' => $_account_fund_info['out_confirm'] + $_withdraw_money,
                'updatetime'  => time(),
            ];

            $ret_update_1 = $account_fund_model->where($tmp_where)->update($tmp_save_data);

            // 更新收款账户（需要转换货币）
            $_converted_withdraw_money = ToolsLib::getInstance()->convertCurrency($_withdraw_money, $_account_fund_info['account_currency'], $withdraw_account_fund_info['account_currency']);

            $tmp_where_2     = ['id' => $params['withdraw_to']];
            $tmp_save_data_2 = [
                'into_confirm' => $withdraw_account_fund_info['into_confirm'] + $_converted_withdraw_money,
                'updatetime'   => time(),
            ];
            $ret_update_2    = $account_fund_model->where($tmp_where_2)->update($tmp_save_data_2);

            if (!$ret_update_1 || !$ret_update_2) {
                $account_fund_model->rollback();
                return json(['code' => -1, 'msg' => "操作失败"]);
            }

            // 添加流水
            $add_fund_detail_data = [
                'number'                 => $this->genFlowNumber(),
                'type'                   => '2', // 0=对内转账 1=对外转账 2=提现 3=平账
                'from_account_id'        => $_account_info['id'],
                'from_account_funds_id'  => $_account_fund_info['id'],
                'from_account'           => $_account_info['account'],
                'from_currency'          => $_account_fund_info['account_currency'],
                'from_account_funds'     => $_account_fund_info['account_funds'],
                'from_account_type'      => $_account_info['type'],
                'from_account_type_attr' => $_account_info['type_attr'],
                'from_amount'            => $_withdraw_money,
                'to_account_funds'       => $withdraw_account_fund_info['account_funds'],
                'to_account_id'          => $withdraw_account_info['id'],
                'to_account'             => $withdraw_account_info['account'],
                'to_account_funds_id'    => $withdraw_account_fund_info['id'],
                // 'to_username' => '', // 没有这个字段
                'to_account_type'        => $withdraw_account_info['type'],
                'to_account_type_attr'   => $withdraw_account_info['type_attr'],
                'account_currency'       => $withdraw_account_fund_info['account_currency'],
                'amount'                 => $_converted_withdraw_money,
                'fees'                   => $_withdraw_fee,
                'confirm_amount'         => 0, // 确认交易金额 在 确认操作时填写
                'status'                 => 0, // 状态 0=待确认 1=已确认
                'createuser_id'          => $params['auth_id'],
                'createuser'             => $params['auth_username'],
                'createtime'             => time(),
                'remarks'                => $params['withdraw_remark'],
            ];

            $ret_add_fund_detail = $account_fund_detail_model->insert($add_fund_detail_data);

            if (!$ret_add_fund_detail) {
                $account_fund_model->rollback();
                return json(['code' => -1, 'msg' => "操作失败(2)"]);
            }
        }

        $account_fund_model->commit();
        return json(['code' => 0, 'msg' => '操作成功']);
    }


    /**
     * 确认到账
     * 情景1：收款方只收到了50% 款额，那么付款方余额要 减去( 50% 的转账金额 + 手续费)
     * 情景2：收款方收到0 元，且付款方有支付 手续费，那么付款方减去 手续费
     * 情景3：付款方 转10 元，手续费100 元（不存在）
     * @author lamkakyun
     * @date 2018-11-20 10:29:51
     * @return array
     */
    public function confirmMoneyArrival($params)
    {
        $fund_model        = new AccountFund();
        $fund_detail_model = new AccountFundDetail();
        $fund_diff_model   = new AccountFundDiff();

        if (!FilterLib::isNum($params, 'id')) return json(['code' => -1, 'msg' => '参数错误']);

        $params['confirm_money'] = replace_money($params['confirm_money']);
        if (!FilterLib::isFloat($params['confirm_money']) || !is_numeric($params['confirm_money']) || $params['confirm_money'] < 0) {
            return json(['code' => -1, 'msg' => '确认到账金额错误']);
        }
        $where_detail = ['id' => $params['id']];

        $fund_detail = $fund_detail_model->where($where_detail)->find()->toArray();
        if (!$fund_detail) return json(['code' => -1, 'msg' => '转账记录不存在']);
        if ($fund_detail['status'] == 1) return json(['code' => -1, 'msg' => '请不要重复确认']);

        // 理论上，确认金额只能少于等于 转账金额
        //if ($params['confirm_money'] > $fund_detail['amount']) return json(['code' => -1, 'msg' => "确认金额不能大于转账金额{$fund_detail['amount']}"]);

        $from_fund = $fund_model->where(['id' => $fund_detail['from_account_funds_id']])->find()->toArray();
        $to_fund   = $fund_model->where(['id' => $fund_detail['to_account_funds_id']])->find();

        if (!$from_fund || !$to_fund) return json(['code' => -1, 'msg' => "余额账户数据异常"]);

        /***********************************
         * TODO:1.update 流水明细
         *      2.update 转入账户
         *      3.update 转出账户
         *      4.add 流水差额
         ***********************************/
        $fund_detail_model->startTrans();

        // 差异金额
        $diff_money = $fund_detail['amount'] - $params['confirm_money'];

        $save_detail = [
            'status'         => 1,
            'confirm_amount' => $params['confirm_money'],
            'confirmuser'    => $params['auth_username'],
            'confirmuser_id' => $params['auth_id'],
            'confirmtime'    => time(),
        ];

        $ret_update_fund_detail = $fund_detail_model->where($where_detail)->update($save_detail);

        // 转出的总额 = 原转出的总额 + 转账金额 (需转换货币)
        $save_from_fund = [
            'account_funds' => ($from_fund['account_funds'] - $fund_detail['from_amount']),
            'out_confirm'   => ($from_fund['out_confirm'] - $fund_detail['from_amount']),
            'out_totals'    => ($from_fund['out_totals'] + $fund_detail['from_amount']),
            'updatetime'    => time(),
        ];

        // 转入的总额 = 原转入的总额 + 确认金额 (无需转换货币)
        // 注意，需要更新 余额，总转入金额
        // $to_confirm_money = ToolsLib::getInstance()->convertCurrency($params['confirm_money'], $from_fund['account_currency'], $to_fund['account_currency']);
        // $to_amount_money  = ToolsLib::getInstance()->convertCurrency($fund_detail['amount'], $from_fund['account_currency'], $to_fund['account_currency']);

        $save_to_fund = [
            'into_confirm'  => ($to_fund['into_confirm'] - $fund_detail['amount']),
            'into_totals'   => ($to_fund['into_totals'] + $params['confirm_money']),
            'account_funds' => ($to_fund['account_funds'] + $params['confirm_money']),
            'updatetime'    => time(),
        ];

        $ret_update_from_fund = $fund_model->where(['id' => $fund_detail['from_account_funds_id']])->update($save_from_fund);
        $ret_update_to_fund   = $fund_model->where(['id' => $fund_detail['to_account_funds_id']])->update($save_to_fund);

        if (!$ret_update_fund_detail || !$ret_update_from_fund || !$ret_update_to_fund) {
            $fund_detail_model->rollback();
            return json(['code' => -1, 'msg' => "操作失败"]);
        }

        if ($diff_money != 0) {
            $add_diff_data = [
                'number'           => $fund_detail['number'],
                'account_id'       => $to_fund['account_id'],
                'account_funds_id' => $to_fund['id'],
                'currency'         => $to_fund['account_currency'],
                'amount'           => $fund_detail['amount'],
                'confim_amount'    => $params['confirm_money'],
                'diff_amount'      => $diff_money,
                'createuser'       => $params['auth_username'],
                'createuser_id'    => $params['auth_id'],
                'createtime'       => time(),
            ];

            $ret_add_diff = $fund_diff_model->insert($add_diff_data);
            if (!$ret_add_diff) {
                $fund_detail_model->rollback();
                return json(['code' => -1, 'msg' => "操作失败(2)"]);
            }
        }

        $fund_detail_model->commit();
        return json(['code' => 0, 'msg' => "操作成功"]);
    }


    /**
     * 流水明细
     * @author lamkakyun
     * @date
     * @return void
     */
    public function getFlowList($params)
    {
        $fund_detail_model = new AccountFundDetail();

        $where = [];
        if (isset($params['fund_id'])) {
            $where['from_account_funds_id|to_account_funds_id'] = $params['fund_id'];
        } elseif (isset($params['account_id'])) {
            $where['from_account_id|to_account_id'] = $params['account_id'];
        }
        $type = $params['type'] ?? '';
        if ($type !== '') {
            $where['type'] = $type;
        }
        if (FilterLib::isNotEmptyStr($params, 'start_time')) $where['createtime'] = ['EGT', strtotime($params['start_time'])];
        if (FilterLib::isNotEmptyStr($params, 'end_time')) $where['createtime'] = ['ELT', strtotime($params['end_time']) + 86399];
        if (FilterLib::isNotEmptyStr($params, 'start_time') && FilterLib::isNotEmptyStr($params, 'end_time')) $where['createtime'] = [['EGT', strtotime($params['start_time'])], ['ELT', strtotime($params['end_time']) + 86399]];

        if (FilterLib::isNotEmptyStr($params, 'search_field') && FilterLib::isNotEmptyStr($params, 'search_value')) {
            $_search_value = preg_split('/[\s,，]+/', trim($params['search_value']));
            switch ($params['search_field']) {
                case 'number':
                case 'title':
                case 'account_name':
                    $where[$params['search_field']] = ['LIKE', array_map(function ($val) {
                        return "%{$val}%";
                    }, $_search_value), 'OR'];
                    break;
                case 'from_account':
                    $where['from_account|to_account'] = ['LIKE', array_map(function ($val) {
                        return "%{$val}%";
                    }, $_search_value), 'OR'];
                    break;
            }
        }
        if (isset($params['is_export'])) {
            $list = $fund_detail_model->where($where)->order('createtime DESC')->limit(20000)->select();
            $this->exportExcel($list, $params);
        }
        $count = $fund_detail_model->where($where)->count();
        if ($count <= 0) {
            return ['list' => ['data' => []], 'count' => 0, 'page' => ''];
        }
        $list = $fund_detail_model->where($where)->order('createtime DESC')->paginate($params['ps']);

        return ['list' => $list->toArray(), 'count' => $count, 'page' => $list->render()];
    }

    /**
     * 导出Excel
     * @param $list
     */
    public function exportExcel($list, $params)
    {
        $fundType = ToolsLib::getInstance()->getFundType();
        foreach ($list as $key => $value) {
            $list[$key]['type']        = $fundType[$value['type']];
            $list[$key]['createtime']  = date('Y-m-d H:i:s', $value['createtime']);
            $list[$key]['confirmtime'] = $value['confirmtime'] > 0 ? date('Y-m-d H:i:s', $value['confirmtime']) : '--';
            $list[$key]['status']      = $value['status'] == 1 ? '已确认' : '待确认';

            $money_type = '';
            if (isset($params['fund_id'])) {
                if ($params['fund_id'] == $value['from_account_funds_id']) $money_type = '支出';
                elseif ($params['fund_id'] == $value['to_account_funds_id']) $money_type = '收入';
            } elseif (isset($params['account_id'])) {
                if ($params['account_id'] == $value['from_account_id']) $money_type = '支出';
                elseif ($params['account_id'] == $value['to_account_id']) $money_type = '收入';
            }
            if ($money_type == '支出') {
                if (empty($value['from_currency'])) $list[$key]['from_currency'] = $value['account_currency'];
                if ($value['from_amount'] == 0) $list[$key]['from_amount'] = $value['amount'];
            }
            $list[$key]['money_type'] = $money_type;

            $list[$key]['in_money']                 = $money_type == '收入' ? $value['amount'] : '';
            $list[$key]['out_money']                = $money_type == '支出' ? $value['from_amount'] : '';
            $list[$key]['transaction_currency']     = $money_type == '收入' ? $value['account_currency'] : $value['from_currency'];
            $list[$key]['transaction_balance']      = $money_type == '收入' ? $value['to_account_funds'] : $value['from_account_funds'];
            $list[$key]['transaction_with_account'] = $money_type == '收入' ? $value['from_account'] : $value['to_account'];
        }

        // $headers  = [
        //     'number'           => '流水号',
        //     'type'             => '类型',
        //     'from_account'     => '转出账户',
        //     'to_account'       => '收款账户',
        //     'money_type'       => '交易类型',
        //     'amount'           => '交易金额',
        //     'account_currency' => '币种',
        //     'fees'             => '手续费',
        //     'confirm_amount'   => '确认到账金额',
        //     'createuser'       => '创建人',
        //     'createtime'       => '创建时间',
        //     'confirmuser'      => '确认人',
        //     'confirmtime'      => '确认时间',
        //     'remarks'          => '备注',
        // ];

        $headers = [
            'createtime'               => '转账日期',
            'type'                     => '操作类型',
            'number'                   => '流水单号',
            'status'                   => '状态',
            'money_type'               => '交易类型',
            'transaction_currency'     => '币种',
            'in_money'                 => '收入',
            'out_money'                => '支出',
            'transaction_balance'      => '余额',
            'fees'                     => '手续费',
            'transaction_with_account' => '对方账户',
            'confirmtime'              => '到账时间',
            'createuser'               => '操作人',
            'remarks'                  => '备注',
        ];

        $filename = "明细导出-" . date('Y-m-d');

        ToolsLib::getInstance()->exportExcel($filename, $headers, $list, $is_seq = false);
    }


    /**
     * 获取收入和支出
     * @author lamkakyun
     * @date 2019-02-13 14:49:19
     * @return void
     */
    public function getFundsIncomeAndExpend($fund_id_arr, $start_time, $end_time, $type = 'income')
    {
        $fund_detail_model = new AccountFundDetail();

        if ($type == 'income') {
            // 已确认 + 确认时间 + fund_id
            $where = ['status' => 1, 'confirmtime' => [['EGT', strtotime($start_time)], ['LT', strtotime($end_time) + 86400]], 'to_account_funds_id' => ['IN', $fund_id_arr]];

            $group_by = 'to_account_funds_id';
            $fields   = 'to_account_funds_id as fund_id, SUM(amount) as sum_amount';
        } else // expend
        {
            // 已确认 + 确认时间 + fund_id
            $where = ['status' => 1, 'confirmtime' => [['EGT', strtotime($start_time)], ['LT', strtotime($end_time) + 86400]], 'from_account_funds_id' => ['IN', $fund_id_arr]];

            $group_by = 'from_account_funds_id';
            $fields   = 'from_account_funds_id as fund_id, SUM(from_amount) as sum_amount';
        }

        $tmp = $fund_detail_model->field($fields)->where($where)->group($group_by)->select()->toArray();
        if (request()->get('debug') == 'sql') {
            echo $fund_detail_model->getLastSql() . PHP_EOL;
        }

        $data = [];
        foreach ($tmp as $k => $v) {
            $data[$v['fund_id']] = $v;
        }

        return $data;
    }


    /**
     * 获取账户余额
     * @author lamkakyun
     * @date 2019-02-13 15:33:12
     * @return void
     */
    public function getFundBalance($fund_id, $start_time, $end_time)
    {
        // $fund_detail_model = new AccountFundDetail();
        $fund_model = new AccountFund();

        $balance = $fund_model->where(['id' => $fund_id])->value('account_funds');
        return $balance;

        // $where = ['status' => 1, 'confirmtime' => [['EGT', strtotime($start_time)], ['LT', strtotime($end_time) + 86400]], 'to_account_funds_id|from_account_funds_id' => $fund_id];

        // $order_by = 'confirmtime DESC';
        // $fields = 'from_account_funds_id, to_account_funds_id, from_account_funds, to_account_funds';
        if (request()->get('debug') == 'sql') {
            echo $fund_detail_model->getLastSql() . PHP_EOL;
        }

        // $data = $fund_detail_model->field($fields)->where($where)->order($order_by)->find();

        // // 如果时间段内没有流水，那么直接取余额
        // if (!$data)
        // {
        //     $balance = $fund_model->where(['id' => $fund_id])->value('account_funds');
        //     return $balance;
        // }

        // if ($data['from_account_funds_id'] == $fund_id)
        // {
        //     return $data['from_account_funds'];
        // }
        // else
        // {
        //     return $data['to_account_funds'];
        // }
    }

}