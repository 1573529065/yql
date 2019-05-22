<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 3/19/2018
 * Time: 4:03 PM
 */

namespace App\Unit\Traits;


use App\Models\Boundobd;
use App\Models\Business;
use App\Models\ObdChannel;
use App\Models\System\ServicePush;
use App\Models\System\ServicePushLog;
use App\Models\System\ServicePushWarn;
use App\Models\Userdetails;
use App\Models\Usergarage;
use App\Models\VehicleProperty;
use App\Unit\Arr;
use App\Unit\Consts;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

trait ScopeRelation
{

    /**
     *
     * @auther ifehrim@gmail.com
     * @return Model
     */
    public static function query(){
        return new static();
    }

    /**
     * @auther jerry
     * @param Builder $builder
     * @param $select
     * @return mixed
     */
    public function scopeS(Builder $builder,$select)
    {
        $model = $builder->getModel();
        $select_arr =  isset($model->select_list[$select]) ? $model->select_list[$select] : '*';
        return $builder->select($select_arr);
    }



    /**
     *
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @param null $class
     * @param null $first
     * @param null $operator
     * @param null $second
     * @param null $jo
     * @return mixed
     */
    public function scopeJo(Builder $builder, $class = null, $first = null, $operator = null, $second = null, $jo = null)
    {

        $inner_function = function (Builder $builder, $class = null, $first = null, $operator = null, $second = null, $jo = null) {
            $model = $builder->getModel();
            $table = $model->getTable();

            list($_first, $_operator, $_second, $_jo) = $jo_arr = isset($model->jo_list[$class]) ? $model->jo_list[$class] : [null, null, null, null];
            if (empty($first)) $first = $_first;
            if (empty($operator)) $operator = $_operator;
            if (empty($second)) $second = $_second;
            if (empty($jo)) $jo = $_jo;

            $ob = new $class;
            if ($ob instanceof Model) {
            };

            $fields = explode(".", $second);
            if (count($fields) == 2) {
                $table = $fields[0];
                $second = $fields[1];
            }
            $isContinue = false;
            $joins = $builder->getQuery()->joins;
            if (!empty($joins)) {
                foreach ($builder->getQuery()->joins as $join) {
                    if ($join instanceof JoinClause) {
                        if ($join->table == $ob->getTable()) {
                            $isContinue = true;
                            break;
                        }
                    }
                }
            }
            if ($isContinue) return $builder;

            if (isset($jo_arr[4]) && is_array($jo_arr[4])) {
                foreach ($jo_arr[4] as $_raw) {
                    $builder->whereRaw($_raw);
                }
            }
            $builder->join($ob->getTable(), $ob->getTable() . "." . $first, $operator, $table . "." . $second, $jo);
            return $builder;
        };

        if (is_array($class)) {
            foreach ($class as $_class) {
                $builder = $inner_function($builder, $_class, $first, $operator, $second, $jo);
            }
        } else {
            $builder = $inner_function($builder, $class, $first, $operator, $second, $jo);
        }

        return $builder;
    }


    public function scopeServiceCondition(Builder $builder, $project_id = null, $val = "", $isOr = false)
    {
        $sql = "";
        switch ($project_id) {
            case ServicePush::TYPE_CHANGE_OIL:
                list($val, $standard) = is_array($val) ? $val : [null, null];
                $sql = "(project_id = {$project_id} AND (((ug_next_main_mileage - mileage)  >= 0 AND (ug_next_main_mileage - mileage) <= {$val}) OR (ISNULL(ug_next_main_mileage) AND mileage >={$standard}-{$val}))) ";
                break;
            case ServicePush::TYPE_CHANGE_OIL_TIME:
                $sql = "(project_id = {$project_id} AND ug_next_maintenance <> '' AND CURDATE() >= (DATE_ADD(FROM_UNIXTIME(ug_next_maintenance),INTERVAL -{$val} DAY)) AND CURDATE() <= FROM_UNIXTIME(ug_next_maintenance))";
                break;
            case ServicePush::TYPE_CHANGE_TYRE:
                $sql = "(project_id = {$project_id} AND (mileage >= {$val}))";
                break;
            case ServicePush::TYPE_EXPIRE_YEARLY:
                $sql = "(project_id = {$project_id} AND ug_next_annual_inspection <> '' AND CURDATE() >= (DATE_ADD(FROM_UNIXTIME(ug_next_annual_inspection),INTERVAL -{$val} DAY)) AND CURDATE() >= DATE_ADD(IFNULL(ug_recent_contact,'1970-01-01 00:00:00'),
		INTERVAL 1 MONTH))";
                break;
            case ServicePush::TYPE_EXPIRE_WARRANTY:
                $sql = "(project_id = {$project_id} AND ug_warranty_time <> '' AND CURDATE() >= (DATE_ADD(FROM_UNIXTIME(ug_warranty_time),INTERVAL -{$val} DAY)) AND CURDATE() >= DATE_ADD(IFNULL(ug_recent_contact,'1970-01-01 00:00:00'), INTERVAL 1 MONTH))";
                break;
            case ServicePush::TYPE_EXPIRE_MILEAGE:
                $sql = "(project_id = {$project_id} AND mileage >= (ug_warranty_mileage-{$val}) AND CURDATE() >= DATE_ADD(IFNULL(ug_recent_contact,'1970-01-01 00:00:00'),
		INTERVAL 1 MONTH))";
                break;
            case ServicePush::TYPE_EXPIRE_INSURANCE:
                $sql = "(project_id = {$project_id} AND ug_insured <> '' AND CURDATE() >= (DATE_ADD(DATE_ADD(FROM_UNIXTIME(ug_insured),INTERVAL 1 YEAR),INTERVAL -{$val} DAY)) AND CURDATE() >= DATE_ADD(IFNULL(ug_recent_contact,'1970-01-01 00:00:00'),
		INTERVAL 1 MONTH))";
                break;
            case ServicePushWarn::TYPE_DRIVING_MORE_THAN_MILEAGE_NOT_TO_SHOP:
                $sql = "(project_id = {$project_id} AND ug_next_main_mileage <> '' AND (mileage >= ug_next_main_mileage) AND (mileage - ug_next_main_mileage) >= {$val})";
                break;
            case ServicePushWarn::TYPE_DRIVING_OVERDUE_MAINTENANCE_NOT_TO_SHOP:
                $sql = "(project_id = {$project_id} AND ug_next_maintenance <> '' AND (CURDATE() >= FROM_UNIXTIME(ug_next_maintenance)) AND DATEDIFF(CURDATE(),FROM_UNIXTIME(ug_next_maintenance)) >= {$val})";
                break;
            case ServicePush::TYPE_CHANGE_AIR_CLEANING_TIME:
                $sql = "(project_id = {$project_id} AND ug_next_cleaning_time <> '' AND CURDATE() >= (DATE_ADD(FROM_UNIXTIME(ug_next_cleaning_time),INTERVAL -{$val} DAY)))";
                break;
        }
        if (!empty($sql)) {
            if ($isOr) {
                $count = count($builder->getQuery()->wheres);
                $_sql = "(" . Arr::_last($builder->getQuery()->wheres, "sql") . " OR {$sql})";
                $builder->getQuery()->wheres[$count - 1]["sql"] = $_sql;
            } else {
                $builder->whereRaw($sql);
            }
        }

        return $builder;
    }


    /**
     * 1 OBD 2 SEE 3 PAR 4 AIR
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @param null $device
     * @return Model|Builder|\Illuminate\Database\Query\Builder
     */
    public function scopeDevice(Builder $builder, $device = null)
    {
        return $builder->where("boundobd.bo_type", Boundobd::device_type($device));
    }

    /**
     * 故障判断
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @return Model|Builder|\Illuminate\Database\Query\Builder
     */
    public function scopeIsImpact(Builder $builder)
    {
        return $builder->where(function (Builder $q) {
            $q->where('boundobd.bo_impact', '>', 0)->orwhere('boundobd.bo_malfunction', '>', 0);
        });
    }


    /**
     * 身份和车辆 已认证的
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @param null $class
     * @return $this|Model|Builder|\Illuminate\Database\Query\Builder
     */
    public function scopeAuthed(Builder $builder, $class = null)
    {
        $model = $builder->getModel();
        if ($model instanceof Usergarage || $class == Usergarage::class) {
            return $builder->where("ug_vehicle_auth", Usergarage::VEHICLE_AUTH_CERTIFICATION);
        }
        if ($model instanceof Userdetails || $class == Userdetails::class) {
            return $builder->where("ud_userauth", Userdetails::USERAUTH_CERTIFIED);
        }
        return $builder;
    }

    /**
     * 身份和车辆 没有认证的
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @param null $class
     * @return $this|Model|Builder|\Illuminate\Database\Query\Builder
     */
    public function scopeNoAuthed(Builder $builder, $class = null)
    {
        $model = $builder->getModel();
        if ($model instanceof Usergarage || $class == Usergarage::class) {
            return $builder->where("ug_vehicle_auth", "!=", Usergarage::VEHICLE_AUTH_CERTIFICATION);
        }
        if ($model instanceof Userdetails || $class == Userdetails::class) {
            return $builder->where("ud_userauth", "!=", Userdetails::USERAUTH_CERTIFIED);
        }
        return $builder;
    }

    /**
     * 渠道编号查询
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @param null $cid
     * @param string $field
     * @return $this|Model|Builder
     */
    public function scopeCider(Builder $builder, $cid = null, $field = "cid")
    {

        $model = $builder->getModel();

        if ($model instanceof Boundobd) {
            $builder->jo(ObdChannel::class);
            return $builder->where((new ObdChannel)->getTable() . ".channel_id", $cid);
        }
        if ($model instanceof Business) {
            $builder->jo(ObdChannel::class);
            return $builder->where((new ObdChannel)->getTable() . ".channel_id", $cid);
        }

        return $builder->where($model->getTable() . ".{$field}", $cid);
    }

    /**
     * 之间升级版
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @param $val
     * @param string $type
     * @param string $field
     * @return $this|Model|Builder
     */
    public function scopeBetween(Builder $builder, $val, $type = "day", $field = "created_at")
    {
        $model = $builder->getModel();
        $table = $model->getTable();
        if (is_array($val)) {
            $st = $val[0];
            $et = $val[1];
            if ($type != "day") $field = $type;
        } else {
            $date = date("Y-m-{$val}");
            $st = timestamp($date, "Y-m-d 00:00:00");
            $et = timestamp($date, "Y-m-d 23:59:59");
            if ($type == "year") {
                $date = date("{$val}-m-d");
                $st = timestamp($date, "Y-01-01 00:00:00");
                $et = timestamp($date, "Y-12-31 23:59:59");
            }
            if ($type == "month") {
                $date = date("Y-{$val}-d");
                $st = timestamp($date, "Y-m-01 00:00:00");
                $et = timestamp($date, "Y-m-t 23:59:59");
            }
        }

        $fields = explode(".", $field);
        if (count($fields) == 2) {
            $table = $fields[0];
            $field = $fields[1];
        }

        return $builder->whereBetween("{$table}.{$field}", [$st, $et]);
    }


    /**
     * 状态判断
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @param null $status
     * @param null $table
     * @return $this|Model|Builder
     */
    public function scopeStatus(Builder $builder, $status = null, $table = null)
    {
        $model = $builder->getModel();
        if (empty($table)) $table = $model->getTable();
        return $builder->where("{$table}.status", $status);
    }

    /**
     * 统计关联关系
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @param $class
     * @param null $field
     * @param string $counter_key
     * @param string $counter_end
     * @param string $func
     * @return Builder
     */
    public function scopeCounter(Builder $builder, $class, $field = null, $counter_key = "default", $counter_end = "",$func="count(*)")
    {

        $model = $builder->getModel();
        $_table = $model->getTable();
        $prefix = $model->getConnection()->getQueryGrammar()->getTablePrefix();
        $counters = $model->counters;
        if (is_array($counters) && isset($counters[$class])) {
            $counter = $counters[$class];
            $class_arr = explode(":", $class);
            $instance = new $class_arr[0];
            if ($instance instanceof Model) {
                $table = $instance->getTable();
                if (is_array($counter)) {
                    $counter = $counter[$counter_key];
                } else {
                    if ($counter_key != "default") {
                        if(!empty($counter_end))$func=$counter_end;
                        $counter_end = $counter_key;
                    }
                }
                if (!empty($counter_end)) $counter .= " AND " . $counter_end;
                $counter = str_replace('{self}', $prefix . $table, $counter);
                $counter = str_replace('{target}', $prefix . $_table, $counter);

                $func = str_replace('{self}', $prefix . $table, $func);

                if (empty($field)) $field = $table . "_counter";
                $column = DB::raw("(SELECT {$func} FROM {$prefix}{$table} {$counter}) AS {$field}");
                return $builder->addSelect($column);
            }
        }
        return $builder;
    }


    /**
     * order release
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @return Builder
     */
    public function scopeOrderRelease(Builder $builder)
    {

        $builder->getQuery()->orders = [];
        $builder->getQuery()->unionOrders = [];
        return $builder;
    }

    /**
     * special count
     * @time 4/25/2018 09:08
     * @auther ifehrim@gmail.com
     * @param Builder $builder
     * @return mixed
     */
    public function scopeCounts(Builder $builder)
    {
        return (int)$builder->addSelect(DB::raw('count(*) as aggregate'))->value("aggregate");
    }
}