<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\auth;

use app\common\controller\AuthController;
use app\common\library\Email;
use app\index\model\Config as ConfigModel;

/**
 * 基本设置
 * Class Config
 * @package app\index\controller\auth
 */
class Config extends AuthController
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Config');
    }

    /**
     * 查看
     * @access auth
     * @return string
     * @throws \ReflectionException
     */
    public function index()
    {
        $siteList  = [];
        $groupList = ConfigModel::getGroupList();
        foreach ($groupList as $k => $v) {
            $siteList[$k]['name']  = $k;
            $siteList[$k]['title'] = $v;
            $siteList[$k]['list']  = [];
        }
        foreach ($this->model->all() as $k => $v) {
            if (!isset($siteList[$v['group']])) {
                continue;
            }
            $value          = $v->toArray();
            $value['title'] = __($value['title']);
            if (in_array($value['type'], ['select', 'selects', 'checkbox', 'radio'])) {
                $value['value'] = explode(',', $value['value']);
            }
            if (in_array($value['type'], ['array'])) {
                $value['value'] = json_decode($value['value'], true);
            }
            $value['content']                = json_decode($value['content'], TRUE);
            $siteList[$v['group']]['list'][] = $value;
        }
        $index = 0;
        foreach ($siteList as $k => &$v) {
            $v['active'] = !$index ? true : false;
            $index++;
        }

        $this->view->assign('siteList', $siteList);
        $this->view->assign('typeList', ConfigModel::getTypeList());
        $this->view->assign('groupList', ConfigModel::getGroupList());
        return parent::fetchAuto();
    }

    /**
     * 编辑
     * @access auth
     * @throws \ReflectionException
     */
    public function save()
    {
        if (!$this->request->isPost()) {
            $this->error(__('请求异常'));
        }
        $row = $this->request->post("row/a");

        if ($row) {
            $configList = [];
            foreach ($this->model->all() as $v) {
                if (isset($row[$v['name']])) {
                    $value = $row[$v['name']];

                    if (is_array($value) && isset($value['field'])) {
                        $value = json_encode(ConfigModel::getArrayData($value), JSON_UNESCAPED_UNICODE);
                    } else {
                        $value = is_array($value) ? implode(',', $value) : $value;
                    }
                    $v['value']   = $value;
                    $configList[] = $v->toArray();
                }
            }
            $this->model->allowField(true)->saveAll($configList);
            try {
                $this->refreshFile();
                $this->success();
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }
        $this->error(__('Parameter %s can not be empty', ''));
    }

    /**
     * 刷新配置文件
     * @access path
     */
    public function refreshFile()
    {
        $config = [];
        foreach ($this->model->all() as $k => $v) {
            $value = $v->toArray();
            if (in_array($value['type'], ['selects', 'checkbox', 'images', 'files'])) {
                $value['value'] = explode(',', $value['value']);
            }
            if ($value['type'] == 'array') {
                $value['value'] = (array) json_decode($value['value'], TRUE);
            }
            $config[$value['name']] = $value['value'];
        }
        file_put_contents(APP_PATH . 'extra' . DS . 'site.php', '<?php' . "\n\nreturn " . var_export($config, true) . ";");
        $this->success('更新成功');
    }
}