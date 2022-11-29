<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carousel;
use App\Models\Category;
use App\Models\Video;
use App\TraitClass\AdTrait;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\DataSourceTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    use PHPRedisTrait, GoldTrait, VideoTrait, AdTrait, MemberCardTrait,ApiParamsTrait,DataSourceTrait;

    public function category(Request $request): \Illuminate\Http\JsonResponse
    {
        $cacheData = Cache::get('api_home_category');
        $data = $cacheData ? $cacheData->toArray() : [];

        //隐藏最后一个
        /*$res = [];
        foreach ($data as $v){
            if(isset($v->id) && $v->id!=10058){
                $res[] = $v;
            }
        }*/
        /*if($request->user()->id==8490593){
            Log::info('==Category==',[8490593,$res]);
        }*/
        //Log::info('==Category==',[$request->user()->id,$data]);
        return response()->json([
            'state'=>0,
            'data'=>$data
        ]);
    }

    //轮播

    /**
     * @throws ValidationException
     */
    public function carousel(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if(isset($request->params)){
                $params = self::parse($request->params);
                $validated = Validator::make($params,[
                    'cid' => 'required|integer'
                ])->validated();
                $cid = $validated['cid'];
                // Log::info('==carouselLog===',[$validated]);
                $carouselData = Cache::get('api_carousel.'.$cid);
                if(!$carouselData){
                    $carouselData = Carousel::query()
                        ->where('cid', $cid)
                        ->where('status', 1)
                        ->orderByDesc('sort')
                        ->get(['id','title','img','url','action_type','vid','status','sort','line','end_at']);
                    Cache::put('api_carousel.'.$cid,$carouselData,3600);
                }

                $data = $carouselData ? $carouselData->toArray() : [];
                $res = [];
                if(!empty($data)){
                    $domain = env('RESOURCE_DOMAIN2');
                    foreach ($data as &$carousel){
                        $carousel['img'] = $this->transferImgOut($carousel['img'],$domain,1,'auto');
                        $carousel['action_type'] = (string) $carousel['action_type'];
                        $carousel['vid'] = (string) $carousel['vid'];
                    }
                    $res = $this->frontFilterAd($data);
                }
                return response()->json([
                    'state'=>0,
                    'data'=>$res
                ]);
            }
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

        return response()->json(['state' => -1, 'msg' => "参数错误",'data'=>[]], 200, ['Content-Type' => 'application/json;charset=UTF-8','Charset' => 'utf-8']);
    }

    /**
     * @throws ValidationException
     */
    public function lists(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            //Log::info('==lists_uid==',[$request->user()->id]);
            if(isset($request->params)){
                $user = $request->user();
                $params = self::parse($request->params);
                //Log::info('list_params',[$params]);
                $validated = Validator::make($params,[
                    'cid' => 'required|integer',
                    'page' => 'required|integer',
                ])->validated();
                $cid = $validated['cid'];
                $perPage = 4;
                $page = $validated['page'];

                $redis = $this->redis();
                $sectionKey = 'homeLists_'.$cid.'-'.$page;

                //二级分类列表
                $res = $redis->get($sectionKey);
                $res = json_decode($res,true);
                $freshTime = $redis->get('homeLists_fresh_time')??0;
                $ctime = $res['ctime'] ?? 0;
                if(!$res || $freshTime > $ctime){
                    $paginator = DB::table('topic')->where('cid',$cid)->where('status',1)->orderBy('sort')->simplePaginate($perPage,['id','name','show_type','contain_vids'],'homeContent',$page);
                    $res['hasMorePages'] = $paginator->hasMorePages();
                    $topics = $paginator->items();
                    Log::info('fresh_list',[$cid]);
                    $ids = [];
                    $size = 0;
                    foreach ($topics as &$topic){
                        $topic = (array)$topic;
                        $topic['style'] = (string)$topic['show_type']; //android要是字符串

                        if(!empty($topic['contain_vids'])){
                            $num = $topic['style'] == 7 ? 7: 8;
                            $size = $num+$size;
                            //获取专题数据
                            $topic['title'] = '';
                            $expAll = explode(',',$topic['contain_vids']);
                            if(!empty($expAll)){
                                $topic['tmp_ids'] = array_slice($expAll,0,$num);
                                $ids = [...$ids ,...$topic['tmp_ids']];
                            }
                        }
                        unset($topic['contain_vids']);
                        unset($expAll);
                    }
                    $ids = array_values(array_unique($ids));
                    $size = count($ids);
                    $body = [
                        'size' => $size,
                        '_source' => $this->videoFields,
                        'query' => [
                            'bool'=>[
                                'must' => [
                                    ['terms' => ['id'=>$ids]],
                                ]
                            ]
                        ]
                    ];
                    $body['sort'] = [['id' => 'desc']];
                    $body['search_after'] = [$this->maxVid];
                    $searchParams = [
                        'index' => 'video_index',
                        'body' => $body
                    ];

                    $es = $this->esClient();
                    $response = $es->search($searchParams);
                    //Log::info('searchParam_home_list',[json_encode($searchParams)]);
                    $videoList = [];
                    if(isset($response['hits']) && isset($response['hits']['hits'])){
                        foreach ($response['hits']['hits'] as $item) {
                            $videoList[] = $item['_source'];
                        }
                        unset($response);
                    }
                    if(!empty($videoList)){
                        $videoList = array_column($videoList,null,'id');
                        foreach ($topics as &$top){
                            $top['small_video_list']=[];
                            if(isset($top['tmp_ids'])){
                                foreach ($top['tmp_ids'] as $vid){
                                    isset($videoList[$vid]) && $top['small_video_list'][] = $videoList[$vid];
                                }
                                unset($top['tmp_ids']);
                            }
                        }
                        unset($videoList);
                        //广告
                        $topics = $this->insertAds($topics,'home_page',true,$page,$perPage);
                    }else{
                        $topics = [];
                    }
                    $res['list'] = $topics;
                    unset($topics);
                    $res['ctime'] = time();
                    //
                    $redis->set($sectionKey,json_encode($res,JSON_UNESCAPED_UNICODE));
                    $redis->expire($sectionKey,3600);

                }

                if(isset($res['list'])){
                    $domain = env('RESOURCE_DOMAIN2');
                    foreach ($res['list'] as &$r){
                        if(!empty($r['ad_list'])){
                            $this->frontFilterAd($r['ad_list'],$domain);
                        }
                        if(!empty($r['small_video_list'])){
                            $r['small_video_list'] = $this->handleVideoItems($r['small_video_list'],false,$user->id,['cid'=>$cid,'device_system'=>$user->device_system]);
                        }
                    }
                    return response()->json(['state'=>0, 'data'=>$res]);
                }
                return response()->json(['state'=>0, 'data'=>[]]);
            }
            return response()->json(['state' => -1, 'msg' => "参数错误"]);
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

}

