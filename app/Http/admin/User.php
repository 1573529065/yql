<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/3
 * Time: 9:16
 */

namespace App\Controller\Admin;

use App\Dal\User as UserDal;
use App\Dal\UserLoginLog;
use App\Logic\System;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;
use App\Dal\UserBanned;
use App\Service\Upush;
use App\Logic\Queue;


class User extends BaseController
{
    protected $whiteList = ['autocomplete', 'toggle', 'user_lock_all'];

    /**
     * 会员列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $id = intval($this->request->get('id'));
        $username = trim($this->request->get('username'));
        $mobile = trim($this->request->get('mobile'));
        $reg_channel = trim($this->request->get('reg_channel'));
        $reg_imei = trim($this->request->get('reg_imei'));
        $reg_ip = trim($this->request->get('reg_ip'));

        $where = ['status !=' => -1];
        !empty($username) && $where['username'] = $username;
        !empty($mobile) && $where['mobile'] = $mobile;
        !empty($reg_imei) && $where['reg_channel'] = $reg_channel;
        !empty($reg_ip) && $where['reg_ip'] = $reg_ip;

        if ($id > 0) {
            $where['id'] = $id;
        }

        if ($reg_channel != "") {
            $where['reg_channel'] = $reg_channel;
        }

        $list = UserDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = UserDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'id' => $id,
            'username' => $username,
            'mobile' => $mobile,
            'reg_channel' => $reg_channel,
            'reg_imei' => $reg_imei,
            'reg_ip' => $reg_ip,
            'p' => $curpage
        ]);
    }

    /**
     * 修改会员
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `用户id`',
                'nickname' => 'required `昵称`',
                'avatar' => 'required `头像`',
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            UserDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }

        $id = $this->request->get('id');
        $p = max(intval($this->request->get('p')), 1);
        $info = UserDal::fetchOne(['id' => $id]);
        $info['phone'] = !empty($info['mobile']) ? substr_replace($info['mobile'], '****', 3, 4) : '';
        $this->view->setVars([
            'info' => $info,
            'p' => $p
        ]);
    }

    /**
     * 修改手机号
     */
    public function edit_mobile()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `用户id`',
                'mobile' => 'required|is_mobile `手机号`',
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            UserDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }
    }

    /**
     * 修改密码
     */
    public function edit_pass()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `用户id`',
                'passwd' => 'required `密码`',
                'passwd1' => 'required|same:passwd ``两次输入的密码不一致``',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            $userInfo = UserDal::fetchOne(['id' => $data['id']], 'id,salt');
            if (isset($userInfo['salt'])) {
                UserDal::update(['id' => $data['id']], ['passwd' => md5(md5($data['passwd']) . $userInfo['salt'])]);
            }
            Helper::json(true);
        }
    }

    /**
     * 用户详情-基本信息
     */
    public function user_det()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $tab = $this->request->get('tab');
        $id = $this->request->get('id');
        $where['user_id'] = $id;

        $userInfo = UserDal::fetchOne(['id' => $id]);

        $list = UserLoginLog::__fetchList($id, $where, $offset, $pagesize, 'id DESC');
        $total = UserLoginLog::__count($id, $where);
        $page = new Pagination($total, $pagesize, $curpage);

        $imei = UserLoginLog::__fetchAll($id, ['user_id' => $id], '', 'imei');
        $imei_num = empty($imei) ? 0 : count(array_unique(array_column($imei, 'imei')));

        $lastImei = UserLoginLog::__fetchOne($id, ['user_id' => $id], '*', 'id DESC');

        $inviteUserList = UserDal::fetchList(['pid' => $id], $offset, $pagesize, 'id DESC');
        $inviteUserTotal = UserDal::count(['pid' => $id]);
        $inviteUserPage = new Pagination($inviteUserTotal, $pagesize, $curpage);

        $this->view->setVars([
            'userInfo' => $userInfo,
            'list' => $list,
            'page' => $page->generate(),
            'inviteUserList' => $inviteUserList,
            'inviteUserPage' => $inviteUserPage->generate(),
            'imei_num' => $imei_num,
            'last_imei' => $lastImei['imei'] ?? '',
            'tab' => $tab ?: 1
        ]);
    }

    /**
     * 锁定-解锁用户
     */
    public function user_lock()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'user_id' => 'required|intval|gt:0 `用户id`',
                'remarks' => 'required `备注`',
                'type' => 'required `类型`',
                'snapshot' => '`截图`',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            if ($data['type'] == 2) {
                $status = 0;
                $data['snapshot'] = isset($data['snapshot']) ? json_encode($data['snapshot']) : '';
            } elseif ($data['type'] == 3) {
                $status = 1;
            }
            $data['addtime'] = time();
            $data['admin_id'] = $this->auth['id'];
            UserDal::update(['id' => $data['user_id']], ['status' => $status]);
            UserBanned::insert($data);
            Helper::json(true);
        }
    }

    /**
     * 一键锁定-解锁用户
     */
    public function user_lock_all()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'user_ids' => 'required| `用户id`',
                'remarks' => 'required `备注`',
                'type' => 'required `类型`',
                'snapshot' => '',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            $data['addtime'] = time();
            $data['admin_id'] = $this->auth['id'];

            if ($data['type'] == 2) {
                $status = 0;
                $data['snapshot'] = isset($data['snapshot']) ? json_encode($data['snapshot']) : '';
            } elseif ($data['type'] == 3) {
                $status = 1;
            }
            $userIds = explode(',', $data['user_ids']);
            unset($data['user_ids']);
            $ids = [];
            foreach ($userIds as $key => $item) {
                $userInfo = UserDal::fetchOne(['id' => $item], 'id,status');
                if ($userInfo['status'] == $status) {
                    continue;
                }
                $ids[] = $item;
                $data['user_id'] = $item;
                UserBanned::insert($data);
            }
            if (empty($ids)) {
                Helper::json(false, '无需' . ($data['type'] == 2 ? '锁定' : '解锁'));
            }

            UserDal::update(['id IN' => $ids], ['status' => $status]);
            Helper::json(true);
        }
    }

    /**
     * 发消息
     */
    public function message()
    {
        $post = $this->request->getPost();
        if ($post['user_id'] && $post['message']) {
            //推送
            $extra = ['intent' => Upush::INTENT_NOP];
            $message = [
                'type' => Upush::TYPE_SINGLE,
                'user_id' => $post['user_id'],
                'title' => '系统消息',
                'content' => $post['message'],
                'extra' => $extra
            ];
            Queue::putPush($message);
            Helper::json(true);
        }
        Helper::json(true);
    }

    /**
     * 修改用户状态
     */
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $field = $this->request->getPost('field');
        $status = $this->request->getPost('status');

        if ($id > 0) {
            UserDal::update(['id' => $id], [$field => $status]);
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    /**
     * 模糊搜索 会员名
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        empty($name) && Helper::json(false, '缺少参数');

        $list = UserDal::fetchList(['username LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,username as title');
        Helper::json(true, '', $list);
    }

}