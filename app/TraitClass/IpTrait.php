<?php

namespace App\TraitClass;

trait IpTrait
{
    public function getRealIp(): string
    {
        // $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? \request()->getClientIp();
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? \request()->getClientIp());
        $forceIp = $this->forceToIpV4($ip);
        $ipArr = explode(',',$forceIp);
        if(count($ipArr)>1){
            $forceIp = $ipArr[0];
        }
        return $forceIp;
    }

    public function forceToIpV4($ip): string
    {
        $IPV4 = $ip;
        if(filter_var($ip,FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
            $IPV4 = @hexdec(substr($ip, 0, 2)). "." . @hexdec(substr($ip, 2, 2)). "." . @hexdec(substr($ip, 5, 2)). "." . @hexdec(substr($ip, 7, 2));
        }
        return $IPV4;
    }
}