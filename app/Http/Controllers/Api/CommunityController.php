<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommunityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommunityController extends Controller
{
    use ApiParamsTrait,CommunityTrait;

    public function square(Request $request): \Illuminate\Http\JsonResponse
    {
        /*$params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'page' => 'required|integer',
        ])->validated();*/
        //热门话题
        $hotTopic = DB::table('circle_topic')->orderByDesc('id')->limit(12)->get(['id','name']);
        //热门圈子
        $hotCircle = DB::table('circle')->limit(8)->get(['id','name','background','many_friends']);
        //圈子精选
        $featuredCircle = DB::table('circle')->limit(8)->get(['id','name','avatar','introduction','background','many_friends']);
        $data = [];
        $data['list'] = [
            [
                'name' => '热门话题',
                'data_list' => $hotTopic,
            ],
            [
                'name' => '热门圈子',
                'data_list' => $hotCircle,
            ],
            [
                'name' => '圈子精选',
                'data_list' => $featuredCircle,
            ],
        ];
        $res = [
            'state' => 0,
            'hasMorePages' => false,
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