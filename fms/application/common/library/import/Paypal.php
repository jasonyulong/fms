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
 * @date 2018-11-30 09:11:13
 */
class Paypal extends Import
{
    /**
     * 导入 excel 流水
     * @author lamkakyun
     * @date 2018-11-30 09:11:20
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
        // Paypal 从第2行数据开始读取
        for ($i = 1; $i < count($excel_data); $i++) {
            $one_row_data = $excel_data[$i];
            if (empty($one_row_data)) {
                $fail_arr[] = ['transaction_id' => 0, 'msg' => '获取excel数据失败', 'row_data' => $one_row_data];
                continue;
            }

            // PAYPAL EXCEL 这个文件的时间是 EXCEL 的时间， 要转换成 PHP 中的时间（其实不明白EXCEL 的时间究竟是什么时间！！）
            // 参考链接，https://www.kancloud.cn/weber_lzw/book/217116
            $now_time = is_string($one_row_data[0]) ? strtotime($one_row_data[0] . ' ' . $one_row_data[1]) : \PHPExcel_Shared_Date::ExcelToPHP($one_row_data[0] + $one_row_data[1]) - 8 * 60 * 60;

            $action_type         = str_replace(" ", "", $one_row_data[4]);
            $status              = $one_row_data[5]; // 状态没有用（已询问产品）
            $first_currency      = $one_row_data[6];
            $first_amount        = abs(str_replace(',', '', $one_row_data[7]));
            $fee                 = abs(str_replace(',', '', $one_row_data[8])) ?: 0;
            $second_amount       = abs(str_replace(',', '', $one_row_data[9]));
            $transaction_id      = $one_row_data[12];
            $second_account_name = $one_row_data[11]; // index = 11 的字段没有什么用

            if ($action_type == '发出的集中付款') {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '不支持改类型记录', 'row_data' => $one_row_data];
                continue;
            }

            // 为了避免重复插入，用 hash 来验证 是否同一条记录
            // $hash_value = 'PAYPAL_' . md5(implode('', $one_row_data));

            // 使用 paypal 的交易id就足够了
            $hash_value = 'PAYPAL_' . $transaction_id;

            // 没有交易时间的流水不导入
            if (!$now_time) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '流入记录日期有误', 'row_data' => $one_row_data];
                continue;
            }

            if (!FilterLib::isFloat($first_amount) || empty($first_amount)) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '金额不正确', 'row_data' => $one_row_data];
                continue;
            }

            // 构建remark
            $remark = sprintf("交易号(%s)", $transaction_id);

            $fund_model->startTrans();

            // 就是入账,忽略付款账户。 入账first_amount 都 大于等于 0
            if (in_array($action_type, ['eBay竞拍付款', 'eBay拍賣付款'])) {
                // 寻找指定货币的 余额账户
                $where_to_fund    = ['account_id' => $account_id, 'account_currency' => $first_currency];
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
                    'confirm_amount'   => $second_amount,
                    'fees'             => $fee,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    'createtime'    => $now_time,
                    'createuser_id' => $params['auth_id'],
                    'createuser'    => $params['auth_username'],
                    'confirmtime'   => $now_time,
                    'remarks'       => $remark,
                ];

                $save_to_fund_data = ['account_funds' => $to_fund['account_funds'] + $second_amount];

                // 这个语句可能报错, 因为操作人员，重复导入同一个excel，导致unique 报错
                try {
                    $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                } catch (\Exception $e) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '数据异常' . $e->getMessage(), 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $ret_save_to_fund = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);

                if (!$ret_add_fund_detail || !$ret_save_to_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '添加数据失败', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }
            } else // 其他都类似 提现的操作
            {
                // 误差应少于0.1
                if (abs($first_amount - $second_amount - $fee) >= 0.1) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '提现的付款金额和收款金额不一致', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                $where_from_fund    = ['account_id' => $account_id, 'account_currency' => $first_currency];
                $where_from_account = ['id' => $account_id];

                $from_fund    = $fund_model->where($where_from_fund)->lock(true)->find();
                $from_account = $account_model->where($where_from_account)->find();

                if (!$from_account || !$from_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '提现付款账户不存在', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                // TODO: 试图寻找 收款账户
                $has_to        = false;
                $where_to_fund = ['account_name' => $second_account_name, 'account_currency' => $first_currency];
                $to_fund       = $fund_model->where($where_to_fund)->lock(true)->find();
                if ($to_fund) {
                    $where_to_account = ['id' => $to_fund['account_id']];
                    $to_account       = $account_model->where($where_to_account)->find();
                    if ($to_account) {
                        $has_to = true;
                    }
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

                    'account_currency' => $first_currency,
                    'amount'           => $first_amount,
                    'confirm_amount'   => $second_amount,
                    'fees'             => $fee,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    'createtime'    => $now_time,
                    'createuser_id' => $params['auth_id'],
                    'createuser'    => $params['auth_username'],
                    'confirmtime'   => $now_time,
                    'remarks'       => $remark,
                ];

                $ret_save_to_fund = true;
                if ($has_to) {
                    $add_fund_detail['to_account_id']        = $to_account['id'];
                    $add_fund_detail['to_account_funds_id']  = $to_fund['id'];
                    $add_fund_detail['to_account']           = $to_account['account'];
                    $add_fund_detail['to_account_type']      = $to_account['type'];
                    $add_fund_detail['to_account_type_attr'] = $to_account['type_attr'];

                    $save_to_fund_data = ['account_funds' => ($to_fund['account_funds'] + $second_amount)];

                    $ret_save_to_fund = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);
                }

                $save_from_fund_data = ['account_funds' => ($from_fund['account_funds'] - $first_amount)];
                $ret_save_from_fund  = $fund_model->where(['id' => $from_fund['id']])->update($save_from_fund_data);

                // 这个语句可能报错, 因为操作人员，重复导入同一个excel，导致unique 报错
                try {
                    $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                } catch (\Exception $e) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '数据异常', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                if (!$ret_add_fund_detail || !$ret_save_from_fund || !$ret_save_to_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '添加数据失败', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }
            }
            // elseif (in_array($action_type, ['冻结余额以调查争议', '取消冻结以解决争议', '付款退款', '可用余额冻结', '集中付款', '普通账户冻结撤销', '付款审查放款'， '退单']))


            $success_num++;
            $fund_model->commit();
        }

        $ret_msg = $this->genImportReport($success_num, $fail_arr, ['0', '12'], ['序号', '流水ID']);
        return ['code' => 0, 'msg' => $ret_msg, 'fail_list' => $fail_arr, 'success_num' => $success_num];
    }

}