<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gold;
use App\Models\MemberCard;
use App\Models\Order;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\IpTrait;
use App\TraitClass\PayTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * 支付基础类
 * Class CJController
 * @package App\Http\Controllers\Api
 */
class PayBaseController extends Controller
{
    use PHPRedisTrait,ApiParamsTrait,PayTrait,IpTrait;

    public function prepay($request,$payName)
    {
        $params = self::parse($request->params ?? '');
        Validator::make($params, [
            'pay_id' => 'required|string',
            'type' => [
                'required',
                'string',
                Rule::in(['1', '2']),
            ],
        ])->validate();
        //Log::info('df_pay_params===', [$params]);//参数日志
        $payEnv = self::getPayEnv();
        $payEnvInfo = $payEnv[$payName];
        $secret = $payEnvInfo['secret'];

        $orderInfo = Order::query()->find($params['pay_id']);
        if (!$orderInfo) {
//            throw new Exception("订单不存在");
            Log::error($payName.'_pay_exception===', [$params]);
            $return = $this->format(-1, [], '订单不存在');
            return response()->json($return);
        }

        $channelNo = $params['type'];
        if (in_array($params['type'], ['1', '2'])) {
            $channelNo = $this->getOwnMethod($orderInfo->type, $orderInfo->type_id, $params['type']);
        }

        $mercId = $payEnvInfo['merchant_id'];
        $notifyUrl = 'https://' .$_SERVER['HTTP_HOST'] . $payEnvInfo['notify_url'];


        //Log::info($payName.'_third_params===', [$input]);//三方参数日志
        Log::info($payName.'_pay_url===', [$payEnvInfo['pay_url']]);//三方参数日志
        return [
            'secret' => $secret,
            'order_info' => $orderInfo,
            'notifyUrl' => $notifyUrl,
            'merchId' => $mercId,
            'channelNo' => $channelNo,
            'pay_url' => $payEnvInfo['pay_url'],
            'ip' => $this->getRealIp(),
        ];
    }

    /**
     * 返回通道代码
     * @param $type
     * @param $goodsId
     * @param $channel
     * @return mixed
     */
    protected function getOwnMethod($type, $goodsId, $channel): mixed
    {
        if ($type == 1) {
            $memberCardData = $this->getMemberCardData();
            if ($channel == 1) {
                return $memberCardData[$goodsId]['zfb_channel'];
            }
            return $memberCardData[$goodsId]['wx_channel'];
        } elseif ($type == 2) {
            $goldCardData = $this->getGoldData();
            if ($channel == 1) {
                return $goldCardData[$goodsId]['zfb_channel'];
            }
            return $goldCardData[$goodsId]['wx_channel'];
        }
    }


    private function getMemberCardData(): array
    {
        $memberCardApiKey = "member_card_key";
        $cacheData = Cache::get($memberCardApiKey);
        if (!$cacheData) {
            $lock = Cache::lock('member_card_lock',10);
            $cacheData = MemberCard::query()->get();
            Cache::forever($memberCardApiKey,$cacheData) && $lock->release();
        }
        return array_column($cacheData->toArray(), null, 'id');
    }

    private function getGoldData(): array
    {
        $goldCardApiKey = "member_gold";
        $cacheData = Cache::get($goldCardApiKey);
        if (!$cacheData) {
            $lock = Cache::lock('member_gold_lock',10);
            $cacheData = MemberCard::query()->get();
            Cache::forever($goldCardApiKey,$cacheData) && $lock->release();
        }
        return array_column($cacheData->toArray(), null, 'id');
    }
}