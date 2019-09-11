<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\auth;

use app\common\controller\AuthController;
use app\common\model\AdminRule;
use fast\Tree;
use think\Cache;

/**
 * 菜单管理
 * @icon fa fa-list
 * @remark 规则通常对应一个控制器的方法,同时左侧的菜单栏数据也从规则中体现,通常建议通过控制台进行生成规则节点
 */
class Rule extends AuthController
{
    protected $model = null;
    protected $rulelist = [];
    protected $multiFields = 'ismenu,status';

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('AdminRule');
        // 必须将结果集转换为数组
        $ruleList = collection($this->model->order('weigh', 'desc')->select())->toArray();
        foreach ($ruleList as $k => &$v) {
            $v['title']  = __($v['title']);
            $v['remark'] = __($v['remark']);
        }
        unset($v);
        Tree::instance()->init($ruleList);
        $this->rulelist = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'title');

        $ruledata = [0 => __('无')];
        foreach ($this->rulelist as $k => &$v) {
            if (!$v['ismenu'])
                continue;
            $ruledata[$v['id']] = $v['title'];
        }
        $this->view->assign('ruledata', $ruledata);
    }

    /**
     * 查看
     * @access auth
     * @return string|\think\response\Json
     */
    public function index()
    {
        $this->view->assign('rulelist', $this->rulelist);
        return parent::fetchAuto();
    }

    /**
     * 添加
     * @access auth
     * @return string
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a", [], 'strip_tags');
            if ($params) {
                if (!$params['ismenu'] && !$params['pid']) {
                    $this->error(__('非菜单规则必须具有父级'));
                }
                $params['type'] = $params['ismenu'] == 1 ? 'menu' : 'file';
                // 保存数据
                $result = $this->model->validate()->save($params);
                if ($result === FALSE) {
                    $this->error($this->model->getError());
                }
                \app\index\model\AdminRule::saveRuleNode();
                Cache::rm('__menu__');
                $this->success(__("添加成功"));
            }
            $this->error();
        }
        return parent::fetchAuto('form');
    }

    /**
     * 编辑
     * @access auth
     * @param null $ids
     * @return string
     */
    public function edit($ids = NULL)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row)
            $this->error(__('No Results were found'));
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a", [], 'strip_tags');
            if ($params) {
                if (!$params['ismenu'] && !$params['pid']) {
                    $this->error(__('非菜单规则必须具有父级'));
                }
                $params['type'] = $params['ismenu'] == 1 ? 'menu' : 'file';
                //这里需要针对name做唯一验证
                $ruleValidate = \think\Loader::validate('AdminRule');
                $ruleValidate->rule([
                    'name' => 'require|format|unique:AdminRule,name,' . $row->id,
                ]);
                $result = $row->validate()->save($params);
                if ($result === FALSE) {
                    $this->error($row->getError());
                }
                \app\index\model\AdminRule::saveRuleNode($ids);
                Cache::rm('__menu__');
                $this->success(__("编辑成功"));
            }
            $this->error();
        }
        $this->view->assign("row", $row);
        return parent::fetchAuto('form');
    }

    /**
     * 删除
     * @access auth
     * @param string $ids
     */
    public function del($ids = "")
    {
        if ($ids) {
            $delIds = [];
            foreach (explode(',', $ids) as $k => $v) {
                $delIds = array_merge($delIds, Tree::instance()->getChildrenIds($v, TRUE));
            }
            $delIds = array_unique($delIds);
            $count  = $this->model->where('id', 'in', $delIds)->delete();
            if ($count) {
                Cache::rm('__menu__');
                $this->success(__("删除成功"));
            }
        }
        $this->error(__("请求失败"));
    }

    /**
     * 删除
     * @access auth
     */
    public function clear()
    {
        \app\index\model\AdminRule::saveRuleNode();
        Cache::rm('__menu__');
        $this->success(__("操作成功"), '');
    }
}
