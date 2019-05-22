<?php
/**
 * Created by PhpStorm.
 * User: HWT51
 * Date: 2018/9/26
 * Time: 17:55
 */

namespace App\Vendor\IM;

interface IMInterface
{
    public function register($user);

    public function importAccounts($users);

    public function importFriends($user, $friends);

    public function addFriendInOneDirection($userA, $userB);

    public function addFriendInBothDirection($userA, $userB);

    public function deleteFriend($userA, $userB);

    public function updateProfile($user, $profiles);
}