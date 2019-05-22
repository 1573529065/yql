<?php
/**
 * Created by PhpStorm.
 * User: HWT51
 * Date: 2018/12/5
 * Time: 13:14
 */

namespace App\Vendor\TencentCloud;


Interface TimExtendedRestApiInterface
{
    /**
     * 批量删除群成员
     *
     * @param string $groupId
     * @param array $members
     * @param int $slience
     * @return mixed
     */
    function group_batch_delete_group_member(string $groupId, array $members, int $silence);


    /**
     * 批量增加群成员
     *
     * @param string $groupId
     * @param array $members
     * @param int $slience
     * @return mixed
     */
    function group_batch_add_group_member(string $groupId, array $members, int $silence);
}