<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/25
 * Time: 9:36
 */

namespace App\Controller\Admin;

use App\Dal\ChannelAnnouncement as ChannelAnnouncementDal;
use App\Dal\SystemAdmin;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;


class ChannelAnnouncement extends BaseController
{

    protected $whiteList = ['toggle'];

    /**
     * 渠道官方公告列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * 15;

        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');
        $admin_id = $this->request->get('admin_id');

        $where['status !='] = -1;
        if (!empty($start_time)) {
            $where['addtime >='] = strtotime($start_time);
        }
        if (!empty($end_time)) {
            $where['addtime <='] = strtotime($end_time);
        }
        if (!empty($admin_id)) {
            $where['admin_id'] = $admin_id;
        }

        $list = ChannelAnnouncementDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = ChannelAnnouncementDal::count($where);

        $page = new Pagination($total, $pagesize, $curpage);

        $adminIds = array_unique(array_column($list, 'admin_id'));
        $adminInfos = !empty($adminIds) ? SystemAdmin::fetchAll(['id in' => $adminIds], 'id asc', 'id,nickname') : [];
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $adminList = SystemAdmin::fetchAll(['status' => 1], 'id asc', 'id,nickname');
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'adminInfos' => $adminInfos,
            'adminList' => $adminList,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'admin_id' => $admin_id,
        ]);
    }

    /**
     * 添加渠道公告
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'title' => 'required|minlen:2|maxlen:50 `标题`',
                'content' => 'required `内容`',
            ];

            if (!$v->setRules($rules)->validate($this->request->getPost())) {
                Helper::json(false, $v->getErrorString());
            }

            $data = $v->getData();
            $data['addtime'] = time();
            $data['admin_id'] = $this->auth['id'];

            ChannelAnnouncementDal::insert($data);
            Helper::json(true);
        }
    }

    /**
     * 编辑渠道公告
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'title' => 'required|minlen:4|maxlen:50 `标题`',
                'content' => 'required `内容`',
            ];

            if (!$v->setRules($rules)->validate($this->request->getPost())) {
                Helper::json(false, $v->getErrorString());
            }

            $data = $v->getData();

            ChannelAnnouncementDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }

        $id = intval($this->request->get('id'));
        $p = max($this->request->get('p'), 1);

        $info = ChannelAnnouncementDal::fetchOne(['id' => $id]);

        $this->view->setVars([
            'id' => $id,
            'p' => $p,
            'info' => $info,
        ]);
    }

    /**
     * 删除渠道公告
     */
    public function delete()
    {
        $id = $this->request->get('id');

        empty($id) && Helper::json(false, 'ID不能为空');

        ChannelAnnouncementDal::update(['id' => $id], ['status' => -1]);
        Helper::json(true);
    }

    /**
     * 修改渠道公告状态
     */
    public function toggle()
    {
        $id = $this->request->get('id');
        $field = $this->request->get('field');
        $val = $this->request->get('val');

        empty($id) && Helper::json(false, 'ID不能为空');
        if (empty($field)) {
            Helper::json(false, '缺少参数');
        }

        ChannelAnnouncementDal::update(['id' => $id], [$field => $val]);
        Helper::json(true);
    }

}