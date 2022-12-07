<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommunityTrait;
use App\TraitClass\EsTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommunityController extends Controller
{
    use ApiParamsTrait,CommunityTrait,PHPRedisTrait,VideoTrait,EsTrait;

    public array $discussField = ['id','vid','uid','circle_id','circle_topic_id','content','circle_name','circle_topic_name','avatar','album','author','tag_kv','scan','comments','likes','created_at'];

    //创建话题
    public function addCircleTopic(Request $request)
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
    public function addCircle(Request $request)
    {
        $upMasterId = $this->getUpMasterId($request->user()->id);
        if(!$upMasterId){
            return response()->json(['state' => -1, 'msg' => "请联系客服开通",'data'=>[]]);
        }else{
            // todo
            return response()->json(['state' => -1, 'msg' => "创建成功",'data'=>[]]);
        }
    }

    //我的数据
    public function myData(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
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
        $hotCircle = DB::table('circle')->orderByDesc('many_friends')->limit(8)->get(['id','uid','name','avatar','many_friends as user']);
        //封面图处理
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        foreach ($hotCircle as $h){
            $h->isJoin = $redis->sIsMember('circleJoinUser:'.$uid,$h->id) ? 1 : 0;
            $h->avatar = $this->transferImgOut($h->avatar,$domainSync,$_v);
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
        $field = ['id','uid','name','participate','avatar','introduction as des','many_friends as user'];
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
            $f->isJoin = $redis->sIsMember('circleJoinUser:'.$uid,$f->id) ? 1 : 0;
        }
        $data['list'] = $featuredCircle;
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
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

        $field = ['id','uid','cname','name','scan','avatar','introduction as des','background as imgUrl','many_friends as user'];
        $paginator= DB::table('circle')->simplePaginate(8,$field,'circleFeatured',$page);
        $hasMorePages = $paginator->hasMorePages();
        $featuredCircle = $paginator->items();
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        foreach ($featuredCircle as $f){
            //$f->user_avatar = [];//圈友头像（三个，不足三个有多少给多少）todo
            $f->user_avatar[] = '/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->user_avatar[] = '/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->user_avatar[] = '/upload/encImg/'.rand(1,43).'.htm?ext=png';
            $f->avatar = $this->transferImgOut($f->avatar,$domainSync,$_v);
            $f->imgUrl = $this->transferImgOut($f->imgUrl,$domainSync,$_v);
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

    public function topicInfo(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'tid' => 'required|integer',
        ])->validated();
        $tid = $validated['tid'];

        //月话题精选
        $field = ['id','uid','name','desc','circle_name','avatar','circle_avatar','circle_friends as user','author','interactive as inter','participate'];
        $one = DB::table('circle_topic')->find($tid,$field);
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
            $item->isJoin = $redis->sIsMember('circleJoinUser:'.$uid,$item->circle_id) ? 1 : 0;
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
            'uid' => 'required|integer',
            'filter' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $uid = $validated['uid'];
        $filter = $validated['filter']; //1按最多播放、2按最新 todo
        $page = $validated['page'];
        $uid = $request->user()->id;

        $build = DB::table('circle_discuss')
//            ->where('uid',$uid)
        ;
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

    public function video(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'filter' => 'required|integer',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $uid = $validated['uid'];       //todo
        $filter = $validated['filter']; //todo
        $type = $validated['type'];
        $page = $validated['page'];

        $build = DB::table('video')
            ->where('dev_type',$type)
            ->orderByDesc('id')
//            ->where('uid',$uid)
        ;
        $paginator = $build->simplePaginate(8,$this->videoFields,'video',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $data['list'] = $this->handleVideoItems($data['list']);
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
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
        $vids = DB::table('circle_collection')->where('id',$validated['id'])->value('vids');
        if(!empty($vids)){
            $videoIds = explode(',',$vids);
            $body = [
                'size' => 1000,
                '_source' => ['id','name','dev_type','gold','restricted','cover_img','views','updated_at'],
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
                    $item['updated_at'] = $this->mdate($item['updated_at']);
                }
                return response()->json(['state' => 0, 'data' => ['list'=>$pageLists,'isBuy'=>$isBuy,'hasMorePages'=>$hasMorePages]]);
            }

        }
        return response()->json(['state' => 0, 'data' => ['list'=>[],'hasMorePages'=>false]]);
    }

    public function collection(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        $user = $request->user();
        $uid = $validated['uid'];       //todo
        $type = $validated['type'];
        $page = $validated['page'];

        $build = DB::table('circle_collection')
            ->where('type',$type)
            ->orderByDesc('id')
//            ->where('uid',$uid)
        ;
        $paginator = $build->simplePaginate(8,['id','name','cover','views','gold','created_at'],'collection',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $domain = env('RESOURCE_DOMAIN');
        $redis = $this->redis('login');
        $key = 'unlockCollectionUser:'.$user->id;
        foreach ($data['list'] as $item){
            $item->created_at = $this->mdate(strtotime($item->created_at));
            $item->views = $this->generateRandViews($item->views,50000);
            $item->isBuy = (int)$redis->sIsMember($key,$item->id);
            !empty($item->cover) && $item->cover = $domain.$item->cover;
        }

        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function topic(Request $request): \Illuminate\Http\JsonResponse
    {
        //月话题精选
        $hotTopic = DB::table('circle_topic')->orderByDesc('id')->limit(7)->get(['id','uid','name','circle_name','avatar','circle_friends as user','author','interactive as inter','participate']);
        //分类
        $topicCat = $this->getCircleTopicCat();

        //列表
        if(!empty($topicCat)){
            $firstIndex = key($topicCat);
            $topicList = DB::table('circle_topic')->where('cid',$firstIndex)->limit(7)->get(['id','uid','name','circle_name','avatar','circle_friends as user','interactive as inter']);
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

    public function actionEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'id' => 'required|integer',
            'hit' => 'required|integer', //1点击 0 取消点击
            'action' => 'required|integer', //1加入圈子 2关注帖子 3喜欢帖子
        ])->validated();
        $id = $validated['id'];
        $hit = $validated['hit'];
        $action = $validated['action'];
        $user = $request->user();

        switch($action){
            case 1:
                $key = 'circleJoinUser:'.$user->id;
                break;
            case 2:
                $key = 'discussFocusUser:'.$user->id;
                break;
            case 3:
                $key = 'discussLikesUser:'.$user->id;
                break;
        }
        if(isset($key)){
            $redis = $this->redis('login');
            if($hit==1){
                $redis->sAdd($key,$id);
//                $redis->expireAt($key,time()+30*24*3600);
                if($action==1){
                    DB::table('circle')->where('id',$id)->increment('participate');
                }
            }else{
                if($action==1){
                    DB::table('circle')->where('id',$id)->decrement('participate');
                }
                $redis->sRem($key,$id);
            }
        }
        return response()->json([
            'state' => 0,
            'msg' => '成功',
            'data' => [],
        ]);
    }


}