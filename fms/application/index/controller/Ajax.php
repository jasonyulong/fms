<?php
/**
 * @copyright Copyright (c) 2018 http://www.jeoshi.com All rights reserved.
 * @version   Beta 5.0
 * @author    kevin
 */

namespace app\index\controller;

use think\Lang;
use fast\Random;
use think\Cache;
use think\Config;
use think\Cookie;
use think\Session;
use app\common\model\Bank;
use app\common\model\Account;
use app\common\library\FilterLib;
use app\index\library\AccountLib;
use app\common\controller\PublicController;

/**
 * Ajax异步请求接口
 * @internal
 */
class Ajax extends PublicController
{
    protected $noNeedLogin = ['lang'];
    protected $noNeedRight = ['*'];
    protected $layout = '';

    public function _initialize()
    {
        parent::_initialize();

        //设置过滤方法
        $this->request->filter(['strip_tags', 'htmlspecialchars']);
    }

    /**
     * 加载语言包
     */
    public function lang()
    {
        header('Content-Type: application/javascript');
        $controllername = input("controllername");
        //默认只加载了控制器对应的语言名，你还根据控制器名来加载额外的语言包
        $this->loadlang($controllername);
        return jsonp(Lang::get(), 200, [], ['json_encode_param' => JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 登录时间校验
     */
    public function access()
    {
        $accesstime = Session::get('accesstime');
        $locktime   = Config::get('site.locktime');
        // 是否必须要重新登录
        $mustlogin = Cookie::get('mustlogin', false);
        // 超时需要锁屏
        if (!$accesstime || (time() - $accesstime) > ($locktime * 60) || $mustlogin) {
            Cookie::set('mustlogin', time());
            $this->error(__('超时'), url('/index/login/locks', ['url' => $this->request->post('url', '/')]));
        }
        return $this->success(__('Success'), null, ['refundtime' => (time() - $accesstime)]);
    }

    /**
     * 上传文件
     */
    public function upload()
    {
        Config::set('default_return_type', 'json');
        $file = $this->request->file('file');
        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }

        //判断是否已经存在附件
        $sha1 = $file->hash();

        $upload = Config::get('upload');

        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $type     = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size     = (int) $upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
        $fileInfo = $file->getInfo();
        $suffix   = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix   = $suffix ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr     = explode('/', $fileInfo['type']);

        //验证文件后缀
        if ($upload['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            $this->error(__('Uploaded file format is limited'));
        }
        $replaceArr = [
            '{year}'     => date("Y"),
            '{mon}'      => date("m"),
            '{day}'      => date("d"),
            '{hour}'     => date("H"),
            '{min}'      => date("i"),
            '{sec}'      => date("s"),
            '{random}'   => Random::alnum(16),
            '{random32}' => Random::alnum(32),
            '{filename}' => $suffix ? substr($fileInfo['name'], 0, strripos($fileInfo['name'], '.')) : $fileInfo['name'],
            '{suffix}'   => $suffix,
            '{.suffix}'  => $suffix ? '.' . $suffix : '',
            '{filemd5}'  => md5_file($fileInfo['tmp_name']),
        ];
        $savekey    = $upload['savekey'];
        $savekey    = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName  = substr($savekey, strripos($savekey, '/') + 1);
        //
        $splInfo = $file->validate(['size' => $size])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
        if ($splInfo) {
            $imagewidth = $imageheight = 0;
            if (in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf'])) {
                $imgInfo     = getimagesize($splInfo->getPathname());
                $imagewidth  = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
                $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
            }
            $params     = array(
                'admin_id'    => (int) $this->auth->id,
                'user_id'     => 0,
                'filesize'    => $fileInfo['size'],
                'imagewidth'  => $imagewidth,
                'imageheight' => $imageheight,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $fileInfo['type'],
                'url'         => $uploadDir . $splInfo->getSaveName(),
                'uploadtime'  => time(),
                'storage'     => 'local',
                'sha1'        => $sha1,
            );
            $attachment = model("attachment");
            $attachment->data(array_filter($params));
            $attachment->save();
            \think\Hook::listen("upload_after", $attachment);
            $this->success(__('Upload successful'), null, [
                'url' => $uploadDir . $splInfo->getSaveName()
            ]);
        } else {
            // 上传失败获取错误信息
            $this->error($file->getError());
        }
    }

    /**
     * 清空系统缓存
     */
    public function wipecache()
    {
        $type = $this->request->request("type");
        switch ($type) {
            case 'content' || 'all':
                rmdirs(CACHE_PATH, false);
                Cache::clear();
                if ($type == 'content')
                    break;
            case 'template' || 'all':
                rmdirs(TEMP_PATH, false);
                if ($type == 'template')
                    break;
        }

        \think\Hook::listen("wipecache_after");
        $this->success();
    }


    /**
     * 根据银行id, 获取支行 列表
     * @author lamkakyun
     * @date 2018-11-12 11:49:22
     * @return void
     */
    public function getSubBanks()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));
        if (!FilterLib::isNum($params, 'bank_id')) return json(['code' => -1, 'msg' => '参数错误']);

        $where = ['bank_id' => $params['bank_id']];
        if (FilterLib::isNum($params, 'province_id')) $where['province_id'] = $params['province_id'];
        if (FilterLib::isNum($params, 'city_id')) $where['city_id'] = $params['city_id'];

        $bank_model = new Bank();
        $data       = $bank_model->where($where)->field('sub_branch_id, sub_branch_name')->select()->toArray();

        return json(['code' => 0, 'msg' => 'bingo', 'data' => $data]);
    }


    /**
     * 获取第三方账户
     * @author lamkakyun
     * @date 2018-11-29 11:36:14
     * @return JSON
     */
    public function getThirdAccounts()
    {
        $params = array_merge(input('get.', '', 'trim'), input('post.', '', 'trim'));

        $account_params = ['type_attr' => $params['type']];
        $account_list = AccountLib::getInstance()->getThirdAccounts($account_params);

        return json(['code' => 0, 'msg' => 'bingo', 'data' => $account_list]);

    }
}
