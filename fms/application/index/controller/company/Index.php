<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\company;

use app\common\model\Company;
use app\common\library\ToolsLib;
use app\index\library\CompanyLib;
use app\common\model\CompanyAccount;
use app\common\controller\AuthController;

/**
 * 公司管理
 * Class index
 * @package app\index\controller\company
 */
class Index extends AuthController
{
    /**
     * 查看
     * @access auth
     * @return string
     * @throws \ReflectionException
     */
    public function index()
    {
        $params       = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $params['p']  = $params['p'] ?? 1;
        $params['ps'] = $params['ps'] ?? 50;
        $start_select = ($params['p'] - 1) * $params['ps'];

        $platform_list = ToolsLib::getInstance()->getPlatformList(1);

        $data         = CompanyLib::getInstance()->getCompanyList($params);
        $company_list = $data['list'];
        $count        = $data['count'];

        // TODO: 查出 company 对应的 account 数目, 然后合并
        $tmp_data = CompanyLib::getInstance()->getAllCompanyAccountGroupList();

        $comp_acc_grp_list = [];
        foreach ($tmp_data as $key => $value) {
            $tmp_key = "{$value['platform']}_{$value['company_id']}";

            $comp_acc_grp_list[$tmp_key] = $value['grp_count'];
        }
        foreach ($company_list as $key => $value) {
            foreach ($platform_list as $v) {
                $tmp_key = "{$v}_{$value['id']}";

                $company_list[$key]['platform_account_num'][$v] = isset($comp_acc_grp_list[$tmp_key]) ? $comp_acc_grp_list[$tmp_key] : 0;
            }
        }

        $this->_assignPagerData($this, $params, $count);
        $this->assign('list_total', $count);
        $this->assign('platform_list', $platform_list);
        $this->assign('params', $params);
        $this->assign('company_list', $company_list);
        return $this->fetchAuto();
    }

    /**
     * 添加
     * @access auth
     * @return string|void
     * @throws \ReflectionException
     */
    public function add()
    {
        $params        = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $company_model = new Company();

        $is_edit = isset($params['id']);

        if ($this->request->isGet()) {
            if ($is_edit) 
            {
                $company_info = $company_model->where(['id' => $params['id']])->find();
                $this->assign('company_info', $company_info);
            }

            $this->assign('params', $params);
            return $this->fetchAuto();
        } else {
            $company_name = request()->post('company_name', false);
            if (!$company_name) return $this->error(__('公司名称不能为空'));

            if ($is_edit)
            {
                $company_info = $company_model->where(['id' => $params['id']])->find();
                if (!$company_info) $this->error(__('公司不存在'));

                $where = ['id' => $params['id']];
                $save_data = ['company_name' => $company_name];
                $ret = $company_model->where($where)->update($save_data);
                $ret = $ret === false ? false : true;
            }
            else
            {
                // 不管禁用/启用
                $count = $company_model->where(['company_name' => $company_name])->count();
                if ($count > 0) return $this->error(__('公司名称已存在'));

                $add_data = [
                    'company_name' => $company_name,
                    'createuser'   => $this->auth->username,
                    'createtime'   => time(),
                ];
    
                $ret = $company_model->insert($add_data);
            }

            
            if (!$ret) {
                return $this->error($company_model->getError());
            }
            return $this->success(__(($is_edit ? '编辑' : '添加') . '成功'));
        }
    }

    /**
     * 订单详情
     *
     * @access auth
     * @author lamkakyun
     * @date 2018-11-09 10:59:58
     * @return void
     */
    public function detail()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $company_model         = new Company();
        $company_account_model = new CompanyAccount();

        if (!isset($params['company_id']) || !preg_match('/^\d+$/', $params['company_id'])) return $this->error(__('参数错误'));

        $company_info  = $company_model->where(['id' => $params['company_id']])->find()->toArray();
        $where_account = ['company_id' => $params['company_id'], 'status' => 1];

        if (isset($params['account']) && !empty($params['account'])) {
            $where_account['account'] = ['IN', preg_split('/[\s,，]+/', trim($params['account']))];
        }
        $platform = $params['platform'] ?? '';
        if (!empty($platform)) {
            $where_account['platform'] = $platform;
        }

        $company_account_list = $company_account_model->where($where_account)->order('platform')->select();

        $this->assign('company_info', $company_info);
        $this->assign('company_account_list', $company_account_list);
        return $this->fetchAuto();
    }

}