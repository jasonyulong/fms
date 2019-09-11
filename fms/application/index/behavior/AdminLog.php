<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\behavior;

/**
 * 管理员日志
 * Class AdminLog
 * @package app\index\behavior
 */
class AdminLog
{
    /**
     * 写入日志
     * @param $params
     */
    public function run(&$params)
    {
        if (request()->isPost()) {
            \app\index\model\AdminLog::record();
        }
    }
}
