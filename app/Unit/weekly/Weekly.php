<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2018/10/24
 * Time: 13:58
 */

namespace App\Unit\weekly;


use App\Model\Mongo_Analysis;
use App\Models\Boundobd;
use App\Models\Member\Friend as mFriend;
use App\Models\ObdAnalysisDaily;
use App\Unit\Rcc;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Weekly
{

    /**
     * 获取当前用户昨日好友里程排名
     * @param $ub_id    当前用户ub_id
     * @param $imei 当前用户设备号
     */
    public static function friendMileageRanking($ub_id, $imei){
        $fbound[] = $imei;
        $friend = mFriend::leftjoin('usergarage as ug', 'friend.f_ub_id', '=', 'ug.ub_id')
            ->where(['friend.ub_id' => $ub_id, 'ug.ug_status' => 1, 'ug.ug_state' => 0])
            ->get(['friend.*', 'ug.ug_id']);

        foreach ($friend as $key => $item){
            $bound = Boundobd::where([
                'ub_id' => $item->f_ub_id,
                'ug_id' => $item->ug_id,
                'bo_type' => 1,
                'bo_status' => 1
            ])->pluck('bo_imei');
            if (!$bound->isEmpty()) $fbound[] = $bound;
        }

        $today = date('Ymd', strtotime('-1 day'));
        $new = new ObdAnalysisDaily();
        $new->setTable(date('Y'));
        $franking = $new->whereIn('id', $fbound)
            ->where('date', intval($today))
            ->where('distance', '>', 0)
            ->orderby('distance','desc')
            ->get();

        $frankingId = array_column($franking->toArray(), 'id');
        $fexist = array_search($imei, $frankingId);
        $data = $fexist !== false ? ($fexist + 1) : 0;

        return $data;
    }

    public static function queryWeekly($imei, $gener){

        $monday = date('Ymd', strtotime(date('N') == 1 ? '-1 monday' : '-2 monday')); //无论今天几号,-1 monday为上一个有效周一
        $sunday = date('Ymd', strtotime('-1 sunday')); //上一个有效周日,同样适用于其它星期

        $data['monday'] = strtotime($monday);
        $data['sunday'] = strtotime($sunday);

        if ($gener == 1) {
            $information = (new Mongo_Analysis('stat_weekly'))->getWeekMileage($imei, $monday, $sunday);
//                    周报一
            $data['mileage'] = isset($information->distance) ? round($information->distance, 1) : 0;
            $data['fuel'] = isset($information->real_fuel) ? round($information->real_fuel, 1) : 0;
            $data['bestfuel'] = isset($information->lp100km) ? round($information->lp100km, 1) : 0;

//                    周报二
            $data['speed'] = isset($information->max_speed) ? round($information->max_speed, 1) : 0;    // 本周最高时速
            $data['avgspeed'] = isset($information->avg_speed) ? round($information->avg_speed, 1) : 0; // 本周平均时速
            $data['stranded'] = isset($information->idle_time) ? round($information->idle_time, 1) : 0; // 本周怠速时长
//                    周报三
            $data['acceleration'] = isset($information->ra) ? round($information->ra, 1) : 0;   // 本周急加速次数
            $data['brake'] = isset($information->rd) ? round($information->rd, 1) : 0;          // 本周急减速次数
            $data['turn'] = isset($information->zigzag) ? round($information->zigzag, 1) : 0;   // 本周急转弯次数

            for ($i = 0; $i < 7; $i++){
                $data['information'][$i]['oti_time'] = strtotime($i . 'day', $data['monday']);
//                    周报一
                $data['information'][$i]['oti_mileage'] = isset($information->daily_distance[$i]) ? round($information->daily_distance[$i], 1) : 0;  // 当日里程 柱状图里程
                $data['information'][$i]['oti_fuel'] = isset($information->daily_real_fuel[$i]) ? round($information->daily_real_fuel[$i], 1) : 0;   // 每日油耗 柱状图油耗
//                    周报二
                $data['information'][$i]['oti_speed'] = isset($information->daily_max_speed[$i]) ? round($information->daily_max_speed[$i], 1) : 0;  // 本周每日最高时速
                $data['information'][$i]['avgspeed'] = isset($information->daily_avg_speed[$i]) ? round($information->daily_avg_speed[$i], 1) : 0;   // 本周每日平均时速
            }
        } else {
            $rccInfo = (new Rcc())->GetDriveHistoryDayList($imei,  date('Y-m-d', $data['monday']), date('Y-m-d', $data['sunday']));
            if ($rccInfo->status == 0) Log::debug('获取周报信息错误日志-queryWeekly', ['info' => $rccInfo, 'imei' => $imei, 'monday' => $monday, 'sunday' => $sunday,]);
            $data['mileage'] = 0;   // 周总里程
            $data['fuel'] = 0;      // 本周真实油耗
            $data['bestfuel'] = 0;  // 本周百公里平均油耗
            $data['speed'] = 0;     // 本周最高时速
            $data['avgspeed'] = 0;  // 本周平均时速
            $data['stranded'] = 0;  // 本周怠速时长 Rcc没有怠速时长, 返回0
            $data['information'] = [];

            //                    周报三
            $mongoInfo = (new Mongo_Analysis('stat_weekly'))
                ->where('id', $imei)
                ->where('start', '>=', intval($monday))
                ->where('end', '<=', intval($sunday))
                ->orderBy('_id', 'DESC')
                ->first();
            $data['turn'] = isset($mongoInfo->zigzag) ? round($mongoInfo->zigzag, 1) : 0;   // 本周急转弯次数
            $data['brake'] = isset($mongoInfo->rd) ? round($mongoInfo->rd, 1) : 0;          // 本周急减速次数
            $data['acceleration'] = isset($mongoInfo->ra) ? round($mongoInfo->ra, 1) : 0;   // 本周急加速次数

            $day_averageSpeed = 0;
            $count = 0;
            if (!empty($rccInfo->driveHistoryDayList)) {
                $num = count($rccInfo->driveHistoryDayList);
                $bestfuel = 0;
                foreach ($rccInfo->driveHistoryDayList as $key => $item){
//                    周报一
                    $data['mileage'] += $item->distance;    // 周总里程
                    $data['fuel'] += $item->totalOil;       // 本周真实油耗
                    $bestfuel += $item->averageOil;

                    $data['information'][$key]['oti_time'] = strtotime($item->day);;
                    $data['information'][$key]['oti_mileage'] = $item->distance;    // 当日里程 柱状图里程
                    $data['information'][$key]['oti_fuel'] = $item->totalOil;   // 每日油耗 柱状图油耗
//                    周报二
                    if ($data['speed'] < $item->maxSpeed) $data['speed'] = $item->maxSpeed;   // 本周最高时速
                    $data['information'][$key]['oti_speed'] = $item->maxSpeed;      // 本周每日最高时速
                    $data['information'][$key]['avgspeed'] = $item->averageSpeed;   // 本周每日平均时速
                    $day_averageSpeed += $item->averageSpeed;
                    $count++;
                }
                $data['bestfuel'] = round($bestfuel / $num, 1); // 本周百公里平均油耗
                $data['information'] = (new \Publics())->my_sort($data['information'], 'oti_time');
                $data['avgspeed'] = round($day_averageSpeed / $count, 1);  // 本周平均时速
            }
        }
        Redis::hset('obd:' . $imei, 'weekly', json_encode($data));
        return $data;
    }

}