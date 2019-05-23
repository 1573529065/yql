<?php

namespace app\index\controller;

use app\common\entity\UserInviteCode;
use app\common\service\Market\Auth;
use app\common\service\Users\Identity;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\LabelAlignment;
use Grafika\Color;
use Grafika\Grafika;
use think\facade\Env;
use think\facade\Session;
use think\facade\Url;
use think\Request;
use app\common\service\Users\Service;
use app\common\entity\User;
use app\common\entity\ConvertLog;
use app\common\entity\UserProduct;
use app\common\entity\UserMagicLog;
use app\common\entity\GetyueLog;
use app\common\entity\UserTurnLog;
use app\common\entity\UserJifenLog;
use app\common\entity\UserYueLog;
use app\common\entity\Address;
use think\Db;
use app\common\entity\Config;
use app\common\entity\UserMboTurnLog;
use app\common\entity\UserTotalLog;
use app\common\entity\ShopOrder;


class Member extends Base
{

    public function index()
    {
        //获取缓存用户详细信息
        $userInfo = User::where('id', $this->userId)->find();
        //获取用户冻结资金 和交易总数
//        $freeze = $userInfo->getFreeze();
        $level = Config::getValue('level_' . $userInfo['level']);
        return $this->fetch('memberinfo', [
            'list' => $userInfo,
            'level' => $level
//                    'freeze' => $freeze
        ]);
    }

    /**
     * 设置页面
     */
    public function set()
    {
        //获取缓存用户详细信息
        // $identity = new Identity();
        // $identity->delCache($this->userId);
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);
        return $this->fetch('set', ["list" => $userInfo]);
    }

    /**
     * 关于
     */
    public function about()
    {
        return $this->fetch("about");
    }

    /**
     * 修改密码页面
     */
    public function password()
    {
        return $this->fetch("password");
    }

    /**
     * 联盟
     */
    public function union()
    {
        $userInfo = User::where('id', $this->userId)->find();

        //获得直推会员
        $userList = $userInfo->getChilds($this->userId);
        return $this->fetch('union', [
                "list" => $userInfo,
                "userList" => $userList
            ]
        );
    }

    /**
     * 修改密码
     */
    public function updatePassword(Request $request)
    {
        $validate = $this->validate($request->post(), '\app\index\validate\PasswordForm');

        if ($validate !== true) {
            return json(['code' => 1, 'message' => $validate]);
        }

        $oldPassword = $request->post('old_pwd');
        $user = User::where('id', $this->userId)->find();
        $service = new \app\common\service\Users\Service();
        $result = $service->checkPassword($oldPassword, $user);

        if (!$result) {
            return json(['code' => 1, 'message' => '原密码输入错误']);
        }

        //修改
        $user->password = $service->getPassword($request->post('new_pwd'));

        if ($user->save() === false) {
            return json(['code' => 1, 'message' => '修改失败']);
        }

        return json(['code' => 0, 'message' => '修改成功']);
    }

    /**
     * 新手解答
     */
    public function articleList()
    {
        //获取缓存用户详细信息
        $article = new \app\index\model\Article();
        $articleList = $article->getArticleList(2);
        return $this->fetch('articleList', ["list" => $articleList]);
    }

    /**
     * 问题留言
     */
    public function submitMsg(Request $request)
    {
        //获取缓存用户详细信息
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        //内容
        $data['content'] = trim($request->post("content"));
        if (empty($data['content'])) {
            return json(['code' => 1, 'message' => '请填写内容！']);
        }
        if (mb_strlen($data['content'], 'UTF-8') < 10) {
            return json(['code' => 1, 'message' => '最低输入10个字符!']);
        }
        if (!empty($request->post("img"))) {
            $data['img'] = $request->post("img");
        }
        $data['create_time'] = time();
        $data['user_id'] = $this->userId;
        $res = \app\common\entity\Message::insert($data);
        if ($res) {
            return json(['code' => 0, 'message' => '提交成功', 'toUrl' => url('member/message')]);
        } else {
            return json(['code' => 1, 'message' => '提交失败']);
        }
    }

    /**
     * 客服页面
     */
    public function message()
    {
        $entity = \app\common\entity\Message::field('m.*, u.nick_name, u.avatar')
            ->alias("m")
            ->leftJoin("user u", 'm.user_id = u.id')
            ->where('m.user_id', $this->userId)
            ->order('m.create_time', 'desc')
            ->select();
        return $this->fetch("message", ['list' => $entity]);
    }

    /**
     * 实名认证
     */
    public function certification()
    {
        //获取缓存用户详细信息 
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        return $this->fetch("certification", ['list' => $userInfo]);
    }

    /**
     * 实名认证下一步
     */
    public function lastreal(Request $request)
    {
        $data['real_name'] = $request->get("real_name");
        $data['card_id'] = $request->get("card_id");

        if (!$data['real_name'] || !$data['card_id']) {
            $this->error("请输入姓名和身份证号！！");
        }

        //获取缓存用户详细信息 
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        return $this->fetch("lastreal", ['list' => $userInfo, "data" => $data]);
    }

    /**
     * 支付宝
     */
    public function zfb()
    {
        //获取缓存用户详细信息 
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        return $this->fetch("zfb", ['list' => $userInfo]);
    }

    /**
     * 微信
     */
    public function wx()
    {
        //获取缓存用户详细信息 
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        return $this->fetch("wx", ['list' => $userInfo]);
    }

    /**
     * 添加银行卡
     */
    public function card()
    {
        //获取缓存用户详细信息
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        return $this->fetch("card", ['list' => $userInfo]);
    }

    /**
     * 修改个人信息
     */
    public function updateUser(Request $request)
    {
        //获取缓存用户详细信息
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        $user = new Service();

        $data = array();

        $card = $request->post("card"); //银行卡号
        if ($card) {
            if ($user->checkMsg("card", $card, $userInfo->user_id)) {
                return json(['code' => 1, 'message' => '该银行卡号已经被绑定了']);
            } else {
                $data['card'] = $card;
            }
        }
        $card_name = $request->post("card_name"); //开户行
        if ($card_name) {
            $data['card_name'] = $card_name;
        }
        $zfb = $request->post("zfb"); //支付宝
        if ($zfb) {
            if ($user->checkMsg("zfb", $zfb, $userInfo->user_id)) {
                return json(['code' => 1, 'message' => '该支付宝号已经被绑定了']);
            } else {
                $data['zfb'] = $zfb;
            }
        }
        $zfb_image_url = $request->post("zfb_image_url");

        if ($zfb_image_url) {
            $data['zfb_image_url'] = $zfb_image_url;
        }
        $wx = $request->post("wx"); //微信
        if ($wx) {
            if ($user->checkMsg("wx", $wx, $userInfo->user_id)) {
                return json(['code' => 1, 'message' => '该微信号已经被绑定了']);
            } else {
                $data['wx'] = $wx;
            }
        }
        $wx_image_url = $request->post("wx_image_url");
        if ($wx_image_url) {
            $data['wx_image_url'] = $wx_image_url;
        }
        $real_name = $request->post("real_name"); //真实姓名
        if ($real_name) {
            $data['real_name'] = $real_name;
        }
        $card_id = $request->post("card_id"); //身份证号
        if ($card_id) {
            $data['card_id'] = $card_id;
        }
        $card_left = $request->post("card_left"); //身份证反面
        if ($card_left) {
            $data['card_left'] = $card_left;
        }
        $card_right = $request->post("card_right"); //身份证反面
        if ($card_right) {
            $data['card_right'] = $card_right;
        }
        $avatar = $request->post("avatar"); //头像
        if ($avatar) {
            $data['avatar'] = $avatar;
        }

        $res = \app\common\entity\User::where('id', $this->userId)->update($data);
        // dump(\app\common\entity\User::getLastsql());die;
        if ($res) {
            //更新缓存
            $identity->delCache($this->userId);
            return json(['code' => 0, 'message' => '修改成功', 'toUrl' => url('member/index')]);
        } else {
            return json(['code' => 1, 'message' => '修改失败']);
        }
    }

    /**
     * 魔盒
     */
    public function magicbox()
    {
        $user_product = new UserProduct();
        $magicList = $user_product->getBox($this->userId);
        return $this->fetch("magicbox", ["magicList" => $magicList]);
    }

    /**
     * 清除缓存
     */
    public function delCache()
    {
        $identity = new Identity();
        $identity->delCache($this->userId);
    }

    /**
     * 登录到交易市场
     */
    public function login(Request $request)
    {
        if ($request->isPost()) {
            $password = $request->post('password');
            if (!$password) {
                return json(['code' => 1, 'message' => '请输入密码']);
            }
            $auth = new Auth();
            if (!$auth->check($password)) {
                return json(['code' => 1, 'message' => '密码错误']);
            }
            $url = Session::get('prev_url');
            Session::delete('prev_url');
            return json(['code' => 0, 'message' => '登录成功', 'toUrl' => $url]);
        }
        Session::set('prev_url', !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('market/index'));
        return $this->fetch('login');
    }

    /**
     * 账单
     */
//    public function magicloglist(Request $request) {
//        $type = $request->get("type", 1);
//        if ($request->isAjax()) {
//            $page = $request->get('page', 1);
//            $limit = $request->get('limit', 10);
//            $model = new UserMagicLog();
//            $list = $model->magicloglist($type, $this->userId, $page, $limit);
//
//            return json(['code' => 0, 'message' => 'success', 'data' => $list]);
//        }
//        return $this->fetch("magicloglist", [
//                    'type' => $type
//        ]);
//    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $service = new Identity();
        $service->logout();

        $this->redirect('publics/index');
    }

    /**
     * 推广
     */
    public function spread()
    {
        $code = UserInviteCode::where('user_id', $this->userId)->value('invite_code');
        $fileName = Env::get('app_path') . '../public/code/qrcode_' . $code . '.png';
        if (!file_exists($fileName)) {
            $path = $this->qrcode($code);

            ob_clean();
            $editor = Grafika::createEditor();

            $background = Env::get('app_path') . '../public/static/img/zhaomubg.png';

            $editor->open($image1, $background);
//            $editor->text($image1, $code, 20, 300, 775, new Color('#ffffff'), '', 0);
//            $editor->text($image1, "https://dafuai.com/a/S55pms", 20, 300, 785, new Color('#ffffff'), '', 0);
            $editor->open($image2, $path);
            $editor->blend($image1, $image2, 'normal', 1, 'top-center', '', 420, 300);
            $editor->save($image1, Env::get('app_path') . '../public/code/qrcode_' . $code . '.png');
        }

        return $this->fetch('spread', [
            'path' => '/code/qrcode_' . $code . '.png', 'code' => $code
        ]);
    }

    protected function qrcode($code)
    {
        //$code = UserInviteCode::where('user_id', $this->userId)->value('invite_code');
        $path = Env::get('app_path') . '../public/code/' . $code . '.png';

        if (!file_exists($path)) {
            ob_clean();
            $url = url('publics/register', ['code' => $code], 'html', true);
            $qrCode = new \Endroid\QrCode\QrCode();

            $qrCode->setText($url);
            $qrCode->setSize(220);
            $qrCode->setWriterByName('png');
            $qrCode->setMargin(10);
            $qrCode->setEncoding('UTF-8');
            $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH);
            $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
            $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 100]);
            //$qrCode->setLabel('Scan the code', 16, __DIR__.'/../assets/fonts/noto_sans.otf', LabelAlignment::CENTER);
//            $qrCode->setLogoPath(Env::get('app_path') . '../public/static/img/logo5.png');
            $qrCode->setLogoWidth(80);
            $qrCode->setValidateResult(false);

            header('Content-Type: ' . $qrCode->getContentType());
            $content = $qrCode->writeString();

            $path = Env::get('app_path') . '../public/code/' . $code . '.png';

            file_put_contents($path, $content);
        }

        return $path;
    }

    public function safepassword(Request $request)
    {
        if ($request->isPost()) {
            $validate = $this->validate($request->post(), '\app\index\validate\PasswordForm');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }

            //判断原密码是否相等
            $oldPassword = $request->post('old_pwd');
            $user = User::where('id', $this->userId)->find();
            $service = new \app\common\service\Users\Service();
            $result = $service->checkSafePassword($oldPassword, $user);

            if (!$result) {
                return json(['code' => 1, 'message' => '原密码输入错误']);
            }

            //修改
            $user->trad_password = $service->getPassword($request->post('new_pwd'));

            if (!$user->save()) {
                return json(['code' => 1, 'message' => '修改失败']);
            }

            return json(['code' => 0, 'message' => '修改成功']);
        }
        return $this->fetch('safepassword');
    }

    //余额转出列表
    public function out_yue()
    {
        return $this->fetch('out_yue');
    }

    //余额转出列表 下一步操作
    public function next_yue()
    {
        if (!\request()->isPost())
            return false;
        $mobile = input('post.mobile');
        $result = User::field('id')->where("nick_name=:mobile and status=status", ['mobile' => $mobile])->find();
        if ($result)
            return json(['code' => 200, 'msg' => $result]);
        return json(['code' => 400, 'msg' => '昵称不存在,请重试']);
    }

    //转账详情页
    public function out_detail()
    {
        $id = input('get.id');
        //禁止非法请求
        if (empty($id)) {
            echo "window.history.go(-1);";
            exit;
        }

        $list = User::field('id,avatar,nick_name,mobile')->where("id=:id", ['id' => [$id, \PDO::PARAM_INT]])->find();
        $config = Config::getValues(['yue_out_max', 'yue_out_min']);
        return $this->fetch('out_detail', compact(array('list', 'config')));
    }

    //提交--转账
    public function ajax_out_detail()
    {
        if (!request()->isPost())
            return false;
        $data = input('post.');
        if (!is_numeric($data['yue']) || $data['yue'] < 0)
            return json(['code' => 400, 'msg' => '非法输入!']);
        $config = Config::getValues(['yue_out_max', 'yue_out_min']);
        if (($data['yue'] < $config['yue_out_min']) || ($data['yue'] > $config['yue_out_max'])) {
            return json(['code' => 400, 'msg' => '请输入满足条件的余额!']);
        }
        $psw = $data['psw'];
        $auth = new Auth();
        if (!$auth->check($psw)) {
            return json(['code' => 400, 'msg' => '密码错误']);
        }
        if ($this->userId == $data['other_id']) {
            return json(['code' => 400, 'msg' => '不能与自己交易']);
        }
        //获取手续费配置
        $pro = Config::getValue('trun_bili') / 100;
        $magic_pro = bcmul($pro, $data['yue'], 8);
        $sum = bcadd($data['yue'], $magic_pro, 8);
        $magic = User::field('magic')->where('id', $this->userId)->find()['magic'] ?? 0;
        if ($magic < $sum) {
            return json(['code' => 400, 'msg' => '数量不足']);
        }
        $sql = "update user set magic = case id when {$this->userId} then magic-{$sum} when {$data['other_id']} then magic+{$data['yue']} end where id in ({$this->userId},{$data['other_id']})";
        $result = Db::execute($sql);
        if (!$result)
            return json(['code' => 400, 'msg' => '转账失败']);
        //插入转账记录
        UserMboTurnLog::insert([
            'user_id' => $this->userId,
            'user_name' => $this->userInfo->nick_name,
            'other_id' => $data['other_id'],
            'other_name' => $data['name'],
            'other_mobile' => $data['mobile'],
            'other_magic' => $data['yue'],
            'shouxufei' => $magic_pro,
            'create_time' => time(),
        ]);
        //插入日志记录
        UserMagicLog::insertAll([
            [
                'user_id' => $this->userId,
                'magic' => '-' . $sum,
                'remark' => '转出',
                'types' => UserMagicLog::TYPE_OUT,
                'create_time' => time(),
            ],
            [
                'user_id' => $data['other_id'],
                'magic' => $data['yue'],
                'remark' => '转入',
                'types' => UserMagicLog::TYPE_GET,
                'create_time' => time(),
            ]
        ]);

        return json(['code' => 200, 'msg' => '转账成功!']);
    }

    //验证交易密码密码
    public function yan_tpsw()
    {
        if (!request()->isPost())
            return false;
        $password = input('post.psw');
        $auth = new Auth();
        if (!$auth->check($password)) {
            return json(['code' => 400, 'msg' => '密码错误']);
        }
        return json(['code' => 200, 'msg' => '']);
    }

    //红包积分释放
    public function release()
    {
        if (!request()->isPost())
            return false;
        $yue = input('post.yue');
        $data['yue'] = round($this->userInfo->yue + $yue, 2);
        $data['jifen'] = round($this->userInfo->jifen - $yue, 2);
        //更新余额积分
        $result = User::where('id=' . $this->userId)->update($data);
        if (!$result)
            return json(['code' => 400, 'msg' => $data]);
        $log = new GetyueLog();
        // 插入日志记录
        $log->save(['user_id' => $this->userId, 'get_yue' => $yue, 'get_time' => date('Y-m-d H:i:s')]);
        return json(['code' => 200, 'msg' => '领取成功!']);
    }

    //转出-记录
    public function out_log()
    {
        $type = input('get.type');
        $title = '';
        if ($type == 1) {
            $title = '转出记录';
            $list = UserMboTurnLog::where("user_id={$this->userId}")->order('id desc')->paginate(20, false, [
                'query' => ['type' => $type]
            ]);
        } else {
            $title = '转入记录';
            $list = UserMboTurnLog::where("other_id={$this->userId}")->order('id desc')->paginate(20, false, [
                'query' => ['type' => $type]
            ]);
        }

        return $this->fetch('out_log', compact(array('list', 'title', 'type')));
    }

    // 兑换积分
    public function convert_jifen()
    {
        //兑换比例
        $convert = Config::getValue('yue_convert_jifen');
        //最小兑换数
        $min_convert = Config::getValue('convert_min_jifen');
        $list = User::field('yue,jifen')->where('id', $this->userId)->find();
        if (\request()->isPost()) {
            $yue = input('post.yue');
            if ($yue < $min_convert)
                return json(['code' => 400, 'msg' => '兑换余额过小！']);
            if ($yue > $list['yue'])
                return json(['code' => 400, 'msg' => '余额不足，请充值！']);
            $pro = explode(':', $convert);
            $jifen = round(($pro[1] / $pro[0] * $yue), 2);
            $result = User::where('id', $this->userId)->dec('yue', $yue)->inc('jifen', $jifen)->update();
            if (!$result)
                return json(['code' => 400, 'msg' => '兑换失败!']);
            $date = date('Y-m-d H:i:s');
            //插入日志记录
            ConvertLog::insert(['user_id' => $this->userId, 'yue' => $yue, 'jifen' => $jifen, 'get_time' => $date]);
            $yue_log = new UserYueLog();
            $jifen_log = new UserJifenLog();
            //插入余额日志
            $yue_log->save([
                'user_id' => $this->userId,
                'yue' => '-' . $yue,
                'remark' => $yue_log->getType(7),
                'types' => 7,
                'just_yue' => $yue,
                'create_time' => $date,
            ]);
            //插入积分日志 
            $jifen_log->save([
                'user_id' => $this->userId,
                'jifen' => $jifen,
                'remark' => $jifen_log->getType(6),
                'types' => 6,
                'create_time' => $date,
            ]);
            return json(['code' => 200, 'msg' => '兑换成功!', 'jifen' => $jifen]);
        }
        return $this->fetch('convert_jifen', compact(array('convert', 'min_convert', 'list')));
    }

    //兑换记录
    public function convert_log()
    {
        $list = ConvertLog::where('user_id', $this->userId)->field('yue,jifen,get_time')->order('id', 'desc')->paginate(20);
        $page = $list->render();
        // 获取总记录数
        $count = $list->total();
        return $this->fetch('convert_log', compact(array('list', 'page', 'count')));
    }

    //转出成功
    public function out_success()
    {
        if (input('get.type') == 'change')
            return $this->fetch('convert_success', ['info' => input('get.')]);

        return $this->fetch('out_success', ['name' => input('get.nick_name')]);
    }

    //转入
    public function get_yue()
    {
        $fileName = Env::get('app_path') . '../public/yueCode/' . $this->userId . '.png';
        if (!file_exists($fileName)) {
            $url = url('member/out_detail', ['id' => $this->userId], 'html', true);
            $this->get_yue_code($url);
        }
        return $this->fetch('get_yue', ['path' => '/yueCode/' . $this->userId . '.png']);
    }

    //生成二维码
    protected function get_yue_code($url)
    {
        ob_clean();
        $qrCode = new \Endroid\QrCode\QrCode();
        $qrCode->setText($url);
        $qrCode->setSize(160);
        $qrCode->setWriterByName('png');
        $qrCode->setMargin(10);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH);
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 100]);
        //$qrCode->setLabel('Scan the code', 16, __DIR__.'/../assets/fonts/noto_sans.otf', LabelAlignment::CENTER);
        $qrCode->setLogoWidth(60);
        $qrCode->setValidateResult(false);

        header('Content-Type: ' . $qrCode->getContentType());
        $content = $qrCode->writeString();
        $path = Env::get('app_path') . '../public/yueCode/' . $this->userId . '.png';
        file_put_contents($path, $content);
        return $path;
    }


    //地址
    public function address_list()
    {
        $list = Address::where('user_id', $this->userId)->field('id,name,address,mobile')->order('add_time desc')->select();
        return $this->fetch('address_list', ['list' => $list]);
    }

    //添加地址
    public function address_add()
    {
        if (request()->isPost()) {
            $user_id = $this->userId;
            $count = Address::where('user_id', $user_id)->count();
            if ($count >= 5)
                return json(['code' => 400, 'msg' => '最多添加5个地址！']);
            $data = input('post.');
            $re = Address::insert([
                'name' => htmlentities($data['name']),
                'address' => htmlentities($data['address']),
                'mobile' => $data['mobile'],
                'user_id' => $user_id,
                'add_time' => date('Y-m-d H:i:s'),
            ]);
            if ($re) return json(['code' => 200]);
            return json(['code' => 400, 'msg' => '添加失败！']);
        }
        return $this->fetch('address_add');
    }

    public function address_del()
    {
        $id = input('id');
        $re = Address::where('id=:id', ['id' => [$id, \PDO::PARAM_INT]])->delete();
        if ($re) return json(['code' => 200]);
        return json(['code' => 400, 'msg' => '删除失败！']);
    }

    public function address_edit()
    {
        if (request()->isPost()) {
            $data = input('post.');
            $address = new Address();
            $data['add_time'] = date('Y-m-d H:i:s');
            $re = $address->isUpdate(true)->save($data);
            if ($re === false) return json(['code' => 400, 'msg' => '修改失败！']);
            return json(['code' => 200]);
        }
        $id = input('id');
        $list = Address::where('id=:id', ['id' => [$id, \PDO::PARAM_INT]])->field('id,name,address,mobile')->find();
        if (empty($list)) return $this->redirect('address_list');
        return $this->fetch("address_edit", ['list' => $list]);
    }

    //上传、修改凭证
    public function upImg()
    {
        $file = request()->file('image');
        // 移动到框架应用根目录/uploads/ 目录下
        $info = $file->validate(['size' => 1048576, 'ext' => 'jpg,png,gif'])->move('../public/uploads/msgimg/');
        if (!$info) {
            return json(['code' => 400, 'msg' => $file->getError()]);
        }
        return json(['code' => 200, 'msg' => '/uploads/msgimg/' . $info->getSaveName()]);
    }

    //矿机升级
    public function upgrade()
    {
        if (!request()->isPost()) return false;
        $id = input('post.id');
        $up = UserProduct::alias('up')->where("up.id=:id and up.user_id =:uid", ['id' => [$id, \PDO::PARAM_INT], 'uid' => $this->userId])
            ->join('product p', 'p.id = up.product_id')
            ->field('up.*,p.level')
            ->find();
        if (!$up) {
            return json(['code' => 500, 'msg' => '矿机不存在']);
        }
        //检测是否添加发货地址
        $address = Address::where('user_id', $this->userId)->order('add_time desc')->find();
        if (!$address) {
            return json(['code' => 500, 'msg' => '未添加地址']);
        }
        $price = \app\common\entity\Product::where('level', 'in', [$up['level'], $up['level'] + 1])
            ->field('id,price,product_name')
            ->order('level')
            ->select();
        if (empty($price[1])) {
            return json(['code' => 500, 'msg' => '已是最高级']);
        }
        $diff = $price[1]['price'] - $price[0]['price'];
        $user = new User();
        $userInfo = $user->where('id', $this->userId)->field('yue,pid,magic,everyday,sum_lucre,everyday_conf,sum_lucre_conf')->find();
        if ($userInfo['yue'] < $diff) {
            return json(['code' => 500, 'msg' => '余额不足']);
        }
        //计算上级可获得数量
        $user_parent = $user->get_parent($userInfo['pid'], $diff, 1, $price[1]['price']);
        //补算下三代矿机奖励
        $con = explode('@', Config::getValue('rules_spread_rate'));
//        ($user,$num,$con,$sum = 0,$diff=0,$product_id,$dai = 0){
        $boot_sum = get_lower_sum($this->userId, 3, $con, 0, $diff, $price[1]['id']);
        //总封顶
        $sum_total = bcadd($userInfo['sum_lucre'], $boot_sum, 8);
        //日封顶
        $sum_day = bcadd($userInfo['everyday'], $boot_sum, 8);
        //总封顶 大于等于 日封顶
        if (($userInfo['sum_lucre_conf'] - $sum_total) >= ($userInfo['everyday_conf'] - $sum_day)) {
            //如果日封顶总和大于配置
            if ($sum_day > $userInfo['everyday_conf']) {
                //产币量就等于 配置的日封顶 减去 用户现有的日封顶
                $boot_diff = bcsub($userInfo['everyday_conf'], $userInfo['everyday'], 8);
            }
        } else {
            //如果总封顶和 大于 总封顶配置
            if ($sum_total > $userInfo['sum_lucre_conf']) {
                //产币量就等于 配置的总封顶 减去 用户的总封顶
                $boot_diff = bcsub($userInfo['sum_lucre_conf'], $userInfo['sum_lucre'], 8);
            }
        }
        $shop_bili = Config::getValue('output_bl');
        if (empty($boot_diff)) {
            $boot_diff = $boot_sum;
        }
        $boot_diff_de = bcmul($boot_diff, $shop_bili, 8);
        $boot_diff_shop = bcsub($boot_diff, $boot_diff_de, 8);
        $doubles = Config::getValues(['day_double', 'sum_double', 'three_double', 'kg_double']);
        // 启动事务
        Db::startTrans();
        try {
            $user->where('id', $this->userId)
                ->dec('yue', $diff)
                ->inc('sum_lucre', $boot_diff)
                ->inc('everyday', $boot_diff)
                ->inc('magic', $boot_diff_de)
                ->inc('shop_magic', $boot_diff_shop)
                ->inc('kg_conf', ($diff * $doubles['three_double']))
                ->inc('kg_conf', ($diff * $doubles['kg_double']))
                ->inc('everyday_conf', ($diff * $doubles['day_double']))
                ->inc('sum_lucre_conf', ($diff * $doubles['sum_double']))
                ->update();
            $user->isUpdate(true)->saveAll($user_parent);
            UserProduct::where('id', $up['id'])->update(['product_id' => $price[1]['id']]);
            ShopOrder::insert([
                'shop_name' => '升级' . $price[1]['product_name'],
                'shop_price' => $price[1]['price'],
                'num' => 1,
                'payment_type' => 2,
                'name' => $address['name'],
                'address' => $address['address'],
                'mobile' => $address['mobile'],
                'add_time' => date('Y-m-d H:i:s'),
                'user_id' => $this->userId,
            ]);
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务'升级失败'
            Db::rollback();
            return json(['code' => 500, 'msg' => '升级失败']);
        }
        UserYueLog::insert([
            'user_id' => $this->userId,
            'yue' => '-' . $diff,
            'remark' => '矿机升级',
            'types' => UserYueLog::TYPE_KG_UP,
            'create_time' => date('Y-m-d H:i:s'),
            'just_yue' => $diff
        ]);
        $total_log = $magic_log = [];
        foreach ($user_parent as $k => $v) {
            //总业绩 日志
            $total_log[$k] = [
                'user_id' => $v['id'],
                'num' => $diff,
                'remark' => "下级{$this->userInfo->nick_name}升级矿机",
                'types' => UserTotalLog::TYPE_UP,
                'create_time' => time()
            ];
            if ($v['diff'] > 0) {
                $magic_log[$k] = [
                    'user_id' => $v['id'],
                    'magic' => $v['diff'],
                    'old' => 0,
                    'new' => 0,
                    'remark' => '推广三代奖励',
                    'types' => UserMagicLog::TYPE_REWARD,
                    'create_time' => time()
                ];
            }
        }
        array_unshift($magic_log, [
            'user_id' => $this->userId,
            'magic' => $boot_diff,
            'old' => $userInfo['magic'],
            'new' => (bcadd($userInfo['magic'], $boot_diff, 8)),
            'remark' => '奖励补算',
            'types' => UserMagicLog::TYPE_SHOP_B,
            'create_time' => time()
        ]);
        UserMagicLog::insertAll($magic_log);
        UserTotalLog::insertAll($total_log);
        return json(['code' => 200, 'msg' => '升级成功']);
    }

}
