#!/usr/bin/env bash

#  -----------------------------------
#  此脚本为同步ERP数据到FMS来
#  author  Kevin
# -----------------------------------

#PHP脚本的执行目录
phpRoot=/opt/web/fms
#PHP
phpBin=/usr/local/php/bin/php
#PHP file
phpRun=think
cd $phpRoot
days=$1
# 当前时间
nowtime=$(date +%Y-%m-%d)
if [[ $days -lt 0 ]]; then
    nowtime=$(date +%Y-%m-%d -d "$1 days")
fi
# 运行路由方法
grepName="api -m erp -a paypal"
# 过期时间 秒, 超过这个时间的进程直接干掉, 处理死进程
timeOut=1800

# 根据退款时间同步
count=`ps -ef | grep "$grepName" | grep -v "grep" | wc -l`
if [[ $count -le 0 ]]; then
    $phpBin $phpRun $grepName > /dev/null 2>&1 &
fi