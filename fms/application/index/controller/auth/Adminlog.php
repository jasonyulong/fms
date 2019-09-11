<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\auth;

use app\common\model\AdminGroup;
use app\common\controller\AuthController;

/**
 * 管理员日志
 *
 * @icon fa fa-users
 * @remark 管理员可以查看自己所拥有的权限的管理员日志
 */
class Adminlog extends AuthController
{

    protected $model = null;
    protected $childrenGroupIds = [];
    protected $childrenAdminIds = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('AdminLog');

        $this->childrenAdminIds = $this->auth->getChildrenAdminIds(true);
        $this->childrenGroupIds = $this->auth->getChildrenGroupIds($this->auth->isSuperAdmin() ? true : false);

        $groupName = AdminGroup::where('id', 'in', $this->childrenGroupIds)->column('id,name');

        $this->view->assign('groupdata', $groupName);
    }

    /**
     * 查看
     * @access auth
     * @return string|\think\response\Json
     * @throws \ReflectionException
     */
    public function index()
    {
        $sort     = $this->request->get("sort", "id");
        $order    = $this->request->get("order", "DESC");
        $keywords = $this->request->get('keywords', null);

        $where = [];
        if (!empty($keywords)) {
            $where['username'] = ['LIKE', $keywords];
        }

        $total = $this->model->where($where)
            ->where('admin_id', 'in', $this->childrenAdminIds)
            ->order($sort, $order)
            ->count();

        $list = $this->model->where($where)
            ->where('admin_id', 'in', $this->childrenAdminIds)
            ->order($sort, $order)->paginate(20);

        $this->assign('page', $list->render());
        $this->assign('rows', $list);
        $this->assign('total', $total);
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
            $childrenGroupIds = $this->childrenGroupIds;
            $adminList        = $this->model->where('id', 'in', $ids)->where('admin_id', 'in', function ($query) use ($childrenGroupIds) {
                $query->name('admin_group_access')->field('admin_id');
            })->select();
            if ($adminList) {
                $deleteIds = [];
                foreach ($adminList as $k => $v) {
                    $deleteIds[] = $v->id;
                }
                if ($deleteIds) {
                    $this->model->destroy($deleteIds);
                    $this->success(__('删除成功'), '');
                }
            }
        }
        $this->error();
    }
}
