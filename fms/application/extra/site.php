<?php

return array (
  'name' => 'ERP 2.0',
  'version' => '1.0.1',
  'timezone' => 'Asia/Shanghai',
  'forbiddenip' => '*
10.0.2.2
192.168.1.1
127.0.0.1',
  'fixedpage' => '/',
  'platforms' => 
  array (
    1 => 'ebay',
    2 => 'wish',
    3 => 'amazon',
    4 => 'cdiscount',
    5 => 'priceminister',
    6 => 'walmart',
    8 => 'linio',
    9 => 'fnac',
    10 => 'joom',
    11 => 'jumia',
    12 => 'lazada',
    13 => 'manomano',
    14 => 'maymal',
    15 => 'shopee',
    16 => 'aliexpress',
  ),
  'third_pay_account_type' => 
  array (
    1 => 'Payoneer',
    2 => 'Paypal',
    3 => '连连',
    4 => 'OFX',
    5 => 'Pingpong',
    6 => '支付宝',
    7 => 'WordFirst',
    8 => '微信',
    9 => '海外银行账户',
  ),
  'mail_type' => '1',
  'mail_smtp_host' => 'smtp.qq.com',
  'mail_smtp_port' => '465',
  'mail_smtp_user' => '10000',
  'mail_smtp_pass' => 'password',
  'mail_verify_type' => '2',
  'mail_from' => '10000@qq.com',
  'account_type' => 
  array (
    1 => '平台账户',
    2 => '第三方收款卡',
    3 => '转账卡',
  ),
  'type_scene' => 
  array (
    1 => '付款',
    2 => '收款',
    3 => '不限',
  ),
  'locktime' => '30',
  'logintime' => '300',
  'bank_type' => 
  array (
    0 => '对私',
    1 => '对公',
  ),
);