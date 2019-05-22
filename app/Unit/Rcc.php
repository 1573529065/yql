<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/7/26
 * Time: 17:58
 */

namespace App\Unit;

class Rcc
{
    protected $base_url;

    protected $token;

    public function __construct()
    {
        $this->base_url = config('admin.globals.rcc.base_url');
        $this->token = config('admin.globals.rcc.token');
    }

    //获取集团列表
    public function GetGroupList()
    {
        $curl = $this->base_url . __FUNCTION__ . "?token={$this->token}";
        $data = $this->curls($curl);
        return $data;
    }

    // 获取渠道信息
    public function GetStoreListByGroupId($id)
    {
        if (!isset($id)) return false;
        $curl = $this->base_url . __FUNCTION__ . "?token={$this->token}&groupId={$id}";
        $data = $this->curls($curl);
        return $data;
    }

    // 获取用户列表
    public function GetUserListByStoreId($sid, $pagenumber = 1, $pagesize = 10)
    {
        if (!isset($sid)) return false;
        $list = $this->base_url . __FUNCTION__ . "?token={$this->token}&storeId={$sid}&pageNumber={$pagenumber}&pageSize={$pagesize}";
        $data = $this->curls($list);
        return $data;
    }

    // 获取用户信息
    public function GetUserInfo($serialNumber)
    {
        if (!isset($serialNumber)) return false;
        $curl = $this->base_url . __FUNCTION__ . "?token={$this->token}&serialNumber={$serialNumber}";
        $data = $this->curls($curl);
        return $data;
    }

    // 获取实时车辆数据
    public function GetCarObd($serialNumber)
    {
        if (!isset($serialNumber)) return false;
        $curl = $this->base_url . __FUNCTION__ . "?token={$this->token}&serialNumber={$serialNumber}";
        $data = $this->curls($curl);
        return $data;
    }

    // 获取行程报表（月）
    public function GetDriveHistoryMonthList($serialNumber, $formMonth, $toMonth, $coordinateType = 'wgs84')
    {
        if (!($serialNumber && $formMonth && $toMonth && $coordinateType)) return false;
        $curl = $this->base_url . __FUNCTION__ . "?token={$this->token}&serialNumber={$serialNumber}&fromMonth={$formMonth}&toMonth={$toMonth}&coordinateType={$coordinateType}";
        $data = $this->curls($curl);
        return $data;
    }

    //获取行程报表（周）
    public function GetDriveHistoryWeekList($serialNumber, $fromday, $today)
    {
        if (!($serialNumber && $fromday && $today)) return false;
        $curl = $this->base_url . __FUNCTION__ . "?token={$this->token}&serialNumber={$serialNumber}&fromWeek={$fromday}&toWeek={$today}";
        $data = $this->curls($curl);
        return $data;
    }

    //获取行程报表（天）
    public function GetDriveHistoryDayList($serialNumber, $fromday, $today, $coordinateType = 'wgs84')
    {
        if (!($serialNumber && $fromday && $today && $coordinateType)) return false;
        $curl = $this->base_url . __FUNCTION__ . "?token={$this->token}&serialNumber={$serialNumber}&fromDay={$fromday}&toDay={$today}&coordinateType={$coordinateType}";
        $data = $this->curls($curl);
        return $data;
    }

    // 获取行程历史
    public function GetDriveHistoryList($serialNumber, $fromDate, $today, $page = '1', $coordinateType = 'wgs84')
    {
        if (!($serialNumber && $fromDate && $today)) return false;
        $list = $this->base_url . __FUNCTION__ . "?token=" . $this->token . "&serialNumber=" . $serialNumber . "&fromDate=" . $fromDate . "&toDate=" . $today . "&coordinateType={$coordinateType}&page=" . $page;
        $data = $this->curls($list);
        return $data;
    }

    // 获取急加速历史
    public function GetAlarmSpeedUpList($serialNumber, $fromday, $today, $coordinateType = 'wgs84')
    {
        if (!($serialNumber && $fromday && $today && $coordinateType)) return false;
        $curl = $this->base_url . __FUNCTION__ . "?token={$this->token}&serialNumber={$serialNumber}&fromDate={$fromday}&toDate={$today}&coordinateType={$coordinateType}";
        $data = $this->curls($curl);
        return $data;
    }

    // 获取轨迹历史
    public function GetDeviceGpsList($serialNumber, $fromday, $today, $coordinateType = 'wgs84')
    {
        if (!($serialNumber && $fromday && $today)) return false;
        $list = $this->base_url . __FUNCTION__ . "?token={$this->token}&serialNumber=" . $serialNumber . '&fromDate=' . $fromday . '&toDate=' . $today . "&coordinateType={$coordinateType}";
        $data = $this->curls($list);
        return $data;

    }

    public function curls($url)
    {
        //初始化
        $ch = curl_init();
        $url = str_replace(' ', '%20', $url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_ENCODING,'gzip,deflate,sdch');
        // 执行后不直接打印出来
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        // 跳过证书检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // 不从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //执行并获取HTML文档内容
        $output = json_decode(curl_exec($ch));

        //释放curl句柄
        curl_close($ch);

        return $output;

    }

}