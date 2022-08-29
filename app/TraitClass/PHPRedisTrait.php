<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\Redis;

trait PHPRedisTrait
{
    public function redis($name=null)
    {
        return Redis::connection($name)->client();
    }

    public function redisBatchDel($keys,$redis=null): void
    {
        $redis = $redis ?? $this->redis();
        foreach ($keys as $key){
            $redis->del($key);
        }
    }


}