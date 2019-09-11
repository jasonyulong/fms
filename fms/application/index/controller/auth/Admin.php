<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\auth;

use app\common\controller\AuthController;
use app\common\model\AdminGroup;
use app\common\model\AdminGroupAccess;
use fast\Random;
use fast\Tree;

/**
 * 管理员管理
 * @icon fa fa-users
 * @remark 一个管理员可以有多个角色组,左侧的菜单根据管理员所拥有的权限进行生成
 */
class Admin extends AuthController
{
    protected $model = null;
    protected $childrenGroupIds = [];
    protected $childrenAdminIds = [];

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Admin');

        $this->childrenAdminIds = $this->auth->getChildrenAdminIds(true);
        $this->childrenGroupIds = $this->auth->getChildrenGroupIds(true);

        $groupList = collection(AdminGroup::where('id', 'in', $this->childrenGroupIds)->select())->toArray();

        Tree::instance()->init($groupList);
        $groupdata = [];
        if ($this->auth->isSuperAdmin()) {
            $result = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0));
            foreach ($result as $k => $v) {
                $groupdata[$v['id']] = $v['name'];
            }
        } else {
            $result = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0));
            foreach ($result as $k => $v) {
                $groupdata[$v['id']] = $v['name'];
            }
        }

        $this->view->assign('groupdata', $groupdata);
        $this->assignconfig("admin", ['id' => $this->auth->id]);
        $this->assign('adminStatus', ['禁用', '正常', '注销']);
    }

    /**
     * 查看
     * @access auth
     * @return string
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        //如果发送的来源是Selectpage，则转发到Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        $childrenGroupIds = $this->childrenGroupIds;
        $groupName        = AdminGroup::where('id', 'in', $childrenGroupIds)->column('id,name');
        $authGroupList    = AdminGroupAccess::where('group_id', 'in', $childrenGroupIds)->field('admin_id as uid,group_id')->select();

        $adminGroupName = [];
        foreach ($authGroupList as $k => $v) {
            if (isset($groupName[$v['group_id']]))
                $adminGroupName[$v['uid']][$v['group_id']] = $groupName[$v['group_id']];
        }
        $groups = $this->auth->getGroups();
        foreach ($groups as $m => $n) {
            $adminGroupName[$this->auth->id][$n['id']] = $n['name'];
        }
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $total = $this->model->where($where)
            ->where('id', 'in', $this->childrenAdminIds)
            ->count();

        $list = $this->model->where($where)
            ->where('id', 'in', $this->childrenAdminIds)
            ->field(['password', 'salt'], true)
            ->order($sort, $order)->paginate($limit);

        foreach ($list as $k => &$v) {
            $groups           = isset($adminGroupName[$v['id']]) ? $adminGroupName[$v['id']] : [];
            $v['groups']      = implode(',', array_keys($groups));
            $v['groups_text'] = implode(',', array_values($groups));
        }
        unset($v);
        $this->assign('page', $list->render());
        $this->assign('rows', $list);
        $this->assign('total', $total);
        return parent::fetchAuto();
    }

    /**
     * 添加
     * @access auth
     * @return string
     * @throws \Exception
     * @throws \think\Exception
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params['salt']     = Random::alnum();
                $params['password'] = $this->model->encryptPassword($params['password'], $params['salt']);
                $params['avatar']   = '/assets/dist/img/avatar_' . $params['sex'] . '.png'; //设置新管理员默认头像。

                $result = $this->model->validate('Admin.add')->save($params);
                if ($result === false) {
                    $this->error($this->model->getError());
                }
                $group = $this->request->post("group/a");

                //过滤不允许的组别,避免越权
                $group   = array_intersect($this->childrenGroupIds, $group);
                $dataset = [];
                foreach ($group as $value) {
                    $dataset[] = ['admin_id' => $this->model->id, 'group_id' => $value];
                }
                model('AdminGroupAccess')->saveAll($dataset);
                $this->success();
            }
            $this->error();
        }
        return parent::fetchAuto();
    }

    /**
     * 编辑
     * @access auth
     * @param null $ids
     * @return string
     * @throws \Exception
     * @throws \think\Exception
     */
    public function edit($ids = NULL)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row)
            $this->error(__('No Results were found'));
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                if ($params['password']) {
                    $params['salt']     = Random::alnum();
                    $params['password'] = $this->model->encryptPassword($params['password'], $params['salt']);
                } else {
                    unset($params['password'], $params['salt']);
                }
                if (isset($params['sex'])) {
                    $params['avatar'] = '/assets/dist/img/avatar_' . $params['sex'] . '.png'; //设置新管理员默认头像。
                }
                //这里需要针对username和email做唯一验证
                $adminValidate = \think\Loader::validate('Admin');
                $adminValidate->rule([
                    'username' => 'require|max:50|unique:admin,username,' . $row->id,
                    'email'    => 'require|email|unique:admin,email,' . $row->id
                ]);
                $params['loginfailure'] = 0;
                $result = $row->validate('Admin.edit')->save($params);
                if ($result === false) {
                    $this->error($row->getError());
                }
                // 先移除所有权限
                model('AdminGroupAccess')->where('admin_id', $row->id)->delete();

                $group = $this->request->post("group/a");

                // 过滤不允许的组别,避免越权
                $group = array_intersect($this->childrenGroupIds, $group);

                $dataset = [];
                foreach ($group as $value) {
                    $dataset[] = ['admin_id' => $row->id, 'group_id' => $value];
                }
                model('AdminGroupAccess')->saveAll($dataset);
                $this->success();
            }
            $this->error();
        }
        $grouplist = $this->auth->getGroups($row['id']);
        $groupids  = [];
        foreach ($grouplist as $k => $v) {
            $groupids[] = $v['id'];
        }
        $this->view->assign("row", $row);
        $this->view->assign("groupids", $groupids);
        return parent::fetchAuto();
    }

    /**
     * 删除
     * @access auth
     * @param string $ids
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            return $this->error('请求异常');
        }
        if ($ids) {
            // 避免越权删除管理员
            $childrenGroupIds = $this->childrenGroupIds;
            $adminList        = $this->model->where('id', 'in', $ids)->select();
            if (empty($adminList)) {
                $this->error("删除失败,请检查是否有权限删除");
            }
            if ($adminList) {
                $deleteIds = [];
                foreach ($adminList as $k => $v) {
                    $deleteIds[] = $v->id;
                }
                $deleteIds = array_diff($deleteIds, [$this->auth->id]);
                if ($deleteIds) {
                    $this->model->destroy($deleteIds);
                    model('AdminGroupAccess')->where('admin_id', 'in', $deleteIds)->delete();
                    $this->success();
                }
            }
        }
        $this->error();
    }
}
