<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\model;

use app\common\model\AccountAdmin;
use app\common\model\AccountFundDetail;
use app\index\library\Auth;
use think\Session;

/**
 * 管理员模型
 * Class Admin
 * @package app\index\model
 */
class Admin extends \app\common\model\Admin
{
    /**
     * @param $admin_id
     * @param int $limit
     * @return array
     * @throws \think\exception\DbException
     */
    public static function getAccountId($admin_id, $limit = 20)
    {
        $model      = new AccountAdmin();
        $fundsmodel = new AccountFundDetail();

        $column     = $model->where(['admin_id' => $admin_id])->column('account_id');
        $accountIds = !empty($column) ? $column : [0];

        $where = ['status' => 0];
        if (!Auth::instance()->isSuperAdmin()) {
            $where['to_account_id'] = ['IN', $accountIds];
        }

        $total = $fundsmodel->where($where)->count();
        $list  = $fundsmodel->where($where)->order('id', 'desc')->paginate($limit);

        return [$list->render(), $list, $total];
    }

    /**
     * 重置用户密码
     * @param $uid
     * @param $NewPassword
     * @return $this
     */
    public function resetPassword($uid, $newPassword)
    {
        $passwd = $this->encryptPassword($newPassword);
        $ret    = $this->where(['id' => $uid])->update(['password' => $passwd]);
        return $ret;
    }

    /**
     * 密码加密
     * @param $password
     * @param string $salt
     * @param string $encrypt
     * @return mixed
     */
    public function encryptPassword($password, $salt = '', $encrypt = 'md5')
    {
        return $encrypt(APP_SECRETKEY . $password . $salt);
    }
}
