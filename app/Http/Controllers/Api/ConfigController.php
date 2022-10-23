<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\TraitClass\AdTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\IpTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    use PHPRedisTrait,AdTrait,IpTrait,EsTrait;

    public function ack(): \Illuminate\Http\JsonResponse
    {
        $configKey = 'api_config';
        $configData = $this->redis()->get($configKey);
        $res = $configData ? (array)json_decode($configData,true) : $this->getConfigDataFromDb();
        $this->frontFilterAd($res['open_screen_ads']);
        $this->frontFilterAd($res['activity_ads']);
        //Log::info('==ack==',[$res]);
        return response()->json([
            'state'=>0,
            'data'=>$res
        ]);

    }

    public function pullOriginVideo(Request $request): \Illuminate\Http\JsonResponse
    {
        $ip = $this->getRealIp();
        if($ip == '154.207.98.132'){
            $size = 100;
            $page = $request->get('page',1);
            $offset = ($page-1)*$size;
            $source = ['id','is_top','name','gold','cat','tag_kv','sync','title','dash_url','hls_url','duration','type','restricted','cover_img','updated_at'];
            $searchParams = [
                'index' => 'video_index',
                'body' => [
                    'track_total_hits' => true,
                    'size' => $size,
                    'from' => $offset,
                    '_source' => $source,
                    'query' => [
                        'bool'=>[
                            'must' => [
                                ['term' => ['type'=>4]],
                            ]
                        ]
                    ],
                ],
            ];

            $es = $this->esClient();
            $response = $es->search($searchParams);
            //Log::info('searchParam_home_list',[json_encode($searchParams)]);
            $videoList = [];
            if(isset($response['hits']) && isset($response['hits']['hits'])){
                foreach ($response['hits']['hits'] as $item) {
                    $videoList[] = $item['_source'];
                }
            }
            return response()->json([
                'state'=>0,
                'data'=>$videoList
            ]);
        }
        return response()->json([
            'state'=>401,
            'data'=>[]
        ]);
    }

}
