<?php
/**
 * Created by PhpStorm.
 * User: HWT51
 * Date: 2018/12/3
 * Time: 13:42
 */

namespace App\Vendor\IM;


interface GroupInterface
{
    /**
     * 创建群组
     * @param string $ownerId
     * @param string $groupId
     * @param string $groupName
     * @param array $memberIds
     * @param array $groupInfo
     * @return mixed
     */
    public function createGroup(string $ownerId, string $groupId, string $groupName, array $memberIds = [], array $groupInfo = []);

    /**
     * 解散群组
     * @param string $groupId
     * @return mixed
     */
    public function destroyGroup(string $groupId);

    /**
     * 添加群成员
     * @param string $groupId
     * @param array $userIds
     * @param int $silence
     * @return mixed
     */
    public function addGroupMember(string $groupId, array $userIds, int $silence);

    /**
     * 删除群成员
     * @param string $groupId
     * @param array $userIds
     * @param int $silence
     * @return mixed
     */
    public function deleteGroupMember(string $groupId, array $userIds, int $silence);

    /**
     * 转让群
     * @param string $groupId
     * @param string $newOwnerId
     * @return mixed
     */
    public function changeGroupOwner(string $groupId, string $newOwnerId);

    /**
     * 添加群管理员
     * @param string $groupId
     * @param mixed ...$newAdminIds
     * @return mixed
     */
    public function addGroupAdmin(string $groupId, ...$newAdminIds);

    /**
     * 发送群系统通知
     * @param string $groupId
     * @param string $content
     * @param mixed ...$userIds
     * @return mixed
     */
    public function sendGroupSystemNotification(string $groupId, string $content, ...$userIds);

    /**
     * 用户禁言
     * @param string $groupId
     * @param string $shutUpTime
     * @param mixed ...$userIds
     * @return mixed
     */
    public function forbidSendMsg(string $groupId, string $shutUpTime, ...$userIds);


    /**
     * 获取群信息
     * @param string $groupId
     * @return array
     */
    public function getGroupInfo(string $groupId): array;

    /**
     * 修改群信息
     *
     * @param string $groupId
     * @param array $info
     * array['max_member_num']     int      群最大成员数
     *      ['introduction']       string   群介绍
     *      ['notification']       string   群公告
     *      ['face_url']           string   群头像
     *      ['name']               string   群名称
     *
     * @return mixed
     */
    public function modifyGroupBaseInfo(string $groupId, array $info);

    /**
     * @param string $groupId
     * @param string $userId
     * @param array $setting
     * array['role']         int      角色
     *      ['msgFlag']      string   消息屏蔽类型
     *      ['nameCard']     string   群名片
     * @return bool
     */
    public function modifyGroupMemberInfo(string $groupId, string $userId, array $setting):bool;
}