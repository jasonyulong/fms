<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\common\controller;

use app\index\library\Auth;
use app\index\model\Admin;
use think\Config;
use think\Controller;
use think\Cookie;
use think\Hook;
use think\Lang;
use think\Session;

/**
 * 后台控制器基类
 * Class AuthController
 * @package app\common\controller
 */
class AuthController extends Controller
{
    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = [];

    /**
     * 布局模板
     * @var string
     */
    protected $layout = '';

    /**
     * 权限控制类
     * @var Auth
     */
    protected $auth = null;

    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';

    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

    /**
     * 是否开启数据限制
     * 支持auth/personal
     * 表示按权限判断/仅限个人
     * 默认为禁用,若启用请务必保证表中存在admin_id字段
     */
    protected $dataLimit = false;

    /**
     * 数据限制字段
     */
    protected $dataLimitField = 'admin_id';

    /**
     * 数据限制开启时自动填充限制字段值
     */
    protected $dataLimitFieldAutoFill = true;

    /**
     * 是否开启Validate验证
     */
    protected $modelValidate = false;

    /**
     * 是否开启模型场景验证
     */
    protected $modelSceneValidate = false;

    /**
     * Multi方法可批量修改的字段
     */
    protected $multiFields = 'status';

    /**
     * 导入文件首行类型
     * 支持comment/name
     * 表示注释或字段名
     */
    protected $importHeadType = 'comment';

    /**
     * 初始运行
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _initialize()
    {
        $modulename     = $this->request->module();
        $controllername = strtolower($this->request->controller());
        $actionname     = strtolower($this->request->action());

        $path = $modulename . '/' . str_replace('.', '/', $controllername) . '/' . $actionname;

        // 定义是否Addtabs请求
        !defined('IS_ADDTABS') && define('IS_ADDTABS', input("addtabs") ? TRUE : FALSE);

        // 定义是否Dialog请求
        !defined('IS_DIALOG') && define('IS_DIALOG', input("dialog") ? TRUE : FALSE);

        // 定义是否AJAX请求
        !defined('IS_AJAX') && define('IS_AJAX', $this->request->isAjax());

        $this->auth = Auth::instance();
        // IP白名单
        if (!$this->auth->forbiddenip()) {
            $this->error(__('抱歉, 您无权限访问!!!'), null, null, 0);
        }
        // 设置当前请求的URI
        $this->auth->setRequestUri($path);
        // 检测是否需要验证登录
        if (!$this->auth->match($this->noNeedLogin)) {
            //检测是否登录
            if (!$this->auth->isLogin()) {
                Hook::listen('admin_nologin', $this);
                $url = Session::get('referer');
                $url = $url ? $url : $this->request->url();
                $this->error(__('Please login first'), url('/index/login/logout', ['url' => $url]));
            }
            // 判断是否需要验证权限
            if (!$this->auth->match($this->noNeedRight)) {
                // 判断控制器和方法判断是否有对应权限
                if (!$this->auth->check($path)) {
                    Hook::listen('admin_nopermission', $this);
                    $this->error(__('您没有权限访问!!!'), null, [], 30);
                }
            }
        }

        // 判断是否需要锁屏
        $accesstime = Session::get('accesstime');
        $locktime   = Config::get('site.locktime');
        if (!$accesstime || (time() - $accesstime) > ($locktime * 60)) {
            Cookie::set('mustlogin', time());
            $this->error(__('请重新登录'), url('/index/login/locks', ['url' => $this->request->post('url', '/')]));
        }

        // 设置面包屑导航数据
        $breadcrumb = $this->auth->getBreadCrumb($path);
        array_pop($breadcrumb);
        $this->view->breadcrumb = $breadcrumb;

        // 如果有使用模板布局
        if ($this->layout) {
            $this->view->engine->layout('layout/' . $this->layout);
        }

        // 语言检测
        $lang   = strip_tags(Lang::detect());
        $site   = Config::get("site");
        $upload = \app\index\model\Config::upload();

        // 上传信息配置后
        Hook::listen("upload_config_init", $upload);
        // 配置信息
        $config = [
            'site'           => array_intersect_key($site, array_flip(['name', 'indexurl', 'cdnurl', 'version', 'timezone', 'languages'])),
            'upload'         => $upload,
            'modulename'     => $modulename,
            'controllername' => $controllername,
            'actionname'     => $actionname,
            'jsname'         => 'index/' . str_replace('.', '/', $controllername),
            'moduleurl'      => rtrim(url("/{$modulename}", '', false), '/'),
            'language'       => $lang,
            'app'            => Config::get('app'),
            'urls'           => Config::get('urls'),
            'referer'        => Session::get("referer")
        ];
        $config = array_merge($config, Config::get("view_replace_str"));
        // 设置配置
        Config::set('upload', array_merge(Config::get('upload'), $upload));
        // 更新访问时间
        Session::set('accesstime', time());
        // 配置信息后
        Hook::listen("config_init", $config);
        //加载当前控制器语言包
        $this->loadlang($controllername);
        //渲染站点配置
        $this->assign('site', $site);
        //渲染配置信息
        $this->assign('config', $config);
        //渲染权限对象
        $this->assign('auth', $this->auth);
        //渲染管理员对象
        $this->assign('admin', $this->auth->getUser());
        //渲染菜单
        list($menulist, $navlist) = $this->auth->getSidebar([], $path);
        // 渲染待确认消息
        list($page, $rows, $total) = Admin::getAccountId($this->auth->id, 10);

        $this->view->assign('toprows', $rows);
        $this->view->assign('toptotal', $total);
        $this->view->assign('menulist', $menulist);
        $this->view->assign('navlist', $navlist);
    }

    /**
     * 加载语言文件
     * @param string $name
     */
    protected function loadlang($name)
    {
        __();
        Lang::load(APP_PATH . $this->request->module() . '/lang/' . Lang::detect() . '/' . str_replace('.', '/', $name) . '.php');
    }

    /**
     * 渲染配置信息
     * @param mixed $name 键名或数组
     * @param mixed $value 值
     */
    protected function assignconfig($name, $value = '')
    {
        $this->view->config = array_merge($this->view->config ? $this->view->config : [], is_array($name) ? $name : [$name => $value]);
    }

    /**
     * 自动加载模版
     * @param null $template
     * @param array $data
     * @return string
     * @throws \ReflectionException
     */
    protected function fetchAuto($template = null, $data = [])
    {
        $_this       = new \ReflectionClass($this);
        $classTitle  = grepDocComment($_this->getDocComment());
        $mechodTitle = grepDocComment($_this->getMethod(request()->action())->getDocComment());

        // 如果是弹窗打开页面
        $dialog = request()->get('dialog', 0);

        $this->view->assign('rule_title', $classTitle);
        $this->view->assign('method_title', $mechodTitle);
        $this->view->assign('title', __($classTitle . "-" . $mechodTitle));
        return $this->view->fetch($template, $data);
    }

    /**
     * 生成查询所需要的条件,排序方式
     * @param mixed $searchfields 快速查询的字段
     * @param boolean $relationSearch 是否关联查询
     * @return array
     */
    protected function buildparams($searchfields = null, $relationSearch = null)
    {
        $searchfields   = is_null($searchfields) ? $this->searchFields : $searchfields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;

        $search    = $this->request->get("search", '');
        $filter    = $this->request->get("filter", '');
        $op        = $this->request->get("op", '', 'trim');
        $sort      = $this->request->get("sort", "id");
        $order     = $this->request->get("order", "DESC");
        $offset    = $this->request->get("offset", 0);
        $limit     = $this->request->get("limit", 20);
        $filter    = json_decode($filter, TRUE);
        $op        = json_decode($op, TRUE);
        $filter    = $filter ? $filter : [];
        $where     = [];
        $tableName = '';
        if ($relationSearch) {
            if (!empty($this->model)) {
                $tableName = $this->model->getQuery()->getTable() . ".";
            }
            $sort = stripos($sort, ".") === false ? $tableName . $sort : $sort;
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $where[] = [$tableName . $this->dataLimitField, 'in', $adminIds];
        }
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $tableName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        foreach ($filter as $k => $v) {
            $sym = isset($op[$k]) ? $op[$k] : '=';
            if (stripos($k, ".") === false) {
                $k = $tableName . $k;
            }
            $v   = !is_array($v) ? trim($v) : $v;
            $sym = strtoupper(isset($op[$k]) ? $op[$k] : $sym);
            switch ($sym) {
                case '=':
                case '!=':
                    $where[] = [$k, $sym, (string) $v];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                case 'LIKE %...%':
                case 'NOT LIKE %...%':
                    $where[] = [$k, trim(str_replace('%...%', '', $sym)), "%{$v}%"];
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $where[] = [$k, $sym, intval($v)];
                    break;
                case 'FINDIN':
                case 'FIND_IN_SET':
                    $where[] = "FIND_IN_SET('{$v}', `{$k}`)";
                    break;
                case 'IN':
                case 'IN(...)':
                case 'NOT IN':
                case 'NOT IN(...)':
                    $where[] = [$k, str_replace('(...)', '', $sym), is_array($v) ? $v : explode(',', $v)];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr))
                        continue;
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } else if ($arr[1] === '') {
                        $sym = $sym == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, $sym, $arr];
                    break;
                case 'RANGE':
                case 'NOT RANGE':
                    $v   = str_replace(' - ', ',', $v);
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr))
                        continue;
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'RANGE' ? '<=' : '>';
                        $arr = $arr[1];
                    } else if ($arr[1] === '') {
                        $sym = $sym == 'RANGE' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, str_replace('RANGE', 'BETWEEN', $sym) . ' time', $arr];
                    break;
                case 'LIKE':
                case 'LIKE %...%':
                    $where[] = [$k, 'LIKE', "%{$v}%"];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $sym))];
                    break;
                default:
                    break;
            }
        }
        $where = function ($query) use ($where) {
            foreach ($where as $k => $v) {
                if (is_array($v)) {
                    call_user_func_array([$query, 'where'], $v);
                } else {
                    $query->where($v);
                }
            }
        };
        return [$where, $sort, $order, $offset, $limit];
    }

    /**
     * 获取数据限制的管理员ID
     * 禁用数据限制时返回的是null
     * @return mixed
     */
    protected function getDataLimitAdminIds()
    {
        if (!$this->dataLimit) {
            return null;
        }
        if ($this->auth->isSuperAdmin()) {
            return null;
        }
        $adminIds = [];
        if (in_array($this->dataLimit, ['auth', 'personal'])) {
            $adminIds = $this->dataLimit == 'auth' ? $this->auth->getChildrenAdminIds(true) : [$this->auth->id];
        }
        return $adminIds;
    }

    /**
     * 发送分页 数据 到模板
     * @AUTHOR: Lamkakyun
     * @DATE: 2018-10-10 06:30:20
     */
    protected function _assignPagerData($thisobj, $params, $total)
    {
        $current_url = url('', '', '');
        if ($_GET) $current_url = $current_url . '?' . http_build_query(array_filter($_GET, function ($val) {
                return $val != '';
            }));
        //分页
        $pager_data = gen_pager_data($params['p'], $total, $params['ps']);
        $thisobj->assign('all_page_num', $pager_data['all_page_num']);
        $thisobj->assign('last_page', $pager_data['last_page']);
        $thisobj->assign('next_page', $pager_data['next_page']);
        $thisobj->assign('current_url', $current_url);
    }
}
