<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 12/4/2017
 * Time: 10:57 AM
 */

namespace App\Unit;
/**
 * 全局变量
 * @auther ifehrim@gmail.com
 * Class Consts
 * @package App\Unit
 */

class Consts
{

    /**
     * 微信推送者【判断】
     * @auther ifehrim@gmail.com
     * @date 2017-04-12 11:00:00
     */
    CONST PUSHER_TYPE_SECTION = 1;
    CONST PUSHER_TYPE_TEAMS = 2;
    CONST PUSHER_TYPE_RELATION = 3;


    /**
     * @auther ifehrim@gmail.com
     * @date 2017-05-12 14:00:00
     */

    CONST DB_SPECIAL_TYPE_UPDATE = 1;
    CONST DB_SPECIAL_TYPE_INSERT = 2;
    CONST DB_SPECIAL_TYPE_DELETE = 3;
    CONST DB_SPECIAL_TYPE_WHERE = 4;


    /**
     * @auther ifehrim@gmail.com
     * @time 3/20/2018 9:22 AM
     */
    CONST JO_IN = 1;
    CONST JO_RIGHT_IN = 2;
    CONST JO_LEFT_IN = 3;
    CONST JO_RIGHT_OUTER_IN = 4;
    CONST JO_LEFT_OUTER_IN = 5;


}