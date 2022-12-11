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

    public array $upVideoFields = ['id','name','dev_type','gold','tag_kv','duration','restricted','cover_img','circle','circle_topic','views'];

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
        $hotCircle = DB::table('circle')
            ->orderByDesc('id') //todo
            ->limit(8)->get(['id','uid','name','avatar']);
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
            foreach ($uidWorkNum as $item){
                if($item['key']>0){
                    $one = DB::table('video')->where('uid',$item['key'])->first(['uid','author','auth_avatar']);
//                    Log::info('TEST',[$item['key'],$one]);
                    $dataList[] = [
                        'uid'=>$item['key'],
                        'isFocus'=>$redis->sIsMember('topicFocusUser:'.$user->id,$item['key']) ? 1 : 0,
                        'work_num'=>$item['doc_count'],
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
        $redis = $this->redis('login');
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
            ->simplePaginate(8,$field,'circleFeatured',$page);
        $hasMorePages = $paginator->hasMorePages();
        $featuredCircle = $paginator->items();
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        $redis = $this->redis('login');
        foreach ($featuredCircle as $f){
            $f->avatar = $this->transferImgOut($f->avatar,$domainSync,$_v);
            $f->isJoin = $redis->hExists('joinCircle:'.$f->id,$uid) ? 1 : 0;
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
                $arr['avatar'] = $domainSync.$arr['avatar'];
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

    public function handleUpVideoItems($dataList)
    {
        $domainSync = self::getDomain(2);
        foreach ($dataList as $item) {
            $item->views = $this->generateRandViews($item->views);
            $item->tag_kv = json_decode($item->tag_kv,true)??[];
            $item->gold = $item->gold * 0.01;
            $item->cover_img = $this->transferImgOut($item->cover_img,$domainSync);
            if(isset($item->likes)){
                $item->likes = $this->generateRandViews($item->likes,5000);
            }
            if(isset($item->auth_avatar)){
                $item->auth_avatar = $domainSync.$item->auth_avatar;
            }
            if(!empty($item->circle_topic)){
                $item->circle_topic = json_decode($item->circle_topic,true);
            }
            if(!empty($item->circle)){
                $item->circle = json_decode($item->circle,true);
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
        $vids = DB::table('circle_collection')->where('id',$validated['id'])->value('vids');
        if(!empty($vids)){
            $videoIds = explode(',',$vids);
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

        switch($action){
            case 1:
                //$key = 'circleJoinUser:'.$user->id;
                break;
            case 2:
                $key = 'discussFocusUser:'.$user->id;
                break;
            case 3:
                $key = 'discussLikesUser:'.$user->id;
                break;
            case 4:
                $key = 'topicFocusUser:'.$user->id;
                break;
            case 5:
                $key = 'upMasterFocusUser:'.$user->id;
                break;
        }
        if(isset($key)){
            $redis = $this->redis('login');
            if($hit==1){ //命中
//                $redis->expireAt($key,time()+30*24*3600);
                if($action==1){
//                    $redis->sAdd('participateCircle:'.$id,$user->id);
                    $redis->hSet('joinCircle:'.$id,$user->id,json_encode([
                        'avatar'=>$user->avatar,
                        'nickname'=>$user->nickname,
                        'at_time'=>time(),
                        'discuss_num'=>0, //todo
                    ],JSON_UNESCAPED_UNICODE));
                }else{
                    $redis->sAdd($key,$id);
                }
            }else{ //取消
                if($action==1){
                    $redis->hDel('joinCircle:'.$id,$user->id);
                }else{
                    $redis->sRem($key,$id);
                }
            }
        }
        return response()->json([
            'state' => 0,
            'msg' => '成功',
            'data' => [],
        ]);
    }


}