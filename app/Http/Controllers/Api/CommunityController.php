<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommunityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommunityController extends Controller
{
    use ApiParamsTrait,CommunityTrait;

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

    function  mdate($time = NULL): string
    {
        $time  =  $time === NULL ||  $time  > time() ? time() :  intval ( $time );
        $t  = time() -  $time ;  //时间差 （秒）
        if($t == 0){
            $txt = '刚刚';
        } elseif ($t < 60){
            $txt = $t . '秒前';
        } elseif ($t < 60 * 60){
            $txt = floor($t / 60) . '分钟前';
        } elseif ($t < 60 * 60 * 24){
            $txt = floor($t / (60 * 60)) . '小时前';
        } elseif (60 * 60 * 24 * 7){
            $txt = floor($t / (60 * 60 * 24)) . '天前';
        } elseif (60 * 60 * 24 * 30){
            $txt = floor($t / (60 * 60 * 24 * 7)) . '周前';
        } elseif (60 * 60 * 24 * 365){
            $txt = floor($t / (60 * 60 * 24 * 30)) . '月前';
        } else {
            $txt = date('Y年m月d日', $time);
        }
        return $txt;
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