<?php
namespace app\index\model;

use app\common\entity\Config;

class SiteAuth
{
    //判断站点是否开启
    public static function checkSite()
    {
        $switch = Config::getValue('web_switch');
        if (!$switch) {
            return Config::getValue('web_close_message') ?: '站点关闭';
        }

        return true;
    }
}