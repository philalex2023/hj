<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\TraitClass\AdTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\IpTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    use PHPRedisTrait,AdTrait,IpTrait,EsTrait;

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
                for ($i=0;$i<$weight;++$i){
                    $keys[] = $key;
                }
            }
            $use = rand(0, $weight -1);
            $hitKey = $weight==0 ? 0 : $keys[$use];

            Log::info('==weight==',['命中第 '.($hitKey+1).' 张',$use,count($keys)]);
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
