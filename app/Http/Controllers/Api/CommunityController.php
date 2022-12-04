<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommunityTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommunityController extends Controller
{
    use ApiParamsTrait,CommunityTrait,PHPRedisTrait;

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

    public function square(Request $request): \Illuminate\Http\JsonResponse
    {
        if(!$request->user()){
            return response()->json([]);
        }
        //todo cache
        //热门话题
        $hotTopic = DB::table('circle_topic')->orderByDesc('id')->limit(12)->get(['id','name','interactive as inter']);
        //热门圈子
        $hotCircle = DB::table('circle')->orderByDesc('many_friends')->limit(8)->get(['id','name','background as imgUrl','many_friends as user']);
        //圈子精选
        $featuredCircle = DB::table('circle')->orderByDesc('introduction')->limit(8)->get(['id','name','avatar','introduction as des','background as imgUrl','many_friends as user']);

        $data = [
            [
                'name' => '热门话题',
                'list' => $hotTopic,
            ],
            [
                'name' => '热门圈子',
                'list' => $hotCircle,
            ],
            [
                'name' => '圈子精选',
                'list' => $featuredCircle,
            ],
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
        $field = ['id','name','desc','circle_name','avatar','circle_avatar','circle_friends as user','author','interactive as inter','participate'];
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

        $field = ['id','content','circle_name','avatar','author','tag_kv','scan','comments','likes','created_at'];
        $build = DB::table('circle_discuss')->where('uid',$uid);
        /*if($filter==1){

        }else{

        }*/
        $paginator = $build->simplePaginate(7,$field,'topicInfo',$page);
        $hasMorePages = $paginator->hasMorePages();
        $data['list'] = $paginator->items();
        foreach ($data['list'] as $item){
            $item->tag_kv = json_decode($item->tag_kv,true) ?? [];
            $item->created_at = $this->mdate(strtotime($item->created_at));
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
        $hotTopic = DB::table('circle_topic')->orderByDesc('id')->limit(7)->get(['id','name','circle_name','avatar','circle_friends as user','author','interactive as inter','participate']);
        //分类
        $topicCat = $this->getCircleTopicCat();

        //列表
        if(!empty($topicCat)){
            $firstIndex = key($topicCat);
            $topicList = DB::table('circle_topic')->where('cid',$firstIndex)->limit(7)->get(['id','name','circle_name','avatar','circle_friends as user','interactive as inter']);
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

    public function focus(Request $request): \Illuminate\Http\JsonResponse
    {
        /*if(!$request->user()){
            return response()->json([]);
        }*/
        //todo cache
        $ids = []; //todo
        //热门圈子
        $domain = env('RESOURCE_DOMAIN');
        $hotCircle = DB::table('circle')->orderByDesc('many_friends')->limit(8)->get(['id','name','author','background as imgUrl','many_friends as user']);
        foreach ($hotCircle as $hot){
            $hot->imgUrl = $domain.$hot->imgUrl;
        }
        //我加入的圈子
        $joinCircle = DB::table('circle')
//            ->whereIn('id',$ids)
            ->orderByDesc('id')
            ->limit(8)->get(['id','name','author','background as imgUrl']);
        foreach ($joinCircle as $join){
            $join->imgUrl = $domain.$join->imgUrl;
        }
        //来自我关注的圈子

        $fromMeFocusCircle = DB::table('circle_topic')
//            ->whereIn('id',$ids)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id','circle_id','name','circle_name','avatar','desc','author','scan','comments','likes','album','created_at','tag_kv']);

        foreach ($fromMeFocusCircle as $item){
            $item->tag_kv = json_decode($item->tag_kv,true) ?? [];
            $item->created_at = $this->mdate(strtotime($item->created_at));
            if(!empty($item->album) && $item->album!='null'){
                $item->album = json_decode($item->album,true) ?? [];
                foreach ($item->album as &$album){
                    $album = $domain . $album;
                }
            }
            if(!empty($item->avatar)){
                $item->avatar = $domain . $item->avatar;
            }
        }

        $data = [
            [
                'name' => '热门圈子',
                'list' => $hotCircle,
            ],
            [
                'name' => '我加入的圈子',
                'list' => $joinCircle,
            ],
            [
                'name' => '来自我关注的圈子',
                'list' => $fromMeFocusCircle,
            ],
        ];
        $res = [
            'state' => 0,
            'data' => $data,
        ];

        return response()->json($res);
    }

    public function circleFeatured ()
    {
        //圈子精选
        $featuredCircle = DB::table('circle')->limit(8)->get(['id','name','avatar','introduction','background','many_friends']);
    }

    public function cat(): \Illuminate\Http\JsonResponse
    {
        $data = $this->getCommunityCat();
        return response()->json($data);
    }

    public function HotCircleTopic(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();

        return response()->json([]);
    }

}