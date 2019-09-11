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
 * @date 2018-11-28 09:58:33
 */
class Lianlian extends Import
{
    /**
     * 导入 excel 流水
     * @author lamkakyun
     * @date 2018-11-29 14:43:32
     * @return array
     */
    public function importFlow($account_id, $excel_data, $params)
    {
        set_time_limit(0);
        $fund_model        = new AccountFund();
        $fund_detail_model = new AccountFundDetail();
        $account_model     = new Account();

        // requirement: 备注信息需要，记录下 每条 记录的详细信息
        $headers = $excel_data[3];

        $fail_arr    = [];
        $success_num = 0;

        // lianlian 从第5行数据开始读取
        for ($i = 4; $i < count($excel_data); $i++) {
            $one_row_data = $excel_data[$i];

            $date           = $one_row_data[1];
            $transaction_id = $one_row_data[0];
            $action_type    = $one_row_data[2];
            $first_currency = $one_row_data[4];
            $first_amount   = str_replace(',', '', $one_row_data[5]);;
            $first_fund_name  = $one_row_data[8];
            $second_fund_name = $one_row_data[9];
            $second_currency  = $one_row_data[11];
            $fee              = $one_row_data[12] ?: 0;

            $now_time = strtotime($date);

            // 没有交易时间的流水不导入
            if (!$now_time) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '流水记录日期有误', 'row_data' => $one_row_data];
                continue;
            }

            // 为了避免重复导入，用 hash 来验证 是否同一条记录
            $hash_value = 'LIANLIAN_' . md5($transaction_id);

            if (!FilterLib::isFloat($first_amount)) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '金额不正确', 'row_data' => $one_row_data];
                continue;
            }

            // 构建remark
            $remark = sprintf("交易号(%s)", $transaction_id);

            $fund_model->startTrans();
            // switch 不能和 contine 一起使用，改为if-elseif-else
            if ($action_type == '提现') {
                if (!$second_currency || $first_currency != $second_currency) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '提款货币不一致', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $where_from_fund    = ['account_id' => $account_id, 'account_currency' => $first_currency];
                $where_from_account = ['id' => $account_id];

                // 连连的导入，只知道 付款账户，不知道收款账户，经确认，不需要对收款账户操作
                $from_fund    = $fund_model->where($where_from_fund)->lock(true)->find();
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

                    'account_currency' => $from_fund['account_currency'],
                    'amount'           => $first_amount,
                    'confirm_amount'   => $first_amount - $fee,
                    'fees'             => $fee,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    'createtime'    => $now_time,
                    'createuser_id' => $params['auth_id'],
                    'createuser'    => $params['auth_username'],
                    'confirmtime'   => $now_time,
                    'remarks'       => $remark,
                ];

                $save_from_fund_data = ['account_funds' => ($from_fund['account_funds'] - $first_amount)];


                // 这个语句可能报错, 因为操作人员，重复导入同一个excel，导致unique 报错
                try {
                    $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                } catch (\Exception $e) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '数据异常', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $ret_save_from_fund = $fund_model->where(['id' => $from_fund['id']])->update($save_from_fund_data);

                if (!$ret_add_fund_detail || !$ret_save_from_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '添加数据失败', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }
            } elseif (in_array($action_type, ['入账', '退款'])) {
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
                    'confirm_amount'   => $first_amount - $fee,
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

        $ret_msg = $this->genImportReport($success_num, $fail_arr, ['0'], ['流水ID']);

        return ['code' => 0, 'msg' => $ret_msg, 'fail_list' => $fail_arr, 'success_num' => $success_num];
    }
}