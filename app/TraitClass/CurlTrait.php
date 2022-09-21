<?php

namespace App\TraitClass;

trait CurlTrait
{

    public function curlByUrl($url): bool|string
    {
        $ch = curl_init();
        $timeout = 6000;
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $file_contents = curl_exec($ch);
        curl_close($ch);
        return $file_contents;
    }

}