<?php

namespace App\Controller\Admin;

use App\Service\Helper;
use App\Service\Validator;
use App\Logic\System;
use App\Dal\SystemMenu as MenuDal;

class Menu extends BaseController
{
    protected $whiteList = ['update_sort'];

    //菜单列表
    public function index()
    {
        $list = System::getSortedMenuList();
        $this->view->setVar('list', $list);
    }

    //添加/编辑菜单
    public function add()
    {
        if ($this->request->isPost()) {
            $id = $this->request->getPost('id');
            $v = new Validator();
            $rules = [
                'title' => 'required|trim',
                'pid' => 'required|intval',
                'url' => 'trim',
                'display' => 'required|intval',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if ($data['pid'] == 0) {
                    $data['url'] = '';
                }
                if ($id > 0) {
                    MenuDal::update(['id' => $id], $data);
                } else {
                    $data['ico_class'] = 'layui-icon-align-left';
                    MenuDal::insert($data);
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $id = intval($this->request->get('id'));
        $info = ($id > 0) ? MenuDal::fetchOne($id) : [];
        $this->view->setVar('info', $info);
        $this->view->setVar('topMenus', System::getTopMenuList());
    }

    //删除菜单
    public function delete()
    {
        $id = $this->request->get('id');
        if ($id > 0) {
            MenuDal::update(['id' => $id], ['status' => -1]);
            Helper::json(true);
        } else {
            Helper::json(false, '参数错误');
        }
    }

    //更改菜单排序
    public function update_sort()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'sort' => 'required|intval|gte:0',
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData(true);
            MenuDal::update(['id' => $data['id']], ['sort' => $data['sort']]);
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }

    //显示隐藏
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $display = intval($this->request->getPost('display'));
        $display = $display ? 1 : 0;
        if ($id > 0) {
            MenuDal::update(['id' => $id], ['display' => $display]);
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }
}