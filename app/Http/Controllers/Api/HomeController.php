<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carousel;
use App\Models\Category;
use App\Models\Video;
use App\TraitClass\AdTrait;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\EsTrait;
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
    use PHPRedisTrait, GoldTrait, VideoTrait, AdTrait, MemberCardTrait,ApiParamsTrait,EsTrait;

    public function category(Request $request): \Illuminate\Http\JsonResponse
    {
        $cacheData = Cache::get('api_home_category');
        $data = $cacheData ? $cacheData->toArray() : [];

        //隐藏最后一个
        $res = [];
        foreach ($data as $v){
            if(isset($v->id) && $v->id!=10058){
                $res[] = $v;
            }
        }
        /*if($request->user()->id==8490593){
            Log::info('==Category==',[8490593,$res]);
        }*/
        return response()->json([
            'state'=>0,
            'data'=>$res
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
            if(isset($request->params)){
                $user = $request->user();
                $params = self::parse($request->params);
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
                $res = false;
                if(!$res){
                    $lock = Cache::lock('homeLists_lock');
                    if(!$lock->get()){
                        Log::info('index_list',[$sectionKey]);
                        return response()->json(['state' => -1, 'msg' => '服务器繁忙请稍候重试']);
                    }

                    $paginator = DB::table('topic')->where('cid',$cid)->where('status',1)->orderBy('sort')
                        ->simplePaginate($perPage,['id','name','contain_vids'],'homeContent',$page);
                    $res['hasMorePages'] = $paginator->hasMorePages();
                    $topics = $paginator->items();
                    foreach ($topics as &$topic){
                        $topic = (array)$topic;
//                    $topic['small_video_list'] = [];
                        //获取专题数据
                        $topic['title'] = '';
                        $ids = explode(',',$topic['contain_vids']);
                        //Log::info('index_list',$ids);
                        $searchParams = [
                            'index' => 'video_index',
                            'body' => [
                                'size' => 8,
                                '_source' => ['id','is_top','name','gold','cat','tag_kv','sync','title','dash_url','hls_url','duration','type','restricted','cover_img','views','likes','updated_at'],
//                                '_source' => false,
                                'query' => [
                                    'bool'=>[
                                        'must' => [
                                            'terms' => ['id'=>$ids],
                                        ]
                                    ]
                                ],
                            ],
                        ];
                        $es = $this->esClient();
                        $response = $es->search($searchParams);
                        $videoList = [];
                        if(isset($response['hits']) && isset($response['hits']['hits'])){
                            foreach ($response['hits']['hits'] as $item) {
                                $videoList[] = $item['_source'];
                            }
                        }
                        //$videoBuild = DB::table('video')->where('status',1)->whereIn('id',$ids);
                        //$videoList = $videoBuild->limit(8)->get(['video.id','video.is_top','name','gold','cat','tag_kv','sync','title','dash_url','hls_url','duration','type','restricted','cover_img','views','likes','updated_at'])->toArray();
                        $topic['small_video_list'] = $videoList;
                        unset($topic['contain_vids']);
                    }
                    //广告
                    $topics = $this->insertAds($topics,'home_page',true,$page,$perPage);
                    $res['list'] = $topics;
                    $redis->set($sectionKey,json_encode($res,JSON_UNESCAPED_UNICODE));
                    $lock->release();
                }

                if(isset($res['list'])){
                    $domain = env('RESOURCE_DOMAIN2');
                    foreach ($res['list'] as &$r){
                        if(!empty($r['ad_list'])){
                            $this->frontFilterAd($r['ad_list'],$domain);
                        }
                        if(!empty($r['small_video_list'])){
                            $r['small_video_list'] = $this->handleVideoItems($r['small_video_list'],false,$user->id);
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
