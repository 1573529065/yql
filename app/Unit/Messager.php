<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 11/21/2017
 * Time: 11:53 AM
 */

namespace App\Unit;

use App\Events\PushEvent;
use App\Models\Userbase;

class Messager
{

    /**
     * 1001 加好友
     * 1002成为好友
     * 1003 删除好友
     * 2001 周报
     * 2002 违章
     * 2003点赞
     * 3001 任务完成
     * 3002账号被顶
     * 4001 每日推送
     * 6001 安卓更新
     * 8001 微信推送 **
     * 5001 头像审核
     * 5002 背景审核
     * 5003 身份认证
     * 5004 车辆认证
     * 6001 安卓更新
     * 7000 天气推送
     * 8001 自定义服务
     * 9010 服务推送
     * 9002 营销活动
     * 9003 优惠券发放成功
     * 9004 意见反馈推送
     * @param array $options
     */
    public function __construct($options = [])
    {
        //todo
    }

    public function handel($code, $fub_id, $content, $authtype)
    {
        if (empty($fub_id)) return;
        if (is_array($fub_id)) {
            $fub_id = array_map('intval', $fub_id);
        } else {
            $fub_id = [(int)$fub_id];//因传过来有可能是字符串  移动端接收不到 所以强制转换为int
        }
        $dataToSend = $this->handelData($code, $fub_id, $content, $authtype);

        self::triggerEvent($dataToSend);

        $huaweiIdArr = Userbase::whereIn('ub_id', $fub_id)->where('phone_brand', 'huawei')->pluck('ub_id')->toArray();

        if (!empty($huaweiIdArr)) {
            $dataToSend = array_merge($dataToSend, [
                'fub_id' => $huaweiIdArr,
                'isSilent' => true,
                'isRecord' => false,
            ]);
            self::triggerEvent($dataToSend);
        }
    }

    public function handelData($code, $fub_id, $content = '', $authtype = '')
    {
        $data = [];
        $data['code'] = $code;
        $data['fub_id'] = $fub_id;
        switch ($code) {
            case  2001:
                $data['content'] = '您最新一周的车辆健康报告已出炉';
                break;
            case  2005:
                $data['title'] = '你有一条新活动评论';
                $data['content'] = '你有一条新活动评论';
                $data['isSilent'] = true;
                $data['isRecord'] = false;
                break;
            // 完成任务推送
            case 3001:
                $data['content'] = '恭喜你完成' . $content['t_title'];
                $data['build_id'] = '3001' . $content['t_id'];
                break;

            // 每日登陆
            case 4001:
                $data['title'] = '每日登录成功获得5积分';
                $data['content'] = '每日登录成功获得5积分';
                break;

            // 头像审核推送
            case 5001:
                if ((int)$authtype == 1) {
                    $data['content'] = '恭喜您头像审核成功';
                } else {
                    $data['content'] = '头像审核失败:' . $content;
                    $data['isSkip'] = true;
                }
                break;
            // 背景审核推送
            case 5002:
                if ((int)$authtype == 1) {
                    $data['content'] = '恭喜您个人主页封面审核成功';
                } else {
                    $data['content'] = '个人主页封面审核失败:' . $content;
                    $data['isSkip'] = true;
                }
                break;
            // 身份认证推送
            case 5003:
                if ((int)$authtype == 1) {
                    $data['content'] = '恭喜您身份认证成功';
                } else {
                    $data['content'] = '身份认证失败:' . $content;
                    $data['isSkip'] = true;
                }
                break;
            // 车辆认证推送
            case 5004:
                if ((int)$authtype == 1) {
                    $data['content'] = '恭喜您车辆认证成功';
                } else {
                    $data['content'] = '车辆认证失败:' . (isset($content["content"]) ? $content["content"] : "");
                    $data['isSkip'] = true;
                }
                $data['ug_id'] = $content['ug_id'];
                break;

            /** 51开头 四位数 商城 **/
            // 评价有礼
            case 5100:
                if ($content['credit']) {
                    $data['content'] = '您对本次购买的商品是否满意?快去评价吧,还可获得' . $content["credit"] . '积分奖励';
                } else {
                    $data['content'] = '您对本次购买的商品是否满意?快去评价吧';
                }
                break;
            // 评价有礼
            case 5101:
                $data['content'] = '评价成功,恭喜您获得' . $content["credit"] . '积分';
                break;


            /*************       53开头工具箱          ****************/
            case 5301:
                if ($content['isOut']) { // 0 进入 1 离开
                    $data['content'] = $content['ug_series_name'] . '离开【' . $content['type'] . '】电子围栏范围';
                } else {
                    $data['content'] = $content['ug_series_name'] . '进入【' . $content['type'] . '】电子围栏范围';
                }
                $data['isOut'] = $content['isOut'];
                $data['ug_id'] = $content['ug_id'];
                $data['residence_at'] = $content['residence_at'];

                break;

            // 安卓更新包推送
            case 6001:
                $data['title'] = 'APP有了新的更新';
                $data['content'] = $content['content'];
                $data['down'] = $content['download'];
                $data['status'] = $content['status'];
                $data['upcon'] = $content['major_version'] . '.' . $content['minor_version'] . '.' . $content['revised_version'];
                $data['installer_name'] = $content['installer_name'];
                $data['platform'] = ['android'];
                $data['isAllAudience'] = true;
                break;
            // 群聊推送
            case 6002:
                $data['title'] = $content['content'];//$content['title'];
                $data['content'] = $content['content'];
                $data['group_id'] = $content['group_id'];
                $data['ub_id'] = $content['ub_id'];
                $data['f_ub_id'] = $content['f_ub_id'];
                $data['face'] = $content['face'];
                $data['attached'] = ['title' => $content['title']];
                break;

            //踢出群组
            case 6003:
                $data['content'] = $content['content'];
                $data['group_id'] = $content['group_id'];
                $data['ub_id'] = $content['ub_id'];
                $data['f_ub_id'] = $content['f_ub_id'];
                $data['attached'] = ['group_id' => $content['group_id']];
                break;

            //解散群组
            case 6004:
                $data['content'] = $content['content'];
                $data['group_id'] = $content['group_id'];
                $data['attached'] = ['group_id' => $content['group_id']];
                break;

            //成为新群主
            case 6005:
                $data['content'] = $content['content'];
                $data['group_id'] = $content['group_id'];
                $data['ub_id'] = $content['ub_id'];
                $data['f_ub_id'] = $content['f_ub_id'];
                $data['attached'] = ['group_id' => $content['group_id']];
                break;

            // 自定义推送
            case 8001:
                $data['title'] = $content['title'];
                $data['content'] = $content['content'];
                //设置时间
                if ($content['status'] === 0 && !empty($content['push_time'])) {
                    $data['isSchedule'] = true;
                    $data['push_time'] = $content["push_time"];
                }
                break;
            // 消息通知
            case 9001:
                $data['alert'] = !empty($content['content']) ? $content['content'] : '消息通知';
                if (!empty($content['url'])) $data['url'] = $content['url'];
                if (!empty($content['headlines'])) $data['image'] = media_url($content['headlines']);
                //设置时间
                $data['schedule_id'] = !empty($content['schedule_id']) ? $content['schedule_id'] : null;
                $data['notification_id'] = $content['nid'];
                if (!empty($content['type']) && $content['type'] == 2) {
                    $data['isSchedule'] = true;
                    $data['push_time'] = $content["push_time"];
                }
                break;
            //营销活动
            case 9002:
                $data['title'] = isset($content['push_content']) ? $content['push_content'] : $content['title'];
                $data['content'] = isset($content['content']) ? $content['content'] : $content['title'];
                if (!empty($content['url'])) $data['url'] = $content['url'];
                $data['image'] = !empty($content['img_url']) ? media_url($content['img_url']) : '';
                if (!empty($content['type'])) $data['type'] = $content['type'];
                if (!is_null($content['cid'])) $data['cid'] = $content['cid'];
                $data['type_url'] = !empty($content['type_url']) ? $content['type_url'] : '';
                $data['activity_id'] = $content['aid'];
                //设置时间
                if ($content['status'] === 0 && !empty($content['push_time'])) {
                    $data['isSchedule'] = true;
                    $data['push_time'] = $content["push_time"];
                    $data['time'] = strtotime($content["push_time"]);
                }
                $data['attached'] = ['aid' => $content['aid'], 'type' => isset($data['type']) ? $data['type'] : 0, 'type_url' => $data['type_url']];//用于活动ios和安卓好跳转所新加附加字段
                break;
            //卡券奖励
            case 9003:
                $data['title'] = '恭喜您获得 【' . $content['name'] . '】，卡券已存入您的卡包，请注意查收。';
                $data['content'] = $data['title'];
                $data['coupon_id'] = $content['id'];
                $data['android_notification']['extras']['coupon_id'] = $content['id'];
                $data['ios_notification']['extras']['coupon_id'] = $content['id'];
                $data['isSkip'] = true;
                break;
            //意见反馈推送
            case 9004:
                $data['title'] = "您的{$content['title']}已经有了回复，点击查看。";
                $data['content'] = $data['title'];
                $data['fb_id'] = $content['id'];
                $data['isSkip'] = true;
                break;
            //积分变动推送
            case 9005:
                $data['title'] = '您的积分变动了';
                $pre = isset($content["task"]) ? "恭喜您完成{$content["task"]}任务" : "";
                $data['content'] = $pre . ($content["credit"] > 0 ? "获得了 {$content["credit"]} 积分" : "使用了 {$content["credit"]} 积分");
                break;
            //车辆信息变更
            case 10001:
                $data['title'] = '车辆信息变更';
                $data['content'] = '车辆信息变更';
                $data['isSilent'] = true;
                $data['ub_id'] = is_array($fub_id) ? $fub_id[0] : $fub_id;;
                $data['ug_id'] = $content;
                break;

            //用户信息变更
            case 10002:
                $data['title'] = '用户信息变更';
                $data['content'] = '用户信息变更';
                $data['isSilent'] = true;
                $data['ub_id'] = is_array($fub_id) ? $fub_id[0] : $fub_id;;
                break;
            //新增车辆
            case 10003:
                $data['title'] = '新增车辆';
                $data['content'] = '新增车辆';
                $data['isSilent'] = true;
                $data['ub_id'] = is_array($fub_id) ? $fub_id[0] : $fub_id;;
                $data['ug_id'] = $content;
                break;
            //车辆冻结
            case 10004:
                $data['title'] = '车辆冻结';
                $data['content'] = '车辆冻结';
                $data['isSilent'] = true;
                $data['ub_id'] = is_array($fub_id) ? $fub_id[0] : $fub_id;;
                $data['ug_id'] = $content;
                break;


            //服务评价
            case 11000:
                $data['title'] = '系统通知';//服务评价
                $data['content'] = '请您对本次服务进行评价!评价成功后可获得200积分奖励';
                $data['url'] = env('APP_WEB_RESERVE_URL', 'http://ydapp.www.ve-link.com/app-reservation/comment.html') . '?id=' . $content;
                break;
            //预约维保成功预约
            case 20001:
                $data['title'] = '系统通知';//预约维保
                $data['content'] = '您的爱车维保已预约成功，请按时前往门店。';
                $data['url'] = env('APP_WEB_RESERVE_SUCCESS_URL', 'http://ydapp.www.ve-link.com/app-reservation/records.html');
                break;
            //预约维保失败预约
            case 20002:
                $data['title'] = '系统通知';// 预约维保
                $data['content'] = '您的爱车维保预约失败，请重新预约。'; //很抱歉 本次预约失败。
                $data['url'] = env('APP_WEB_RESERVE_SUCCESS_URL', 'http://ydapp.www.ve-link.com/app-reservation/records.html');
                break;
            //当开机有修改发送广播
            case 20003:
                $data['content'] = '遇道提醒您：近期持续高温，停车请注意防晒，注意安全';
                $data['type'] = $content;
                $data['isSilent'] = true;
                $data['isRecord'] = false;
                $data['isAllAudience'] = true;
                break;
            //活动首页推荐推送
            case 20004:
                $data['title'] = '活动推荐';
                $data['content'] = '首页活动推荐';
                $data['isSilent'] = true;
                $data['isRecord'] = false;
                if (!empty($content['release_date'])) {
                    $data['push_time'] = timestamp($content["release_date"]);
                }
                break;
            //禁闭
            case 20005:
                $data['title'] = '系统通知';
                $data['content'] = isset($content['content']) ? $content['content'] : '';
                $data['isSilent'] = true;
                $type = isset($content['type']) ? $content['type'] : 0;
                if ($type == 2) $data['isRecord'] = false;
                $data['attached'] = ['type' => $type];
                break;
            //潮流
            case 20006:
                $data['title'] = isset($content['title']) ? $content['title'] : '';
                $data['content'] = isset($content['content']) ? $content['content'] : '';
                if (!$data['title']) return false;
                if ($content['status'] === 0 && !empty($content['push_time'])) {
                    $data['isSchedule'] = true;
                    $data['push_time'] = $content["push_time"];
                }
                $data['image'] = !empty($content['img']) ? media_url($content['img']) : '';
                $data['attached'] = ['id' => $content['id'], 'genre' => $content['genre'], 'genre_url' => $content['genre_url']];
                break;
            //遇道更新个人兴趣标签 一次性 安卓
            case 20008:
                $data['title'] = '遇道更新个人兴趣标签';
                $data['platform'] = ['android'];
                $data['isSilent'] = true;
                $data['isRecord'] = false;
                break;
            //积分商城发货提醒
            case 20009:
                $data['content'] = $data['title'] = '您的订单已发货，请注意查收';
                break;
            //订单催评
            case 20010:
                $msg = $content > 0 ? '，立即评价可获得' . $content . '积分' : '。';
                $data['content'] = $data['title'] = '您对本次购买的商品还满意吗？快去评价吧' . $msg;
                break;
            //订单退货
            case 20011:
                $msg = $content->pay_integral > 0 ? '和积分变动。' : '。';
                $data['content'] = $data['title'] = '您购买的 ' . $content->name . ' 订单退货完成，请注意查看退款进度' . $msg;
                break;
            //评价有了新回复
            case 20012:
                $data['isSilent'] = true;
                $data['title'] = '订单新回复';
                $data['content'] = '您评价的' . $content['name'] . '商品有新的回复';
                break;

            case 20013:
                $data['title'] = '满意度调查';
                $data['content'] = '你对本次服务是否满意？填写“服务满意度小调查”问卷，即可获得1000积分。';
                $data['url'] = env('APP_WEB_SATISFACTION_SURVEY', 'http://ydapp.test.ve-link.com/app-serveEvaluate/index.html?dev_id=') . $content;
                $data['isSkip'] = true;
                break;
            case 20014:
                $data['title'] = '啊哦…您提交的群封面审核失败…';
                $data['content'] = '群封面审核失败:' . $content;
                $data['isSkip'] = false;
                break;
            case 20015:
                $data['title'] = $content['title'];
                $data['content'] = $content['title'];
                $data['group_id'] = $content['group_id'];
                $data['group_owner_id'] = $content['ub_id'];
                break;
            case 20016:
                $data['title'] = $content['title'];
                $data['content'] = $content['content'];
                break;
            case 20018:
                $data['title'] = '系统通知';
                $data['content'] = '发现您的爱车有一条故障码';
                $data['ub_id'] = is_array($fub_id) ? $fub_id[0] : $fub_id;
                $data['attached'] = [
                    'ug_id' => $content
                ];
                break;
            case 20019:
                $data['title'] = '静默推送';
                $data['content'] = '故障码关闭';
                $data['isSilent'] = true;
                $data['isRecord'] = false;
                $data['ub_id'] = is_array($fub_id) ? $fub_id[0] : $fub_id;
                $data['ug_id'] = $content['ug_id'];
                $data['error_code'] = $content['code'];
                break;
        }
        return $data;
    }

    protected static function triggerEvent(array $data)
    {
        event(new PushEvent(PushEvent::TYPE_APP, $data));
    }
}