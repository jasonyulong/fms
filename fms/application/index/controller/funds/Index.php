<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\funds;

use app\common\model\Task;
use app\index\model\Admin;
use app\common\model\Account;
use app\index\model\AdminLog;
use app\index\library\FundLib;
use app\common\library\ToolsLib;
use app\common\library\FilterLib;
use app\index\library\AccountLib;
use app\common\library\import\OFX;
use app\common\library\import\Bank;
use app\common\library\import\Paypal;
use app\common\model\AccountFundDiff;
use app\common\library\import\Lianlian;
use app\common\library\import\Payoneer;
use app\common\library\import\Pingpong;
use app\common\controller\AuthController;

/**
 * 余额管理
 * Class index
 * @package app\index\controller\funds
 */
class Index extends AuthController
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('AccountFundDetail');
    }

    /**
     * 待确认列表
     * @access auth
     * @return string
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        list($page, $rows, $total) = Admin::getAccountId($this->auth->id, 20);

        $this->assign('page', $page);
        $this->assign('rows', $rows);
        $this->assign('total', $total);
        return parent::fetchAuto('confirm');
    }

    /**
     * 确定到账
     * @access auth
     * @return string
     * @throws \ReflectionException
     */
    public function determine()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        if ($this->request->isGet()) {
            $this->assign('rows', $this->model->get(input('get.id')));
            return parent::fetchAuto();
        }
        AdminLog::setTitle(__('确定到账'));

        $params['auth_id']       = $this->auth->id;
        $params['auth_username'] = $this->auth->username;
        return FundLib::getInstance()->confirmMoneyArrival($params);
    }

    /**
     * 差异金额
     * @access auth
     * @return string
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public function difference()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $where  = [];

        if (FilterLib::isNum($params, 'account_id')) $where['account_id'] = $params['account_id'];
        if (FilterLib::isNum($params, 'fund_id')) $where['account_funds_id'] = $params['fund_id'];

        $total = AccountFundDiff::where($where)->count();
        $list  = AccountFundDiff::where($where)->order('id', 'desc')->paginate(20);

        if (isset($params['debug']) && $params['debug'] == 'sql') {
            echo '<pre>';
            var_dump(AccountFundDiff::getLastSql());
            echo '</pre>';
            exit;
        }

        $this->assign('page', $list->render());
        $this->assign('rows', $list->toArray());
        $this->assign('total', $total);
        return parent::fetchAuto();
    }

    /**
     * 导入银行流水
     * @access auth
     * @return array|string|\think\response\Json
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function importBankDetail()
    {
        AdminLog::setTitle('导入银行流水');

        $params          = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $getBankAccounts = AccountLib::getInstance()->getBankAccounts();

        if ($this->request->isGet()) {
            $this->assign('params', $params);
            $this->assign('bank_accounts', $getBankAccounts);
            return parent::fetchAuto();
        }

        // TODO: 检测数据是否异常
        if (!FilterLib::isNum($params, 'account_id')) return json(['code' => -1, 'msg' => '请选择上传账户']);
        if (!isset($_FILES) || !isset($_FILES['file'])) return json(['code' => -1, 'msg' => '上传文件失败']);

        $file_info = $_FILES['file'];
        $ext       = get_file_exention($file_info['name']);

        if (!in_array($ext, ['xls', 'xlsx'])) return json(['code' => -1, 'msg' => '请上传excel文件']);

        $ret_import = ToolsLib::getInstance()->getImportExcelData();

        if (!$ret_import['success']) return json(['code' => -1, 'msg' => '获取EXCEL数据失败']);
        $excel_data = $ret_import['data'];

        $importObj = new Bank();
        return $importObj->importFlow($params['account_id'], $excel_data);
    }


    /**
     * 导入收款账户流水
     * @access auth
     * @return \think\response\Json
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function importFlowDetail()
    {
        // 文件太大的时候，可能要修改的配置 nginx: fastcgi_read_timeout, client_max_body_size; php: post_max_size, upload_max_size
        AdminLog::setTitle('导入收款账户流水');
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $params          = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        $account_model   = new Account();
        $third_pay_types = ToolsLib::getInstance()->getThirdPayType();

        if ($this->request->isGet()) {
            $this->assign('params', $params);
            $this->assign('third_pay_account_type', $third_pay_types);

            $task_model = new Task();
            $task_list  = $task_model->where(['type' => 1])->order('id DESC')->limit(10)->select()->toArray();
            $this->assign('task_list', $task_list);

            return parent::fetchAuto();
        }

        // TODO: 检测数据是否异常
        if (!FilterLib::isNum($params, 'account_id')) return json(['code' => -1, 'msg' => '参数错误']);
        if (!FilterLib::isNum($params, 'type') || !in_array($params['type'], array_keys($third_pay_types))) return json(['code' => -1, 'msg' => '参数错误(2)']);

        if (!isset($_FILES) || !isset($_FILES['file'])) return json(['code' => -1, 'msg' => '上传文件失败']);

        $file_info = $_FILES['file'];
        $ext       = get_file_exention($file_info['name']);

        // PHPEXCEL 扩展本身支持CSV，所以无需对CSV文件特殊处理
        if (!in_array($ext, ['xls', 'xlsx', 'csv'])) return json(['code' => -1, 'msg' => '请上传excel文件']);

        $account_info = $account_model->where(['id' => $params['account_id']])->find()->toArray();
        if (!$account_info) return json(['code' => -1, 'msg' => '账户名称不存在']);

        if ($ext == 'csv') $ret_import = ToolsLib::getInstance()->getImportCSVData();
        else $ret_import = ToolsLib::getInstance()->getImportExcelData();

        if (!$ret_import['success']) return json(['code' => -1, 'msg' => '获取EXCEL数据失败']);

        $excel_data = $ret_import['data'];
        if (!$excel_data[0][0]) return json(['code' => -1, 'msg' => '获取EXCEL数据失败(2)']);

        $task_name               = $file_info['name'];
        $params['auth_id']       = $this->auth->id;
        $params['auth_username'] = $this->auth->username;

        // 如果是Paypal，使用后台导入，否则直接导入（因为paypal的excel 文件太大了，导入太长时间，会导致服务器超时）
        if ($params['type'] == 2) {
            $ret_add_task = ToolsLib::getInstance()->buildImportTask($task_name, 1, $params, $excel_data);
            if ($ret_add_task) return json(['code' => 0, 'msg' => '流水导入任务添加成功']);
            return json(['code' => -1, 'msg' => '流水导入任务添加失败,如果是CSV格式的文件请转成.xlsx重试']);
        }

        $importObj = null;
        switch ($params['type']) {
            case '1':
                $importObj = new Payoneer();
                break;
            case '2':
                $importObj = new Paypal();
                break;
            case '3':
                $importObj = new Lianlian();
                break;
            case '4':
                $importObj = new OFX();
                break;
            case '5':
                $importObj = new Pingpong();
                break;
        }
        
        if (!$importObj) return json(['code' => -1, 'msg' => '暂不支持该第三方支付']);
        $ret = $importObj->importFlow($params['account_id'], $excel_data, $params);
        return json($ret);
    }


}