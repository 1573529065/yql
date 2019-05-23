<?php

namespace App\Controller\Admin;

use App\Dal\Version as VersionDal;
use App\Logic\Build;
use App\Service\Validator;
use App\Service\Helper;
use App\Service\Pagination;
use Phalcon\Di;

//版本更新管理
class Version extends BaseController
{
    protected $whiteList = ['toggle'];

    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = ['status !=' => -1];
        $list = VersionDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = VersionDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'curpage' => $curpage,
        ]);
    }

    //添加
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'version' => 'required `新版本号`',
                'size' => 'required|is_numeric `新版大小`',
                'origin_package_android' => 'required|is_url `安卓母包地址`',
                'origin_package_ios' => 'required|is_url `IOS母包地址`',
                'plist_url' => 'required|is_url `IOS描述文件`',
                'force_update' => 'required|in:0,1 `强制更新`',
                'des' => ''
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (VersionDal::fetchOne(['version' => $data['version'], 'status !=' => -1])) {
                    Helper::json(false, '当前版本已存在');
                }
                if (false === VersionDal::insert($data)) {
                    Helper::json(false, '创建失败');
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        //上传签名
        $config = Di::getDefault()->get('config')['upload'];
        $headers = [
            'Appkey' => $config['appkey'],
            'Nonce' => Helper::getRandStr(),
            'Timestamp' => time(),
        ];
        ksort($headers);
        $str = Helper::buildQuery($headers);
        $str .= $config['appsecret'];
        $sign = md5($str);
        $headers['Sign'] = $sign;

        $upload_domain = $config['upload_domain'];
        $this->view->setVars([
            'headers' => $headers,
            'upload_domain' => $upload_domain,
        ]);
    }

    //修改
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'version' => 'required `新版本号`',
                'size' => 'required|is_numeric `新版大小`',
                'origin_package_android' => 'required|is_url `安卓母包地址`',
                'origin_package_ios' => 'required|is_url `IOS母包地址`',
                'plist_url' => 'required|is_url `IOS描述文件`',
                'force_update' => 'required|in:0,1 `强制更新`',
                'des' => ''
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                //查看是否存在新版本号
                if (VersionDal::fetchOne(['id != ' => $data['id'], 'version' => $data['version'], 'status !=' => -1])) {
                    Helper::json(false, '新版本号已经存在，请检查');
                }
                if (false === VersionDal::update($data['id'], $data)) {
                    Helper::json(false, '更新失败');
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        } else {
            $id = intval($this->request->get('id'));
            $p = max(intval($this->request->get('p')), 1);
            $info = VersionDal::fetchOne(['id' => $id, 'status !=' => -1]);
            if (empty($info)) {
                return $this->showError('您编辑的信息不存在');
            }
            $this->view->setVars([
                'info' => $info,
                'p' => $p
            ]);

            //上传签名
            $config = Di::getDefault()->get('config')['upload'];
            $headers = [
                'Appkey' => $config['appkey'],
                'Nonce' => Helper::getRandStr(),
                'Timestamp' => time(),
            ];
            ksort($headers);
            $str = Helper::buildQuery($headers);
            $str .= $config['appsecret'];
            $sign = md5($str);
            $headers['Sign'] = $sign;

            $upload_domain = $config['upload_domain'];
            $this->view->setVars([
                'headers' => $headers,
                'upload_domain' => $upload_domain,
            ]);
        }
    }

    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                VersionDal::update($id, ['status' => -1]);
            }
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    //切换状态
    public function toggle()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'field' => 'required',
            'val' => 'required'
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData();
            VersionDal::update($data['id'], [$data['field'] => $data['val']]);
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }

    //一键分包
    public function build()
    {
        $id = $this->request->get('id');
        $vesion = VersionDal::fetchOne($id);
        if (empty($vesion)) {
            Helper::json(false, '版本不存在');
        }
        $config = Di::getDefault()->get('config')['upload'];
        $urls = parse_url($vesion['origin_package_android']);
        $origin_package_path = $config['path'] . $urls['path'];
        Build::run($origin_package_path, $vesion['version']);
        Helper::json(true);
    }
}