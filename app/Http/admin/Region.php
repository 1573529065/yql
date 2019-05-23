<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/26
 * Time: 17:08
 */

namespace App\Controller\Admin;

use App\Dal\Region as RegionDal;
use App\Service\Helper;

class Region extends BaseController
{

    protected $whiteList = ['get_provinces', 'get_citys'];

    /**
     * 获取所有省
     */
    public function get_provinces()
    {
        $list = RegionDal::fetchAll(['pid' => 0]);
        Helper::json(true, '', $list);
    }

    /**
     * 获取对应省所有市
     */
    public function get_citys()
    {
        $province_id = $this->request->get('pid');
        $list = RegionDal::fetchAll(['pid' => $province_id]);
        Helper::json(true, '', $list);
    }
}