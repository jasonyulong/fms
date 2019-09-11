<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\common\model;

use think\Model;

/**
 * 角色模型
 * Class AdminGroup
 * @package app\index\model
 */
class AdminGroup extends Model
{
    // 表名,不含前缀
    protected $name = 'admin_group';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getNameAttr($value, $data)
    {
        return __($value);
    }

}
