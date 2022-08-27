<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gold;
use App\Models\MemberCard;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;

/**
 * 支付基础类
 * Class CJController
 * @package App\Http\Controllers\Api
 */
class PayBaseController extends Controller
{
    use PHPRedisTrait;
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

    /**
     * 返回支付渠道
     * @param $type
     * @param $goodsId
     * @param $channel
     * @return mixed
     */
    protected function getOwnCode($type, $goodsId, $channel): mixed
    {
        if ($type == 1) {
            $memberCardData = $this->getMemberCardData();
            // var_dump($memberCardData[$goodsId]);
            if ($channel == 1) {
                return $memberCardData[$goodsId]['zfb_action_id'];
            }
            return $memberCardData[$goodsId]['wx_action_id'];
        } elseif ($type == 2) {
            $goldCardData = $this->getGoldData();
            if ($channel == 1) {
                return $goldCardData[$goodsId]['zfb_action_id'];
            }
            return $goldCardData[$goodsId]['wx_action_id'];
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