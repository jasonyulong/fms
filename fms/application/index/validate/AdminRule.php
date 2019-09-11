<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\validate;

use think\Validate;

/**
 * 菜单管理校验
 * Class AuthRule
 * @package app\index\validate
 */
class AdminRule extends Validate
{

    /**
     * 正则
     */
    protected $regex = ['format' => '[a-z0-9_\/]+'];

    /**
     * 验证规则
     */
    protected $rule = [
        'name'  => 'require|format',
        'title' => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'name.format' => 'URL规则只能是小写字母、数字、下划线和/组成'
    ];

    /**
     * 字段描述
     */
    protected $field = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
    ];

    /**
     * 析构函数
     * AuthRule constructor.
     * @param array $rules
     * @param array $message
     * @param array $field
     */
    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'name'      => __('名称'),
            'title'     => __('标题'),
            'condition' => __('请求地址'),
        ];

        $this->message['name.format'] = __('请求地址仅支持字母，数字，下划线和斜杠');
        parent::__construct($rules, $message, $field);
    }

}
