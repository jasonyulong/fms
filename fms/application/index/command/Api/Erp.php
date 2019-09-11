<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\command\Api;

use app\common\library\ToolsLib;
use think\console\Input;
use think\console\Output;

/**
 * 跟ERP交互任务
 * Class Erp
 * @package app\index\command\Api
 */
class Erp
{
    /**
     * 导入paypal流水记录数据
     * @author lamkakyun
     * @param Input $input
     * @param Output $output
     * @cmd php think api -m Erp -a paypal
     */
    public function paypal(Input $input, Output $output)
    {
        ToolsLib::getInstance()->pullERPPayaplDetail();
    }

    /**
     * 导入P卡流水记录数据
     * @param Input $input
     * @param Output $output
     * @cmd php think api -m Erp -a payonner
     */
    public function payonner(Input $input, Output $output)
    {
        ToolsLib::getInstance()->pullERPPayonnerDetail();
    }


    /**
     * 同步 payonner 账号信息
     * @author lamkakyun
     * @date 2019-01-21 18:08:45
     * @cmd php think api -m Erp -a payonnerAccount
     * @return void
     */
    public function payonnerAccount(Input $input, Output $output)
    {
        ToolsLib::getInstance()->syncPayonnerAccounts();
    }
}