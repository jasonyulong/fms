<?php

namespace app\common\library;

use think\Config;
use app\common\model\Bank;
use think\cache\driver\Redis;

/**
 * thinkphp5 也是够烦的，数组key未设置，报错
 * @author lamkakyun
 * @date 2018-11-12 15:31:50
 */
class FilterLib
{
    /**
     * 是数字
     * @author lamkakyun
     * @date 2018-11-12 15:27:00
     * @return bool
     */
    public static function isNum($arr, $key)
    {
        return isset($arr[$key]) && preg_match('/^\d+$/', $arr[$key]);
    }

    /**
     * 是非空字符串
     * @author lamkakyun
     * @date
     * @return bool
     */
    public static function isNotEmptyStr($arr, $key)
    {
        return isset($arr[$key]) && !empty($arr[$key]);
    }

    /**
     * 是价格
     * @author lamkakyun
     * @date 2018-11-12 15:36:38
     * @return bool
     */
    public static function isPrice($arr, $key)
    {
        return isset($arr[$key]) && self::isFloat($arr[$key]);
    }

    /**
     * 判断是否是金额
     * @param $val
     * @return false|int
     */
    public static function isFloat($val)
    {
        return preg_match('/^(-?\d+)(\.\d+)?$/', $val);
    }


    /**
     * 是非空数组
     * @author lamkakyun
     * @date 2018-11-12 19:20:57
     * @return bool
     */
    public static function isNotEmptyArr($arr, $key)
    {
        return isset($arr[$key]) && !empty($arr[$key]);
    }
}
