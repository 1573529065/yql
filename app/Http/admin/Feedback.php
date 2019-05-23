<?php

namespace App\Controller\Admin;

use App\Dal\Feedback as FeedbackDal;
use App\Dal\FeedbackOption;
use App\Dal\User;
use App\Logic\Queue;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Upush;
use App\Service\Validator;

/**
 * 意见反馈
 * Class Feedback
 * @package App\Controller\Admin
 */
class Feedback extends BaseController
{
    //列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $startdate = trim($this->request->get('startdate'));//开始日期
        $enddate = trim($this->request->get('enddate'));//载止日期
        $where = ['status' => 1];

        if ($startdate) {
            $where['addtime >='] = strtotime($startdate);
        }
        if ($enddate) {
            $where['addtime <='] = strtotime($enddate);
        }

        $list = FeedbackDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = FeedbackDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);
        $options = FeedbackOption::fetchAll([]);
        $options = Helper::arrayReindex($options, 'id');

        $uids = array_column($list, 'user_id');
        $users = $uids ? User::fetchAll(['id IN' => $uids], '', 'id,username,nickname') : [];
        $users = Helper::arrayReindex($users, 'id');
        $this->view->setVars([
            'users' => $users,
            'options' => $options,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'list' => $list,//当前列表数据
            'page' => $page->generate(),
        ]);
    }

    /**
     * 回复
     * @throws \Exception
     */
    public function reply()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0 `ID`',
            'reply' => 'required|maxlen:255 `回复内容`',
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData();
            $feedback = FeedbackDal::fetchOne(['id' => $data['id'], 'state' => 0, 'status' => 1]);
            if (empty($feedback)) {
                Helper::json(false, '该反馈不存在或已被处理');
            }
            $data['admin_id'] = $this->auth['id'];
            $data['state'] = 1;
            FeedbackDal::update($data['id'], $data);

            //推送
            $extra = ['intent' => Upush::INTENT_MESSAGE];
            $message = [
                'type' => Upush::TYPE_SINGLE,
                'user_id' => $feedback['user_id'],
                'title' => '反馈回复',
                'content' => $data['reply'],
                'extra' => $extra
            ];
            Queue::putPush($message);
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }

    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            FeedbackDal::update(['id IN' => $ids], ['status' => -1]);
        }
        Helper::json(true);
    }
}