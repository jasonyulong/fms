<?php

namespace app\common\library\import;

use app\common\model\Account;
use app\common\library\Import;
use app\index\library\FundLib;
use app\common\library\ToolsLib;
use app\common\library\FilterLib;
use app\common\model\AccountFund;
use app\common\model\AccountFundDetail;
use app\common\model\CompanyAccountReceipt;

/**
 * Payoneer 导入类
 * @author lamkakyun
 * @date 2018-11-29 17:42:52
 */
class Payoneer extends Import
{

    /**
     * 导入 excel 流水
     * @author lamkakyun
     * @date 2018-11-29 17:42:48
     * @return array
     */
    public function importFlow($account_id, $excel_data, $params)
    {
        set_time_limit(0);
        $fund_model                    = new AccountFund();
        $fund_detail_model             = new AccountFundDetail();
        $account_model                 = new Account();
        $company_account_receipt_model = new CompanyAccountReceipt();

        // requirement: 备注信息需要，记录下 每条 记录的详细信息
        $headers = $excel_data[0];

        $fail_arr    = [];
        $success_num = 0;
        // Payoneer 从第2行数据开始读取
        $all_platform = ToolsLib::getInstance()->getPlatformList();
        for ($i = 1; $i < count($excel_data); $i++) {
            $one_row_data = $excel_data[$i];

            if (is_all_empty($one_row_data)) continue;

            // 这个金额可能会少于 0
            $date           = $one_row_data[0];
            $description    = $one_row_data[1];
            $first_amount   = str_replace(',', '', $one_row_data[2]);
            $first_currency = $one_row_data[3];
            $status         = $one_row_data[4];
            $transaction_id = '';
            $fee            = 0;

            

            // 为了避免重复插入，用 hash 来验证 是否同一条记录
            $hash_value = 'PAYONNER_' . md5(implode('', $one_row_data));

            $now_time = strtotime($date);

            // 没有交易时间的流水不导入
            if (!$now_time) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '流入记录日期有误', 'row_data' => $one_row_data];
                continue;
            }

            $action_type = $first_amount >= 0 ? '入账' : '提现';

            $first_amount = abs($first_amount); // 因为可能是负数，所以转换成正数，避免下面的计算错误
            if (!FilterLib::isFloat($first_amount)) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '金额不正确', 'row_data' => $one_row_data];
                continue;
            }
            if (empty($now_time)) {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '日期不正确', 'row_data' => $one_row_data];
                continue;
            }

            // 构建remark
            $remark = $description;

            $fund_model->startTrans();

            // switch 不能和 contine 一起使用，改为if-elseif-else
            // P卡 提现，不知道提现 账户，但是 入账 可以知道 付款 账户
            if ($action_type == '提现') {
                $where_from_fund    = ['account_id' => $account_id, 'account_currency' => $first_currency];
                $where_from_account = ['id' => $account_id];

                // 行锁直到rollback 或commit
                $from_fund    = $fund_model->where($where_from_fund)->lock(true)->find();
                $from_account = $account_model->where($where_from_account)->find();

                if (!$from_account || !$from_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '提现付款账户不存在', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                if ($from_fund['account_currency'] != $first_currency) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '提现账户货币不一致', 'row_data' => $one_row_data];
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
                    'confirm_amount'   => $first_amount,
                    'fees'             => $fee,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    'createtime'  => $now_time,
                    'createuser_id' => $params['auth_id'],
                    'createuser' => $params['auth_username'],
                    'confirmtime' => $now_time,
                    'remarks'     => $remark,
                ];

                $save_from_fund_data = ['account_funds' => ($from_fund['account_funds'] - $first_amount)];

                $ret_save_from_fund = $fund_model->where(['id' => $from_fund['id']])->update($save_from_fund_data);

                // 这个语句可能报错, 因为操作人员，重复导入同一个excel，导致unique 报错
                try {
                    $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                } catch (\Exception $e) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '数据异常', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }

                if (!$ret_add_fund_detail || !$ret_save_from_fund) {
                    $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '添加数据失败', 'row_data' => $one_row_data];
                    $fund_model->rollback();
                    continue;
                }
            } elseif ($action_type == '入账') {
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
                    'confirm_amount'   => $first_amount,
                    'fees'             => $fee,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    'createtime'  => $now_time,
                    'createuser_id' => $params['auth_id'],
                    'createuser' => $params['auth_username'],
                    'confirmtime' => $now_time,
                    'remarks'     => $remark,
                ];


                // TODO: 试图寻找付款 账户
                $has_from       = false;
                $match_platform = '';
                foreach ($all_platform as $_platform) {
                    if (stristr($description, $_platform)) {
                        $match_platform = $_platform;
                        break;
                    }
                }
                if ($match_platform) {
                    $_tmpdata = $company_account_receipt_model->where(['receipt_id' => $account_id, 'platform' => $match_platform])->find();

                    if ($_tmpdata && $_tmpdata['account_funds_id']) {
                        $from_fund = $fund_model->where(['id' => $_tmpdata['account_funds_id']])->lock(true)->find();

                        if ($from_fund) {
                            $has_from     = true;
                            $from_account = $account_model->where(['id' => $from_fund['account_id']])->find();

                            $add_fund_detail['from_account_id']        = $from_account['id'];
                            $add_fund_detail['from_account_funds_id']  = $from_fund['id'];
                            $add_fund_detail['from_account']           = $from_account['account'];
                            $add_fund_detail['from_account_type']      = $from_account['type'];
                            $add_fund_detail['from_account_type_attr'] = $from_account['type_attr'];
                        }
                    }
                }

                $ret_save_from_fund = true;
                if ($has_from) {
                    $save_from_fund_data = ['account_funds' => ($from_fund['account_funds'] - $first_amount)];
                    $ret_save_from_fund  = $fund_model->where(['id' => $from_fund['id']])->update($save_from_fund_data);
                }

                $save_to_fund_data = ['account_funds' => ($to_fund['account_funds'] + $first_amount - $fee)];
                $ret_save_to_fund  = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);

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
            } else {
                $fail_arr[] = ['transaction_id' => $transaction_id, 'msg' => '不支持该操作', 'row_data' => $one_row_data];
                $fund_model->rollback();
                continue;
            }

            $success_num++;
            $fund_model->commit();
        }

        $ret_msg = $this->genImportReport($success_num, $fail_arr, ['0', '1'], ['序号', '流水ID']);
        return ['code' => 0, 'msg' => $ret_msg, 'fail_list' => $fail_arr, 'success_num' => $success_num];
    }

}