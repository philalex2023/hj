<?php

namespace App\TraitClass;

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
            $cacheData = RechargeChannels::query()->where('status',1)->get();
            Cache::forever($key,$cacheData) && $lock->release();
        }
        return $cacheData;
    }
}