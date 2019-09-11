<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\model;

/**
 * Class AdminRule
 * @package app\index\model
 */
class AdminRule extends \app\common\model\AdminRule
{
    /**
     * 更新菜单下的子项
     * @param int $id
     * @return bool
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public static function saveRuleNode(int $id = 0)
    {
        $where = ['ismenu' => 1];
        if ($id > 0) {
            $where['id'] = $id;
        }

        $rules = AdminRule::where($where)->order('weigh desc')->select();
        if (empty($rules)) return false;
        $default = 'index';

        foreach ($rules as $val) {
            $name = trim($val['name'], '/');

            if (strpos($name, '?') > 0) {
                continue;
            }
            $explode = explode("/", $name);
            if ($val['pid'] == 0 && empty($explode[2])) {
                continue;
            }

            $module        = $explode[0] ?? $default;
            $controllerDir = $explode[1] ?? $default;
            $controller    = $explode[2] ?? $default;

            if (empty($module)) $module = $default;

            if ($controllerDir == $default) {
                $classController = "app\\" . $default . "\controller\\" . ucfirst($default);
                $classFile       = APP_PATH . $default . "/controller/" . ucfirst($default) . ".php";
            } else {
                $classController = "\app\\" . $module . "\controller\\" . $controllerDir . "\\" . ucfirst($controller);
                $classFile       = APP_PATH . $module . "/controller/" . $controllerDir . "/" . ucfirst($controller) . ".php";
            }
            if (!is_file($classFile)) {
                continue;
            }
            // 通过反射获取类对象内容
            $obj     = new $classController();
            $_this   = new \ReflectionClass($obj);
            $methods = $_this->getMethods();

            if (empty($methods)) continue;
            $methodsData = [];
            foreach ($methods as $method) {
                if (strtolower($method->class) != trim(strtolower($classController), "\\"))
                    continue;
                if (strpos($method->name, '_') === 0)
                    continue;
                // 获取注释标题
                $title = grepDocComment($_this->getMethod($method->name)->getDocComment());

                if (empty($title)) $title = ucfirst($method->name);
                // 组成数组
                $attr          = [
                    'type'       => 'file',
                    'pid'        => $val['id'],
                    'name'       => ($name == 'index/index/index' ? 'index/index' : $name) . '/' . $method->name,
                    'title'      => $title,
                    'ismenu'     => 0,
                    'createtime' => time(),
                    'updatetime' => time(),
                    'status'     => 1,
                ];
                $methodsData[] = $attr;
            }
            self::saveRules($val['id'], $methodsData);
        }
        return true;
    }

    /**
     * 更新某个菜单下的子节点
     * @param $id
     * @param $methods
     * @return bool
     * @throws \think\exception\DbException
     */
    private static function saveRules($id, $methods)
    {
        if (empty($methods)) return false;

        // 获取菜单下所有的method
        $findAll = collection(AdminRule::all(['pid' => $id, 'ismenu' => 0]))->toArray();
        $diff    = array_diff(array_column($findAll, 'name'), array_column($methods, 'name'));
        if (!empty($diff)) {
            AdminRule::where(['pid' => $id, 'ismenu' => 0, 'name' => ['IN', $diff]])->delete();
        }
        foreach ($methods as $method) {
            $isMenu = AdminRule::get(['ismenu' => 1, 'name' => $method['name']]);
            if (!empty($isMenu)) {
                continue;
            }
            $rules = AdminRule::get(['pid' => $id, 'ismenu' => 0, 'name' => $method['name']]);
            if (!empty($rules)) {
                $rules->save(['title' => $method['title']]);
            } else {
                AdminRule::insert($method);
            }
        }
        return true;
    }
}