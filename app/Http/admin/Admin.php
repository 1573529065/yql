<?php

namespace App\Controller\Admin;

use App\Dal\SystemAdmin;
use App\Dal\SystemGroup;
use App\Service\Pagination;
use App\Service\Validator;
use App\Service\Helper;
use App\Logic\System;

class Admin extends BaseController
{
    protected $whiteList = ['autocomplete'];

    //列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = ['status !=' => -1];
        $list = SystemAdmin::fetchList($where, $offset, $pagesize);
        $total = SystemAdmin::count($where);

        $page = new Pagination($total, $pagesize, $curpage);

        $groups = SystemGroup::fetchAll([], '', 'id,title,description');
        $groups = array_combine(array_column($groups, 'id'), $groups);

        $this->view->setVars([
            'list' => $list,
            'groups' => $groups,
            'page' => $page->generate(),
            'curpage' => $curpage
        ]);
    }

    //添加管理员
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'group_id' => 'required|intval|gt:0 `角色`',
                'username' => 'required|minlen:4|maxlen:16|is_alpha_num_dash `用户名`',
                'nickname' => 'required|minlen:2|maxlen:16 `昵称`',
                'mobile' => 'required|trim|is_mobile `手机`',
                'password' => 'required|minlen:6|maxlen:16 `密码`',
                'repwd' => 'required|same:password ``重复密码错误``',
                'is_business' => 'required|in:0,1 `是否商务`',
                'qq' => 'trim `QQ`',
                'status' => 'required|intval|in:0,1',
            ];

            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData(true);
                unset($data['repwd']);
                if (SystemAdmin::fetchOne(['username' => $data['username']])) {
                    Helper::json(false, '用户名已存在');
                }
                $salt = Helper::getRandStr(6);
                $data['salt'] = $salt;
                $data['password'] = md5($data['password'] . $salt);
                $data['addtime'] = time();
                if (false === SystemAdmin::insert($data)) {
                    Helper::json(false, '创建失败');
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
        $groups = SystemGroup::fetchAll(['status' => 1]);
        $this->view->setVar('groups', $groups);
    }

    //编辑
    public function edit()
    {
        if ($this->request->isPost()) {
            $id = intval($this->request->getPost('id'));
            $v = new Validator();
            $rules = [
                'group_id' => 'required|intval|gt:0 `角色`',
                'username' => 'required|minlen:4|maxlen:16|is_alpha_num_dash `用户名`',
                'nickname' => 'required|minlen:2|maxlen:16 `昵称`',
                'mobile' => 'required|trim|is_mobile `手机`',
                'password' => 'minlen:6|maxlen:16 `密码`',
                'repwd' => 'same:password ``重复密码错误``',
                'is_business' => 'required|in:0,1 `是否商务`',
                'qq' => 'trim `QQ`',
                'status' => 'required|intval|in:0,1',
            ];

            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData(true);
                unset($data['repwd']);
                $info = SystemAdmin::fetchOne($id);
                if (empty($info)) {
                    Helper::json(false, '用户不存在');
                }
                if (empty($data['password'])) {
                    unset($data['password']);
                } else {
                    $data['password'] = md5($data['password'] . $info['salt']);
                }
                if (false === SystemAdmin::update(['id' => $id], $data)) {
                    Helper::json(false, '更新失败');
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $p = max(intval($this->request->get('p')), 1);
        $edit = SystemAdmin::fetchOne(intval($this->request->get('id')));
        if (empty($edit)) {
            Helper::json(false, '编辑用户不存在');
        }
        $groups = SystemGroup::fetchAll(['status' => 1]);
        $this->view->setVars([
            'p' => $p,
            'edit' => $edit,
            'groups' => $groups
        ]);
    }

    //修改资料
    public function info()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'nickname' => 'required|minlen:2|maxlen:16 `昵称`',
                'mobile' => 'required|is_mobile `手机号`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (false !== System::updateUser($this->auth['id'], $data)) {
                    System::updateSession($this->auth['id']);
                    Helper::json(true);
                }
            } else {
                Helper::json(false, $v->getErrorString());
            }
            Helper::json(false, '修改失败');
        }

        $groups = SystemGroup::fetchAll([], '', 'id,title,description');
        $groups = array_combine(array_column($groups, 'id'), $groups);
        $this->view->setVar('groups', $groups);
    }

    //修改密码
    public function chpwd()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'oldPassword' => 'required|minlen:4|maxlen:16 `原始密码`',
                'password' => 'required|minlen:4|maxlen:16 `新密码`',
                'repassword' => 'required|same:password `重复新密码`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (!System::checkPwd($this->auth['id'], $data['oldPassword'])) {
                    Helper::json(false, "旧密码错误");
                }
                if (false !== System::updateUser($this->auth['id'], ['password' => $data['password']])) {
                    Helper::json(true);
                }
            } else {
                Helper::json(false, $v->getErrorString());
            }
            Helper::json(false, '修改失败');
        }
    }

    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                SystemAdmin::update(['id' => $id], ['status' => -1]);
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
            SystemAdmin::update(['id' => $id], ['status' => $status]);
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    /**
     * 自动搜索
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        empty($name) && Helper::json(false, '缺少参数');

        $list = SystemAdmin::fetchList(['nickname LIKE' => "%{$name}%", 'status !=' => -1], 0, 10, 'id DESC', 'id as value, nickname as title');
        Helper::json(true, '', $list);
    }
}