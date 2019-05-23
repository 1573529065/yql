<?php

namespace App\Controller\Admin;

use App\Dal\Ad as AdDal;
use App\Dal\Adzone;
use App\Dal\Adowner;
use App\Dal\AdStat;
use App\Dal\Cates;
use App\Service\Validator;
use App\Service\Helper;
use App\Service\Pagination;

class Ad extends BaseController
{
    public $whiteList = ['toggle', 'update_sort', 'copy'];
    //app内跳
    public $app_intents = array(
        'kepan://intent/nop' => ['title' => '无动作', 'val' => 'kepan://intent/nop'],
        'kepan://intent/invite' => ['title' => '邀请好友', 'val' => 'kepan://intent/invite'],
        'kepan://intent/game' => ['title' => '游戏详情', 'val' => 'kepan://intent/game?id={id}'],
    );

    //广告列表
    public function index()
    {
        $pagesize = 10;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        //参数初始化
        $todaydate = date("Y-m-d"); //今天日期
        $startdate = $this->request->get('startdate') ? $this->request->get('startdate') : $todaydate;//开始日期
        $enddate = $this->request->get('enddate') ? $this->request->get('enddate') : $todaydate;//载止日期
        $name = trim($this->request->get('name'));
        $platform = $this->request->get('platform');
        $status = trim($this->request->get('status'));
        $zoneid = $this->request->get('zoneid');
        $ownerid = $this->request->get('ownerid');

        $where = $where_adstat = [];
        if ($name) {
            $where['name LIKE'] = "%{$name}%";
        }
        if (strlen($status)) {
            $where['status'] = $status;
        } else {
            $where['status !='] = -1;
        }
        if ($zoneid) {
            $where['zoneid'] = $zoneid;
        }
        if ($ownerid) {
            $where['owner_id'] = $ownerid;
        }
        if ($platform) {
            $where['platform'] = $platform;
        }
        $list = AdDal::fetchList($where, $offset, $pagesize, 'sort ASC,id DESC');
        $total = AdDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $where_adstat['date BETWEEN'] = [$startdate, $enddate];

        //拼凑数据
        $total_display = 0;
        $total_click = 0;
        $list_adzone = Adzone::fetchAll(['status' => 1], 'sort ASC,id ASC');
        $list_adzone = array_combine(array_column($list_adzone, 'id'), $list_adzone);
        $list_adowner = Adowner::fetchAll(['status' => 1]);
        $list_adowner = array_combine(array_column($list_adowner, 'id'), $list_adowner);

        foreach ($list as $key => $val) {
            $list[$key]['adzone_name'] = $list_adzone[$val['zoneid']]['title'] ?? '';
            $list[$key]['owner'] = $list_adowner[$val['owner_id']]['name'] ?? '';

            $where_adstat['ad_id'] = $val['id'];
            $display = AdStat::sum('display', $where_adstat);
            $click = AdStat::sum('click', $where_adstat);
            $list[$key]['display'] = $display;
            $list[$key]['click'] = $click;
            $total_display += $display;
            $total_click += $click;
        }

        $summary = ['total' => $total, 'total_display' => $total_display, 'total_click' => $total_click];
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'summary' => $summary,
            'status' => $status,
            'platform' => $platform,
            'zoneid' => $zoneid,
            'ownerid' => $ownerid,
            'name' => $name,
            'list_adzone' => $list_adzone,
            'list_adowner' => $list_adowner,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'app_intents' => $this->app_intents,
            'p' => $curpage
        ]);
    }

    //添加
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:30 `广告简称`',
                'owner_id' => 'required|intval|gt:0 `广告主`',
                'zoneid' => 'required|intval|gt:0 `广告位置`',
                'platform' => 'required|in:1,2 `所属平台`',
                'title' => 'required|maxlen:50 `标题`',
                'ad_type' => 'required|in:1,2,3 `落地页类型`',
                'link' => 'required `落地页标识`',
                'des_color' => 'required `描述的文字颜色`',
                'weight' => 'required|intval|gte:1|lte:10 `权重`',
                'display_frequency' => 'required|is_numeric `展示频率`',
                'start_time' => 'required `开始时间`',
                'end_time' => 'required `结束时间`',
                'status' => 'required|in:0,1 `状态`',
                'icon' => 'is_url `广告图标`',
                'img' => '',
                'appid' => '',
                'h5_style' => '',
                'des' => ''
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                $data['addtime'] = time();
                if (AdDal::insert($data)) {
                    Helper::json(true);
                } else {
                    Helper::json(false, '创建失败');
                }
            } else {
                Helper::json(false, $v->getErrorString());
            }
        } else {
            $adzone_list = Adzone::fetchAll(["status = 1"], 'sort ASC,id DESC');
            $adowner_list = Adowner::fetchAll(["status = 1"], 'id ASC');
            $start_time = date("Y-m-d", time()); //默认10年
            $end_time = date("Y-m-d", strtotime("+10years", time()));//默认10年
            $this->view->setVars([
                'adzone_list' => $adzone_list,
                'adowner_list' => $adowner_list,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'app_intents' => $this->app_intents
            ]);
        }
    }

    //修改
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'name' => 'required|maxlen:30 `广告简称`',
                'owner_id' => 'required|intval|gte:0 `广告主`',
                'zoneid' => 'required `广告位置`',
                'platform' => 'required|in:1,2 `所属平台`',
                'title' => 'required `标题`',
                'ad_type' => 'required|in:1,2,3 `落地页类型`',
                'link' => 'required `落地页标识`',
                'des_color' => 'required `描述的文字颜色`',
                'weight' => 'required|intval|gte:1|lte:10 `权重`',
                'display_frequency' => 'required|is_numeric `展示频率`',
                'start_time' => 'required `开始时间`',
                'end_time' => 'required `结束时间`',
                'status' => 'required|in:0,1 `状态`',
                'icon' => 'is_url `广告图标`',
                'img' => '',
                'appid' => '',
                'h5_style' => '',
                'des' => ''
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (AdDal::update($data['id'], $data)) {
                    Helper::json(true);
                } else {
                    Helper::json(false, '修改失败');
                }
            } else {
                Helper::json(false, $v->getErrorString());
            }
        } else {
            $id = intval($this->request->get('id'));
            $p = intval($this->request->get('p'));
            $info = $id ? AdDal::fetchOne($id) : [];
            if (empty($info)) {
                return $this->showError('您编辑的信息不存在');
            }
            $adzone_list = Adzone::fetchAll(["status = 1"], 'sort ASC,id DESC');
            $adowner_list = Adowner::fetchAll(["status = 1"], 'id ASC');
            $start_time = date("Y-m-d", time()); //默认10年
            $end_time = date("Y-m-d", strtotime("+10years", time()));//默认10年
            $this->view->setVars([
                'info' => $info,
                'adzone_list' => $adzone_list,
                'adowner_list' => $adowner_list,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'app_intents' => $this->app_intents,
                'p' => $p,
            ]);
        }
    }

    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (empty($ids)) {
            Helper::json(false, '参数错误');
        }

        foreach ($ids as $id) {
            AdDal::update($id, ['status' => -1]);
        }
        Helper::json(true);

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
        if (!$v->setRules($rules)->validate($this->request->getPost())) {
            Helper::json(false, $v->getErrorString());
        }

        $data = $v->getData();
        AdDal::update($data['id'], [$data['field'] => $data['val']]);
        Helper::json(true);
    }

    //更改排序
    public function update_sort()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'sort' => 'required|intval|gte:0',
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData(true);
            AdDal::update(['id' => $data['id']], ['sort' => $data['sort']]);
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }

    //复制功能
    public function copy()
    {
        $id = (int)$this->request->get('id');
        $info = AdDal::fetchOne($id);
        unset($info['id']);
        $info['status'] = 0;
        AdDal::insert($info);
        Helper::json(true);
    }

    //广告报表
    public function stat()
    {
        $platform = $this->request->get('platform');//平台
        $zoneid = $this->request->get('zoneid');//广告位置
        $ownerid = $this->request->get('ownerid');//广告主ID
        $type = $this->request->get('type') ? (int)$this->request->get('type') : 1;//type 1 汇总显示 2 日期显示
        $ad_id = $this->request->get('ad_id') ? (int)$this->request->get('ad_id') : '';
        if ($this->request->hasQuery('startdate')) {
            $startdate = $this->request->get('startdate');
            $enddate = $this->request->get('enddate');
        } else {
            $startdate = $enddate = date('Y-m-d');
        }

        $where = [];
        $condition = [];
        if ($ownerid) {
            $condition['owner_id'] = $ownerid;
        }
        if ($zoneid) {
            $condition['zoneid'] = $zoneid;
        }
        if ($platform) {
            $condition['platform'] = $platform;
        }
        $ad_ids = AdDal::fetchAll($condition, '', 'id');
        $ad_ids = $ad_ids ? array_column($ad_ids, 'id') : [];
        if ($ad_ids) {
            $where['ad_id IN'] = $ad_ids;
        }
        if ($startdate && $enddate && $enddate >= $startdate) {
            if ($startdate == $enddate) {
                $where['date'] = $startdate;
            } else {
                $where['date BETWEEN'] = [$startdate, $enddate];
            }
        }
        $ad_total = AdStat::count($where, 'DISTINCT(ad_id)');
        $total_display = AdStat::sum('display', $where);
        $total_click = AdStat::sum('click', $where);
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;


        if ($type == 1) {
            $list = AdStat::fetchList($where, $offset, $pagesize, 'id DESC', 'id,ad_id,sum(display) as display,sum(click) as click', 'ad_id');
            $total = AdStat::count($where, 'DISTINCT(ad_id)');
        } else {
            if ($ad_id) {
                $where['ad_id'] = $ad_id;
                $ad_one_info = AdDal::fetchOne($ad_id);
            }
            $list = AdStat::fetchList($where, $offset, $pagesize, 'date DESC');
            $total = AdStat::count($where);
        }

        $page = new Pagination($total, $pagesize, $curpage);
        //拼凑数据
        $ad_ids = $list ? array_column($list, 'ad_id') : [];
        $ad_nature = $ad_ids ? AdDal::fetchAll(['id IN' => $ad_ids], 'id,name,owner_id,zoneid,platform,status,start_time,end_time') : [];
        $ad_nature = $ad_nature ? array_combine(array_column($ad_nature, 'id'), $ad_nature) : [];

        foreach ($list as $k => $v) {
            //status 1正常 2停用 3过期
            if ($ad_nature[$v['ad_id']]['status'] == 1) {
                $now_date = date('Y-m-d', time());
                if ($now_date >= $ad_nature[$v['ad_id']]['start_time'] && $now_date <= $ad_nature[$v['ad_id']]['end_time']) {
                    $list[$k]['status'] = 1;
                } else {
                    $list[$k]['status'] = 3;
                }
            } else {
                $list[$k]['status'] = 2;
            }
        }

        $list_adzone = Adzone::fetchAll([], 'sort ASC,id ASC');
        $list_adzone = $list_adzone ? array_combine(array_column($list_adzone, 'id'), $list_adzone) : [];
        $list_adowner = Adowner::fetchAll();
        $list_adowner = $list_adowner ? array_combine(array_column($list_adowner, 'id'), $list_adowner) : [];

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'platform' => $platform ?? '',
            'zoneid' => $zoneid ?? '',
            'ownerid' => $ownerid ?? '',
            'list_adzone' => $list_adzone,
            'list_adowner' => $list_adowner,
            'ad_nature' => $ad_nature,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'ad_total' => $ad_total,
            'total_display' => $total_display,
            'total_click' => $total_click,
            'type' => $type,
            'ad_id' => $ad_id,
            'ad_one_info' => $ad_one_info ?? [],
        ]);
    }
}