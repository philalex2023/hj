<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\Models\CommChat;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommChatController extends Controller
{
    use PHPRedisTrait, ApiParamsTrait;
    public function post(Request $request)
    {
        return response()->json([
            'state' => -1,
            'msg' => '暂未开放'
        ]);
        /*if (isset($request->params)) {
            $params = self::parse($request->params);
            Validator::make($params, [
                'to_user_id' => 'required|integer',
                'type' => 'nullable',
                'content' => 'required',
            ])->validate();
            $vid = $params['to_user_id'];
            $type = $params['type']??1;
            $content = $params['content'];
            $user = $request->user();
            $uid = $user->id;
            $toUser = CacheUser::user($vid);
            $insertData = [
                'to_user_id' => $vid,
                'nickname' => $user->nickname,
                'to_user_nickname' => $toUser?->nickname,
                'avatar' => $user->avatar,
                'to_user_avatar' => $toUser?->avatar,
                'user_id' => $uid,
                'type' => $type,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            DB::beginTransaction();
            try {   //先偿试队列
                $commentId = DB::table('community_chat')->insertGetId($insertData);
                DB::commit();
                // 创建关系
                $relationName = "relation_chat";
                // 处理缓存结构
                $min = min($vid,$uid);
                $max = max($uid,$vid);
                $existKey = "chat_pair_{$min}_{$max}";
                $exitPair = $this->redis()->get($existKey);
                if ($exitPair) {
                    $this->redis()->sRem($relationName,$exitPair);
                }
                $this->redis()->set($existKey,$commentId);

                $this->redis()->sAdd($relationName,$commentId);

                if ($commentId > 0) {
                    //存入未读用户
                    $unReadUserKey = 'status_me_unread_' . $vid;
                    $this->redis()->sAdd($unReadUserKey,$uid);
                    //消息红点提示=========================
                    $keyMe = "status_me_message_".$vid;
                    $this->redis()->set($keyMe,1);
                    //===================================
                    return response()->json([
                        'state' => 0,
                        'msg' => '发送成功'
                    ]);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('bbsChat===' . $e->getMessage());
            }
            return response()->json([
                'state' => -1,
                'msg' => '发送失败'
            ]);

        }
        return [];*/
    }

    /**
     * 消息列表
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function lists(Request $request)
    {
        return response()->json([
            'state' => 0,
            'data' => ['list'=>[],'hasMorePages'=>false]
        ]);
    }
}