<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\AdminGoldLog;
use App\Models\User;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommRewardController extends Controller
{
    use ApiParamsTrait;
    /**
     * 关注
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Request $request)
    {
        if (isset($request->params)) {
            $params = self::parse($request->params);
            Validator::make($params, [
                'to_user_id' => 'required|integer',
                'money' => 'required|string',
                'bbs_id' => 'required|integer',
                'note' => 'nullable'
            ])->validate();
            $toUserId = $params['to_user_id'];
            $money = $params['money'];
            $bbsId = $params['bbs_id'];
            /*$note = $params['note']??'';
            $insertData = [
                'user_id' => $request->user()->id,
                'to_user_id' => $toUserId,
                'bbs_id' => $bbsId,
                'note' => $note,
                'money' => $money,
            ];*/
            $user = $request->user();
            DB::beginTransaction();
            try {   //先偿试队列
                $originGold = $user->gold;
                if($originGold < $money){
                    return response()->json([
                        'state' => -1,
                        'msg' => '余额不足'
                    ]);
                }
                $date = date('Y-m-d H:i:s');
                $insertData = [
                    'user_id' => $user->id,
                    'bbs_id' => $bbsId,
                    'to_user_id' => $toUserId,
                    'nickname' => $user->nickname,
                    'to_user_nickname' => CacheUser::user($toUserId)?->nickname,
                    'money' => $money,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
                DB::table('community_reward')->insert($insertData);
                DB::table('community_bbs')->where('id',$bbsId)->increment('rewards',$money);
                User::where('id', $toUserId)->increment('gold', $money);
                User::where('id', $user->id)->decrement('gold', $money);
                Cache::forget('cachedUser.'.$toUserId);
                Cache::forget('cachedUser.'.$user->id);
                DB::commit();
                //$now = date('Y-m-d H:i:s');
                // 已方记录
                /*AdminGoldLog::query()->create([
                    'uid' => $request->user()->id,
                    'goods_id' => $bbsId,
                    'cash' => $money,
                    'goods_info' => json_encode($money),
                    'before_cash' => $originGold,
                    'use_type' => 1,
                    'device_system' => $request->user()->device_system,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                // 被打赏方记录
                AdminGoldLog::query()->create([
                    'uid' => $toUserId,
                    'goods_id' => $bbsId,
                    'cash' => $money,
                    'goods_info' => json_encode($money),
                    'before_cash' => User::find($toUserId)->gold,
                    'use_type' => 1,
                    'device_system' => $request->user()->device_system,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);*/

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

        }
        return [];
    }
}