<?php

namespace app\common\library\import;

use app\common\model\Account;
use app\common\library\Import;
use app\index\library\FundLib;
use app\common\library\FilterLib;
use app\common\model\AccountFund;
use app\common\model\AccountFundDetail;

/**
 * pingpong 导入类
 * @author lamkakyun
 * @date 2018-11-29 15:57:54
 */
class OFX extends Import
{
    /**
     * 导入 excel 流水
     * @author lamkakyun
     * @date 2018-11-29 15:58:00
     * @return array
     */
    public function importFlow($account_id, $excel_data, $params)
    {
        set_time_limit(0);
        $fund_model        = new AccountFund();
        $fund_detail_model = new AccountFundDetail();
        $account_model     = new Account();
        // requirement: 备注信息需要，记录下 每条 记录的详细信息
        $headers = $excel_data[0];


        $fail_arr = [];
        // $success_arr = [];
        $success_num = 0;
        // OFX 从第2行数据开始读取

        for ($i = 1; $i < count($excel_data); $i++) {
            $one_row_data = $excel_data[$i];

            // Credit 入账 Debit 提现 
            $date           = $one_row_data[0];
            $action_type    = strtolower(trim($one_row_data[1]));
            $first_currency = $one_row_data[2];
            $first_amount   = str_replace(',', '', $one_row_data[3]);;
            $first_fund_name     = $one_row_data[5];
            $transaction_id      = $one_row_data[7];
            $second_account_name = $one_row_data[8];
            $second_amount       = str_replace(',', '', $one_row_data[10]);;
            $rate            = $one_row_data[11];
            $second_currency = $one_row_data[12];
            $fee             = $one_row_data[13] ?: 0;

            // 为了避免重复插入，用 hash 来验证 是否同一条记录
            $hash_value = 'OFX_' . md5(implode('', $one_row_data));

            $now_time = strtotime($date);

            // 没有交易时间的流水不导入
            if (!$now_time) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '流入记录日期有误', 'row_data' => $one_row_data];
                continue;
            }

            if (!FilterLib::isFloat($first_amount)) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '金额不正确', 'row_data' => $one_row_data];
                continue;
            }

            // 构建remark
            $remark = $action_type == 'debit' ? $one_row_data[4] : sprintf("交易号(%s)", $transaction_id);

            $fund_model->startTrans();

            if ($action_type == 'debit') // 提现
            {
                if (!FilterLib::isFloat($second_amount)) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '金额不正确(2)', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                if (!$second_currency || $first_currency != $second_currency) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '提款货币不一致', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $where_from_fund    = ['account_id' => $account_id, 'account_currency' => $first_currency, 'fund_name' => $first_fund_name];
                $where_from_account = ['id' => $account_id];

                $where_to_fund = ['account_name' => $second_account_name, 'account_currency' => $second_currency];

                $from_fund = $fund_model->where($where_from_fund)->lock(true)->find();
                $to_fund   = $fund_model->where($where_to_fund)->lock(true)->find();

                // OFX提现的收款账户不是必要的
                $to_account = [];
                if ($to_fund) {
                    $where_to_account = ['id' => $to_fund['account_id']];
                    $to_account       = $account_model->where($where_to_account)->find();
                }

                $from_account = $account_model->where($where_from_account)->find();
                if (!$from_account || !$from_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '提现付款账户不存在', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $add_fund_detail = [
                    'number' => $hash_value,
                    'type'   => 2, // 0=对内转账 1=对外转账 2=提现 3=平账

                    'from_account_id'        => $from_account['id'],
                    'from_account_funds_id'  => $from_fund['id'],
                    'from_account'           => $from_account['account'],
                    'from_account_type'      => $from_account['type'],
                    'from_account_type_attr' => $from_account['type_attr'],
                    'from_amount'            => $first_amount,
                    'from_account_funds'     => $from_fund['account_funds'],
                    'from_currency'          => $from_fund['account_currency'],
                    'to_account_funds'       => $to_fund['account_funds'] ?? '0',

                    'to_account_id'        => $to_account['id'] ?? '0',
                    'to_account_funds_id'  => $to_fund['id'] ?? '0',
                    'to_account'           => $second_account_name, // 即使找不到 收款账户信息，也需要报错excel的账户信息
                    'to_account_type'      => $to_account['type'] ?? '0',
                    'to_account_type_attr' => $to_account['type_attr'] ?? '0',


                    'account_currency' => $first_currency,
                    'amount'           => $first_amount,
                    'confirm_amount'   => $second_amount, // 确认金额，需要减去手续费
                    'fees'             => $fee,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    'createtime'    => $now_time,
                    'createuser_id' => $params['auth_id'],
                    'createuser'    => $params['auth_username'],
                    'confirmtime'   => $now_time,
                    'remarks'       => $remark,
                ];


                // 这个语句可能报错, 因为操作人员，重复导入同一个excel，导致unique 报错
                try {
                    $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                } catch (\Exception $e) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '数据异常', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $save_from_fund_data = ['account_funds' => ($from_fund['account_funds'] - $first_amount)];
                $ret_save_from_fund  = $fund_model->where(['id' => $from_fund['id']])->update($save_from_fund_data);

                $ret_save_to_fund = true;
                /*
                if ($to_fund) {
                    $save_to_fund_data = ['account_funds' => ($to_fund['account_funds'] + $second_amount)];
                    $ret_save_to_fund  = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);
                }*/

                if (!$ret_add_fund_detail || !$ret_save_from_fund || !$ret_save_to_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '添加数据失败', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

            } elseif ($action_type == 'credit') // 入账
            {
                // 寻找指定货币的 余额账户
                $where_to_fund    = ['account_id' => $account_id, 'account_currency' => $first_currency, 'fund_name' => $first_fund_name];
                $where_to_account = ['id' => $account_id];

                $to_fund    = $fund_model->where($where_to_fund)->lock(true)->find();
                $to_account = $account_model->where($where_to_account)->find();

                if (!$to_fund || !$to_account) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '收款账户不存在', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $add_fund_detail = [
                    'number' => $hash_value,
                    'type'   => 0, // 0=对内转账 1=对外转账 2=提现 3=平账

                    'to_account_id'        => $account_id,
                    'to_account_funds_id'  => $to_fund['id'],
                    'to_account'           => $to_account['account'],
                    'to_account_type'      => $to_account['type'],
                    'to_account_type_attr' => $to_account['type_attr'],
                    'to_account_funds'     => $to_fund['account_funds'] ?? '0',

                    'account_currency' => $to_fund['account_currency'],
                    'amount'           => $first_amount,
                    'confirm_amount'   => $first_amount,
                    'fees'             => $fee,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    'createtime'    => $now_time,
                    'createuser_id' => $params['auth_id'],
                    'createuser'    => $params['auth_username'],
                    'confirmtime'   => $now_time,
                    'remarks'       => $remark,
                ];

                $save_to_fund_data = ['account_funds' => ($to_fund['account_funds'] + $first_amount - $fee)];

                // 这个语句可能报错, 因为操作人员，重复导入同一个excel，导致unique 报错
                try {
                    $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                } catch (\Exception $e) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '数据异常', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $ret_save_to_fund = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);

                if (!$ret_add_fund_detail || !$ret_save_to_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '添加数据失败', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }
            } else {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '不支持该操作', 'row_data' => $one_row_data];
                $fund_model->rollback();
                continue;
            }

            $success_num++;
            $fund_model->commit();
        }

        $ret_msg = $this->genImportReport($success_num, $fail_arr, ['0', '7'], ['序号', '流水ID']);

        return ['code' => 0, 'msg' => $ret_msg, 'fail_list' => $fail_arr, 'success_num' => $success_num];
    }
}