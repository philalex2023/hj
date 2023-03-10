<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\TraitClass\AdTrait;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommunityTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommunityController extends Controller
{
    use ApiParamsTrait,CommunityTrait,PHPRedisTrait,VideoTrait,EsTrait,AdTrait;

    public array $discussField = ['id','vid','uid','circle_id','circle_topic_id','content','circle_name','circle_topic_name','avatar','album','author','tag_kv','scan','comments','likes','created_at'];

    public array $upVideoFields = ['id','name','dev_type','gold','tag_kv','duration','restricted','cover_img','circle','circle_topic','views'];

    public array $circleFields = ['id','uid','name','participate','avatar','introduction as des'];

    //创建话题
    public function addCircleTopic(Request $request): \Illuminate\Http\JsonResponse
    {
        $upMasterId = $this->getUpMasterId($request->user()->id);
        if(!$upMasterId){
            return response()->json(['state' => -1, 'msg' => "请联系客服开通",'data'=>[]]);
        }else{
            // todo
            return response()->json(['state' => -1, 'msg' => "创建成功",'data'=>[]]);
        }
    }

    //创建圈子
    public function addCircle(Request $request): JsonResponse
    {
        $upMasterId = $this->getUpMasterId($request->user()->id);
        if(!$upMasterId){
            return response()->json(['state' => -1, 'msg' => "请联系客服开通",'data'=>[]]);
        }else{
            // todo
            return response()->json(['state' => -1, 'msg' => "创建成功",'data'=>[]]);
        }
    }

    public function myPurse(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json(['state' => 0,'data'=>['movie_ticket'=>$user->movie_ticket,'income'=>0]]);
    }

    //我的数据
    public function myData(Request $request): \Illuminate\Http\JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
        }

        /*$validated = Validator::make($params,[
            'time' => 'required|integer', //时间戳
        ])->validated();*/
        $time = $params['time'] ?? time();
        $upMasterId = $this->getUpMasterId($request->user()->id);

        //todo
        $data = [
            'playTimes' => 0, //视频播放次数
            'comments' => 0, //评论数
            'likes' => 0, //点赞数
            'fans' => 0, //粉丝数
            'share' => 0, //分享
            'income' => 0, //今日收益
        ];
        return response()->json(['state' => -1,'data'=>$data]);
    }

    public function getHotCircle($uid): \Illuminate\Support\Collection
    {
        //热门圈子
        $hotCircle = DB::table('circle')
            ->orderByDesc('id') //todo
            ->limit(8)->get(['id','uid','name','participate','avatar']);
        //封面图处理
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        foreach ($hotCircle as $f){
            $f->avatar = $this->transferImgOut($f->avatar,$domainSync,$_v);
            $f->isJoin = $redis->hExists('joinCircle:'.$f->id,$uid) ? 1 : 0;
            $f->user = $redis->hLen('joinCircle:'.$f->id);
        }
        return $hotCircle;
    }

    public function circleCate(Request $request): \Illuminate\Http\JsonResponse
    {
        if(!$request->user()){
            return response()->json([]);
        }
        $cats = array_values($this->getCircleCat());
        $res = [
            'state' => 0,
            'data' => $cats,
        ];
        return response()->json($res);
    }

    //UP主人气榜 todo 缓存优化
    public function upMasterRank(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $user = $request->user();
        $validated = Validator::make($params,[
            'filter' => 'required|integer', //1原创巨星榜 2最热人物榜
            'page' => 'required|integer'
        ])->validated();
        $perPage = 16;
        $page = $validated['page'];
        $offset = ($page-1)*$perPage;
        $asField = 'work_num';
        $searchParams = [
            'index' => 'video_index',
            'body' => [
                'size' => 0,
                'aggs' => [
                    $asField=>[
                        'terms' => [
                            'field' => 'uid',
                            'order' => ['_count'=>"desc"],
                        ],
                    ]
                ]
            ]
        ];
        $es = $this->esClient();
        $response = $es->search($searchParams);
        $uidWorkNum = [];
        if(isset($response['aggregations']) && isset($response['aggregations'][$asField])){
            //$total = $response['hits']['total']['value'];
            $uidWorkNum = $response['aggregations'][$asField]['buckets'];
            unset($response);
        }

        $dataList = [];
        foreach ($uidWorkNum as $key=>$item){
            if($item['key']==0){
                unset($uidWorkNum[$key]);
                break;
            }
        }
        $uidWorkNum = array_slice($uidWorkNum,$offset,$perPage);
        if(!empty($uidWorkNum)){
//            $build = DB::table('video');
            $redis = $this->redis('login');
            $domainSync = self::getDomain(2);
            $_v = date('Ymd');
            $uid_upId = $this->redis()->hGetAll('MemberUpMaster');
            $upId_uid = array_flip($uid_upId);
            foreach ($uidWorkNum as $item){
                if($item['key']>0){
                    $one = DB::table('video')->where('uid',$item['key'])->first(['uid','author','auth_avatar']);
//                    Log::info('TEST',[$item['key'],$one]);
                    $id = $upId_uid[$item['key']] ?? 1908969;
                    $key = 'upMasterFocusUser:'.$id;
                    $dataList[] = [
                        'id'=> $id,
                        'uid'=>$item['key'],
//                        'isFocus'=>$redis->sIsMember('topicFocusUser:'.$user->id,$item['key']) ? 1 : 0,
                        'isFocus'=>$redis->hExists($key,$user->id),
                        'work_num'=>$item['doc_count'],
                        'fans'=>0,
                        'author' => $one->author,
                        'auth_avatar' => $this->transferImgOut($one->auth_avatar,$domainSync,$_v)
                    ];
                }
            }
        }
        return response()->json(['state' => 0, 'data' => ['list'=>$dataList,'hasMorePages'=>false]]);
    }

    //话题榜
    public function topicRank(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'page' => 'required|integer'
        ])->validated();
        $page = $validated['page'];
        $perPage = 16;
        $field = ['id','uid','name','avatar','circle_id','participate','interactive as inter'];
        $paginator = DB::table('circle_topic')->orderByDesc('id')->simplePaginate($perPage,$field,'topicList',$page);
        $data['list'] = $paginator->items();
        $data['hasMorePages'] = $paginator->hasMorePages();
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        foreach ($data['list'] as $item){
            $item->avatar = $this->transferImgOut($item->avatar,$domainSync,$_v);
            $item->isFocus = $redis->sIsMember('topicFocusUser:'.$uid,$item->id) ? 1 : 0;
        }
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //圈子排行
    public function circleRank(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'page' => 'required|integer'
        ])->validated();
        $page = $validated['page'];
        $field = ['id','uid','name','avatar'];
        $paginator= DB::table('circle')
//            ->where('cid',$cid)
            ->simplePaginate(8,$field,'circleFeatured',$page);
        $hasMorePages = $paginator->hasMorePages();
        $featuredCircle = $paginator->items();
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        foreach ($featuredCircle as $f){
            $f->avatar = $this->transferImgOut($f->avatar,$domainSync,$_v);
            $f->isJoin = $redis->hExists('joinCircle:'.$f->id,$uid) ? 1 : 0;
            $f->participate = $redis->hLen('joinCircle:'.$f->id);
            $f->discuss_num = 0; //todo
        }
        $data['list'] = $featuredCircle;
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //热门搜索
    public function popularSearchVideo(Request $request): \Illuminate\Http\JsonResponse
    {
        $field = ['id','name','views'];
        $data = DB::table('video')->inRandomOrder()->take(10)->get($field);
        foreach ($data as $item) {
            $item->views = $this->generateRandViews($item->views);
        }
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //热搜词
    public function popularSearchWords(Request $request): \Illuminate\Http\JsonResponse
    {
        $res = [
            'state' => 0,
            'data' => [
                '偷拍','原创','少妇人妻'
            ],
        ];
        return response()->json($res);
    }

    public function searchCircle(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params, [
            'words' => 'nullable',
            'page' => 'required|integer'
        ])->validate();
        $words = $validated['words']??false;
        if(empty($words)){
            return response()->json([
                'state' => -1,
                'msg' => '请输入关键词',
                'data' => ['list'=>[]]
            ]);
        }
        $words = substr($words,0,40);
        $page = $validated['page'];
        $uid = $request->user()->id;
        $field = ['id','uid','name','participate','avatar','introduction as des'];
        $paginator= DB::table('circle')
            ->where('name', 'like', '%'.$words.'%')
            ->orderByDesc('id')
            ->simplePaginate(8,$field,'circle',$page);
        $data = $this->handleCircleItems($uid,$paginator);
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function searchTopic(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params, [
            'words' => 'nullable',
            'page' => 'required|integer'
        ])->validate();
        $words = $validated['words']??false;
        if(empty($words)){
            return response()->json([
                'state' => -1,
                'msg' => '请输入关键词',
                'data' => ['list'=>[]]
            ]);
        }
        $words = substr($words,0,40);
        $page = $validated['page'];
        $perPage = 16;
        $field = ['id','uid','name','avatar','circle_id','participate','interactive as inter'];
        $paginator = DB::table('circle_topic')
            ->where('name', 'like', '%'.$words.'%')
            ->orderByDesc('id')
            ->simplePaginate($perPage,$field,'topicList',$page);
        $data['list'] = $paginator->items();
        $data['hasMorePages'] = $paginator->hasMorePages();
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        foreach ($data['list'] as $item){
            $item->avatar = $this->transferImgOut($item->avatar,$domainSync,$_v);
            $item->isFocus = $redis->sIsMember('topicFocusUser:'.$request->user()->id,$item->id) ? 1 : 0;
        }
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function searchVideoByCate(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $cid = $validated['cid'];
        $page = $validated['page'];
        $build = DB::table('video')
            ->where('cid',$cid)
            ->orderByDesc('id');
        $paginator = $build->simplePaginate(8,['id','name','author','auth_avatar','dev_type','gold','tag_kv','duration','restricted','cover_img','circle','circle_topic','likes','views'],'video',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $videoRedis = $this->redis('video');
        $data['list'] = $this->handleUpVideoItems($data['list'],$request->user()->id,$videoRedis);
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function searchVideo(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'words' => 'nullable',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();

        $type = $validated['type'];
        $page = $validated['page'];
        $words = $validated['words']??false;
        if(empty($words)){
            return response()->json([
                'state' => -1,
                'msg' => '请输入关键词',
                'data' => ['list'=>[]]
            ]);
        }
        $words = substr($words,0,40);
        $build = DB::table('video')
            ->where('dev_type',$type)
            ->where('status',1)
            ->where('name', 'like', '%'.$words.'%')
            ->orderByDesc('id');
        $paginator = $build->simplePaginate(8,$this->upVideoFields,'video',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleUpVideoItems($data['list']);
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //搜索综合界面
    public function searchMix(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params, [
            'words' => 'nullable',
        ])->validate();
        $words = $validated['words']??false;
        if(empty($words)){
            return response()->json([
                'state' => -1,
                'msg' => '请输入关键词',
                'data' => ['list'=>[]]
            ]);
        }

        $words = substr($words,0,40);
        $domainSync = self::getDomain(2);
        $_v = date('ymd');
        $redis = $this->redis('login');
        $uid = $request->user()->id;
        $circleItems = DB::table('circle')
            ->where('name', 'like', '%'.$words.'%')
            ->take(3)->orderByDesc('id')->get(['id','name','avatar']);

        foreach ($circleItems as $item){
            $item->avatar = $this->transferImgOut($item->avatar,$domainSync,$_v);
        }

        $topicItems = DB::table('circle_topic')
            ->where('name', 'like', '%'.$words.'%')
            ->take(2)->orderByDesc('id')->get(['id','name','avatar','interactive as inter','participate']);
        foreach ($topicItems as $item){
            $item->avatar = $this->transferImgOut($item->avatar,$domainSync,$_v);
            $item->isFocus = $redis->sIsMember('topicFocusUser:'.$uid,$item->id) ? 1 : 0;
        }

        $long_videos = DB::table('video')
            ->where('dev_type',0)
            ->where('status',1)
            ->where('name', 'like', '%'.$words.'%')
            ->take(4)->orderByDesc('id')->get($this->upVideoFields);
        $long_videos = $this->handleUpVideoItems($long_videos);
        $short_videos = DB::table('video')
            ->where('dev_type',1)
            ->where('status',1)
            ->where('name', 'like', '%'.$words.'%')
            ->take(4)->orderByDesc('id')->get($this->upVideoFields);
        $short_videos = $this->handleUpVideoItems($short_videos);

        $data['circle'] = $circleItems;
        $data['topic'] = $topicItems;
        $data['video'] = [
            'long' => $long_videos,
            'short' => $short_videos,
        ];

        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //个人动态
    public function personalDynamic(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $uid = $this->getUpMasterId($validated['uid']);
        $page = $validated['page'];
        $uid = $request->user()->id;
        $build = DB::table('circle_discuss');
//            ->where('uid',$uid); todo

        $paginator = $build->simplePaginate(7,$this->discussField,'discuss',$page);
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleDiscussItem($data['list'],$uid);
        $data['hasMorePages'] = $paginator->hasMorePages();
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //个人作品
    public function personalWork(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'type' => 'required|integer',
            'filter' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $uid = $validated['uid'];
        $filter = $validated['filter']; //1最多播放 2最新发布
        $page = $validated['page'];

        $build = DB::table('video');
        /*if($uid>0){ //todo
            $uid = $this->getUpMasterId($uid);
            $build = $build->where('uid',$uid)->where('dev_type',$validated['type'])->orderByDesc('created_at');
        }*/

        $paginator = $build->simplePaginate(8,['id','name','dev_type','likes','author','auth_avatar','gold','tag_kv','duration','restricted','cover_img','circle','circle_topic','views'],'video',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleUpVideoItems($data['list']);
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //个人合集
    public function personalCollection(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $user = $request->user();
        $uid = $this->getUpMasterId($validated['uid']);
        $type = $validated['type'];
        $page = $validated['page'];

        $ids = DB::table('circle')
//            ->where('uid',$uid)
            ->value('collection_ids');
        $res = ['state' => 0,'data'=>['list'=>[],'hasMorePages'=>false]];
        if(!empty($ids)){
            $idArr = explode(',',$ids);
            $build = DB::table('circle_collection')
                ->where('type',$type)
                ->whereIn('id',$idArr)
                ->orderByDesc('id');
            $data['total'] = $build->count();
            $paginator = $build->simplePaginate(8,['id','name','cover','views','gold','created_at'],'collection',$page);
            $hasMorePages = $paginator->hasMorePages();
            $data['list'] = $paginator->items();
            $domain = env('RESOURCE_DOMAIN');
            $_v = date('ymd');
            $redis = $this->redis('login');
            $key = 'unlockCollectionUser:'.$user->id;
            foreach ($data['list'] as $item){
                $item->created_at = $this->mdate(strtotime($item->created_at));
                $item->views = $this->generateRandViews($item->views,50000);
                $item->isBuy = (int)$redis->sIsMember($key,$item->id);
                if(!empty($item->cover)){
                    $cover = json_decode($item->cover,true);
                    $coverImg = [];
                    foreach ($cover as $img){
                        $coverImg[] = $this->transferImgOut($img,$domain,$_v);
                    }
                    $item->cover = $coverImg;
                }
            }

            $data['hasMorePages'] = $hasMorePages;
            $res = [
                'state' => 0,
                'data' => $data,
            ];
        }

        return response()->json($res);
    }

    public function personalLikes(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $uid = $validated['uid'];
        $page = $validated['page'];

        $build = DB::table('video');
        /*if($uid>0){ //todo
            $uid = $this->getUpMasterId($uid);
            $build = $build->where('uid',$uid)->where('dev_type',$validated['type'])->orderByDesc('created_at');
        }*/

        $paginator = $build->simplePaginate(8,['id','name','dev_type','likes','author','auth_avatar','gold','tag_kv','duration','restricted','cover_img','circle','circle_topic','views'],'video',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleUpVideoItems($data['list']);
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //个人资料页面
    public function personalInfo(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
//        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
        ])->validated();
        $domainSync = self::getDomain(2);
        $userInfo = DB::table('users')->where('id',$validated['uid'])->first(['id','nickname','avatar']);
        if(!$userInfo){
            return response()->json(['state' => -1,'msg'=>'该up主未绑定']);
        }
        $userInfo->remark = '海角射区，成人第一射区';
        $userInfo->likes = 0;
        $userInfo->focus = 0;
        $userInfo->fans = 0;
        $userInfo->isFocus = 0;
        $userInfo->avatar = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $userInfo->fansRank[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $userInfo->fansRank[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $userInfo->fansRank[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $userInfo->isUpRank = 0;
        $res = [
            'state' => 0,
            'data' => $userInfo,
        ];
        return response()->json($res);

    }

    public function myCreatedCircle(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $page = $validated['page'];
        $mid = $this->getUpMasterId($validated['uid']);
        $field = ['id','uid','name','scan','cname','participate','avatar','background as imgUrl','introduction as des'];
        $paginator = DB::table('circle')
//            ->where('uid',$mid)
            ->where('uid',1)
            ->simplePaginate(8,$field,'myCreatedCircle',$page);
        $data = $this->handleCircleItems($uid,$paginator);
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function myJoinedCircle(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $page = $validated['page'];
        $field = ['id','uid','name','scan','cname','participate','avatar','background as imgUrl','introduction as des'];
        $paginator = DB::table('circle')
//            ->where('uid',$validated['uid'])
            ->simplePaginate(8,$field,'myCreatedCircle',$page);
        $data = $this->handleCircleItems($uid,$paginator);
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function handleCircleItems($uid,$paginator): array
    {
        $hasMorePages = $paginator->hasMorePages();
        $featuredCircle = $paginator->items();
        $domainSync = self::getDomain(2);
        $_v = date('ymd');
        $redis = $this->redis('login');
        foreach ($featuredCircle as $f){
            $f->avatar = $this->transferImgOut($f->avatar,$domainSync,$_v);
            if(isset($f->imgUrl)){
                $f->imgUrl = $this->transferImgOut($f->imgUrl,$domainSync,$_v);
                $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
                $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
                $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
            }
            $f->isJoin = $redis->hExists('joinCircle:'.$f->id,$uid) ? 1 : 0;
            $f->user = $redis->hLen('joinCircle:'.$f->id);
        }
        $data['list'] = $featuredCircle;
        $data['hasMorePages'] = $hasMorePages;
        return $data;
    }

    public function circle(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $cid = $validated['cid'];
        $page = $validated['page'];
        $field = ['id','uid','name','participate','avatar','introduction as des'];
        $paginator= DB::table('circle')
//            ->where('cid',$cid)
            ->simplePaginate(8,$field,'circle',$page);
        $data = $this->handleCircleItems($uid,$paginator);
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //圈友列表
    public function circleUserList(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'id' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $page = $validated['page'];
        $perPage = 16;
        $offset = ($page-1)*$perPage;
        $redis = $this->redis('login');
        $dataList = $redis->hGetAll('joinCircle:'.$validated['id']);
        $data = ['list'=>[],'hasMorePages'=>false];
        $domainSync = self::getDomain(2);
        if(!empty($dataList)){
            $items = [];
            foreach ($dataList as $userId => $jsonStr){
                $arr = json_decode($jsonStr,true);
                $arr['at_time'] = $this->mdate($arr['at_time']);
                $arr['avatar'] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
                $items[] = ['uid' => $userId] + ['isJoin'=>$redis->hExists('joinCircle:'.$validated['id'],$uid)] + $arr;
            }
            $data['list'] = array_slice($items,$offset,$perPage);
            $data['hasMorePages'] = count($items) > $perPage*$page;
        }
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //粉丝列表
    public function fansList(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'id' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $page = $validated['page'];
        $perPage = 16;
        $offset = ($page-1)*$perPage;
        $redis = $this->redis('login');
//        $dataList = $redis->hGetAll('joinCircle:'.$validated['id']);

        $key = 'upMasterFocusUser:'.$validated['id'];
        $dataList = $redis->hGetAll($key);
        $data = ['list'=>[],'hasMorePages'=>false];
        $domainSync = self::getDomain(2);
        if(!empty($dataList)){
            $items = [];
            foreach ($dataList as $userId => $jsonStr){
                $arr = json_decode($jsonStr,true);
                $arr['at_time'] = $this->mdate($arr['at_time']);
                $arr['avatar'] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
                $items[] = ['uid' => $userId] + ['isJoin'=>$redis->hExists($key,$uid)] + $arr;
            }
            $data['list'] = array_slice($items,$offset,$perPage);
            $data['hasMorePages'] = count($items) > $perPage*$page;
        }
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //圈子详情
    public function circleDetail(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $uid = $request->user()->id;
        $validated = Validator::make($params,[
            'id' => 'required|integer'
        ])->validated();
        $id = $validated['id'];
        $field = ['id','uid','name','video_num','collection_ids','participate','avatar','introduction as des','background as imgUrl'];
        $f= DB::table('circle')->where('id',$id)->first($field);

        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        if(!$f){
            return response()->json(['state'=>-1,'msg'=>'记录不存在']);
        }
        $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $f->avatar = $this->transferImgOut($f->avatar,$domainSync,$_v);
        $f->imgUrl = $this->transferImgOut($f->imgUrl,$domainSync,$_v);
        $f->isJoin = $redis->hExists('joinCircle:'.$f->id,$uid) ? 1 : 0;
        $f->user = $redis->hLen('joinCircle:'.$f->id);
        //帖子数
        $f->discuss_num = DB::table('circle_discuss')->where('circle_id',$id)->count();
        //推荐视频三个 collection_ids
        $video = [];
        if(!empty($f->collection_ids)){
            $collectionIds = explode(',',$f->collection_ids);
            $vidArr = [];
            $collections = DB::table('circle_collection')->whereIn('id',$collectionIds)->get('vids');
            foreach ($collections as $collection){
                $vidArr = [...$vidArr, ...explode(',',$collection->vids)];
            }
            $vidArr = array_unique($vidArr);
            shuffle($vidArr);//暂时随机
            $vidArr = array_slice($vidArr,0,3);
            $vidNum = count($vidArr);
            for ($i=0;$i<$vidNum;$i++){
                $video[] = DB::table('video')->where('id',$vidArr[$i])->first(['id','name']);
            }
        }

        unset($f->collection_ids);
        //当前话题
        $topic = DB::table('circle_topic')->where('circle_id',$id)->get(['id','name','interactive as inter']);
        $res = [
            'state' => 0,
            'data' => [
                'detail' => $f,
                'topic' => $topic,
                'video' => $video,
            ],
        ];
        return response()->json($res);
    }

    //圈子精选
    public function circleFeatured (Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'filter' => 'required|integer', //1最热 2当周 todo
            'page' => 'required|integer'
        ])->validated();

        $filter = $validated['filter'];
        $page = $validated['page'];

        $field = ['id','uid','cname','name','scan','avatar','introduction as des','background as imgUrl'];
        $paginator= DB::table('circle')->simplePaginate(8,$field,'circleFeatured',$page);
        $hasMorePages = $paginator->hasMorePages();
        $featuredCircle = $paginator->items();
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        foreach ($featuredCircle as $f){
            //$f->user_avatar = [];//圈友头像（三个，不足三个有多少给多少）todo
            $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->avatar = $this->transferImgOut($f->avatar,$domainSync,$_v);
            $f->imgUrl = $this->transferImgOut($f->imgUrl,$domainSync,$_v);
            $f->user = $redis->hLen('joinCircle:'.$f->id);
        }
        $data['list'] = $featuredCircle;
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //圈子精选带视频
    public function circleFeaturedWithVideo (Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'filter' => 'required|integer', //1最热 2当周 todo
            'page' => 'required|integer'
        ])->validated();

        $filter = $validated['filter'];
        $page = $validated['page'];

        $field = ['id','uid','cname','name','scan','avatar','introduction as des','background as imgUrl'];
        $paginator= DB::table('circle')->simplePaginate(4,$field,'circleFeatured',$page);
        $hasMorePages = $paginator->hasMorePages();
        $featuredCircle = $paginator->items();
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        foreach ($featuredCircle as $f){
            $f->user = $redis->hLen('joinCircle:'.$f->id);
            $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->avatar = $this->transferImgOut($f->avatar,$domainSync,$_v);
            $f->imgUrl = $this->transferImgOut($f->imgUrl,$domainSync,$_v);

            $videoItems = DB::table('video')->where('circle',$f->name)->take(4)->get($this->upVideoFields);
            $videoItems = $this->handleUpVideoItems($videoItems);
            $f->videoList = $videoItems;
        }
        $data['list'] = $featuredCircle;
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function square(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if(!$user){
            return response()->json([]);
        }
        //todo cache
        //热门话题
        $hotTopic = DB::table('circle_topic')->orderByDesc('id')->limit(12)->get(['id','uid','name','interactive as inter']);
        //热门圈子
        $hotCircle = $this->getHotCircle($user->id);
        //圈子精选

        $data = [
            [
                'name' => '热门话题',
                'list' => $hotTopic,
            ],
            [
                'name' => '热门圈子',
                'list' => $hotCircle,
            ],
            /*[
                'name' => '圈子精选',
                'list' => $featuredCircle,
            ],*/
        ];
        $res = [
            'state' => 0,
            'data' => $data,
        ];

        return response()->json($res);
    }

    //话题列表
    public function topicList(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $cid = $validated['cid'];
        $page = $validated['page'];
        $perPage = 16;
        $field = ['id','uid','name','avatar','circle_id','participate','interactive as inter'];
        $paginator = DB::table('circle_topic')->where('cid',$cid)->simplePaginate($perPage,$field,'topicList',$page);
        $data['list'] = $paginator->items();
        $data['hasMorePages'] = $paginator->hasMorePages();
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        foreach ($data['list'] as $item){
            $item->avatar = $this->transferImgOut($item->avatar,$domainSync,$_v);
        }
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function topicDetail(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'tid' => 'required|integer',
        ])->validated();
        $tid = $validated['tid'];

        //月话题精选
        $field = ['id','uid','name','desc','circle_name','avatar','circle_avatar','circle_id','author','interactive as inter','participate'];
        $one = DB::table('circle_topic')->find($tid,$field);
        $one->user = $this->redis('login')->hLen('joinCircle:'.$one->circle_id);
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $one->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $one->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $one->user_avatar[] = $domainSync.'/upload/encImg/'.rand(1,43).'.htm?ext=png';
        $one->avatar = $this->transferImgOut($one->avatar,$domainSync,$_v);
        $redis = $this->redis('login');
        $one->isFocus = $redis->sIsMember('topicFocusUser:'.$request->user()->id,$one->id) ? 1 : 0;
        $res = [
            'state' => 0,
            'data' => $one,
        ];
        return response()->json($res);
    }

    public function handleDiscussItem($data,$uid)
    {
        $domain = env('RESOURCE_DOMAIN');
        $domainSync = self::getDomain(2);
        $_v = date('ymd');
        $redis = $this->redis('login');
        foreach ($data as $item){
            $item->tag_kv = json_decode($item->tag_kv,true) ?? [];
            $item->created_at = $this->mdate(strtotime($item->created_at));
            $item->avatar = $domain.$item->avatar;
            $item->isJoin = $redis->hExists('joinCircle:'.$item->circle_id,$uid) ? 1 : 0;
            $item->isFocus = $redis->sIsMember('discussFocusUser:'.$uid,$item->id) ? 1 : 0;
            $item->isLike = $redis->sIsMember('discussLikesUser:'.$uid,$item->id) ? 1 : 0;
            $item->album = !$item->album ? [] : json_decode($item->album,true);
            foreach ($item->album as &$album){
                $album = $this->transferImgOut($album,$domainSync,$_v);
            }
            if($item->vid>0){
                $one = DB::table('video')->where('id',$item->vid)->first(['id','name','views','dev_type','cover_img']);
                if(!empty($one)){
//                    $video = $this->handleVideoItems([$one])[0];
                    //封面图处理
                    $one->cover_img = $this->transferImgOut($one->cover_img,$domainSync,$_v);
                    $one->score = '9.5';
                    $one->views = $one->views > 0 ? $this->generateRandViews($one->views) : $this->generateRandViews(rand(500, 99999));
                    $item->video = $one;
                }else{
                    $item->video = [];
                }
            }
        }
        return $data;
    }

    public function discuss(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'filter' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $cid = $validated['cid'];
        $filter = $validated['filter']; //1按最多播放、2按最新 todo
        $page = $validated['page'];
        $uid = $request->user()->id;
        $build = DB::table('circle_discuss')->where('circle_id',$cid);
        /*if($filter==1){

        }else{

        }*/
        $paginator = $build->simplePaginate(7,$this->discussField,'discuss',$page);
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleDiscussItem($data['list'],$uid);
        $data['hasMorePages'] = $paginator->hasMorePages();
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function workCollection(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $page = $validated['page'];
        $user = $request->user();
        $mid = $this->getUpMasterId($user->id); //todo
        $type = $validated['type']; //0长视频 1短视频
        /*if($mid){
            todo
        }*/
        $columns = ['id','uid','name','cover','gold','views','created_at'];
        $build = DB::table('circle_collection')->where('type',$type);
        $paginator = $build->orderByDesc('id')->simplePaginate(8,$columns,'workVideo',$page);
        $items = $paginator->items();
        $domainSync = self::getDomain(2);
        $_v = date('ymd');
        foreach ($items as $item){
            $item->views = $this->generateRandViews($item->views);
            $item->created_at = $this->mdate(strtotime($item->created_at));
            $item->cover = $this->transferImgOut($item->cover,$domainSync,$_v);
        }
        $data['list'] = $items;
        $data['hasMorePages'] = $paginator->hasMorePages();
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function handleUpVideoItems($dataList,$uid=0,$redis=false)
    {
        $domainSync = self::getDomain(2);
        foreach ($dataList as $item) {
            $item->views = $this->generateRandViews($item->views);
            $item->tag_kv = json_decode($item->tag_kv,true)??[];
            $item->gold = $item->gold * 0.01;
            $item->cover_img = $this->transferImgOut($item->cover_img,$domainSync);
            if($uid > 0){
                $item->is_love = $redis->sIsMember('videoLove_'.$uid,$item->id) ? 1 : 0;
            }
            if(isset($item->likes)){
                $item->likes = $this->generateRandViews($item->likes,5000);
            }
            if(isset($item->auth_avatar)){
                $item->auth_avatar = $domainSync.$item->auth_avatar;
                $item->up_avatar = $item->auth_avatar;
            }
            if(!empty($item->circle_topic)){
                $item->circle_topic = json_decode($item->circle_topic,true);
            }
            if(!empty($item->circle)){
                $item->circle = json_decode($item->circle,true)??'';
            }
        }
        return $dataList;
    }

    public function workVideo(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'filter' => 'required|integer',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $user = $request->user();
        $filter = $validated['filter']; //0全部 1已发布 2审核中 3未通过
        $type = $validated['type']; //0长视频 1短视频
        $page = $validated['page'];
        $mid = $this->getUpMasterId($user->id);
//        if($mid){ todo
            $build = DB::table('video')
//                ->where('uid',$mid) todo
            ;
            if($filter>0){
                $build->where('status',$filter);
            }
            if($type>0){
                $build->where('dev_type',$type);
            }
            $paginator = $build->orderByDesc('id')->simplePaginate(16,$this->upVideoFields,'workVideo',$page);
            $data = [];
            $data['list'] = $paginator->items();
            $data['hasMorePages'] = $paginator->hasMorePages();
            $data['list'] = $this->handleUpVideoItems($data['list']);
            return response()->json(['state' => 0, 'data' => $data]);
//        }
//        return response()->json(['state' => -1, 'msg' => '系统错误']);
    }

    //我的收藏
    public function myCollect(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $perPage = 10;
            $res = [];
            $params = self::parse($request->params);
            $user = $request->user();
            $videoRedis = $this->redis('video');
            $videoCollectsKey = 'videoCollects_'.$user->id;
            $shortCollectsKey = 'shortCollects_'.$user->id;
            if(isset($params['delete']) && $params['delete']==1){
                $vid = $params['vid'] ?? [];
                if(!empty($vid)){
                    //清除相关redis中的key
                    $videoRedis->zRem($videoCollectsKey,...$vid);
                    $videoRedis->zRem($shortCollectsKey,...$vid);
                }
                return response()->json([
                    'state'=>0,
                    'msg' => '删除成功',
                    'data'=>[
                        "list"=>[],
                        "hasMorePages"=>false,
                    ]
                ]);
            }
            $page = $params['page'] ?? 1;
            if(isset($params['pageSize']) && ($params['pageSize']<$perPage)){
                $perPage = $params['pageSize'];
            }

            $type = $params['type'] ?? 0;
            if($type==0){
                //
                $vidArrAll = $videoRedis->zRevRange($videoCollectsKey,0,-1,true);
            }else{
                $vidArrAll = $videoRedis->zRevRange($shortCollectsKey,0,-1,true);
            }
            $ids = $vidArrAll ? array_keys($vidArrAll) : [];

            if(empty($vidArrAll)){
                Log::info('myCollect==',[$vidArrAll]);
                return response()->json([
                    'state'=>0,
                    'data'=>[
                        "list"=>[],
                        "hasMorePages"=>false,
                    ],
                ]);
            }

//            $ids = [...$videoIds,...$shortVideoIds];

//            $videoList = DB::table('video')->select($this->videoFields)->whereIn('id',$ids)->get()->toArray();
            $videoList = $this->getVideoByIdsForEs($ids,$this->videoFields);

            foreach ($videoList as &$iv){
                $iv = (array)$iv;
                $iv['usage'] = 1;
                $iv['score'] = $vidArrAll[$iv['id']] ?? 0;
                $iv['updated_at'] = date('Y-m-d H:i:s',$iv['score']);
                $restricted = (int)$iv['restricted'];
                $iv['limit'] = 0;
                switch ($restricted){
                    case 2: //金币
                        if(!isset($rights[4])){ //如果没有免费观看金币视频的权益
                            $buy = $this->isBuyShortVideo($iv,$user);
                            !$buy && $iv['limit'] = 2;
                        }
                        break;
                    case 1: //vip会员
                        if ($iv['restricted'] == 1  && (!isset($rights[1]))) {
                            $iv['limit'] = 1;
                        }
                        break;
                }
            }

            $result = [...$videoList];
            unset($videoList);
            $score = array_column($result,'score');
            array_multisort($score,SORT_DESC,$result);
            $offset = ($page-1)*$perPage;
            $pageLists = array_slice($result,$offset,$perPage);
            $hasMorePages = count($result) > $perPage*$page;
            //路径处理
            $res['list'] = $this->handleVideoItems($pageLists,true, true);
            $res['hasMorePages'] = $hasMorePages;
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
        return response()->json([
            'state'=>-1,
            'data'=>'参数错误'
        ]);
    }

    //我购买的视频
    public function purchasedVideos(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
            $validated = Validator::make($params,[
                'type' => 'required|integer',
                'page' => 'required|integer'
            ])->validated();
            $page = $validated['page'];
            $user = $request->user();
            $videoRedis = $this->redis('video');
            $buyVideoKey = 'buyGoldVideo_' . $user->id;
            $ids = $videoRedis->sMembers($buyVideoKey);
            $build = DB::table('video')->where('dev_type',$validated['type'])->whereIn('id',$ids);
            $paginator = $build->simplePaginate(8,$this->upVideoFields,'purchasedVideos',$page);
            $res = [];
            $res['list'] = $paginator->items();
            $res['list'] = $this->handleUpVideoItems($res['list']);
            $res['hasMorePages'] = $paginator->hasMorePages();
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
        return response()->json([
            'state'=>-1,
            'data'=>'参数错误'
        ]);
    }

    //更多
    public function more(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $params = self::parse($request->params);
            $validated = Validator::make($params,[
                'cid' => 'required',
                'type' => 'required|integer', //0长视频 1短视频
                'page' => 'required|integer',
            ])->validated();
            $user = $request->user();
            $tid = $validated['cid'];
            $page = $validated['page'];
            $perPage = 16;
            $offset = ($page-1)*$perPage;

//                $containVidStr = DB::table('topic')->where('id',$tid)->value('contain_vids');

            if(!$tid){
                return response()->json(['state'=>0, 'data'=>['list'=>[], 'hasMorePages'=>false]]);
            }
            $one = DB::table('topic')->where('id',$tid)->first(['name','contain_vids','data_source_id']);

            if(!$one){
                Log::info('SearchNoCat',[$tid]);
                return response()->json(['state'=>0, 'data'=>['list'=>[], 'hasMorePages'=>false]]);
            }
            $res['detail'] = [
                'name' => $one->name,
                'desc' => '人性伦理的性爱体验 惊艳你的眼球',
                'videoNum' => count(explode(',',DB::table('data_source')->where('id',$one->data_source_id)->value('contain_vids'))),
                'views' => $this->generateRandViews(30),
                'collect' => 365,
                'rank' => 18,
            ];
            $ids = explode(',',$one->contain_vids);
            $idParams = [];
            $length = count($ids);
            foreach ($ids as $key => $id) {
                $idParams[] = ['id' => (int)$id, 'score' => $length - $key];
            }

            $searchParams = [
                'index' => 'video_index',
                'body' => [
//                        'track_total_hits' => true,
                    'size' => 500,
                    '_source' => $this->videoFields,
                    'query' => [
                        'function_score' => [
                            'query' => [
                                'bool'=>[
                                    'must' => [
                                        ['terms' => ['id'=>$ids]],
                                    ]
                                ]
                            ],
                            'script_score' => [
                                'script' => [
                                    //'lang' => 'painless',
                                    'params' => [
                                        'scoring' => $idParams
                                    ],
                                    'source' => "for(i in params.scoring) { if(doc['id'].value == i.id ) return i.score; } return 0;"
                                ]
                            ]
                        ]
                    ]
                ],
            ];
            $es = $this->esClient();
            $response = $es->search($searchParams);
            $catVideoList = [];
            $total = 0;
            if(isset($response['hits']) && isset($response['hits']['hits'])){
                $total = $response['hits']['total']['value'];
                foreach ($response['hits']['hits'] as $item) {
                    $catVideoList[] = $item['_source'];
                }
                unset($response);
            }
            if(!empty($catVideoList)){
                foreach ($catVideoList as $it){
                    if($it['dev_type']!=$validated['type']){
                        unset($it);
                    }
                }
                $res['total'] = $total;
                $pageLists = array_slice($catVideoList,$offset,$perPage);
                $hasMorePages = count($catVideoList) > $perPage*$page;
                unset($catVideoList);
                $res['list'] = $this->handleVideoItems($pageLists,false,$user->id);
                unset($pageLists);
                $first = current($res['list']);
                $res['detail']['tag'] = $first['tag_kv'];
                //广告
                $res['list'] = $this->insertAds($res['list'],'more_page',true, $page, $perPage);
                //Log::info('==CatList==',$res['list']);
                $res['hasMorePages'] = $hasMorePages;
            }else{
                $res['list'] = [];
                $res['hasMorePages'] = false;
            }
            if(isset($res['list']) && !empty($res['list'])){
                $domain = env('RESOURCE_DOMAIN2');
                foreach ($res['list'] as &$d){
                    if(!empty($d['ad_list'])){
                        $this->frontFilterAd($d['ad_list'],$domain);
                    }else{
                        $d['ad_list'] = [];
                    }
                }
            }
            return response()->json(['state'=>0, 'data'=>$res??[]]);
        }
        /*try {

        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }*/
        return response()->json([]);
    }

    public function video(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'filter' => 'required|integer',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $cid = $validated['cid'];       //todo
        $filter = $validated['filter']; //todo
        $type = $validated['type'];
        $page = $validated['page'];

        $build = DB::table('video')
            ->where('dev_type',$type)
            ->orderByDesc('id')
//            ->where('uid',$uid)
        ;
        $paginator = $build->simplePaginate(8,$this->upVideoFields,'video',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleUpVideoItems($data['list']);
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //视频榜
    public function rankList(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $cid = $validated['cid'];
        $page = $validated['page'];

        $build = DB::table('video');
        if($cid==0){ //默认
            $build = $build->orderByDesc('likes');
        }
        if($cid==2){ //总榜/热榜
            $build = $build->orderByDesc('views');
        }

        $paginator = $build->simplePaginate(8,['id','name','dev_type','likes','author','auth_avatar','gold','tag_kv','duration','restricted','cover_img','circle','circle_topic','views'],'video',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleUpVideoItems($data['list']);
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function rankingCate(Request $request): \Illuminate\Http\JsonResponse
    {
        if(!$request->user()){
            return response()->json([]);
        }
        $cats = [
            ['id'=>1,'name'=>'抖音榜'],
            ['id'=>2,'name'=>'总榜'],
            ['id'=>3,'name'=>'乱伦榜'],
            ['id'=>4,'name'=>'黑料榜'],
            ['id'=>5,'name'=>'动漫榜'],
            ['id'=>6,'name'=>'原创榜'],
            ['id'=>7,'name'=>'日韩榜']
        ];
        $res = [
            'state' => 0,
            'data' => $cats,
        ];
        return response()->json($res);
    }

    public function buyCollection(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'id' => 'required|integer'
        ])->validated();
        $user= $request->user();
        $collectionId = $validated['id'];
        $needGold = DB::table('circle_collection')->where('id',$collectionId)->value('gold');
        if(!$needGold){
            return response()->json(['state' => -1, 'msg' => '合集不存在', 'data' => []]);
        }
        $newGold = $user->gold - $needGold;
        if($newGold < 0){
            return response()->json(['state' => -1, 'msg' => '金币不足', 'data' => []]);
        }
        $userEffect = User::query()->where('id', '=', $user->id)
            ->where('gold', '>=', $needGold)
            ->update(
                ['gold' => $newGold]
            );
        if (!$userEffect) {
            return response()->json(['state' => -1, 'msg' => '解锁失败', 'data' => []]);
        }
        Cache::forget('cachedUser.'.$user->id);
        $redis = $this->redis('login');
        $key = 'unlockCollectionUser:'.$user->id;
        $redis->sAdd($key,$collectionId);
        $redis->expire($key,90*24*3600);
        return response()->json(['state' => 0, 'msg' => '合集解锁成功', 'data' => []]);
    }

    public function collectionDetail(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'id' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $perPage = 6;
        $page = $validated['page'];
        $offset = ($page-1)*$perPage;
        $user = $request->user();
        $redis = $this->redis('login');
        $key = 'unlockCollectionUser:'.$user->id;
        $isBuy = (int)$redis->sIsMember($key,$validated['id']);
        $one = DB::table('circle_collection')->where('id',$validated['id'])->first(['id','name','desc','vids','cover','views','gold','created_at']);
        if($one && !empty($one->vids)){
            $videoIds = explode(',',$one->vids);
            $body = [
                'size' => 10000,
                '_source' => ['id','name','dev_type','gold','restricted','cover_img','views','created_at'],
                'query' => [
                    'bool'=>[
                        'must' => [
                            ['terms' => ['id'=>$videoIds]],
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
            $videoList = [];
            if(isset($response['hits']) && isset($response['hits']['hits'])){
                //$total = $response['hits']['total']['value'];
                foreach ($response['hits']['hits'] as $item) {
                    $videoList[] = $item['_source'];
                }
                unset($response);
            }
            if(!empty($videoList)){
                $pageLists = array_slice($videoList,$offset,$perPage);
                $hasMorePages = count($videoList) > $perPage*$page;
                unset($videoList);
                $domainSync = self::getDomain(2);
                $_v = date('ymd');
                foreach ($pageLists as &$item){
                    $item['views'] = $this->generateRandViews($item['views']);
                    $item['gold'] = $item['gold'] * 0.01;
                    $item['cover_img'] = $this->transferImgOut($item['cover_img'],$domainSync,$_v);
                    $item['created_at'] = $this->mdate(strtotime($item['created_at']));
                }
                return response()->json(['state' => 0, 'data' => ['list'=>$pageLists,'isBuy'=>$isBuy,'hasMorePages'=>$hasMorePages]]);
            }

        }
        return response()->json(['state' => 0, 'data' => ['list'=>[],'hasMorePages'=>false]]);
    }

    //合集
    public function collection(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $user = $request->user();
        $cid = $validated['cid'];
        $type = $validated['type'];
        $page = $validated['page'];

        $ids = DB::table('circle')->where('id',$cid)->value('collection_ids');
        $res = ['state' => 0,'data'=>['list'=>[],'hasMorePages'=>false]];
        if(!empty($ids)){
            $idArr = explode(',',$ids);
            $build = DB::table('circle_collection')
                ->where('type',$type)
                ->whereIn('id',$idArr)
                ->orderByDesc('id');
            $data['total'] = $build->count();
            $paginator = $build->simplePaginate(8,['id','name','desc','vids','cover','views','gold','created_at'],'collection',$page);
            $hasMorePages = $paginator->hasMorePages();
            $data['list'] = $paginator->items();
            $domain = env('RESOURCE_DOMAIN');
            $_v = date('ymd');
            $redis = $this->redis('login');
            $key = 'unlockCollectionUser:'.$user->id;
            foreach ($data['list'] as $item){
                $item->created_at = $this->mdate(strtotime($item->created_at));
                $item->views = $this->generateRandViews($item->views,50000);
                $item->isBuy = (int)$redis->sIsMember($key,$item->id);
                $item->desc = '合集描述';
                $item->videoNum = count(explode(',',$item->vids)??[]);
                unset($item->vids);
                if(!empty($item->cover)){
                    $cover = json_decode($item->cover,true);
                    $coverImg = [];
                    foreach ($cover as $img){
                        $coverImg[] = $this->transferImgOut($img,$domain,$_v);
                    }
                    $item->cover = $coverImg;
                }
            }

            $data['hasMorePages'] = $hasMorePages;
            $res = [
                'state' => 0,
                'data' => $data,
            ];
        }

        return response()->json($res);
    }

    public function topic(Request $request): \Illuminate\Http\JsonResponse
    {
        //月话题精选
        $hotTopic = DB::table('circle_topic')->orderByDesc('id')->limit(7)->get(['id','uid','name','circle_name','avatar','circle_id','author','interactive as inter','participate']);
        $redis = $this->redis('login');
        foreach ($hotTopic as $item){
            $item->user = $redis->hLen('joinCircle:'.$item->circle_id);
        }
        //分类
        $topicCat = $this->getCircleTopicCat();

        //列表
        if(!empty($topicCat)){
            $firstIndex = key($topicCat);
            $topicList = DB::table('circle_topic')->where('cid',$firstIndex)->limit(7)->get(['id','uid','name','circle_name','avatar','circle_id','interactive as inter']);
            foreach ($topicList as $e){
                $e->user = $redis->hLen('joinCircle:'.$e->circle_id);
            }
        }else{
            $topicList = [];
        }

        $data = [
            [
                'name' => '月话题精选',
                'list' => $hotTopic,
            ],
            [
                'cat' => array_values($topicCat),
                'list' => $topicList,
            ],
        ];
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //来自我关注的圈子 todo 过滤
    public function fromMeFocusCircle(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'page' => 'required|integer'
        ])->validated();
        $page = $validated['page'];
        $uid = $request->user()->id;

        $build = DB::table('circle_discuss'); //todo
        $paginator = $build->simplePaginate(7,$this->discussField,'fromMeFocusCircle',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleDiscussItem($data['list'],$uid);
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function focus(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if(!$user){
            return response()->json([]);
        }
        //todo cache
        $ids = []; //todo
        //热门圈子
        $hotCircle = $this->getHotCircle($user->id);
        //我加入的圈子
        $joinCircle = DB::table('circle')
//            ->whereIn('id',$ids)
            ->orderByDesc('id')
            ->limit(8)->get(['id','uid','name','author','avatar']);

        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        foreach ($joinCircle as $join){
            $join->avatar = $this->transferImgOut($join->avatar,$domainSync,$_v);
        }
        $data = [
            [
                'name' => '热门圈子',
                'list' => $hotCircle,
            ],
            [
                'name' => '我加入的圈子',
                'list' => $joinCircle,
            ]
        ];
        $res = [
            'state' => 0,
            'data' => $data,
        ];

        return response()->json($res);
    }

    //猜你喜欢
    public function popularLikes(Request $request): \Illuminate\Http\JsonResponse
    {
        $page = 0;
        if(isset($request->params)){
            $params = self::parse($request->params);
            $validated = Validator::make($params,[
                'page' => 'required|integer'
            ])->validated();
            $page = $validated['page'];
        }
        $user = $request->user();
        if(!$user){
            return response()->json([]);
        }
        $data = [];
        if($page>0){ //更多
            $paginator = DB::table('video')->inRandomOrder()->simplePaginate(8,$this->upVideoFields,'popularLikes',$page);
            $data['list'] = $paginator->items();
            $data['list'] = $this->handleUpVideoItems($data['list']);
            $data['hasMorePages'] = $paginator->hasMorePages();
        }else{
            $data = DB::table('video')->inRandomOrder()->take(8)->get($this->upVideoFields);
            $data = $this->handleUpVideoItems($data);
        }
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    //热门圈子
    public function popularCircle(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if(!$user){
            return response()->json([]);
        }
        $hotCircle = $this->getHotCircle($user->id);
        $res = [
            'state' => 0,
            'data' => $hotCircle,
        ];
        return response()->json($res);
    }

    public function actionEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'id' => 'required|integer',
            'hit' => 'required|integer', //1点击 0 取消点击
            'action' => 'required|integer', //1加入圈子 2关注帖子 3喜欢帖子 4关注话题 5关注用户
        ])->validated();
        $id = $validated['id'];
        $hit = $validated['hit'];
        $action = $validated['action'];
        $user = $request->user();
        $redis = $this->redis('login');
        switch($action){
            case 1:
                $key = 'joinCircle:'.$id;
                if($hit==1){
                    $redis->hSet($key,$user->id,json_encode([
                        'avatar'=>$user->avatar,
                        'nickname'=>$user->nickname,
                        'at_time'=>time(),
                        'discuss_num'=>0, //todo
                    ],JSON_UNESCAPED_UNICODE));
                }else{
                    $redis->hDel($key,$user->id);
                }
                break;
            case 2:
                $key = 'discussFocusUser:'.$user->id;
                if($hit==1){
                    $redis->sAdd($key,$id);
                }else{
                    $redis->sRem($key,$id);
                }
                break;
            case 3:
                $key = 'discussLikesUser:'.$user->id;
                if($hit==1){
                    $redis->sAdd($key,$id);
                }else{
                    $redis->sRem($key,$id);
                }
                break;
            case 4:
                $key = 'topicFocusUser:'.$user->id;
                if($hit==1){
                    $redis->sAdd($key,$id);
                }else{
                    $redis->sRem($key,$id);
                }
                break;
            case 5:
                $key = 'upMasterFocusUser:'.$id;
                if($hit==1){
                    $redis->hSet($key,$user->id,json_encode([
                        'avatar'=>$user->avatar,
                        'nickname'=>$user->nickname,
                        'at_time'=>time(),
                        'discuss_num'=>0, //todo
                    ],JSON_UNESCAPED_UNICODE));
                }else{
                    $redis->hDel($key,$user->id);
                }
                break;
        }
        return response()->json([
            'state' => 0,
            'msg' => '成功',
            'data' => [],
        ]);
    }


}