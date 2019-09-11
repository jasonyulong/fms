<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\command;

use app\index\command\Api\library\Builder;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;

/**
 * Class Api
 * @package app\index\command
 */
class Api extends Command
{
    private $command_name = 'Api';

    /**
     * 命令定义
     */
    protected function configure()
    {
        $this->setName('api')
            ->addOption('module', 'm', Option::VALUE_REQUIRED, __('类名称, 对应你要操作的那个类文件'), null)
            ->addOption('action', 'a', Option::VALUE_REQUIRED, __('方法名称, 对应你要操作的类文件里的方法名称'), null)
            ->addOption('name', null, Option::VALUE_OPTIONAL, 'api name', '')
            ->addOption('platform', null, Option::VALUE_OPTIONAL, 'platform name', '')
            ->setDescription('请求相关Api数据交互');
    }

    /**
     * 执行命令
     * @param Input $input
     * @param Output $output
     * @return int|null|void
     * @throws Exception
     */
    protected function execute(Input $input, Output $output)
    {
        $ns_prefix = "\\" . __NAMESPACE__ . "\\" . $this->command_name . "\\";
        $class_dir = __DIR__ . DIRECTORY_SEPARATOR . $this->command_name;

        $this->defaultExecute($input, $output, $class_dir, $ns_prefix);
    }

    /**
     * 初始执行命令
     * @param Input $input
     * @param Output $output
     * @param $class_dir
     * @param $ns_prefix
     * @throws Exception
     */
    private function defaultExecute(Input $input, Output $output, $class_dir, $ns_prefix)
    {
        $module   = $input->getOption('module') ?: '';
        $action   = $input->getOption('action') ?: '';
        $filename = $class_dir . DIRECTORY_SEPARATOR . ucfirst($module) . '.php';

        if (!$module) throw new Exception(__('请填写正确的类名称'));
        if (!$action) throw new Exception(__('请填写正确的方法名称'));
        if (!is_file($filename)) throw new Exception(__('填写的类名称错误'));

        $moduleName = $ns_prefix . ucfirst($module);

        $object = new $moduleName($input, $output);
        if (!method_exists($object, $action)) throw new Exception(__('操作方法不存在'));

        $result = $object->$action($input, $output);
        if ($result) $output->info(__($result));
    }
}
