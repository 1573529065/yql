<?php

namespace App\Controller\Admin;

use App\Dal\SystemGroup;
use App\Logic\System;
use App\Service\Pagination;
use App\Service\Validator;
use App\Service\Helper;
use App\Logic\SystemMenu;

class Group extends BaseController
{
    //列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * 15;

        $where = ['status !=' => -1];
        $list = SystemGroup::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = SystemGroup::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'curpage' => $curpage
        ]);
    }

    //添加
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'title' => 'required|maxlen:30 `组名称`',
                'description' => 'maxlen:255 `描述`',
                'permissions' => 'required|is_array `权限`',
                'status' => 'required|in:1,0',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData(true);
                $data['permissions'] = json_encode($data['permissions']);
                $data['addtime'] = time();
                SystemGroup::insert($data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
        $menus = System::getSortedMenuList();
        $this->view->setVar('menus', $menus);
    }

    //编辑
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'title' => 'required|maxlen:30 `组名称`',
                'description' => 'maxlen:255',
                'permissions' => 'required|is_array `权限`',
                'status' => 'required|in:1,0',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData(true);
                $data['permissions'] = json_encode($data['permissions']);
                SystemGroup::update(['id' => $data['id']], $data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $id = intval($this->request->get('id'));
        $p = max(intval($this->request->get('p')), 1);
        $info = ($id > 0) ? SystemGroup::fetchOne($id) : [];
        if (isset($info['permissions'])) {
            $info['permissions'] = json_decode($info['permissions'], true);
        }
        $menus = System::getSortedMenuList();
        $this->view->setVars([
            'info' => $info,
            'menus' => $menus,
            'p' => $p
        ]);
    }


    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                SystemGroup::update(['id' => $id], ['status' => -1]);
            }
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    //切换状态
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $status = intval($this->request->getPost('status'));
        $status = $status ? 1 : 0;
        if ($id > 0) {
            SystemGroup::update(['id' => $id], ['status' => $status]);
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }
}