<?php
/**
 * Created by PhpStorm.
 * User: HWT51
 * Date: 2018/9/26
 * Time: 17:57
 */

namespace App\Vendor\IM\Tencent;


use App\Models\IMImportedUser;
use App\Vendor\IM\GroupInterface;
use App\Vendor\IM\IMInterface;
use App\Vendor\TencentCloud\TimRestAPI;
use Exception;
use Illuminate\Support\Facades\Log;

class TencentIM implements IMInterface, GroupInterface
{
    private $IMRestApi;

    private $IMImportedUser;

    const GROUP_TYPE = 'Public';

    const SILENCE_TRUE = 1;

    const SILENCE_FALSE = 0;

    const LOG_PREFIX = '腾讯云IM:';

    //群组消息通知类型
    const MSG_FLAG_ACCEPT_AND_NOTIFY = 'AcceptAndNotify';

    const MSG_FLAG_DISCARD = 'Discard';

    const MSG_FLAG_ACCEPT_NOT_NOTIFY = 'AcceptNotNotify';

    const TAG_PROFILE = [
        'avatar' => 'Tag_Profile_IM_Image',
        'gender' => 'Tag_Profile_IM_Gender',
        'nickname' => 'Tag_Profile_IM_Nick',
    ];

    /**
     * TencentIM constructor.
     * @param $IMRestApi
     */
    public function __construct()
    {
        $this->IMRestApi = new TimRestAPI();
        $this->IMImportedUser = new IMImportedUser();
    }

    /**
     * @param $user
     * @return bool
     * @throws Exception
     */
    public function register($user): bool
    {
        $result = $this->IMRestApi->account_import((string)$user, '', '');

        $isSucceed = $this->isSucceed($result);

        $this->IMImportedUser->addUser($user);

        if (!$isSucceed || $result['ActionStatus'] != 'OK') {
            Log::error(self::LOG_PREFIX . '注册失败', [
                'user' => $user,
            ]);
        }

        return $isSucceed;
    }

    /**
     * @param $userA
     * @param $userB
     * @return bool
     */
    public function addFriendInOneDirection($userA, $userB): bool
    {
        $result = $this->IMRestApi->sns_friend_import((string)$userA, (string)$userB);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::error(self::LOG_PREFIX . '单向添加好友失败', [
                'userA' => $userA,
                'userB' => $userB,
            ]);
        }

        return $isSucceed;
    }

    /**
     * @param $userA
     * @param $userB
     * @return bool
     */
    public function addFriendInBothDirection($userA, $userB): bool
    {
        $resultA = $this->addFriendInOneDirection($userA, $userB);

        $resultB = $this->addFriendInOneDirection($userB, $userA);

        if ($resultA && $resultB) {

            return true;

        } else {

            $this->deleteFriend($userA, $userB);

            $this->deleteFriend($userB, $userA);

            return false;
        }
    }

    /**
     * @param $userA
     * @param $userB
     * @return bool
     */
    public function deleteFriend($userA, $userB): bool
    {
        $result = $this->IMRestApi->sns_friend_delete((string)$userA, (string)$userB);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::Debug(self::LOG_PREFIX . '删除好友失败', [
                'userA' => $userA,
                'userB' => $userB,
            ]);
        }

        return $isSucceed;
    }


    /**
     * @param $user
     * @param $profiles
     * @throws Exception
     * @return bool
     */
    public function updateProfile($user, $profiles): bool
    {
        $user = (string)$user;

        $profileList = $this->convertProfileKey2Tag($profiles);

        $result = $this->IMRestApi->profile_portrait_set2($user, $profileList);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '更新用户信息失败', [
                'user' => $user,
                'profile' => $profileList,
            ]);
        }

        return $isSucceed;
    }

    /**
     * @param $genderSymbol
     * @return string
     */
    public static function GenderSymbol2String($genderSymbol): string
    {
        switch ($genderSymbol) {
            case 1:
                return 'Gender_Type_Male';
                break;
            case 2:
                return 'Gender_Type_Female';
                break;
            default:
                return 'Gender_Type_Unknown';
        }
    }

    /**
     * @param $users
     * @return bool
     * @throws Exception
     */
    public function importAccounts($users): bool
    {
        $result = $this->IMRestApi->multiaccount_import($users);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '批量导入用户失败', [
                'users' => $users,
            ]);
            return false;
        }

        $importedAccounts = self::getImportedAccounts($users, $result);

        $this->IMImportedUser->addUsers($importedAccounts);

        return true;
    }

    /**
     * @param $user
     * @param $friends
     * @return bool
     * @throws Exception
     */
    public function importFriends($user, $friends): bool
    {
        if (!$this->deleteAllFriend($user)) {
            throw new Exception(self::LOG_PREFIX . '删除所有好友失败');
        }

        $result = $this->IMRestApi->sns_friend_batch_import((string)$user, $friends);

        $isSucceed = self::isSucceed($result);

        if (!$isSucceed) {

            Log::debug(self::LOG_PREFIX . '导入朋友失败', [
                'user' => $user,
                'friends' => $friends,
            ]);

        }

        if (!empty($result['Fail_Account']) || !empty($result['Invalid_Account'])) {

            $err = [
                'user' => $user,
            ];

            !empty($result['Fail_Account']) ?: $err['Fail_Account'] = $result['Fail_Account'];

            !empty($result['Invalid_Account']) ?: $err['Invalid_Account'] = $result['Invalid_Account'];

            Log::debug(self::LOG_PREFIX . '导入失败的朋友', $err);

        }

        return $isSucceed;
    }

    /**
     * @param array $result
     * @return bool
     */
    private function isSucceed($result): bool
    {
        if (!isset($result['ErrorCode']) || $result['ErrorCode'] !== 0) {

            Log::error("TencentIM Request Error: ", [$result]);

            return false;

        } else {

            return true;

        }
    }

    /**
     * @param array $users
     * @param array $requestResult
     * @return array
     * @throws Exception
     */
    private static function getImportedAccounts(array $users, array $requestResult): array
    {
        if (!isset($requestResult['FailAccounts']) || !is_array($requestResult['FailAccounts'])) {
            throw new Exception('Parameter is invalid.');
        }

        $failAccounts = $requestResult['FailAccounts'];

        if (!empty($failAccounts)) {
            Log::info(self::LOG_PREFIX . '批量导入失败的用户的ub_id', $failAccounts);
        }

        $importedAccounts = array_diff($users, $failAccounts);

        return $importedAccounts;
    }

    /**
     * @param $user
     * @return bool
     */
    private function deleteAllFriend($user): bool
    {
        $result = $this->IMRestApi->sns_friend_delete_all((string)$user);

        return $this->isSucceed($result);
    }

    /**
     * @param $profiles
     * @return array
     * @throws Exception
     */
    private function convertProfileKey2Tag($profiles): array
    {
        $profileList = [];

        $tagProfile = self::TAG_PROFILE;

        foreach ($profiles as $key => $value) {

            if (isset($tagProfile[$key]) && !empty($value)) {

                $profileList[] = [
                    'Tag' => $tagProfile[$key],
                    'Value' => $value,
                ];

            } else if (empty($value)) {

                throw new Exception($key . ' is empty.');

            } else {

                throw new Exception('TAG_PROFILE do not contain ' . $key);

            }
        }

        return $profileList;
    }

    /**
     * 创建群组
     * @param string $ownerId
     * @param string $groupId
     * @param string $groupName
     * @param array $memberIds
     * @param array $groupInfo
     * @return mixed
     */
    public function createGroup(string $ownerId, string $groupId, string $groupName, array $memberIds = [], array $groupInfo = [])
    {
        $memberList = [];

        foreach ($memberIds as $memberId) {
            $memberList[] = ['Member_Account' => (string)$memberId];
        }

        $groupInfo = collect($groupInfo)
            ->only(['introduction', 'notification', 'face_url', 'max_member_num'])
            ->put('group_id', $groupId)
            ->toArray();

        $groupType = self::GROUP_TYPE;

        $result = $this->IMRestApi->group_create_group2($groupType, $groupName, $ownerId, $groupInfo, $memberList);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '创建群组', [
                'arguments' => compact('ownerId', 'groupId', 'groupName', 'memberIds', 'groupInfo'),
            ]);
            return false;
        }

        return true;
    }

    /**
     * 解散群组
     * @param string $groupId
     * @return mixed
     */
    public function destroyGroup(string $groupId)
    {
        $result = $this->IMRestApi->group_destroy_group($groupId);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '解散群组', [
                'arguments' => compact('groupId'),
            ]);
            return false;
        }

        return true;
    }

    /**
     * 添加群成员
     * @param string $groupId
     * @param array $userIds
     * @param int $silence
     * @return mixed
     */
    public function addGroupMember(string $groupId, array $userIds, int $silence = self::SILENCE_FALSE)
    {
        $result = $this->IMRestApi->group_batch_add_group_member($groupId, $userIds, $silence);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '添加群成员', [
                'arguments' => compact('groupId', 'userIds', 'silence'),
            ]);
            return false;
        }

        return true;
    }

    /**
     * 删除群成员
     * @param string $groupId
     * @param string $userId
     * @return mixed
     */
    public function deleteGroupMember(string $groupId, array $userId, int $silence = self::SILENCE_TRUE)
    {
        $result = $this->IMRestApi->group_batch_delete_group_member($groupId, $userId, $silence);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '删除群成员', [
                'arguments' => compact('groupId', 'userId', 'silence'),
            ]);
            return false;
        }

        //todo Send system notification to to group owner.

        return true;
    }

    /**
     * 转让群
     * @param string $groupId
     * @param string $newOwnerId
     * @return mixed
     */
    public function changeGroupOwner(string $groupId, string $newOwnerId)
    {
        $result = $this->IMRestApi->group_change_group_owner($groupId, $newOwnerId);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '转让群', [
                'arguments' => compact('groupId', 'newOwnerId'),
            ]);
            return false;
        }

        return true;
    }

    /**
     * 添加群管理员
     * @param string $groupId
     * @param mixed ...$newAdminIds
     * @return mixed
     */
    public function addGroupAdmin(string $groupId, ...$newAdminIds)
    {
        // TODO: Implement addGroupAdmin() method.
    }

    /**
     * 发送群系统通知
     * @param string $groupId
     * @param string $content
     * @param mixed ...$userIds
     * @return mixed
     */
    public function sendGroupSystemNotification(string $groupId, string $content, ...$userIds)
    {
        $result = $this->IMRestApi->group_send_group_system_notification($groupId, $content, $userIds);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '发送群系统通知', [
                'arguments' => compact('groupId', 'content', 'userIds'),
            ]);
            return false;
        }

        return true;
    }

    /**
     * 用户禁言
     * @param string $groupId
     * @param string $shutUpTime
     * @param mixed ...$userIds
     * @return mixed
     */
    public function forbidSendMsg(string $groupId, string $shutUpTime, ...$userIds)
    {
        // TODO: Implement forbidSendMsg() method.
    }

    /**
     * 获取群信息
     * @param string $groupId
     * @return array
     */
    public function getGroupInfo(string $groupId): array
    {
        $result = $this->IMRestApi->group_get_group_info($groupId);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed || !isset($result['GroupInfo'][0]['ErrorCode']) || $result['GroupInfo'][0]['ErrorCode'] != 0) {
            Log::debug(self::LOG_PREFIX . '获取群信息', [
                'arguments' => compact('groupId'),
            ]);
            return [];
        }

        return $result;
    }

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
    public function modifyGroupBaseInfo(string $groupId, array $info)
    {
        // TODO: Implement modifyGroupBaseInfo() method.
        $groupName = $info['name'] ?? null;

        $result = $this->IMRestApi->group_modify_group_base_info2($groupId, $groupName, $info, null);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '修改群信息', [
                'arguments' => compact('groupId', 'info'),
            ]);
            return [];
        }

        return $result;
    }

    /**
     * @param string $groupId
     * @param string $userId
     * @param array $setting
     * array['role']         int      角色
     *      ['msgFlag']      string   消息屏蔽类型
     *      ['nameCard']     string   群名片
     * @return bool
     */
    public function modifyGroupMemberInfo(string $groupId, string $userId, array $setting): bool
    {
        if (empty($setting['role']) && empty($setting['msgFlag'])) {
            return false;
        }

        $msgFlag = $setting['msgFlag'] ?? null;

        $role = $setting['role'] ?? null;

        $result = $this->IMRestApi->group_modify_group_member_info2($groupId, $userId, $role, $msgFlag, 0);

        $isSucceed = $this->isSucceed($result);

        if (!$isSucceed) {
            Log::debug(self::LOG_PREFIX . '修改群信息', [
                'arguments' => compact('groupId', 'info'),
            ]);
            return false;
        }

        return true;
    }
}
