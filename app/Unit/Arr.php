<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 11/21/2017
 * Time: 2:17 PM
 */

namespace App\Unit;

use Illuminate\Support\Arr as BaseArr;

class Arr extends BaseArr
{


    /**
     * @auther ifehrim@gmail.com
     * @date 2017-05-12 14:29:00
     * @param mixed $str
     * @param $str2
     * @param int $len
     * @return string
     */
    public static function merge_str($str, $str2, $len = null)
    {
        $old = str_split($str);
        $new = str_split($str2);

        if (is_numeric($len)) {
            if (isset($new[$len])) $old[$len] = $new[$len];
        }
        foreach ($old as $k => $item) {
            if ($len === null) {
                if (isset($new[$k])) $old[$k] = $new[$k];
            }
            if (is_array($len)) {
                if (in_array($k, $len)) $old[$k] = $new[$k];
            }
        }
        return implode("", $old);
    }


    /**
     * 数组排序
     * @param array $arrays
     * @param string $sort_key
     * @param int|string $sort_order
     * @param int|string $sort_type
     * @return bool|array
     */
    public static function _sort($arrays, $sort_key, $sort_order = SORT_ASC, $sort_type = SORT_NUMERIC)
    {
        if (is_array($arrays)) {
            foreach ($arrays as $array) {
                if (is_array($array)) {
                    $key_arrays[] = $array[$sort_key];
                } else {
                    //todo there has condition
                    //if (is_object($array) && method_exists($array, 'toArray')) {
                    $array = $array->toArray();
                    $key_arrays[] = $array[$sort_key];
                    //}
                }
            }
        } else {
            return false;
        }
        array_multisort($key_arrays, $sort_order, $sort_type, $arrays);
        return $arrays;
    }

    /**
     * @param array $arrays
     * @return array
     */
    public static function ordered_sort(array $arrays)
    {
        $newArray = [];
        foreach ($arrays as $array) {
            if (is_array($array))
                $newArray[] = $array[0];
            else
                $newArray[] = $array;
        }
        return $newArray;
    }


    /**
     * php stdClass array
     * @param $object
     * @return mixed|string
     */
    public static function ret($object)
    {
        return Json::decode(Json::encode($object));
    }

    /**
     * this function is array value not eq 0 and empty filters
     * @auther ifehrim@gmail.com
     * @param $array
     * @return $array
     */
    public static function filter($array)
    {
        if (is_array($array)) {
            foreach ($array as $k => &$v) {
                if (is_array($v)) $v = self::filter($v);
                if ($v === 0 || $v === "0") continue;
                if (empty($v)) unset($array[$k]);
            }
        }
        return $array;
    }

    /**
     * this function is array value not eq 0 and empty assignment is null
     * @param $array
     * @return array
     */
    public static function blank($array)
    {
        if (is_array($array)) {
            foreach ($array as $k=>&$v)
            {
                if (is_array($v)) $v = self::blank($v);
                if ($v === 0 || $v === '0') continue;
                if (empty($v)) $array[$k] = null;
            }
        }
        return $array;
    }

    /**
     * @param $array
     * @return string
     */
    public static function term($array)
    {
        if (empty($array)) {
            return '';
        }
        foreach ($array as $k => $v) {
            if ($k == 'pageindex') {
                continue;
            }
            if (empty($str)) {
                if (is_array($v)) {
                    foreach ($v as $key => $val) {
                        if (empty($str)) {
                            $str = $k . '[]=' . $val;
                        }
                        $str .= '&' . $k . '[]=' . $val;
                    }
                } else {
                    $str = $k . '=' . $v;
                }
            } else {
                if (is_array($v)) {
                    foreach ($v as $key => $val) {
                        if (empty($str)) {
                            $str = $k . '[]=' . $val;
                        }
                        $str .= '&' . $k . '[]=' . $val;
                    }
                } else {
                    $str .= '&' . $k . '=' . $v;
                }
            }
        }
        if (empty($str)) {
            return '';
        }
        return $str;
    }

    /**
     * 数组转字符串
     * @author jerry
     * @date   2017-01-05
     * @update 2017-11-21 of ifehrim@gmail.com
     * @param array $array
     * @param string $mark
     * @return string
     */
    public static function toString($array, $mark = ',')
    {
        if (empty($array) && !is_array($array)) {
            return '';
        }
        $result = implode($mark, $array);
        if (empty($result)) {
            return '';
        }
        return $result;
    }


    /**
     * 两个数组获取not——empty 的值
     * @param $arr
     * @param $arr2
     * @param string $key
     * @param null $newKey
     * @author ifehrim@gmail.com
     * @create_at 2017-11-21
     */
    public static function int_empty(&$arr, &$arr2, $key = "", $newKey = null)
    {
        if ($newKey === null) $newKey = $key;
        if (!empty($arr[$key])) $arr2[$newKey] = $arr[$key];
    }

    /**
     * 数组获取empty 的值
     * @param $arr
     * @param string $key
     * @param null $val
     * @author ifehrim@gmail.com
     * @create_at 2017-11-21
     */
    public static function ist_empty(&$arr, $key = "", $val = null)
    {
        if (empty($arr[$key])) $arr2[$key] = $val;
    }

    /**
     * @param $arr
     * @param $size
     * @param callable|null $callback
     * @return bool
     * @auther ifehrim@gmail.com
     */
    public static function chunk($arr, $size, callable $callback = null)
    {
        if (empty($arr)) {
            return false;
        }

        $results = array_chunk($arr, $size);
        foreach ($results as $result) {
            if (call_user_func($callback, $result) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $arr
     * @param $size
     * @return bool
     * @auther ifehrim@gmail.com
     */
    public static function chunker($size, ...$arr)
    {
        if (empty($arr)) {
            return false;
        }
        $callable = end($arr);
        unset($arr[count($arr) - 1]);
        $results = [];
        foreach ($arr as $item) {
            $results[] = array_chunk($item, $size);
        }
        if (!empty($results)) {
            $first = $results[0];
            unset($results[0]);
            foreach ($first as $k => $result) {
                $params = [];
                $params[] = $result;
                foreach ($results as $p) {
                    if (isset($p[$k])) $params[] = $p[$k];
                }
                if (is_callable($callable)) {
                    if (call_user_func_array($callable, $params) === false) {
                        return false;
                    }
                }
            }
        }
    }

    public static function select($toSql, $getBindings)
    {
        $newStr = "";
        $arr = explode("?", $toSql);
        if (is_array($arr))
            foreach ($arr as $i => $sql) {
                $newStr .= $sql . (isset($getBindings[$i]) ? $getBindings[$i] : "");
            }
        return $newStr;
    }

    /**
     * @param object $arr
     * @return object|bool
     */
    public static function remove_null($arr)
    {
        $newarr = $arr->toArray();
        if (is_object($arr) && is_array($newarr)) {
            foreach ($newarr as $k => $v) {
                if (is_array($v) || $k == 'updated_at' || $k == 'created_at') continue;
                $arr->$k = !is_null($v) ? $v : '';
            }
            return $arr;
        } else {
            return false;
        }

    }

    /**
     * @param array $arr
     * @return array|bool
     */
    public static function reset($arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $k => &$v) {
                if ($v === 0 || $v === "0") continue;
                if (!is_int($v) && empty($v)) $arr[$k] = null;
            }
            return $arr;
        } else {
            return false;
        }
    }

    /**
     *
     * @auther ifehrim@gmail.com
     * @param array $array
     * @param null $callback
     * @param null $default
     * @return mixed
     */
    public static function _first($array, $callback = null, $default = null)
    {
        if (is_string($callback)||is_numeric($callback)) {
            $arr = parent::first($array, null, $default);
            if (!empty($arr) && isset($arr[$callback])) return $arr[$callback];
        }
        return parent::first($array, $callback, $default);
    }

    /**
     *
     * @auther ifehrim@gmail.com
     * @param array $array
     * @param null $callback
     * @param null $default
     * @return mixed
     */
    public static function _last($array, $callback = null, $default = null)
    {
        if (is_string($callback)) {
            $arr = parent::last($array, null, $default);
            if (!empty($arr) && isset($arr[$callback])) return $arr[$callback];
        }
        return parent::last($array, $callback, $default);

    }

    /**
     * 获取数组第一和最后数据
     * @date 3/19/2018
     * @auther ifehrim@gmail.com
     * @param $array
     * @param null $callback
     * @param null $default
     * @return array
     */
    public static function first_last($array, $callback = null, $default = null)
    {
        return [self::_first($array, $callback, $default), self::_last($array, $callback, $default)];
    }




}