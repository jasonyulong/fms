<?php

// 公共助手函数

if (!function_exists('__')) {

    /**
     * 获取语言变量值
     * @param string $name 语言变量名
     * @param array $vars 动态变量值
     * @param string $lang 语言
     * @return mixed
     */
    function __($name, $vars = [], $lang = '')
    {
        if (is_numeric($name) || !$name)
            return $name;
        if (!is_array($vars)) {
            $vars = func_get_args();
            array_shift($vars);
            $lang = '';
        }
        return \think\Lang::get($name, $vars, $lang);
    }

}

if (!function_exists('format_bytes')) {

    /**
     * 将字节转换为可读文本
     * @param int $size 大小
     * @param string $delimiter 分隔符
     * @return string
     */
    function format_bytes($size, $delimiter = '')
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        for ($i = 0; $size >= 1024 && $i < 6; $i++)
            $size /= 1024;
        return round($size, 2) . $delimiter . $units[$i];
    }

}

if (!function_exists('datetime')) {

    /**
     * 将时间戳转换为日期时间
     * @param int $time 时间戳
     * @param string $format 日期时间格式
     * @return string
     */
    function datetime($time, $format = 'Y-m-d H:i:s')
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        return date($format, $time);
    }

}

if (!function_exists('datetimediff')) {
    /**
     * 距离现在多长时间
     * @param $time
     * @return string
     */
    function datetimediff($time, $endtime = 0)
    {
        if ($endtime == 0) {
            $endtime = time();
        }
        $second = $endtime - $time;
        if ($second == 0) {
            return "0秒";
        }
        // 小于1分钟
        if ($second < 60) {
            return $second . '秒';
        }
        if ($second < 3600) {
            return intval($second / 60) . '分钟';
        }
        if ($second < 86400) {
            return intval($second / 3600) . '小时';
        }
        $days = intval($second / 86400);
        return $days . '天';
    }
}

if (!function_exists('human_date')) {

    /**
     * 获取语义化时间
     * @param int $time 时间
     * @param int $local 本地时间
     * @return string
     */
    function human_date($time, $local = null)
    {
        return \fast\Date::human($time, $local);
    }

}

if (!function_exists('cdnurl')) {

    /**
     * 获取上传资源的CDN的地址
     * @param string $url 资源相对地址
     * @param boolean $domain 是否显示域名 或者直接传入域名
     * @return string
     */
    function cdnurl($url, $domain = false)
    {
        $url = preg_match("/^https?:\/\/(.*)/i", $url) ? $url : \think\Config::get('upload.cdnurl') . $url;
        if ($domain && !preg_match("/^(http:\/\/|https:\/\/)/i", $url)) {
            if (is_bool($domain)) {
                $public = \think\Config::get('view_replace_str.__PUBLIC__');
                $url    = rtrim($public, '/') . $url;
                if (!preg_match("/^(http:\/\/|https:\/\/)/i", $url)) {
                    $url = request()->domain() . $url;
                }
            } else {
                $url = $domain . $url;
            }
        }
        return $url;
    }

}


if (!function_exists('grepDocComment')) {
    /**
     * 匹配反射出来的注释
     * @param $doc
     * @param string $name
     * @param string $default
     * @return string
     */
    function grepDocComment($doc, $name = '', $default = '')
    {
        if (empty($doc)) return $default;
        if (preg_match('#^/\*\*(.*)\*/#s', $doc, $comment) === false)
            return $default;

        $comment = trim($comment[1]);
        if (preg_match_all('#^\s*\*(.*)#m', $comment, $lines) === false)
            return $default;

        $docLines = ($lines[1]);
        if (empty($name))
            return trim($docLines[0]) ?? $default;
    }
}


if (!function_exists('is_really_writable')) {

    /**
     * 判断文件或文件夹是否可写
     * @param    string $file 文件或目录
     * @return    bool
     */
    function is_really_writable($file)
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return is_writable($file);
        }
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . md5(mt_rand());
            if (($fp = @fopen($file, 'ab')) === FALSE) {
                return FALSE;
            }
            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return TRUE;
        } elseif (!is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE) {
            return FALSE;
        }
        fclose($fp);
        return TRUE;
    }

}

if (!function_exists('rmdirs')) {

    /**
     * 删除文件夹
     * @param string $dirname 目录
     * @param bool $withself 是否删除自身
     * @return boolean
     */
    function rmdirs($dirname, $withself = true)
    {
        if (!is_dir($dirname))
            return false;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        if ($withself) {
            @rmdir($dirname);
        }
        return true;
    }

}

if (!function_exists('copydirs')) {

    /**
     * 复制文件夹
     * @param string $source 源文件夹
     * @param string $dest 目标文件夹
     */
    function copydirs($source, $dest)
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        foreach (
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item
        ) {
            if ($item->isDir()) {
                $sontDir = $dest . DS . $iterator->getSubPathName();
                if (!is_dir($sontDir)) {
                    mkdir($sontDir, 0755, true);
                }
            } else {
                copy($item, $dest . DS . $iterator->getSubPathName());
            }
        }
    }

}

if (!function_exists('mb_ucfirst')) {

    function mb_ucfirst($string)
    {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_strtolower(mb_substr($string, 1));
    }

}

if (!function_exists('var_export_short')) {

    /**
     * 返回打印数组结构
     * @param string $var 数组
     * @param string $indent 缩进字符
     * @return string
     */
    function var_export_short($var, $indent = "")
    {
        switch (gettype($var)) {
            case "string":
                return '"' . addcslashes($var, "\\\$\"\r\n\t\v\f") . '"';
            case "array":
                $indexed = array_keys($var) === range(0, count($var) - 1);
                $r       = [];
                foreach ($var as $key => $value) {
                    $r[] = "$indent    "
                        . ($indexed ? "" : var_export_short($key) . " => ")
                        . var_export_short($value, "$indent    ");
                }
                return "[\n" . implode(",\n", $r) . "\n" . $indent . "]";
            case "boolean":
                return $var ? "TRUE" : "FALSE";
            default:
                return var_export($var, TRUE);
        }
    }

}

if (!function_exists('gen_pager_data')) {
    /**
     * 生成自定义的分页数据
     * @author: jason
     * @date: 2018-09-14 18:18:59
     */
    function gen_pager_data($current_page, $list_total, $page_size = 100)
    {
        $page_count = ($list_total != $page_size) ? intval($list_total / $page_size) + 1 : intval($list_total / $page_size);
        if ($current_page > $page_count) $current_page = $page_count;
        if ($current_page <= 3) {
            $all_page_num = range(1, $page_count > 5 ? 5 : $page_count);
        } else if ($page_count - $current_page <= 3) {
            $all_page_num = range($page_count - 5, $page_count);
        } else {
            $all_page_num = range($current_page - 2, $current_page + 2);
        }
        $last_page = ($current_page - 1) > 0 ? $current_page - 1 : 1;
        $next_page = ($current_page + 1) < $page_count ? ($current_page + 1) : $page_count;

        $all_page_num = array_filter($all_page_num, function ($val) {
            return $val > 0;
        });
        return ['all_page_num' => $all_page_num, 'last_page' => $last_page, 'next_page' => $next_page];
    }
}


if (!function_exists('get_file_exention')) {
    /**
     * 获取文件扩展(自动转换为小写， 如果没有文件扩展，返回空)
     * @author lamkakyun
     * @date 2018-12-03 10:16:00
     * @return string
     */
    function get_file_exention($file_name)
    {
        $arr = explode('.', $file_name);
        if (count($arr) == 1) return '';

        $ext = $arr[count($arr) - 1];
        return strtolower($ext);
    }
}


if (!function_exists('is_all_empty')) {
    /**
     * 判断数组是否全部为空
     * @author lamkakyun
     * @date 2018-12-03 10:15:11
     * @return boolean
     */
    function is_all_empty($arr)
    {
        $is_all_empty = true;
        foreach ($arr as $v) {
            if (!empty($v)) {
                $is_all_empty = false;
                break;
            }
        }

        return $is_all_empty;
    }
}



if (!function_exists('replace_money')) {
    /**
     * 格式化金额
     * @param $money
     * @return mixed
     */
    function replace_money($money)
    {
        return str_replace(',', '', $money);
    }
}


if (!function_exists('curl_get'))
{
    /**
     * 模拟请求 GET
     * @author lamkakyun
     * @date 2018-12-28 11:47:17
     * @return void
     */
    function curl_get($url, $header = [])
    {
        $ch = curl_init();
        try {
            curl_setopt($ch, CURLOPT_URL, $url);
            if ($header) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $res_data = curl_exec($ch);
            if (curl_errno($ch)) return ['success' => false, 'msg' => curl_error($ch)];

            return ['success' => true, 'msg' => 'bingo', 'data' => $res_data];
        } 
        catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
        finally {
            curl_close($ch); 
        }
    }
}

if (!function_exists('curl_post'))
{
    /**
     * 模拟请求 POST
     * @author lamkakyun
     * @date 2018-12-28 11:47:17
     * @return void
     */
    function curl_post($url, $header = [], $post_data = [])
    {
        $ch = curl_init();
        try {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($post_data) curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            if ($header) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $res_data = curl_exec($ch);
            if (curl_errno($ch)) return ['success' => false, 'msg' => curl_error($ch)];

            return ['success' => true, 'msg' => 'bingo', 'data' => $res_data];
        } 
        catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
        finally {
            curl_close($ch); 
        }
    }
}