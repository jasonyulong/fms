<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\auth;

use fast\Tree;
use think\Cache;
use app\index\library\TplLib;
use app\common\model\AdminRule;
use app\common\library\FilterLib;
use app\common\model\AccountTemplate;
use app\common\controller\AuthController;

/**
 *  收款模板管理
 * @icon fa fa-list
 * @remark 转账模板 添加 删除 编辑
 */
class Tpl extends AuthController
{

    /**
     * 模板列表
     * @access auth
     * @author lamkakyun
     * @date 2018-12-11 14:23:31
     * @return void
     */
    public function index()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $params['p'] = $params['p'] ?? 1;
        $params['ps'] = $params['ps'] ?? 20;
        $start = ($params['p'] - 1) * $params['ps'];

        $account_tpl_model  = new AccountTemplate();

        $where = ['createuser_id' => $this->auth->id];

        if (FilterLib::isNotEmptyStr($params, 'search_field') && FilterLib::isNotEmptyStr($params, 'search_value'))
        {
            switch($params['search_field'])
            {
                case 'tpl_name':
                    $where['title'] = ['LIKE', "%{$params['search_value']}%"];
                    break;
                case 'account':
                case 'account_user':
                    $where[$params['search_field']] = ['LIKE', "%{$params['search_value']}%"];
                    break;
            }
        }

        $count = $account_tpl_model->where($where)->count();
        $tpl_list = $account_tpl_model->where($where)->limit($start, $params['ps'])->select()->toArray();
        $this->_assignPagerData($this, $params, $count);

        $this->assign('params', $params);
        $this->assign('list_total', $count);
        $this->assign('account_type_list', TplLib::$account_type_list);
        $this->assign('status_list', TplLib::$status_list);
        $this->assign('tpl_list', $tpl_list);
        return parent::fetchAuto();
    }

    /**
     * 添加模板
     * @access auth
     * @author lamkakyun
     * @date 2018-12-11 14:23:49
     * @return void
     */
    public function add()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $this->assign('params', $params);

        $account_type_list = TplLib::$account_type_list;

        if ($this->request->isGet()) {
            $this->assign('params', $params);
            $this->assign('account_type_list', $account_type_list);
            $this->assign('status_list', TplLib::$status_list);

            return parent::fetchAuto();
        }

        $params['auth_id'] = $this->auth->id;
        $params['auth_username'] = $this->auth->username;

        return TplLib::getInstance()->addNewTpl($params);
    }


    /**
     * 编辑模板
     * @access auth
     * @author lamkakyun
     * @date 2018-12-11 14:23:56
     * @return void
     */
    public function edit()
    {
        $account_tpl_model  = new AccountTemplate();
        $account_type_list = TplLib::$account_type_list;

        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        if ($this->request->isGet()) {
            if (!preg_match('/^\d+$/', $params['id'])) return $this->error('参数错误');

            $tpl_info = $account_tpl_model->where(['id' => $params['id']])->find();
            if (!$tpl_info) return $this->error('模板不存在');
            $this->assign('params', $params);
            $this->assign('tpl_info', $tpl_info);
            $this->assign('account_type_list', $account_type_list);
            $this->assign('status_list', TplLib::$status_list);

            return parent::fetchAuto('add');
        }

        $params['is_edit'] = 1;
        $params['auth_id'] = $this->auth->id;
        $params['auth_username'] = $this->auth->username;

        return TplLib::getInstance()->addNewTpl($params);

    }


    /**
     * 删除模板
     * @access auth
     * @author lamkakyun
     * @date 2018-12-11 14:24:28
     * @return void
     */
    public function delete()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $account_tpl_model  = new AccountTemplate();

        if (!FilterLib::isNum($params, 'id')) return json(['code' => '-1', 'msg' => '参数错误']);

        $where = ['id' => $params['id']];

        $ret = $account_tpl_model->where($where)->delete();
        if (!$ret) return json(['code' => '-1', 'msg' => '操作失败']);

        return json(['code' => '0', 'msg' => '操作成功']);
    }
}