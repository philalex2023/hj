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
//        $y  =  date ( 'Y' ,  $time )- date ( 'Y' , time()); //是否跨年
        return match ($t) {
            $t == 0 => '刚刚',
            $t < 60 => $t . '秒前',
            $t < 60 * 60 => floor($t / 60) . '分钟前',
            $t < 60 * 60 * 24 => floor($t / (60 * 60)) . '小时前',
            $t < 60 * 60 * 24 * 7 => floor($t / (60 * 60 * 24)) . '天前',
            $t < 60 * 60 * 24 * 30 => floor($t / (60 * 60 * 24 * 7)) . '周前',
            $t < 60 * 60 * 24 * 365 => floor($t / (60 * 60 * 24 * 30)) . '月前',
//            $t < 60 * 60 * 24 * 3 => floor($time / (60 * 60 * 24)) == 1 ? '昨天 ' . date('H:i', $time) : '前天 ' . date('H:i', $time),
//            $t < 60 * 60 * 24 * 30 => date('m月d日 H:i', $time),
//            $t < 60 * 60 * 24 * 365 && $y == 0 => date('m月d日', $time),
            default => date('Y年m月d日', $time),
        };
    }

    public function focus(Request $request): \Illuminate\Http\JsonResponse
    {
        /*if(!$request->user()){
            return response()->json([]);
        }*/
        //todo cache
        $ids = []; //todo
        //热门圈子
        $hotCircle = DB::table('circle')->orderByDesc('many_friends')->limit(8)->get(['id','name','background as imgUrl','many_friends as user']);
        //我加入的圈子
        $joinCircle = DB::table('circle')
//            ->whereIn('id',$ids)
            ->orderByDesc('id')
            ->limit(8)->get(['id','name','background as imgUrl']);
        //来自我关注的圈子

        $fromMeFocusCircle = DB::table('circle_topic')
//            ->whereIn('id',$ids)
            ->orderByDesc('created_at')
            ->limit(8)->get([
            'id','name','circle_name','avatar','desc','author','scan','comments','likes','album','created_at','tag_kv'
        ]);
        foreach ($fromMeFocusCircle as &$item){
            Log::info('TestFocus',[$item->tag_kv]);

            $item['tag_kv'] = json_decode($item->tag_kv,true) ?? [];
            $item['created_at'] = $this->mdate(strtotime($item->created_at));
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