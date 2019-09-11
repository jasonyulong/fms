<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\auth;

use app\common\model\AdminGroup;
use app\common\controller\AuthController;
use fast\Tree;

/**
 * 角色管理
 * @icon fa fa-group
 * @remark 角色组可以有多个,角色有上下级层级关系,如果子角色有角色组和管理员的权限则可以派生属于自己组别下级的角色组或管理员
 */
class Group extends AuthController
{
    protected $model = null;
    //当前登录管理员所有子组别
    protected $childrenGroupIds = [];
    //当前组别列表数据
    protected $groupdata = [];
    //无需要权限判断的方法
    protected $noNeedRight = ['roletree'];

    /**
     * 初始化
     */
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('AdminGroup');

        $this->childrenGroupIds = $this->auth->getChildrenGroupIds(true, true);

        $groupList = collection(AdminGroup::where('id', 'in', $this->childrenGroupIds)->select())->toArray();
        Tree::instance()->init($groupList);
        $result = [];
        if ($this->auth->isSuperAdmin()) {
            $result = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0));
        } else {
            $groups = $this->auth->getGroups();
            foreach ($groups as $m => $n) {
                $result = array_merge($result, Tree::instance()->getTreeList(Tree::instance()->getTreeArray($n['pid'])));
            }
        }
        $groupName = [0 => '顶级角色'];
        foreach ($result as $k => $v) {
            $groupName[$v['id']] = $v['name'];
        }
        $this->groupdata = $groupName;
        $this->assignconfig("admin", ['id' => $this->auth->id, 'group_ids' => $this->auth->getGroupIds()]);

        $this->view->assign('groupdata', $this->groupdata);
    }

    /**
     * 查看
     * @access auth
     * @return string
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $list      = AdminGroup::all(array_keys($this->groupdata));
        $list      = collection($list)->toArray();
        $groupList = [];
        foreach ($list as $k => $v) {
            $groupList[$v['id']] = $v;
        }
        $list = [];
        foreach ($this->groupdata as $k => $v) {
            if (isset($groupList[$k])) {
                $groupList[$k]['name'] = $v;
                $list[]                = $groupList[$k];
            }
        }
        $total = count($list);
        $this->assign('lists', $list);
        $this->assign('total', $total);
        return parent::fetchAuto();
    }

    /**
     * 添加
     * @access auth
     * @return string
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a", [], 'strip_tags');
            if ($params) {
                if (isset($params['name'])) {
                    $name   = $params['name'];
                    $unique = $this->model->where(['name' => $name])->find();
                    if (!empty($unique)) {
                        $this->error($name . "已经使用");
                    }
                }

                $this->model->create($params);
                $this->success();
            }
            $this->error();
        }
        $this->view->assign("row", []);
        return parent::fetchAuto('form');
    }

    /**
     * 编辑
     * @access auth
     * @param null $ids
     * @return string|void
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public function edit($ids = NULL)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row)
            $this->error(__('No Results were found'));
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a", [], 'strip_tags');
            // 父节点不能是它自身的子节点
            /*
            if (!in_array($params['pid'], $this->childrenGroupIds)) {
                $this->error(__('父角色不能是自身的子角色'));
            }*/
            if ($params) {
                if (isset($params['name'])) {
                    $name   = $params['name'];
                    $unique = $this->model->where(['name' => $name, 'id' => ['NEQ', $ids]])->find();
                    if (!empty($unique)) {
                        $this->error($name . "已经使用");
                    }
                }
                $row->save($params);
                $this->success();
            }
            $this->error();
            return;
        }
        $this->view->assign("row", $row);
        return parent::fetchAuto('form');
    }

    /**
     * 删除
     * @access auth
     * @param string $ids
     * @throws \think\exception\DbException
     */
    public function del($ids = "")
    {
        if ($ids) {
            $ids       = explode(',', $ids);
            $grouplist = $this->auth->getGroups();
            $group_ids = array_map(function ($group) {
                return $group['id'];
            }, $grouplist);
            // 移除掉当前管理员所在组别
            $ids = array_diff($ids, $group_ids);

            // 循环判断每一个组别是否可删除
            $grouplist        = $this->model->where('id', 'in', $ids)->select();
            $groupaccessmodel = model('AdminGroupAccess');
            foreach ($grouplist as $k => $v) {
                // 当前组别下有管理员
                $groupone = $groupaccessmodel->get(['group_id' => $v['id']]);
                if ($groupone) {
                    $ids = array_diff($ids, [$v['id']]);
                    continue;
                }
                // 当前组别下有子组别
                $groupone = $this->model->get(['pid' => $v['id']]);
                if ($groupone) {
                    $ids = array_diff($ids, [$v['id']]);
                    continue;
                }
            }
            if (!$ids) {
                $this->error(__('你不能删除含有子组和管理员的组'));
            }
            $count = $this->model->where('id', 'in', $ids)->delete();
            if ($count) {
                $this->success();
            }
        }
        $this->error();
    }

    /**
     * 授权
     * @access auth
     * @return string
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function roletree()
    {
        $this->loadlang('auth/group');

        $model = model('AdminGroup');
        $id    = $this->request->get("id");
        $pid   = $this->request->get("pid");

        $parentGroupModel  = $pid > 0 ? $model->get($pid) : null;
        $currentGroupModel = null;
        if ($id) {
            $currentGroupModel = $model->get($id);
        }
        if ((!$id || $currentGroupModel)) {
            $id            = $id ? $id : null;
            $ruleList      = collection(model('AdminRule')->order('weigh', 'desc')->select())->toArray();
            $parentRuleIds = is_null($parentGroupModel) ? model('AdminRule')->column('id') : explode(',', $parentGroupModel->rules);

            //读取父类角色所有节点列表
            $parentRuleList = [];
            if (in_array('*', $parentRuleIds)) {
                $parentRuleList = $ruleList;
            } else {
                foreach ($ruleList as $k => $v) {
                    if (in_array($v['id'], $parentRuleIds)) {
                        $parentRuleList[] = $v;
                    }
                }
            }
            //当前所有正常规则列表
            Tree::instance()->init($parentRuleList);

            //读取当前角色下规则ID集合
            $adminRuleIds = $this->auth->getRuleIds();
            //是否是超级管理员
            $superadmin = $this->auth->isSuperAdmin();
            //当前拥有的规则ID集合
            $currentRuleIds = $id ? explode(',', $currentGroupModel->rules) : [];
            if (!$id || !in_array($pid, Tree::instance()->getChildrenIds($id, TRUE))) {
                $parentRuleList = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'name');
                $hasChildrens   = [];
                foreach ($parentRuleList as $k => $v) {
                    if ($v['haschild'])
                        $hasChildrens[] = $v['id'];
                }
                $parentRuleIds = array_map(function ($item) {
                    return $item['id'];
                }, $parentRuleList);
                $nodeList      = [];
                foreach ($parentRuleList as $k => $v) {
                    //if (!$superadmin && !in_array($v['id'], $adminRuleIds))
                    //continue;
                    if ($v['pid'] && !in_array($v['pid'], $parentRuleIds))
                        continue;
                    $state      = array('selected' => in_array($v['id'], $currentRuleIds) && !in_array($v['id'], $hasChildrens));
                    $nodeList[] = array('id' => $v['id'], 'parent' => $v['pid'] ? $v['pid'] : '#', 'text' => __($v['title']), 'type' => 'menu', 'state' => $state);
                }
                $this->view->assign("row", $this->model->get(['id' => $id]));
                $this->assign('nodeList', $nodeList);
                return parent::fetchAuto();
            } else {
                $this->error(__('父角色不能是它的子角色'));
            }
        } else {
            $this->error(__('角色未找到'));
        }
    }

}
