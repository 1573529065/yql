<?php
/**
 * Created by PhpStorm.
 * User: lirui
 * Date: 2018/4/11
 * Time: 上午11:31
 */

namespace app\index\controller;


use think\facade\Cache;
use think\Controller;

class Cl extends Controller
{
    public function clear()
    {
        Cache::rm('market_price_cache');
    }
}