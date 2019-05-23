<?php

namespace App\Controller\Admin;

use App\Dal\Adowner as AdownerDal;
use App\Service\Validator;
use App\Service\Helper;
use App\Service\Pagination;

class Adowner extends BaseController
{

    /**
     * 广告主列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;
        $where['status'] = 1;

        $list = AdownerDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = AdownerDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage
        ]);
    }

    //添加
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|minlen:1|maxlen:30 `广告主`',
                'contact' => 'required|minlen:1|maxlen:30 `联系人`',
                'mobile' => 'is_mobile `手机`',
                'qq' => 'is_numeric|maxlen:12 `QQ`',
                'remarks' => 'maxlen:255 `备注`'
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                $data['addtime'] = time();
                if (AdownerDal::insert($data)) {
                    Helper::json(true);
                } else {
                    Helper::json(false, '创建失败');
                }
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
    }

    //修改
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'name' => 'required|minlen:1|maxlen:30 `广告主`',
                'contact' => 'required|minlen:1|maxlen:30 `联系人`',
                'mobile' => 'is_mobile `手机`',
                'qq' => 'is_numeric|maxlen:12 `QQ`',
                'remarks' => 'maxlen:255 `备注`'
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (AdownerDal::update($data['id'], $data) !== false) {
                    Helper::json(true);
                } else {
                    Helper::json(false, '编辑失败');
                }
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
        $id = intval($this->request->get('id'));
        $p = max(intval($this->request->get('p')), 1);

        $info = AdownerDal::fetchOne($id);
        if (empty($info)) {
            return $this->showError('您编辑的信息不存在');
        }
        $this->view->setVars([
            'info' => $info,
            'p' => $p
        ]);

    }

    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                AdownerDal::update($id, ['status' => -1]);
            }
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

}