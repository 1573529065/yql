<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/3/28
 * Time: 9:24
 */

namespace App\Controller\Admin;

use App\Dal\Tags as TagsDal;
use App\Logic\System;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;


class Tags extends BaseController
{
    protected $whiteList = ['toggle'];

    /**
     * 游戏标签管理
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where['status !='] = -1;

        $list = TagsDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = TagsDal::count($where);

        $page = new Pagination($total, $pagesize, $curpage);
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
        ]);
    }

    /**
     * 添加游戏标签
     */
    public function add()
    {
        if (!$this->request->isPost()) return;

        $v = new Validator();

        $rules = [
            'name' => 'required `标签名`',
            'color' => 'required `颜色`',
        ];

        !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());

        $data = $v->getData();
        $data['addtime'] = time();
        $userInfo = System::getLoginInfo();
        $data['admin_id'] = $userInfo['id'] ?? 0;

        $success = TagsDal::insert($data);

        $success ? Helper::json(true) : Helper::json(false, '添加失败');
    }

    /**
     * 修改游戏标签
     */
    public function edit()
    {
        if (!$this->request->isPost()) return;
        $v = new Validator();

        $rules = [
            'id' => 'required|intval',
            'name' => 'required `标签名`',
            'color' => 'required `颜色`'
        ];

        !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());

        $data = $v->getData();
        TagsDal::update($data['id'], $data);

        Helper::json(true);
    }

    /**
     * 删除推荐位(单个)
     */
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        empty($ids) && Helper::json(false, '参数错误');

        TagsDal::update(['id IN' => $ids], ['status' => -1]);
        Helper::json(true);
    }

    /**
     * 修改单个字段的值
     */
    public function toggle()
    {
        $v = new Validator();

        $rules = [
            'id' => 'required|intval|gt:0',
            'field' => 'required `状态`',
            'val' => 'required'
        ];

        !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());

        $data = $v->getData();
        TagsDal::update($data['id'], [$data['field'] => $data['val']]);

        Helper::json(true);
    }
}