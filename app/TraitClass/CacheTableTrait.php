<?php

namespace App\TraitClass;

use App\Models\RechargeChannel;
use App\Models\RechargeChannels;
use Illuminate\Support\Facades\Cache;

trait CacheTableTrait
{
    public function getRechargeChannelById($id): array
    {
        $key = 'recharge_channel_'.$id;
        $redis = $this->redis();
        $cacheData = $redis->hGetAll($key);
        if(!$cacheData){
            $cacheData = RechargeChannel::query()->where('id',$id)->first()->toArray();
            $redis->hMSet($key,$cacheData);
            $redis->expire($key,14400);
        }
        return $cacheData;
    }

    public static function rechargeChannelCache()
    {
        $key = 'recharge_channel';
        $cacheData = Cache::get($key);
        if(!$cacheData){
            $lock = Cache::lock($key.'_lock',5);
            $cacheData = RechargeChannel::query()->where('status',1)->get();
            Cache::put($key,$cacheData,14400) && $lock->release();
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