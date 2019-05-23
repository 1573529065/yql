<?php
namespace app\index\validate;

use app\common\entity\Config;
use app\common\entity\MarketPrice;
use app\common\entity\Orders;
use app\common\entity\User;
use app\common\service\Users\Identity;
use think\Validate;

class SaleForm extends Validate
{
    protected $rule = [
        'number' => 'require|checkNumber',
        'price' => 'require|checkPrice',
    ];

    protected $message = [
        'number.require' => '卖出数量不能为空',
        'price.require' => '单价不能为空',
    ];

    public function checkNumber($value, $rules, $data = [])
    {
        if (!preg_match('/^[1-9]\d*$/', $value)) {
            return '卖出数量必须为大于1的正整数';
        }

        $min = Config::getValue('market_min');
        $max = Config::getValue('market_max');

        if ($min > 0 && $value < $min) {
            return sprintf('卖出数量必须在%s-%s之间', $min, $max);
        }
        if ($max > 0 && $value > $max) {
            return sprintf('卖出数量必须在%s-%s之间', $min, $max);
        }

        //检查用户的魔石数量
        $order = new Orders();
        $chargNumber = $order->getChargeNumber($value);

        $idenity = new Identity();
        $user = User::where('id', $idenity->getUserId())->find();

        if ($user->magic < $chargNumber + $value) {
            return sprintf('魔石不够了，需要%s的手续费', $chargNumber);
        }

        return true;
    }

    public function checkPrice($value, $rules, $data = [])
    {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return '卖出单价最多为2位小数';
        }
        $marketPrice = new MarketPrice();
        $prices = $marketPrice->getCurrentPrice();

        $min = $prices['prices']['min'];
        $max = $prices['prices']['max'];

        if ($value < $min || $value > $max) {
            return sprintf('单价在%s-%s之间', $min, $max);
        }
        return true;
    }


}