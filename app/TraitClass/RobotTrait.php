<?php

namespace App\TraitClass;

use App\Models\User;
use GuzzleHttp\Client;

trait RobotTrait
{
    public function RobotSendMsg($msg='')
    {
        //通知
        $tgApiToken = '5497303996:AAGjlfy0NDjM-L7p7ql74ZOVyte5ZeLGtGg';
        $apiUrl = 'https://api.telegram.org/bot' .$tgApiToken.'/sendMessage';
        $input = [
            'chat_id'=>'-804384145',
            'text'=>$msg,
        ];
        $curl = (new Client([
            'verify' => false,
            'proxy' => ['https'  => 'tcp://github.com/:80']
        ]))->post($apiUrl,['form_params' => $input]);
        $this->info($curl->getBody()->getContents());
    }
}