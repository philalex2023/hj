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

        if (isset($request->params)) {
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
        return [];
    }

    /**
     * 消息列表
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function lists(Request $request)
    {
        if (isset($request->params)) {
            $params = self::parse($request->params);
            //Log::info('===CommChat===',[$params]);
            Validator::make($params, [
                'to_user_id' => 'nullable',
                'start_id' => 'nullable',
                'start_time' => 'nullable',
                'sort' => 'nullable',
                'page' => 'required|integer',
            ])->validate();
            $toUserId = $params['to_user_id'] ?? 0;
            $startId = $params['start_id'] ?? 0;
            $startTime = $params['start_time'] ?? 0;
            $page = $params['page'] ?? 1;
            $sort = $params['sort'] ?? 1;
            $perPage = 16;
            $queryBuild = CommChat::query()->select('id','user_id','to_user_id','content','created_at','nickname as to_user_nickname','avatar', 'type');

            $uid = $request->user()->id;
            if ($toUserId) {
                $queryBuild->where(function($sql) use ($uid,$toUserId){
                    $sql->whereIn('user_id',[$uid,$toUserId]);
                    $sql->whereIn('to_user_id',[$uid,$toUserId]);
                });
            }
            if ($sort == 1) {
                $queryBuild->orderBy('id','desc');
                if ($startTime) {
                    $queryBuild->where('community_chat.created_at', '<', $startTime);
                }
                if ($startId) {
                    $queryBuild->where('community_chat.id', '<', $startId);
                }
            } else {
                $queryBuild->orderBy('id');
                if ($startTime) {
                    $queryBuild->where('community_chat.created_at', '>', $startTime);
                }
                if ($startId) {
                    $queryBuild->where('community_chat.id', '>', $startId);
                }
            }
            $paginator = $queryBuild->simplePaginate($perPage, '*', 'commentLists', $page);
            $items = $paginator->items();
            $res['list'] = $items;
            $res['hasMorePages'] = $paginator->hasMorePages();
            //清除key========
            //Log::info('===CommChat-items===',[$items,$toUserId,$uid]);
            if(!empty($items) && ($items[0]->to_user_id == $uid)){ //别人发给我的
                $unReadUserKey = 'status_me_unread_' . $uid;
                $this->redis()->sRem($unReadUserKey,$items[0]->user_id);
                if(empty($this->redis()->sMembers($unReadUserKey))){
                    $keyMe = "status_me_message_".$uid;
                    $this->redis()->del($keyMe);
                }
            }
            //===========================
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        }
        return [];
    }
}