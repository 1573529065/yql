<?php

namespace app\index\controller;

use app\common\entity\Config;
use think\App;
use think\Request;
use think\Db;

class Index extends Base
{

    public function index()
    {
//        echo App::VERSION;
        $jianjie = Db::table('jianjie')->order('sort')->select();
        $inter = Db::table('inter')->select();
        $give = Db::table('give')->field('id,title')->select();
        return $this->fetch('index', [
            'jianjie' => $jianjie,
            'inter' => $inter,
            'give' => $give,
            'bs' => '首页'
        ]);
    }

    public function give_details()
    {
        $id = input('get.id');
        if (empty($id)) {
            $this->redirect('index');
        }
        $data = Db::table('give')->where('id = :id', ['id' => [$id, \PDO::PARAM_INT]])->find();
        $data['qq'] = explode('@', $data['qq']);
        $this->assign('data', $data);
        $this->assign('title', '【招聘公告】' . $data['title']);
        return $this->fetch('give_details', ['bs' => '首页']);
    }

    //证书展示
    public function certificate()
    {
        $list = Db::table('certificate')->paginate(5);
        return $this->fetch('certificate', [
            'bs' => '证书展示',
            'list' => $list
        ]);
    }

    /**
     * 培训课程
     */
    public function train()
    {
        return $this->fetch('train', [
            'bs' => '培训课程'
        ]);
    }

    /**
     * 学员生活
     */
    public function life()
    {
        return $this->fetch('life', [
            'bs' => '学员生活'
        ]);
    }

    /**
     * 舰队介绍
     */
    public function fleet()
    {
        return $this->fetch('fleet', [
            'bs' => '舰队介绍'
        ]);
    }

    /**
     * 新闻动态
     */
    public function news()
    {
        $pagesize = 10;
        $page = $this->request->get('p', 1);
        $offset = ($page - 1) * 10;
        $list = Db::table('news')->where('status', '=', 1)->limit($offset, $pagesize)->select();
        dump($list);
        $total = count($list);

        $page = Db::table('news')->paginate(1, $total);

        return $this->fetch('news', [
            'bs' => '新闻动态',
            'list' => $list,
            'page' => $page->render()
        ]);
    }

    /**
     * 新闻详情
     */
    public function news_det()
    {
        return $this->fetch('news_det', [
            'bs' => '新闻动态'
        ]);
    }

    /**
     * 关于我们
     */
    public function about()
    {
        return $this->fetch('about', [
            'bs' => '关于我们'
        ]);
    }

}
