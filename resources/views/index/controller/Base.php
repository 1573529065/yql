<?php

namespace app\index\controller;

use app\common\service\Users\Identity;
use app\index\model\SiteAuth;
use think\Controller;
use app\common\entity\Config;
use think\Db;

class Base extends Controller
{

    public function initialize()
    {
        $cf = Config::getALLConfig();
        $this->assign('cf', $cf);
        //轮播
        $ban = Db::table('banner')->order('sort')->select();
        $this->assign('ban', $ban);
        parent::initialize();
    }


}