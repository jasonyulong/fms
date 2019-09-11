<?php

namespace app\common\model;

use think\Model;

class Account extends Model
{
    protected $name = 'account';

    /**
     * 获取账户资金
     * @return \think\model\relation\HasMany
     */
    public function minifund()
    {
        return $this->hasMany('AccountFund', 'account_id', 'id')->field('id, fund_name, account_id, account_currency');
    }

    /**
     * 获取账户资金
     * @return \think\model\relation\HasMany
     */
    public function funds()
    {
        return $this->hasMany('AccountFund', 'account_id', 'id');
    }
}