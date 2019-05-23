<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/25
 * Time: 9:36
 */

namespace App\Controller\Admin;

use App\Dal\SystemAdmin;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;
use App\Dal\Invoice as InvoiceDal;


class Invoice extends BaseController
{
    protected $whiteList = ['autocomplete', 'invoice_list', 'autocomplete_number'];

    /**
     * 发票管理列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * 15;

        $name = $this->request->get('name');
        $number = $this->request->get('number');
        $type = $this->request->get('type');
        $admin_id = $this->request->get('admin_id');
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');

        $where['status !='] = -1;
        if (!empty($name)) {
            $where['name'] = $name;
        }
        if (!empty($number)) {
            $where['number'] = $number;
        }
        if (!empty($type)) {
            $where['type'] = $type;
        }
        if (!empty($admin_id)) {
            $where['admin_id'] = $admin_id;
        }
        if (!empty($start_time)) {
            $where['addtime >='] = strtotime($start_time);
        }
        if (!empty($end_time)) {
            $where['addtime <='] = strtotime($end_time);
        }

        $list = InvoiceDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = InvoiceDal::count($where);

        $page = new Pagination($total, $pagesize, $curpage);

        $adminIds = array_unique(array_column($list, 'admin_id'));
        $adminInfos = !empty($adminIds) ? SystemAdmin::fetchAll(['id in' => $adminIds], 'id asc', 'id,nickname') : [];
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $adminList = SystemAdmin::fetchAll(['status' => 1], 'id asc', 'id,nickname');

        $total_money = InvoiceDal::sum('amount', ['status' => 1]);
        $special_total_money = InvoiceDal::sum('amount', ['status' => 1, 'type' => 1]);
        $general_total_money = InvoiceDal::sum('amount', ['status' => 1, 'type' => 2]);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'adminInfos' => $adminInfos,
            'adminList' => $adminList,
            'total_money' => $total_money,
            'special_total_money' => $special_total_money,
            'general_total_money' => $general_total_money,
            'name' => $name,
            'number' => $number,
            'type' => $type,
            'admin_id' => $admin_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);
    }

    /**
     * 添加发票
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:30 `公司名称`',
                'type' => 'intval|gt:0 `发票类型`',
                'imei' => 'is_alpha_num|maxlen:30 `发票代码`',
                'number' => 'is_numeric|maxlen:30 `发票号码`',
                'amount' => 'required|gt:0 `金额`',
                'time' => '',
                'img' => 'is_url `发票截图`',
                'remarks' => '',
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            $data['admin_id'] = $this->auth['id'];
            $data['addtime'] = time();
            $data['time'] = strtotime($data['time']);

            InvoiceDal::insert($data);
            Helper::json(true);
        }
    }

    /**
     * 编辑发票
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|gt:0 `ID`',
                'name' => 'required|maxlen:30 `公司名称`',
                'type' => 'intval|gt:0 `发票类型`',
                'imei' => 'is_alpha_num|maxlen:30 `发票代码`',
                'number' => 'is_numeric|maxlen:30 `发票号码`',
                'amount' => 'required|gt:0 `金额`',
                'time' => '',
                'img' => 'is_url `发票截图`',
                'remarks' => '',
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            $data['time'] = strtotime($data['time']);

            InvoiceDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }
        $id = intval($this->request->get('id'));
        $p = max($this->request->get('p'), 1);

        $info = InvoiceDal::fetchOne(['id' => $id]);

        $this->view->setVars([
            'info' => $info,
            'p' => $p,
        ]);
    }

    /**
     * 删除发票
     */
    public function delete()
    {
        $id = intval($this->request->getPost('id'));
        empty($id) && Helper::json(false, 'ID不能为空');

        $restule = InvoiceDal::update(['id' => $id], ['status' => -1]);

        !$restule && Helper::json(false, '删除失败');
        Helper::json(true);
    }

    /**
     * 相关发票列表
     */
    public function invoice_list()
    {
        $pagesize = 10;
        $p = max($this->request->get('p'), 1);
        $offset = ($p -1) * $pagesize;
        $name = $this->request->getPost('name');

        empty($name) && Helper::json(false, '缺少参数');
        $where = [
            'name' => $name,
            'status' => 1
        ];
        $data['list'] = InvoiceDal::fetchList($where, $offset, $pagesize, 'addtime DESC');

        foreach ($data['list'] as $key => $item) {
            $data['list'][$key]['addtime'] = date('Y-m-d H:i:s', $item['addtime']);
        }
        $total = InvoiceDal::count($where);
        $page = new Pagination($total, $pagesize, $p);
        $data['page'] = $page->generate();

        Helper::json(true, '', $data);
    }

    /**
     * 搜索 发票号码
     */
    public function autocomplete_number()
    {
        $name = $this->request->getPost('name');
        empty($name) && Helper::json(false, '缺少参数');

        $list = InvoiceDal::fetchList(['number LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,number as title, order_no');
        Helper::json(true, '', $list);
    }

    /**
     * 搜索 公司名
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        empty($name) && Helper::json(false, '缺少参数');

        $list = InvoiceDal::fetchList(['name LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,name as title');
        Helper::json(true, '', $list);
    }
}