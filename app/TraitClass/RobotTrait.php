<?php

namespace App\TraitClass;

use App\Models\User;
use GuzzleHttp\Client;

trait RobotTrait
{
    public function RobotSendMsg($msg='')
    {
        //通知
        $tgApiToken = env('TG_ROBOT_TOKEN');
        $chat_id = env('TG_CHAT_ID');
        $apiUrl = 'https://api.telegram.org/bot' .$tgApiToken.'/sendMessage';
        $input = [
            'chat_id'=>$chat_id,
            'text'=>$msg,
        ];
        $curl = (new Client([
            'verify' => false,
//            'proxy' => ['https'  => 'tcp://www.youtube.com:80']
        ]))->post($apiUrl,['form_params' => $input]);
        $this->info($curl->getBody()->getContents());
    }
}