<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// [ 应用入口文件 ]
// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');

// 判断判断
if (PHP_VERSION < 7) {
    exit('PHP版本过低，请升级PHP版本. (http://www.php.net/downloads.php)') . PHP_EOL;
}

//开发环境
define('ENVIRONMENT', 'development');

//测试环境
//define('ENVIRONMENT', 'test');

//生产环境
//define('ENVIRONMENT', 'production');

// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
