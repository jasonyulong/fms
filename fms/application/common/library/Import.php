<?php


namespace app\common\library;

use think\View;

abstract class Import
{
    /**
     * 导入流水(抽象类)
     * @author lamkakyun
     * @date 2018-11-28 14:48:39
     */
    abstract public function importFlow($account_id, $excel_data, $params);


    /**
     * 生成导入报告 模板
     * @author lamkakyun
     * @date 2018-12-01 10:54:19
     * @return void
     */
    protected function genImportReport($success_num, $fail_arr, $index_arr, $column_arr)
    {
        return View::instance()->fetch(APP_PATH . "index/view/funds/index/import_report2.html", ['success_num' => $success_num, 'fail_arr' => $fail_arr, 'index_arr' => $index_arr, 'column_arr' => $column_arr]);
    }
}