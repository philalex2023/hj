<?php

namespace App\TraitClass;

use App\Models\RechargeChannel;
use App\Models\RechargeChannels;
use Illuminate\Support\Facades\Cache;

trait CacheTableTrait
{
    public static function rechargeChannelCache()
    {
        $key = 'recharge_channel';
        $cacheData = Cache::get($key);
        if(!$cacheData){
            $lock = Cache::lock($key.'_lock',5);
            $cacheData = RechargeChannel::query()->where('status',1)->get();
            Cache::put($key,$cacheData,3600) && $lock->release();
        }
        return $cacheData;
    }

    public static function rechargeChannelsCache()
    {
        $key = 'recharge_channels';
        $cacheData = Cache::get($key);
        if(!$cacheData){
            $lock = Cache::lock($key.'_lock',5);
            $cacheData = RechargeChannels::query()->get();
            Cache::put($key,$cacheData,3600) && $lock->release();
        }
        return $cacheData;
    }
}