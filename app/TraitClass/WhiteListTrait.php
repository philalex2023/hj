<?php

namespace App\TraitClass;

use App\Models\WhiteList;
use App\TraitClass\IpTrait;
use Illuminate\Support\Facades\Log;

trait WhiteListTrait
{
    use IpTrait;
    public function whitelistPolice(): bool
    {
        $ip = $this->getRealIp();
        //白名单
        $whiteList = WhiteList::query()
            ->where('status',1)
            ->where('type',1)
            ->pluck('ip')->toArray();
        //Log::info('===adminLoginIPS===',[$whiteList,$ip]);
        //Log::info('===adminSERVER===',[$_SERVER]);
        if(!in_array($ip, $whiteList)){
            return false;
        }
        return true;
    }
}