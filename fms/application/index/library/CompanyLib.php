<?php

namespace app\index\library;

use think\Config;
use app\common\model\Company;
use app\common\model\CompanyAccount;

/**
 * 账号相关操作
 */
class CompanyLib
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
            static::$instance = new CompanyLib();
        }
        return static::$instance;
    }

    /**
     * 获取所有公司(因为公司不多，所以可以一次性获取所有)
     * @author lamkakyun
     * @date 2018-11-09 17:44:27
     * @return void
     */
    public function getAllCompany($format_type = 0)
    {
        $company_model = new Company();
        $list          = $company_model->select()->toArray();
        if ($format_type == 1) {
            $tmp  = $list;
            $list = [];
            foreach ($tmp as $value) {
                $list[$value['id']] = $value;
            }
        }

        return $list;
    }


    /**
     * 获取公司列表
     * @author lamkakyun
     * @date 2018-11-09 17:41:51
     * @return void
     */
    public function getCompanyList($params)
    {
        $company_model = new Company();
        $start_select  = ($params['p'] - 1) * $params['ps'];

        $count = $company_model->count();

        $company_list = $count ? $company_model->limit($start_select, $params['ps'])->order('id desc')->select()->toArray() : [];

        return ['list' => $company_list, 'count' => $count];
    }


    /**
     * 获取 所有 公司和账号的分组数据 的列表
     * @author lamkakyun
     * @date 2018-11-09 17:53:16
     * @return void
     */
    public function getAllCompanyAccountGroupList()
    {
        $company_account_model = new CompanyAccount();
        $data                  = $company_account_model->field('company_id, platform, COUNT(*) AS grp_count')->where(['status' => 1])->group('company_id, platform')->select()->toArray();

        return $data;
    }


    /**
     * 根据账号 id 获取 账号信息
     * @author lamkakyun
     * @date 2018-11-13 16:27:47
     * @return void
     */
    public function getCompanyAccountByIds($account_id_arr, $format_type = 0)
    {
        $account_model = new CompanyAccount();

        $where = ['id' => ['IN', $account_id_arr]];

        $data = $account_model->where($where)->select()->toArray();

        // 将 id 放到key 上
        if ($format_type == 1) {
            $tmp  = $data;
            $data = [];
            foreach ($tmp as $key => $value) {
                $data[$value['id']] = $value;
            }
        }

        return $data;
    }
}