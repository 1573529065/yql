<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/9/27
 * Time: 13:50
 */

namespace App\Unit\Locus;

use App\Model\HistoricalTrack;
use App\Model\Member;
use App\Model\Mongo_Gps;
use App\Model\Boundobd;


/**
 * 奥文obd历史轨迹
 * @auther jerry
 * Class Aowen
 * @package App\Unit\Locus
 */
class AoWenLocus
{

    public static function TrackPoints(Member $user, Boundobd $device, $end_time, $time)
    {
        $mongo_gps = new Mongo_Gps(date('Ymd', $time));
//          获取此段时间内所有的点,如果没有不操作
        $gps = $mongo_gps->getRangePoint($device->bo_imei, strtotime($end_time));
        return $gps;
    }

    public static function JumpPoint($gps)
    {
        //  取出所有跳变点
        $length = $gps->count();
        $last = $gps[0];
        $info = [];

//      如果数组第一个不是起始点(等于3为熄火点),将今天第一个点作为起始点,插入数组头部
        for ($i = 0; $i < $length; $i++) {
            if (($gps[$i]->data['status'] == 3 && $last->data['status'] != 3) || ($gps[$i]->data['status'] != 3 && $last->data['status'] == 3)) {
                $info[] = $gps[$i];
                $last = $gps[$i];
            }
        }
        return $info;
    }

    public static function Processing($info, $gps)
    {
        if (!empty($info)) {
//          如果数组第一个不是起始点(等于3为熄火点),将今天第一个点作为起始点,插入数组头部
            $first = reset($info);
            $final = end($info);
            if ($first->data['status'] == 3) {
                array_unshift($info, $gps[0]);
            }
//          如果数组最后一个不是熄火点(等于3为熄火点),将今天最后一个点作为结束点,插入数组尾部,[1,3,1,3...]
            if ($final->data['status'] != 3) {
                //todo 如果info最后一个点和gps最后一个点是同一个点会出现bug
                array_push($info, $gps[count($gps) - 1]);
            }
//          自动启停的车,两分钟内算一段行程
            $num = count($info);
            if ($num > 2) {
                for ($j = 1; $j < $num - 2; $j += 2) {
                    $ts = $info[$j + 1]['data']['ts'] - $info[$j]['data']['ts'];
                    if ($ts < 120) {
                        unset($info[$j]);
                        unset($info[$j + 1]);
                    }
                }
            }
        } else {
            if ($gps[0]->data['status'] != 3) {
                $info[0] = $gps[0];
                $info[1] = end($gps);
            } else
                return false;
        }
        return $info;
    }

    public static function Compare(\map $map, HistoricalTrack $locus, $info, $end_time)
    {
        $info = array_values($info);
        if (count($info) < 2) return false;
//      计算此段行程的开始时间和上一段行程结束时间的差值,如果小于两分钟算做一段行程
        $time = $info[0]->data['ts'] - strtotime($end_time);
        if ($time < 120) {
            $end_address = $map->Gps2Addr($info[1]['data']['loc'][0], $info[1]['data']['loc'][1]);
            $locus->ht_end = $end_address['formatted_address'];
            $locus->ht_end_time = $info[1]->data['dt'];
            $locus->ht_end_loc = $info[1]->data['loc'][0] . ',' . $info[1]->data['loc'][1];
            $locus->ht_distance = ($info[1]->data['mileage'] - $info[0]->data['mileage']) + $locus->ht_distance;
            $locus->ht_loc = Null;
            $locus->save();
            unset($info[0]);
            unset($info[1]);
            return $info;
        }
        return $info;
    }

    public static function storage($info, Member $user, \map $map)
    {
        // 两两作为一个数组,组成二维数组,每一个数组都是一段行程
        if (is_array($info) && count($info) >= 2) {
            $data = array_chunk(array_values($info), 2);
            $ht = [];
            foreach ($data as $key => $value) {
                $ht['ub_id'] = $user->ub_id;
                $ht['ug_id'] = $user->ug_id;
                $start_address = $map->Gps2Addr($value[0]->data['loc'][0], $value[0]->data['loc'][1]);
                $end_address = $map->Gps2Addr($value[1]->data['loc'][0], $value[1]->data['loc'][1]);
                $ht['ht_start'] = $start_address['formatted_address'];
                $ht['ht_start_time'] = $value[0]->data['dt'];
                $ht['ht_start_loc'] = $value[0]->data['loc'][0] . ',' . $value[0]->data['loc'][1];
                $ht['ht_end'] = $end_address['formatted_address'];
                $ht['ht_end_time'] = $value[1]->data['dt'];
                $ht['ht_end_loc'] = $value[1]->data['loc'][0] . ',' . $value[1]->data['loc'][1];
                $ht['ht_distance'] = $value[1]->data['mileage'] - $value[0]->data['mileage'];
                HistoricalTrack::updateOrInsert($ht);
            }
        }
    }
}