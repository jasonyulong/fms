<?php

namespace app\common\library\import;

use app\index\library\Auth;
use app\common\library\FilterLib;
use app\common\model\Account;
use app\common\model\AccountFund;
use app\common\model\AccountFundDetail;
use think\View;

class Bank
{
    /**
     * 导入流水
     * @author yang
     * @param int $fund_id 卡号id
     * @param array $excel_data excel导入数据
     * @return array
     * @date 2018-11-29
     */
    public function importFlow($fund_id, $excel_data)
    {
        $fund_model        = new AccountFund();
        $fund_detail_model = new AccountFundDetail();
        $account_model     = new Account();

        $auth       = Auth::instance();
        $admin_id   = $auth->id;
        $admin_name = $auth->username;

        set_time_limit(0);
        $fail_arr    = [];
        $success_num = 0;
        $len         = count($excel_data);

        for ($i = 1; $i < $len; $i++) {
            $one_row_data = $excel_data[$i];
            $time         = isset($one_row_data[0]) ? strtotime(trim($one_row_data[0])) : ''; //交易时间
            $mode         = isset($one_row_data[2]) ? trim($one_row_data[2]) : ''; //交易方式(写入备注)
            $expend       = isset($one_row_data[3]) ? preg_replace('/[,，]+/', '', trim($one_row_data[3])) : ''; //支出金额
            $income       = isset($one_row_data[4]) ? preg_replace('/[,，]+/', '', trim($one_row_data[4])) : ''; //收入金额
            $name         = isset($one_row_data[6]) ? trim($one_row_data[6]) : ''; //
            $account      = isset($one_row_data[7]) ? trim($one_row_data[7]) : ''; //对方账户
            $remark       = isset($one_row_data[10]) ? trim($one_row_data[10]) . "{$mode}({$name})" : "{$mode}({$name})"; //备注
            $currency     = isset($one_row_data[9]) ? trim($one_row_data[9]) : ''; //余额

            if (!$time && !$expend && !$income && !$account) {
                continue;
            }

            //无效的数据
            //$balance    = $one_row_data[5];  //币种 $bank       = $one_row_data[9]; //对方开户行 $type       = $one_row_data[1]; //交易类型
            if (!is_numeric($expend)) $expend = '';
            if (!is_numeric($income)) $income = '';


            if (!FilterLib::isFloat($expend) && !FilterLib::isFloat($income)) {
                $fail_arr[] = ['account' => $account, 'msg' => '金额不正确', 'row_data' => [$time, $account]];
                continue;
            }
            if ($expend && $income) {
                $fail_arr[] = ['account' => $account, 'msg' => '收入、支出金额不能同时存在', 'row_data' => [$time, $account]];
                continue;
            }
            if (!$time) {
                $fail_arr[] = ['account' => $account, 'msg' => '交易时间为空', 'row_data' => [$time, $account]];
                continue;
            }
            if (!$account) {
                $fail_arr[] = ['account' => $account, 'msg' => '对方账户信息错误', 'row_data' => [$time, $account]];
                continue;
            }

            // 寻找指定的账户
            $where_to_fund = ['id' => $fund_id];
            $to_fund       = $fund_model->where($where_to_fund)->find();
            $account_id    = isset($to_fund['account_id']) ? $to_fund['account_id'] : 0;

            if ($account_id) {
                $where_to_account = ['id' => $account_id];
                $to_account       = $account_model->where($where_to_account)->find();
            } else {
                $to_account = [];
            }

            if (!$to_fund || !$to_account) {
                $fail_arr[] = ['account' => $account, 'msg' => '收款账户不存在', 'row_data' => [$time, $account]];
                continue;
            }

            //生成流水号
            $number = md5($time . $income . $expend . $currency . $account . $fund_id);

            //检测流水单号是否存在
            $map = ['number' => $number];
            $num = $fund_detail_model->where($map)->value('number');
            if ($num) {
                $fail_arr[] = ['account' => $account, 'msg' => '流水已存在当前银行卡', 'row_data' => [$time, $account]];
                continue;
            }


            $fund_model->startTrans();
            //收入
            if ($income) {

                if ($income < 0) {
                    $fail_arr[] = ['account' => $account, 'msg' => '金额不能为负值', 'row_data' => [$time, $account]];
                    $fund_model->rollback();
                    continue;
                }

                $add_fund_detail = [
                    'number'               => $number,
                    'type'                 => 0, // 0=对内转账 1=对外转账 2=提现 3=平账

                    //收入账号(银行卡)
                    'to_account_id'        => $account_id,
                    'to_account_funds_id'  => $to_fund['id'],
                    'to_account_funds'     => $to_fund['account_funds'],
                    'to_account'           => $to_account['account'],
                    'to_account_type'      => $to_account['type'],
                    'to_account_type_attr' => $to_account['type_attr'],
                    'to_username'          => $to_account['title'],
                    'to_amount'            => $income,
                    'to_currency'          => $to_fund['account_currency'],

                    //转出账号
                    'from_account'         => $account,

                    'account_currency' => $to_fund['account_currency'],
                    'amount'           => $income,
                    'confirm_amount'   => $income,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    //创建信息
                    'createtime'       => $time,
                    'createuser'       => $admin_name,
                    'createuser_id'    => $admin_id,

                    'confirmtime' => $time,
                    'remarks'     => $remark,
                ];

                $save_to_fund_data = ['account_funds' => ($to_fund['account_funds'] + $income)];

                $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                $ret_save_to_fund    = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);

                if (!$ret_add_fund_detail || !$ret_save_to_fund) {
                    $fail_arr[] = ['account' => $account, 'msg' => '添加数据失败', 'row_data' => [$time, $account]];
                    $fund_model->rollback();
                    continue;
                }
            } //支出
            elseif ($expend) {
                if ($expend < 0) {
                    $fail_arr[] = ['account' => $account, 'msg' => '金额不能为负值', 'row_data' => [$time, $account]];
                    $fund_model->rollback();
                    continue;
                }

                $add_fund_detail   = [
                    'number'                 => $number,
                    'type'                   => 1, // 0=对内转账 1=对外转账 2=提现 3=平账

                    //收入账号
                    'to_account'             => $account,
                    'to_username'            => $name,

                    //支出账号(银行卡)
                    'from_account_id'        => $account_id,
                    'from_account_funds_id'  => $to_fund['id'],
                    'from_account'           => $to_account['account'],
                    'from_account_type'      => $to_account['type'],
                    'from_account_type_attr' => $to_account['type_attr'],
                    'from_account_funds'     => $to_fund['account_funds'],
                    'from_amount'            => $expend,
                    'from_currency'          => $to_fund['account_currency'],


                    'account_currency' => $to_fund['account_currency'],
                    'amount'           => $expend,
                    'confirm_amount'   => $expend,
                    'status'           => 1, // 状态 0=待确认 1=已确认

                    //创建信息
                    'createtime'       => $time,
                    'createuser'       => $admin_name,
                    'createuser_id'    => $admin_id,

                    'confirmtime' => $time,
                    'remarks'     => $remark,
                ];
                $save_to_fund_data = ['account_funds' => ($to_fund['account_funds'] - $expend)];


                $ret_add_fund_detail = $fund_detail_model->insert($add_fund_detail);
                $ret_save_to_fund    = $fund_model->where(['id' => $to_fund['id']])->update($save_to_fund_data);

                if (!$ret_add_fund_detail || !$ret_save_to_fund) {
                    $fail_arr[] = ['account' => $account, 'msg' => '添加数据失败', 'row_data' => [$time, $account]];
                    $fund_model->rollback();
                    continue;
                }
            }

            $success_num++;
            $fund_model->commit();
        }

        $ret_msg = $this->genImportReport($success_num, $fail_arr);

        return json(['code' => 0, 'msg' => $ret_msg, 'fail_list' => $fail_arr, 'success_num' => $success_num]);
    }

    /**
     * 生成导入报告
     * @author lamkakyun
     * @date 2018-11-29 15:15:53
     * @return string
     */
    protected function genImportReport($success_num, $fail_arr)
    {
        return View::instance()->fetch(APP_PATH . sprintf("%s/view/%s/%s.html",
                request()->module(),
                str_replace('.', '/', strtolower(request()->controller())),
                'import_report'),
            ['success_num' => $success_num, 'fail_arr' => $fail_arr]);
    }
}