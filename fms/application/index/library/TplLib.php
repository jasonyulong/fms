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
 * 收款模板相关操作
 */
class TplLib
{
    /**
     * 自定义的账户类型(模板专用)
     */
    public static $account_type_list = [
        '1' => '支付宝',
        '2' => '银行卡',
        '3' => 'paypal',
    ];


    /**
     * 状态类型
     */
    public static $status_list = [
        '1' => '正常',
        '0' => '注销',
        '2' => '冻结',
    ];

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

            static::$instance = new TplLib();
        }
        return static::$instance;
    }


    public function addNewTpl($params)
    {
        $account_tpl_model = new AccountTemplate();

        $account_type_list = TplLib::$account_type_list;

        $is_edit = isset($params['is_edit']) ? true : false;
        $tpl_info = [];
        if ($is_edit)
        {
            $tpl_info = $account_tpl_model->where(['id' => $params['id']])->find();
            if (!$tpl_info) return json(['code' => '-1', 'msg' => '编辑失败，模板不存在']);
            // $tpl_info = $tpl_info->toArray();
        }

        // TODO: 检测 数据 是否正确 -------------------- start
        if (!FilterLib::isNotEmptyStr($params, 't_name')) return json(['code' => '-1', 'msg' => '模板名称不能为空']);

        
        $count = $account_tpl_model->where(['title' => $params['t_name']])->count();
        if (!$is_edit && $count > 0) return json(['code' => '-1', 'msg' => '模板名称已存在']);
        if ($is_edit && $tpl_info['title'] != $params['t_name'] && $count > 0) return json(['code' => '-1', 'msg' => '编辑失败，模板名称已存在']);

        if (!isset($params['account_type']) || !in_array($params['account_type'], array_keys($account_type_list))) {
            return json(['code' => '-1', 'msg' => '账户类型不存在']);
        }

        if (!FilterLib::isNotEmptyStr($params, 'account')) return json(['code' => '-1', 'msg' => '账户不能为空']);

        // TODO: 检测 账户 + type 的 唯一性
        $count = $account_tpl_model->where(['account' => $params['account'], 'type' => $params['account_type']])->count();
        if (!$is_edit && $count > 0) return json(['code' => '-1', 'msg' => '账户已存在，请不要重复添加']);
        if ($is_edit)
        {
            if (!($tpl_info['account'] == $params['account'] && $tpl_info['type'] == $params['account_type']) && $count > 0)
            {
                return json(['code' => '-1', 'msg' => '编辑失败，账户已存在，请不要重复添加']);
            }
        }

        if (!FilterLib::isNotEmptyStr($params, 'account_user')) return json(['code' => '-1', 'msg' => '账户姓名不能为空']);

        if ($params['account_type'] == '2' && !FilterLib::isNotEmptyStr($params, 'bank_name')) {
            return json(['code' => '-1', 'msg' => '开户银行不能为空']);
        }
        // TODO: 检测 数据 是否正确 -------------------- end

        $add_data = [
            'title'         => $params['t_name'],
            'account'       => $params['account'],
            'account_user'  => $params['account_user'],
            'type'          => $params['account_type'],
            'type_attr'     => $params['bank_name'] ?? '',
            'status'        => $params['status'] ?? 1,
        ];

        if (!$is_edit)
        {
            $add_data['createuser_id'] = $params['auth_id'];
            $add_data['createuser'] = $params['auth_username'];
            $add_data['createtime'] = time();
            $ret = $account_tpl_model->insert($add_data);
        }
        else
        {
            $where_save = ['id' => $params['id']];
            $ret = $account_tpl_model->where($where_save)->update($add_data);
            $ret = ($ret === false) ? false : true;
        }

        if (!$ret) return json(['code' => '-1', 'msg' => '操作失败']);

        return json(['code' => '0', 'msg' => '操作成功']);
    }
}