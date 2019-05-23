<?php

namespace App\Controller\Admin;

use App\Logic\Queue;
use App\Service\Helper;
use App\Service\Upush;

//推送管理
class Push extends BaseController
{
    /*public function index()
    {
        //参数初始化
        $where = [];
        $status = $this->request->get('status');
        if (strlen($status)) {
            $where['status'] = $status;
        }
        $sort_name = $this->request->get('sort_name') ? $this->request->get('sort_name') : 'id';
        //分页获取
        $pagesize = 12;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;
        $list = PushDal::fetchList($where, $offset, $pagesize, "`$sort_name` DESC");
        $total = PushDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'status' => $status,
            'page' => $page->generate(),
            'list' => $list
        ]);
    }

    //更新
    public function update()
    {
        $id = $this->request->getPost('id');
        $content = $this->request->getPost('content');
        if ($id && $content) {
            PushDal::update($id, ['title' => $content]);
            Helper::json(true);
        }
        Helper::json(false, '修改失败');
    }

    //删除、撤销
    public function del()
    {
        $id = $this->request->getPost('id');
        $act = $this->request->getPost('act');
        if ($id && $act == 'del') {
            PushDal::delete($id);
        }
        if ($id && $act == 'undo') {
            PushDal::update(['id' => $id], ['status' => 0, 'push_time' => 0]);
        }
        Helper::json(true);
    }

    //推送入队
    public function enqueue()
    {
        $id = $this->request->getPost('id');
        $is_timing = $this->request->getPost('is_timing');
        $push_time = ($is_timing == 2) ? strtotime($this->request->getPost('push_time')) : time();

        $item = PushDal::fetchOne($id);
        if (!$item['status'] && PushDal::update(['id' => $id, 'status' => 0], ['push_time' => $push_time])) {
            $extra = ['intent' => Upush::INTENT_GO_DETAIL, 'item_id' => $item['num_iid']];
            $message = [
                'type' => Upush::TYPE_BROADCAST,
                'title' => Upush::TITLE,
                'content' => $item['title'],
                'extra' => $extra,
                'data' => ['id' => $id, 'push_time' => $push_time]
            ];
            $delay = $push_time - time();
            $delay = $delay > 0 ? $delay : 0;
            Queue::putPush($message, $delay);
        }
    }*/

    //广播推送调试(正式环境请勿使用)
    public function test()
    {
        if (\ENV == 'pro') {
            return;
        }
        if ($this->request->isPost()) {
            $content = $this->request->getPost('content');
            $user_id = $this->request->getPost('user_id');
            $game_id = $this->request->getPost('id');
            $url = $this->request->getPost('url');
            $intent = $this->request->getPost('intent');

            switch ($intent) {
                case 'nop':
                    $extra = ['intent' => Upush::INTENT_NOP];
                    break;
                case 'openapp':
                    $extra = ['intent' => Upush::INTENT_GO_APP];
                    break;
                case 'login':
                    $extra = ['intent' => Upush::INTENT_LOGIN];
                    break;
                case 'invite':
                    $extra = ['intent' => Upush::INTENT_INVITE];
                    break;
                case 'income':
                    $extra = ['intent' => Upush::INTENT_INCOME];
                    break;
                case 'expense':
                    $extra = ['intent' => Upush::INTENT_EXPENSE];
                    break;
                case 'game':
                    $extra = ['intent' => Upush::INTENT_GAME, 'id' => $game_id];
                    break;
                case 'userprofile':
                    $extra = ['intent' => Upush::INTENT_USERPROFILE];
                    break;
                case 'webview':
                    $extra = ['intent' => Upush::INTENT_WEBVIEW, 'url' => $url];
                    break;
                case 'rebate':
                    $extra = ['intent' => Upush::INTENT_REBATE];
                    break;
                case 'rebate_record':
                    $extra = ['intent' => Upush::INTENT_REBATE_RECORD];
                    break;
                case 'verify_realname':
                    $extra = ['intent' => Upush::INTENT_VERIFY_REALNAME];
                    break;
                case 'modify_mobile':
                    $extra = ['intent' => Upush::INTENT_MODIFY_MOBILE];
                    break;
                case 'modify_passwd':
                    $extra = ['intent' => Upush::INTENT_MODIFY_PASSWD];
                    break;
                case 'recharge':
                    $extra = ['intent' => Upush::INTENT_RECHARGE];
                    break;
                case 'customer_service':
                    $extra = ['intent' => Upush::INTENT_CUSTOMER_SERVICE];
                    break;
                case 'coupon':
                    $extra = ['intent' => Upush::INTENT_COUPON];
                    break;
                case 'gifts':
                    $extra = ['intent' => Upush::INTENT_GIFTS];
                    break;
                case 'message':
                    $extra = ['intent' => Upush::INTENT_MESSAGE];
                    break;
                case 'feedback':
                    $extra = ['intent' => Upush::INTENT_FEEDBACK];
                    break;

                case 'broadcast':
                    $extra = ['intent' => Upush::INTENT_GAME, 'id' => $game_id];
                    $message = [
                        'type' => Upush::TYPE_BROADCAST,
                        'title' => Upush::TITLE,
                        'content' => $content,
                        'extra' => $extra
                    ];
                    Queue::putPush($message);
                    Helper::json(true, '发送成功');
                    break;
                default:
                    $extra = ['intent' => 'openapp'];
                    break;
            }

            $message = [
                'type' => Upush::TYPE_SINGLE,
                'user_id' => $user_id,
                'title' => Upush::TITLE,
                'content' => $content,
                'extra' => $extra
            ];
            Queue::putPush($message);
            Helper::json(true, '发送成功');
        }
    }
}