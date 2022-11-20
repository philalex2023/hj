<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\User;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommOperationController extends Controller
{
    use PHPRedisTrait,ApiParamsTrait;

    /**
     * 关注
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function foucs(Request $request)
    {
        /*if (isset($request->params)) {
            $params = self::parse($request->params);
            //Log::info('===Foucs===',[$params]);
            Validator::make($params, [
                'to_user_id' => 'required|integer',
                "focus" => 'nullable' //类型 :1-关注,0-取消收藏
            ])->validate();
            $toUserId = $params['to_user_id'];
            $date = date('Y-m-d H:i:s', time());
            $uid = $request->user()->id;
            $insertData = [
                'user_id' => $uid,
                'to_user_id' => $toUserId,
                'created_at' => $date,
                'updated_at' => $date,
            ];
            DB::beginTransaction();
            try {   //先偿试队列
                $focus = $params['focus'];
                $one = DB::table('community_focus')->where('user_id',$uid)->where('to_user_id',$toUserId)->first();
                if (($focus == 0) && $one) {
                    DB::table('community_focus')->where('user_id',$uid)->where('to_user_id',$toUserId)->delete();
                    User::where('id', $toUserId)->where('fans', '>', 0)->decrement('fans');
                    User::where('id', $uid)->where('attention', '>', 0)->decrement('attention');
                }
                elseif (($focus == 1) && (!$one)) {
                    DB::table('community_focus')->insert($insertData);
                    User::where('id', $toUserId)->increment('fans');
                    User::where('id', $uid)->increment('attention');
                }
                DB::commit();
                return response()->json([
                    'state' => 0,
                    'msg' => '操作成功'
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('operationFocusUser===' . $e->getMessage());
            }
            return response()->json([
                'state' => -1,
                'msg' => '操作失败'
            ]);

        }*/
        return [];
    }


    /**
     * 点赞
     * @param Request $request
     */
    public function like(Request $request): \Illuminate\Http\JsonResponse
    {
        if (isset($request->params)) {
            $params = self::parse($request->params);
            //Log::info('===LIKE===',[$params]);
            Validator::make($params, [
                'bbs_id' => 'required|integer',
                'like' => 'required|integer'
            ])->validate();
            $bbsId = $params['bbs_id'];
            $uid = $request->user()->id;
            $is_love = $params['like'];

            $key = 'bbsLike_'.$uid;
//            $key = 'bbsLike_'.$bbsId;
            $videoRedis = $this->redis('video');
            if($is_love==1){
                $videoRedis->sAdd($key,$bbsId);
                $videoRedis->expire($key,7*24*3600);
                DB::table('community_bbs')->where('id',$bbsId)->increment('likes');
            }else{
                $videoRedis->sRem($key,$bbsId);
                DB::table('community_bbs')->where('id',$bbsId)->decrement('likes');
            }

            return response()->json([
                'state' => 0,
                'msg' => '操作成功'
            ]);
        }
        return response()->json([
            'state' => -1,
            'msg' => 'fuck'
        ]);
    }

}