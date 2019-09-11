<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\model;

use app\index\library\Auth;
use think\Model;
use think\Session;

/**
 * 管理员日志表
 * Class AdminLog
 * @package app\index\model
 */
class AdminLog extends \app\common\model\AdminLog
{
    /**
     * 设置标题
     * @param $title
     */
    public static function setTitle($title)
    {
        self::$title = $title;
    }

    /**
     * 设置内容
     * @param $content
     */
    public static function setContent($content)
    {
        self::$content = $content;
    }

    /**
     * 写入日志
     * @param string $title
     */
    public static function record($title = '')
    {
        $auth     = Auth::instance();
        $userInfo = $auth->getUserInfo();

        // 某些url跳过写入日志
        $url = strtolower(trim(request()->url(), '/'));
        if (strpos($url, 'ajax') > 0 || strpos($url, 'login') > 0) {
            return true;
        }

        $admin_id = !empty($userInfo) ? $userInfo['id'] : 0;
        $username = !empty($userInfo) ? $userInfo['username'] : __('Unknown');

        $content = self::$content;
        if (!$content) {
            $content = request()->param();
            foreach ($content as $k => $v) {
                if (is_string($v) && strlen($v) > 200 || stripos($k, 'password') !== false) {
                    unset($content[$k]);
                }
            }
        }
        $title = empty($title) ? self::$title : $title;
        if (!$title) {
            $title      = [];
            $breadcrumb = Auth::instance()->getBreadcrumb();
            foreach ($breadcrumb as $k => $v) {
                $title[] = $v['title'];
            }
            $title = implode(' ', $title);
        }
        self::create([
            'title'     => $title,
            'content'   => !is_scalar($content) ? json_encode($content) : $content,
            'url'       => request()->url(),
            'admin_id'  => $admin_id,
            'username'  => $username,
            'useragent' => request()->server('HTTP_USER_AGENT'),
            'ip'        => request()->ip()
        ]);
    }

    /**
     * 查找管理员
     * @return $this
     */
    public function admin()
    {
        return $this->belongsTo('Admin', 'admin_id')->setEagerlyType(0);
    }

}
