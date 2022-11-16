<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\TraitClass\AdTrait;
use App\TraitClass\CacheTableTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\IpTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\RobotTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    use PHPRedisTrait,AdTrait,IpTrait,EsTrait,RobotTrait,CacheTableTrait;

    public function ack(): \Illuminate\Http\JsonResponse
    {
        $configKey = 'api_config';
        $configData = $this->redis()->get($configKey);
        $res = $configData ? (array)json_decode($configData,true) : $this->getConfigDataFromDb(true);
        //开屏广告权重显示
        if(!empty($res['open_screen_ads'])){
            //权重显示
            $weight = 0;
            $keys = [];
            foreach ($res['open_screen_ads'] as $key => $ad){
                $weight += $ad['weight'];
                for ($i=0;$i<$ad['weight'];++$i){
                    $keys[] = $key;
                }
            }
            $use = rand(0, $weight -1);
            $hitKey = $weight==0 ? 0 : $keys[$use];

//            Log::info('==weight==',['命中第 '.($hitKey+1).' 张',$use,count($keys),$weight]);
            $one = $res['open_screen_ads'][$hitKey];
            $res['open_screen_ads'] = [$one];
        }

        $this->frontFilterAd($res['open_screen_ads']);
        $this->frontFilterAd($res['activity_ads']);
        //Log::info('==ack==',[$res]);
        return response()->json([
            'state'=>0,
            'data'=>$res
        ]);

    }

    public function robotsUpdate(Request $request): int
    {
        $all = $request->except('s');
        $switchChannel = $this->RobotGetPayInfo()['switch_channel'];
        $message = $all['message']??'';
        if(!empty($message)){
            $text = $message['text']??'none';
            $username = $message['chat']['username']??'';
            $chatId = $message['chat']['id'];

            if($chatId<0){ //群
                $username = $message['from']['username']??'';
                $isCallRobot = str_contains(substr($text,0,32),'ReportBot');
//                Log::info('testRobot',[$isCallRobot,$message]);
                if(!$isCallRobot){
                    return 1;
                }
                $text = substr($text,13);
                $this->RobotSendMsg(json_encode($all,JSON_UNESCAPED_UNICODE),'1006585279');
            }

            $super = str_contains($username,'zhao');
//            $super = false;
            $availableTextForSup = str_contains($text,',');
            $availableTextForNotSup = str_contains($text,'_');
            $availableText = $availableTextForNotSup || $availableTextForSup;

//            Log::info('testRobot',[$super,$chatId]);
            if($super && $chatId>0){
                switch ($text){
                    case 'report':
                        Artisan::call('telegram_bot_report');
                        return 0;
                    case 'pca':
                        $items = self::rechargeChannelCache()->toArray();
                        $arr = [];
                        foreach ($items as $item){
                            $arr[$item['name']] = [2=>$item['wx_code'],1=>$item['zfb_code'],'status'=>$item['status']];
                        }
                        $this->RobotSendMsg(json_encode($arr,JSON_UNESCAPED_UNICODE),$chatId);
                        return 0;
                    case 'pcca':
                        $channelFoPay = self::rechargeChannelCache()->toArray();
                        $channelFoPayItems = array_column($channelFoPay,null,'id');
                        $items = self::rechargeChannelsCache()->toArray();
                        $arr = [];
                        foreach ($items as $item){
                            $channelId = $item['pay_channel'];
                            $channelInfo = $channelFoPayItems[$channelId];
                            $channelCode = match ($item['pay_type']){
                                1 => $channelInfo['zfb_code'],
                                2 => $channelInfo['wx_code'],
                            };
                            $arr[] = [
                                'name'=>$channelInfo['name']??'',
                                'code'=>$channelCode??'',
                                'type'=>$item['pay_type'],
                                'status'=>$item['status'],
                                'channel'=>$item['pay_channel'],
                                'remark'=>$item['remark'],
                            ];
                        }
                        $this->RobotSendMsg(json_encode($arr,JSON_UNESCAPED_UNICODE),$chatId);
                        return 0;
                }

                if(!$availableTextForSup){
                    return 1;
                }else{
                    $textExp = explode(',',$text);
                    $payName = $textExp[0]??'';
                    $code = $textExp[1]??'';
                    $on = $textExp[2]??0;
                    if($payName=='bindGroup'){ //绑定群
                        $groupId = $textExp[1];
                        DB::table('recharge_channels')->where('pay_channel',$textExp[2])->update(['remark'=>$groupId]);
                        $this->RobotSendMsg('绑定成功',$chatId);
                        $this->RobotSendMsg('绑定成功',$groupId);
                        return 1;
                    }
                }
            }else{
                if(!$availableText){
                    //$this->RobotSendMsg('无效的命令格式',$chatId);
                    return 0;
                }
                $cacheData = self::rechargeChannelCache();
                $channelIdName = array_column($cacheData->toArray(),'name','id');
                //有绑定过群
                $pay_channel = DB::table('recharge_channels')->where('remark',$chatId)->value('pay_channel');
//                Log::info('testRobot',[$channelIdName]);
                if(intval($pay_channel)>0){
                    $payName = $channelIdName[$pay_channel];
                }else{ //todo 绑定完后删除这块
                    //没有群绑定
                    if(isset($switchChannel[$username])){
                        $payName = $switchChannel[$username];
                    }else{
                        $kfUsernameKeys  = array_keys($switchChannel);
                        foreach ($kfUsernameKeys as $kfUsernameKey){
                            if(str_contains($username,$kfUsernameKey)){
                                $payName = $switchChannel[$kfUsernameKey];
                            }
                        }
                    }
                }


                if(!isset($payName)){
                    //$this->RobotSendMsg('未绑定',$chatId);
                    return 1;
                }

                $code = substr($text,0,-2);
                $on = substr($text,-1,1);

            }

            if(!$availableText){
                return 1;
                //$this->RobotSendMsg('格式错误, 正确格式为: 通道编码_1/0',$chatId);
            } else {
                $cacheData = self::rechargeChannelCache();
                $payChannel = array_column($cacheData->toArray(),null,'name');
                if(isset($payChannel[$payName])){
                    $payChannelInfo = $payChannel[$payName];
                    if($code==$payChannelInfo['zfb_code'] || $code==$payChannelInfo['wx_code']){
                        $payType = $code==$payChannelInfo['zfb_code'] ? 1 : 2;
                        $status = $on==1 ? 1 : 0;
                        DB::table('recharge_channels')->where('pay_channel',$payChannelInfo['id'])->where('pay_type',$payType)->update(['status'=>$status]);
                        $this->redis()->del('recharge_channels_Z_1');
                        $this->redis()->del('recharge_channels_Z_2');

                        $msg = match ($status){
                            0 => '通道关闭成功',
                            1 => '通道开启成功',
                            default => '设置成功',
                        };
                        $this->RobotSendMsg($msg,$chatId);
                        if($chatId>0){
                            $this->RobotSendMsg('为了更好体验,请请将机器人设为管理员并联系进行群绑定,之后在群里与机器人进行交互!',$chatId);
                        }
                    }else{
                        $this->RobotSendMsg('通道码错误',$chatId);
                    }
                }else{
                    $this->RobotSendMsg('请联系管理员开启',$chatId);
                }
            }

        }
        return 0;
    }

    public function pullOriginVideo(Request $request): \Illuminate\Http\JsonResponse
    {
        /*$ip = $this->getRealIp();
        if(!$ip == '154.207.98.132') {
            return response()->json([
                'state'=>401,
                'data'=>[]
            ]);
        }*/
        $size = 100;
        $id = $request->get('id');
        $origin = $request->get('origin');
        if(!$id){
            return response()->json([
                'state'=>401,
                'data'=>[]
            ]);
        }
//        $offset = ($page-1)*$size;
        $source = ['id','name','gold','tag_kv','hls_url','duration_seconds','restricted','cover_img'];
        if($origin=='saol'){
            $source = ['id','name','gold','hls_url','duration','duration_seconds','restricted','cover_img','url','status','type','created_at','updated_at'];
        }
        $searchParams = [
            'index' => 'video_index',
            'body' => [
                'track_total_hits' => true,
                'size' => $size,
//                'from' => $offset,
                '_source' => $source,
                'query' => [
                    'bool'=>[
                        'must' => [
                            ['term' => ['type'=>4]],
                            ['term' => ['dev_type'=>0]],
                            ['range' => ['id'=>['gt'=>$id]]],
                        ]
                    ]
                ],
            ],
        ];

        $es = $this->esClient();
        $response = $es->search($searchParams);
        //Log::info('searchParam_home_list',[json_encode($searchParams)]);
        $videoList = [];
        $total = 0;
        if(isset($response['hits']) && isset($response['hits']['hits'])){
            $total = $response['hits']['total']['value'];
            foreach ($response['hits']['hits'] as $item) {
                $videoList[] = $item['_source'];
            }
        }
        return response()->json([
            'state'=>0,
            'total'=>$total,
            'length'=>count($videoList),
            'data'=>$videoList
        ]);
    }



}
