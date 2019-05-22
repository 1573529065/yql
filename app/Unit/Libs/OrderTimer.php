<?php
/**
 * Created by PhpStorm.
 * User: HWT51
 * Date: 2019/2/18
 * Time: 13:47
 */

namespace App\Unit\Libs;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class OrderTimer
{
    private $timerInfo;

    const AUTO_PRAISE_TIME = 'auto_praise_time';
    const URGE_COMMENT_TIME = 'urge_comment_time';
    const AUTO_RECEIPT_TIME = 'auto_receipt_time';
    const SHUT_ORDER_TIME = 'shut_order_time';

    const REDIS_KEY = 'shop:Order';

    private $orderNum;

    public static function destroyTimer($orderNum)
    {
        Redis::hDel(self::REDIS_KEY, $orderNum);
    }

    /**
     * OrderTimer constructor.
     * @param string $orderNum
     * @param string $data
     */
    public function __construct(string $orderNum, string $data = '')
    {
        $this->orderNum = $orderNum;

        if (is_null($data)) {
            $this->initTimerInfo($orderNum);
        } else {
            $this->setTimerInfo($data);
        }
    }

    /**
     * @param string $orderNum
     */
    protected function initTimerInfo(string $orderNum)
    {
        $timerInfo = Redis::hget(self::REDIS_KEY, $orderNum);

        $this->setTimerInfo($timerInfo);
    }

    protected function setTimerInfo($timerInfo)
    {
        $info = json_decode($timerInfo, true) ?? [];

        $this->timerInfo = $info;
    }

    /**
     * @return bool
     */
    public function isTimeToShutOder(): bool
    {
        return isset($this->timerInfo['shut_order_time']) && $this->timerInfo['shut_order_time'] <= time();

    }

    /**
     * @return bool
     */
    public function isTimeToAutoReceipt(): bool
    {
        return isset($this->timerInfo['auto_receipt_time']) && $this->timerInfo['auto_receipt_time'] <= time();

    }

    /**
     * @return bool
     */
    public function isTimeToUrgeComment(): bool
    {
        return isset($this->timerInfo['urge_comment_time']) && $this->timerInfo['urge_comment_time'] <= time();

    }

    /**
     * @return bool
     */
    public function isTimeToAutoPraise(): bool
    {
        return isset($this->timerInfo['auto_praise_time']) && $this->timerInfo['auto_praise_time'] <= time();
    }


    /**
     * @return void
     */
    public function save()
    {
        Redis::hSet(self::REDIS_KEY, $this->orderNum, (string)$this);
    }

    /**
     * @return void
     */
    public function destroy()
    {
        self::destroyTimer($this->orderNum);
    }

    /**
     * @param $name
     * @return int | null
     */
    public function __get(string $name)
    {
        return $this->timerInfo[$name] ?? null;
    }

    /**
     * @param string $name
     * @param int | Carbon $value
     */
    public function __set(string $name, $value)
    {
        if ($value instanceof Carbon) {
            $value = $value->timestamp;
        }
        $this->timerInfo[$name] = $value;
    }

    /**
     * @param $name
     */
    public function __unset(string $name)
    {
        unset($this->timerInfo[$name]);
    }

    /**
     * @return false|string
     */
    public function __toString()
    {
        return json_encode($this->timerInfo);
    }
}