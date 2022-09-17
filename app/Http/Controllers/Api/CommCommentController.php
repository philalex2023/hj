<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommCommentController extends Controller
{
    use ApiParamsTrait, MemberCardTrait, PHPRedisTrait;
    public function post(Request $request)
    {
        if(isset($request->params)) {
            $params = self::parse($request->params);
            Validator::make($params, [
                'bbs_id' => 'required|integer',
                'content' => 'required',
            ])->validate();
            $vid = $params['bbs_id'];
            $content = $params['content'];
            $user = $request->user();
            $insertData = [
                'bbs_id' => $vid,
                'user_id' => $user->id,
                'content' => $content,
                'status' => 0,
                'user_avatar' => $user->avatar,
                'user_nickname' => $user->nickname,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
//            $isVip = $this->getVipValue($user);
            $rights = $this->getUserAllRights($user);
            $commentKey = 'commentForCommBbs';
            $redis = $this->redis();
            if(isset($rights[3])){
                $cardTypes = explode(',',$user->member_card_type);
                $comments = intval($redis->hGet($commentKey,$user->id));
                if(in_array(1,$cardTypes) && $comments>=30){//限制30次
                    return response()->json([
                        'state'=>-1,
                        'msg'=>'评论超过限制次数'
                    ]);
                }elseif($comments>=50){//限制50次
                    return response()->json([
                        'state'=>-1,
                        'msg'=>'评论超过限制次数'
                    ]);
                }
            }else{
                return response()->json([
                    'state'=>-1,
                    'msg'=>'没有评论权限'
                ]);
            }

            $redis->hIncrBy($commentKey,$user->id,1);
            DB::beginTransaction();
            try {
                $commentId = DB::table('community_comments')->insertGetId($insertData);
                CommBbs::where('id',$vid)->increment('comments');
                DB::commit();
                if($commentId >0){
                    return response()->json([
                        'state'=>0,
                        'msg'=>'评论成功'
                    ]);
                }
            }catch (\Exception $e){
                DB::rollBack();
                Log::error('bbsComments===' . $e->getMessage());
            }
            return response()->json([
                'state'=>-1,
                'msg'=>'评论失败'
            ]);

        }
        return [];
    }

    /**
     * 评论列表
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function lists(Request $request): JsonResponse
    {
        if(isset($request->params)) {
            $params = self::parse($request->params);
            Validator::make($params, [
                'bbs_id' => 'required|integer',
            ])->validate();
            $bbsId = $params['bbs_id'] ?? 1;
            $page = $params['page'] ?? 1;
            $perPage = 16;
            $result = DB::table('community_comments')
                ->select('id','content','user_id as uid','user_avatar as avatar','user_nickname as nickname','community_comments.created_at as reply_at')
                ->where('bbs_id',$bbsId)
                ->where('status',1)
                ->orderByDesc('id')->get();
            $res = $this->resultToArrayPage($result,$page,$perPage);
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        }
        return response()->json();
    }
}