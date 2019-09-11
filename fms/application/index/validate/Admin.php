<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\validate;

use think\Validate;

/**
 * 管理员校验
 * Class Admin
 * @package app\index\validate
 */
class Admin extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'username' => 'require|max:50|unique:admin',
        'mobile'   => 'require',
        'password' => 'require',
        'email'    => 'email|unique:admin,email',
    ];

    /**
     * 提示消息
     */
    protected $message = [];

    /**
     * 字段描述
     */
    protected $field = [];

    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['username', 'mobile', 'password'],
        'edit' => ['username', 'mobile'],
    ];

    /**
     * 析构函数
     * Admin constructor.
     * @param array $rules
     * @param array $message
     * @param array $field
     */
    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'username' => __('姓名'),
            'mobile'   => __('手机号码'),
            'password' => __('密码'),
            'email'    => __('邮箱'),
        ];
        parent::__construct($rules, $message, $field);
    }

}
