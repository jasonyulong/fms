<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller;

use app\index\model\Admin;
use app\index\model\AdminLog;
use app\common\controller\AuthController;
use fast\Random;
use rsa\RSA;
use think\Config;
use think\Hook;
use think\Session;
use think\Validate;

/**
 * 后台首页
 * @internal
 */
class Index extends AuthController
{
    protected $noNeedLogin = [];
    protected $noNeedRight = [];
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('AccountFundDetail');
    }

    /**
     * 后台首页
     * @access auth
     * @return string
     * @throws \think\Exception
     */
    public function index()
    {
        list($page, $rows, $total) = Admin::getAccountId($this->auth->id, 50);

        $this->assign('page', $page);
        $this->assign('rows', $rows);
        $this->assign('total', $total);

        $this->view->assign('title', __('首页'));
        return $this->view->fetch();
    }

    /**
     * 修改个人信息
     * @access auth
     * @return string|void
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public function changepass()
    {
        AdminLog::setTitle(__('修改个人信息'));
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $params = array_filter(array_intersect_key($params, array_flip(array('email', 'mobile', 'password', 'avatar'))));
            unset($v);
            if ($params) {
                $admin = Admin::get($this->auth->id);
                if (isset($params['password'])) {
                    $params['salt']     = Random::alnum();
                    $params['password'] = $admin->encryptPassword($params['password'], $params['salt']);
                }
                $params['loginfailure'] = 0;
                if (!$admin->save($params)) {
                    return $this->error($admin->getError());
                }
                //因为个人资料面板读取的Session显示，修改自己资料后同时更新Session
                Session::set("admin", \rsa\RSA::getInstance()->encrypt([
                    'id'       => $admin->id,
                    'username' => $admin->username,
                    'token'    => $admin->token,
                ]));
                return $this->success(__('修改成功'));
            }
            return $this->error(__('修改失败'));
        }
        return parent::fetchAuto();
    }
}
