<?php

namespace App\Http\Controllers\ChannelApi;

use App\Http\Controllers\Controller;
use App\TraitClass\ChannelTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LandingController extends Controller
{
    use StatisticTrait,PHPRedisTrait,ChannelTrait;

    public function test(Request $request)
    {
        return response()->json([]);
    }

    public function record(Request $request)
    {
        $dateArr = $this->getDateArr();
        $insert = [
            'ip' => $request->input('ip',''),
            //'channel_id' => $request->input('channel_id',0),
            'download_url' => $request->input('download_url',''),
            'agent_info' => $request->input('agent_info',''),
            'code' => $request->input('code',''),
            'device_system' => $request->input('device_system',0),
            'created_at' => $request->input('created_at',$dateArr['at']),
        ];
        $insert['channel_id'] = $this->getChannelIdByPromotionCode($request->input('channel_id',0));
        $redis = $this->redis();
        $hashKey = 'download:'.$insert['ip'];
        $redis->hMSet($hashKey,$insert);
        $redis->expire($hashKey,7200);
        //统计点击量
        $this->saveStatisticByDay('hits',$insert['channel_id'],$insert['device_system'],$dateArr);
        return response()->json($insert);
    }

    public function index(Request $request)
    {
        $dateArr = $this->getDateArr();
        $insert = [
            'at_time' => time(),
            //'channel_id' => $request->input('channel_id',0),
            'device_system' => $request->input('device_system',0),
        ];
        $insert['channel_id'] = $this->getChannelIdByPromotionCode($request->input('channel_id',0));

        //统计访问量
        $this->saveStatisticByDay('access',$insert['channel_id'],$insert['device_system'],$dateArr);
        return response()->json($insert);
    }

}