<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\common\library;

use think\Hook;

/**
 * 短信验证码类
 * Class Sms
 * @package app\common\library
 */
class Sms
{

    /**
     * 验证码有效时长
     * @var int
     */
    protected static $expire = 120;

    /**
     * 最大允许检测的次数
     * @var int
     */
    protected static $maxCheckNums = 10;

    /**
     * 获取最后一次手机发送的数据
     * @param   int $mobile 手机号
     * @param   string $event 事件
     * @return array|false|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function get($mobile, $event = 'default')
    {
        $sms = \app\common\model\Sms::
        where(['mobile' => $mobile, 'event' => $event])
            ->order('id', 'DESC')
            ->find();
        Hook::listen('sms_get', $sms, null, true);
        return $sms ? $sms : NULL;
    }

    /**
     * 发送验证码
     * @param   int $mobile 手机号
     * @param   int $code 验证码,为空时将自动生成4位数字
     * @param   string $event 事件
     * @param string $username
     * @return bool
     */
    public static function send($mobile, $code = NULL, $event = 'default', $id, $username = '')
    {
        if (empty($mobile)) {
            return FALSE;
        }
        $code   = is_null($code) ? mt_rand(1000, 9999) : $code;
        $time   = time();
        $ip     = request()->ip();
        $sms    = \app\common\model\Sms::create([
            'admin_id'   => $id,
            'event'      => $event,
            'mobile'     => $mobile,
            'code'       => $code,
            'ip'         => $ip,
            'createtime' => $time
        ]);
        $result = \sms\Mysubmail::instance()->send($mobile, ['code' => $code, 'username' => $username]);
        if (!$result) {
            $sms->delete();
            return FALSE;
        }
        return TRUE;
    }

    /**
     * 校验验证码
     * @param   int $mobile 手机号
     * @param   int $code 验证码
     * @param   string $event 事件
     * @return bool|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function check($mobile, $code, $event = 'default')
    {
        $time = time() - self::$expire;
        $sms  = \app\common\model\Sms::where(['mobile' => $mobile, 'event' => $event])->order('id', 'DESC')->find();
        if ($sms) {
            if ($sms['createtime'] > $time && $sms['times'] <= self::$maxCheckNums) {
                $correct = $code == $sms['code'];
                if (!$correct) {
                    $sms->times = $sms->times + 1;
                    $sms->save();
                    return FALSE;
                } else {
                    return TRUE;
                }
            } else {
                // 过期则清空该手机验证码
                self::flush($mobile, $event);
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * 清空指定手机号验证码
     * @param   int $mobile 手机号
     * @param   string $event 事件
     * @return  boolean
     */
    public static function flush($mobile, $event = 'default')
    {
        \app\common\model\Sms::where(['mobile' => $mobile, 'event' => $event])->delete();
        Hook::listen('sms_flush');
        return TRUE;
    }

}
