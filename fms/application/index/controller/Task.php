<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller;

use think\Hook;
use think\Config;
use think\Cookie;
use think\Session;
use think\Validate;
use app\index\model\Admin;
use app\common\library\Sms;
use app\index\model\AdminLog;
use app\common\library\ToolsLib;
use app\common\controller\PublicController;

/**
 * 任务脚本
 */
class Task extends PublicController
{
    /**
     * 运行任务
     * php index.php /index/Task/run
     * @author lamkakyun
     * @date 2018-11-30 17:34:55
     * @return void
     */
    public function run()
    {
        // TODO: 只能 命令行允许
        if (!preg_match("/cli/i", php_sapi_name())) abort(404, 'method not exists, please check your url');
        ToolsLib::getInstance()->runImportTask();
    }

    /**
     * 拉取paypal 的数据到 本地
     * @author lamkakyun
     * @date 2018-12-08 09:50:53
     * @cmd php index.php /index/Task/pullERPPayaplDetail
     * @return void
     */
    public function pullERPPayaplDetail()
    {
        if (!preg_match("/cli/i", php_sapi_name())) abort(404, 'method not exists, please check your url');
        ToolsLib::getInstance()->pullERPPayaplDetail();
    }


    /**
     * 拉取 payonner 的数据到 本地
     * @author lamkakyun
     * @date 2019-01-16 16:40:29
     * @cmd php index.php /index/Task/pullERPPayonnerDetail
     * @return void
     */
    public function pullERPPayonnerDetail()
    {
        if (!preg_match("/cli/i", php_sapi_name())) abort(404, 'method not exists, please check your url');
        ToolsLib::getInstance()->pullERPPayonnerDetail();
    }

    
}