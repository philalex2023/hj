<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommunityTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommunityController extends Controller
{
    use ApiParamsTrait,CommunityTrait,PHPRedisTrait,VideoTrait;

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
        $upMasterId = $this->getUpMasterId($request->user()->id);
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

    public function getHotCircle(): \Illuminate\Support\Collection
    {
        //热门圈子
        $hotCircle = DB::table('circle')->orderByDesc('many_friends')->limit(8)->get(['id','uid','name','avatar','many_friends as user']);
        //封面图处理
        $domainSync = self::getDomain(2);
        $_v = date('Ymd');
        foreach ($hotCircle as $h){
            $h->isJoin = 0; //todo
            $h->avatar = $this->transferImgOut($h->avatar,$domainSync,$_v);
        }
        return $hotCircle;
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
        if(!$request->user()){
            return response()->json([]);
        }
        //todo cache
        //热门话题
        $hotTopic = DB::table('circle_topic')->orderByDesc('id')->limit(12)->get(['id','uid','name','interactive as inter']);
        //热门圈子
        $hotCircle = $this->getHotCircle();
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

        $field = ['id','vid','content','circle_name','avatar','author','tag_kv','scan','comments','likes','created_at'];
        $build = DB::table('circle_discuss')->where('uid',$uid);
        /*if($filter==1){

        }else{

        }*/
        $paginator = $build->simplePaginate(7,$field,'topicInfo',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $domain = env('RESOURCE_DOMAIN');
        foreach ($data['list'] as $item){
            $item->tag_kv = json_decode($item->tag_kv,true) ?? [];
            $item->created_at = $this->mdate(strtotime($item->created_at));
            $item->avatar = $domain.$item->avatar;
            if($item->vid>0){
                $one = DB::table('video')->where('id',$item->vid)->first(['id','name','views']);
                if(!empty($one)){
//                    $video = $this->handleVideoItems([$one])[0];
                    $one->score = '9.5';
                    $one->views = $one->views > 0 ? $this->generateRandViews($one->views) : $this->generateRandViews(rand(500, 99999));
                    $item->video = $one;
                }else{
                    $item->video = [];
                }
            }
        }
        $data['hasMorePages'] = $hasMorePages;
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

    public function collection(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'uid' => 'required|integer',
            'type' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
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
        foreach ($data['list'] as $item){
            $item->created_at = $this->mdate(strtotime($item->created_at));
            $item->views = $this->generateRandViews($item->views,50000);
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
                'cat' => $topicCat,
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

        $field = ['id','vid','uid','circle_id','content','circle_name','avatar','author','tag_kv','scan','comments','likes','created_at'];
        $build = DB::table('circle_discuss'); //todo
        $paginator = $build->simplePaginate(7,$field,'topicInfo',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        $domain = env('RESOURCE_DOMAIN');
        $domainSync = self::getDomain(2);
        $_v = date('ymd');
        foreach ($data['list'] as $item){
            $item->tag_kv = json_decode($item->tag_kv,true) ?? [];
            $item->created_at = $this->mdate(strtotime($item->created_at));
            $item->avatar = $domain.$item->avatar;
            $item->isFocus = 0; //todo
            $item->isLike = 0; //todo
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
        $data['hasMorePages'] = $hasMorePages;
        $res = [
            'state' => 0,
            'data' => $data,
        ];
        return response()->json($res);
    }

    public function focus(Request $request): \Illuminate\Http\JsonResponse
    {
        /*if(!$request->user()){
            return response()->json([]);
        }*/
        //todo cache
        $ids = []; //todo
        //热门圈子
        $hotCircle = $this->getHotCircle();
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

    //加入/退出圈子
    public function joinCircle(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'id' => 'required|integer',
            'join' => 'required|integer', //
            'page' => 'required|integer'
        ])->validated();
        $id = $validated['id'];
        $focus = $validated['join'];
        $user = $request->user();
        $redis = $this->redis('login');
        $key = 'circleJoinUser:'.$user->id;
        if($focus==1){
            $redis->sAdd($key,$id);
            $redis->expireAt($key,time()+30*24*3600);
        }else{
            $redis->sRem($key,$id);
        }

        return response()->json([
            'state' => 0,
            'msg' => '成功',
            'data' => [],
        ]);
    }

    //关注/取消关注帖子
    public function focusDiscuss(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'id' => 'required|integer',
            'focus' => 'required|integer', //
            'page' => 'required|integer'
        ])->validated();
        $id = $validated['id'];
        $focus = $validated['focus'];
        $user = $request->user();
        $redis = $this->redis('login');
        $key = 'discussFocusUser:'.$user->id;
        if($focus==1){
            $redis->sAdd($key,$id);
            $redis->expireAt($key,time()+30*24*3600);
        }else{
            $redis->sRem($key,$id);
        }

        return response()->json([
            'state' => 0,
            'msg' => '成功',
            'data' => [],
        ]);
    }

}