<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CommentTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VipRights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    use VipRights,ApiParamsTrait,CommentTrait;

    public function submit(Request $request): \Illuminate\Http\JsonResponse
    {
        if(isset($request->params)) {
            $params = self::parse($request->params);
            Validator::make($params, [
                'vid' => 'required|integer',
                'content' => 'required',
            ])->validate();
            $vid = $params['vid'];
            $content = $params['content'];
            $user = $request->user();
            $insertData = [
                'vid' => $vid,
                'uid' => $user->id,
                'user_avatar' => $user->avatar,
                'user_nickname' => $user->nickname,
                'status' => 0,
                'content' => $content,
                'reply_at' => date('Y-m-d H:i:s'),
            ];
            return $this->submitComment('comments','video','commentForVideo', $vid, $insertData, $user);
        }
        return response()->json([
            'state'=>-1,
            'msg'=>'参数错误'
        ]);
    }

    public function reply(Request $request): \Illuminate\Http\JsonResponse
    {
        if(isset($request->params)) {
            $user = $request->user();
            $params = self::parse($request->params);
            $validated = Validator::make($params, [
                'comment_id' => 'required|integer|min:1',
                'vid' => 'required|integer|min:1',
                'content' => 'required',
            ])->validated();
            $commentTable = 'comments';
            $repliedInfo = DB::table($commentTable)->where('id',$validated['comment_id'])->first();
            $comment = DB::table($commentTable)->find($validated['comment_id'],['reply_cid']);
            if(!$repliedInfo || !$comment){
                return response()->json([
                    'state'=>-1,
                    'msg'=>'此评论不存在或已被删除'
                ]);
            }
//            $replyUser = CacheUser::user($replied_uid);

            if($comment->reply_cid>0){
                $validated['comment_id'] = DB::table($commentTable)->where('id',$validated['comment_id'])->value('reply_cid');
            }
            $insertData = [
                'reply_cid' => $validated['comment_id'],
                'vid' => $validated['vid'],
                'uid' => $user->id,
                'user_avatar' => $user->avatar,
                'user_nickname' => $user->nickname,
                'reply_user_avatar' => $repliedInfo->user_avatar,
                'reply_user_nickname' => $repliedInfo->user_nickname,
                'status' => 1,
                'replied_uid' => $repliedInfo->uid,
                'content' => $validated['content'],
                'reply_at' => date('Y-m-d H:i:s'),
            ];
            return $this->replyComment($commentTable,'video','commentForVideo',$validated['vid'],$insertData,$user);

        }
        return response()->json([
            'state'=>-1,
            'msg'=>'参数错误'
        ]);
    }

    public function lists(Request $request)
    {
        if(isset($request->params)) {
            $params = self::parse($request->params);
            Validator::make($params, [
                'vid' => 'required|integer',
            ])->validate();
            $reply_cid = $params['comment_id'] ?? 0;
            $vid = $params['vid'];
            $page = $params['page'] ?? 1;
            $perPage = 16;
            $result = DB::table('comments')
                ->select('id','vid','uid','reply_cid','replied_uid','content','replies','reply_at','user_avatar as avatar','user_nickname as nickname','reply_user_avatar as replied_avatar','reply_user_nickname as replied_nickname')
                ->where('vid',$vid)
                ->where('status',1)
                ->where('reply_cid',$reply_cid)
                ->orderByDesc('id')->get();
            $res = $this->resultToArrayPage($result,$page,$perPage);
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
        return [];
    }
}