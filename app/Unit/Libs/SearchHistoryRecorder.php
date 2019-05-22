<?php
/**
 * Created by PhpStorm.
 * User: HWT51
 * Date: 2019/5/6
 * Time: 19:01
 */

namespace App\Unit\Libs;


use Illuminate\Support\Facades\Redis;

class SearchHistoryRecorder
{
    const REDIS_KEY_FORMAT = 'user:ub_id:%s';

    const REDIS_HASH_KEY = 'shop_search';

    private $ub_id;

    private $history;

    private $redisKey;

    /**
     * SearchHistoryRecorder constructor.
     * @param $ub_id
     */
    public function __construct($ub_id)
    {
        $this->ub_id = $ub_id;
        $this->initKey();
        $this->initHistory();
    }

    /**
     * 初始化历史记录
     */
    private function initHistory()
    {
        $redisData = Redis::hget($this->redisKey, self::REDIS_HASH_KEY);

        $this->history = json_decode($redisData, true) ?? [];
    }

    /**
     * 初始化redisKey
     */
    private function initKey()
    {
        $this->redisKey = sprintf(self::REDIS_KEY_FORMAT, $this->ub_id);
    }

    /**
     * 添加历史记录
     *
     * @param String $str
     * @return $this
     */
    public function add(String $str)
    {
        if (mb_strlen($str) == 0) return $this;

        array_unshift($this->history, $str);

        $this->history = array_slice(array_unique($this->history), 0, 10);
        return $this;
    }

    /**
     * 获取全部历史记录
     *
     * @return mixed
     */
    public function get()
    {
        return $this->history;
    }

    /**
     * 清理历史记录
     *
     * @return $this
     */
    public function clean()
    {
        $this->history = [];
        return $this;
    }

    /**
     * 保存历史记录
     *
     * @return $this
     */
    public function save()
    {
        Redis::hset($this->redisKey, self::REDIS_HASH_KEY, json_encode($this->history));
        return $this;
    }

    /**
     * 重新加载历史记录
     *
     * @return $this
     */
    public function fresh()
    {
        $this->initHistory();
        return $this;
    }
}