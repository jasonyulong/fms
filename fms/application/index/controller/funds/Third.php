<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller\funds;

use app\common\model\AccountFund;
use app\index\library\FundLib;
use app\common\library\ToolsLib;
use app\common\library\FilterLib;
use app\index\library\CompanyLib;
use app\index\library\AccountLib;
use app\common\model\AccountTemplate;
use app\common\controller\AuthController;
use think\Config;

/**
 * 第三方收款账户
 * Class index
 * @package app\index\controller\funds
 */
class Third extends AuthController
{
    /**
     * 查看
     * @access auth
     * @return string
     * @throws \ReflectionException
     */
    public function index()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $params['ps']   = $params['ps'] ?? 20;
        if (isset($params['is_export']) && $params['is_export'] == 1) $params['ps'] = 100000;
        $params['type'] = 2;

        // $params['status'] = 1;

        $all_currency_type = ToolsLib::getInstance()->getAllCurrencyType();
        $third_type_attr = ToolsLib::getInstance()->getThirdPayType();
        $data = FundLib::getInstance()->getAccountThirdList($params);



        // TODO: 导出 余额 excel
        if (isset($params['is_export']) && $params['is_export'] == 1) 
        {
            foreach ($data['list'] as $k => $v)
            {
                $data['list'][$k]['type_attr'] = $third_type_attr[$v['type_attr']];
                foreach ($all_currency_type as $_v)
                {
                    $data['list'][$k][$_v] = $v['funds'][$_v][0]['account_funds'] ?? '';
                }
            }

            $headers = [
                'type_attr' => '账户类型',
                'title'     => '账户名称',
                'account'   => '账户',
            ];

            foreach ($all_currency_type as $v) 
            {
                $headers[$v] = $v;
            }

            $file_name = '第三方收款账户(余额)-' . date('Ymd');
            ToolsLib::getInstance()->exportExcel($file_name, $headers, $data['list']);
        }

        $this->assign('all_company', CompanyLib::getInstance()->getAllCompany());
        $this->assign('all_currency_type', $all_currency_type);
        $this->assign('third_pay_account_type', ToolsLib::getInstance()->getThirdPayType());
        $this->assign('admin_list', AccountLib::getInstance()->getAllAccountAdmin());
        $this->assign('type_scene', ToolsLib::getInstance()->getTypeScene());
        $this->assign('third_type_attr', $third_type_attr);

        $this->assign('rows', $data['list']);
        $this->assign('page', $data['page']);
        $this->assign('params', $params);
        return parent::fetchAuto();
    }

    /**
     * 余额账户列表
     * @return string
     * @throws \ReflectionException
     * @throws \think\exception\DbException
     */
    public function more()
    {
        $account_id       = $this->request->get('account_id', 0);
        $account_currency = $this->request->get('currency', '');

        $finds = AccountFund::all(['account_id' => $account_id, 'account_currency' => $account_currency]);
        $this->assign('funds', $finds);
        return parent::fetchAuto();
    }
}