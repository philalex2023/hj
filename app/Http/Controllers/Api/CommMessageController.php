<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommChat;
use App\Models\User;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommMessageController extends Controller
{
    use PHPRedisTrait,ApiParamsTrait;
    /**
     * 列表
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function lists(Request $request)
    {
        if (isset($request->params)) {
            $params = self::parse($request->params);
            Validator::make($params, [
                'page' => 'required|integer',
            ])->validate();
            $page = $params['page'] ?? 1;
            $perPage = 8;
            $queryBuild = CommChat::query()->select('id', 'user_id', 'to_user_id', 'content', 'created_at', 'nickname as to_user_nickname', 'avatar','type');
            $relationName = "relation_chat";
            $subIds = $this->redis()->sMembers($relationName);
            $queryBuild->whereIn('community_chat.id', $subIds);
            $uid = $request->user()->id;

            $queryBuild->where(function($sql) use ($uid){
                $sql->orWhere('user_id',$uid);
                $sql->orWhere('to_user_id',$uid);
            });

            $paginator = $queryBuild
                ->orderByDesc('id')
                ->simplePaginate($perPage, '*', 'commentLists', $page);
            $items = $paginator->items();

            $userIds = [];

            foreach ($items as $k=>$item) {
                $userIds[] = $item['user_id'];
                $userIds[] = $item['to_user_id'];
                $items[$k]['no_read'] = 1;
            }
            $userData = User::query()->whereIn('id',$userIds)->get()->toArray();
            $userInfo = array_column($userData,null,'id');

            foreach ($items as $k=>$item) {
                if ($item['user_id'] == $uid) {
                    $items[$k]['avatar'] = isset($userInfo[$item['to_user_id']]) ? $userInfo[$item['to_user_id']]['avatar'] : '';
                    $items[$k]['to_user_nickname'] =  isset($userInfo[$item['to_user_id']]) ? $userInfo[$item['to_user_id']]['nickname'] : '用户不存在';
                } else {
                    $toUserId = $item['to_user_id'];
                    $tmpUserId = $item['user_id'];
                    $sendUserId = $item['user_id'];
                    $item['user_id'] =  $toUserId;
                    $items[$k]['to_user_id'] = $tmpUserId;

                    $unReadUserKey = 'status_me_unread_' . $uid;
                    $unReads = $this->redis()->sMembers($unReadUserKey);
                    $items[$k]['no_read'] = in_array($sendUserId,$unReads) ? 0 : 1;
                    //Log::info('===CommMessage==',[$unReadUserKey,$unReads,$sendUserId,!in_array($sendUserId,$unReads),$items[$k]['no_read']]);
                }
            }

            $res['list'] = $items;
            $res['hasMorePages'] = $paginator->hasMorePages();
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        }
        return [];
    }
}