<?php

namespace App\Controller\Admin;

use App\Dal\ChargeVip as ChargeVipDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class ChargeVip extends BaseController
{
    protected $whiteList = ['update_exp'];

    //列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max($this->request->get('p'), 1);
        $offset = ($curpage - 1) * $pagesize;

        $list = ChargeVipDal::fetchList([], $offset, $pagesize);
        $total = ChargeVipDal::count([]);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'curpage' => $curpage
        ]);
    }

    /**
     * 添加 充值VIP
     * @throws \Exception
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:10 `VIP标识`',
                'alias' => 'required|maxlen:20 `VIP名称`',
                'badge' => 'required|is_url `勋章图标`',
                'icon' => 'required|is_url `展示图标`',
                'exp' => 'required|intval|gte:0 `所需充值`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                ChargeVipDal::insert($data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
    }

    //编辑
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0',
                'name' => 'required|maxlen:10 `VIP标识`',
                'alias' => 'required|maxlen:20 `VIP名称`',
                'badge' => 'required|is_url `勋章图标`',
                'icon' => 'required|is_url `展示图标`',
                'exp' => 'required|intval|gte:0 `所需充值`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                ChargeVipDal::update(['id' => $data['id']], $data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $id = intval($this->request->get('id'));
        $p = max(intval($this->request->get('p')), 1);
        $info = ($id > 0) ? ChargeVipDal::fetchOne($id) : [];
        if (empty($info)) {
            return $this->showError('您编辑的信息不存在');
        }
        $this->view->setVars([
            'p' => $p,
            'info' => $info,
        ]);
    }

    //更改等级所需经验值
    public function update_exp()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'exp' => 'required|intval',
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData();
            if (ChargeVipDal::update(['id' => $data['id']], ['exp' => $data['exp']]) === false) {
                Helper::json(false, '操作失败');
            }
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }
}