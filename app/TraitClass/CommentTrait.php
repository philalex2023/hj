<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait CommentTrait
{
    use VipRights,PHPRedisTrait;

    public function submitComment($commentTableName, $associationTable, $commentKey, $associationId, $insertData, $user): \Illuminate\Http\JsonResponse
    {
        //权限控制
        $rights = $this->getUserAllRights($user);
        if(!isset($rights[6]) || $user->status==0){
            return response()->json([
                'state' => -1,
                'msg' => "权限不足",
            ]);
        }
        $redis = $this->redis();
        if(intval($redis->hGet($commentKey,$user->id)) >= 50){
            return response()->json([
                'state'=>-1,
                'msg'=>'评论超过限制次数'
            ]);
        }
        try {   //先偿试队列
            $commentId = DB::table($commentTableName)->insertGetId($insertData);
            if($commentId >0){
                $redis->hIncrBy($commentKey,$user->id,1);
                DB::table($associationTable)->where('id',$associationId)->increment('comments');
                return response()->json([
                    'state'=>0,
                    'msg'=>'评论成功'
                ]);
            }
        }catch (\Exception $e){
            Log::error('==='.$commentKey.'===' . $e->getMessage());
        }
        return response()->json([
            'state'=>-1,
            'msg'=>'评论失败'
        ]);
    }

    public function replyComment($commentTableName, $associationTable, $commentKey, $associationId, $insertData, $user): \Illuminate\Http\JsonResponse
    {
        //权限控制
        if(!$this->commentRight($user)){
            return response()->json([
                'state' => -2,
                'msg' => "权限不足",
            ]);
        }
        $redis = $this->redis();
        if(intval($redis->hGet($commentKey,$user->id)) >= 50){
            return response()->json([
                'state'=>-1,
                'msg'=>'评论超过限制次数'
            ]);
        }

        DB::table($commentTableName)->insert($insertData);
        DB::table($commentTableName)->where('id',$insertData['reply_cid'])->increment('replies');
        $redis->hIncrBy($commentKey,$user->id,1);
        DB::table($associationTable)->where('id',$associationId)->increment('comments');
        return response()->json([
            'state'=>0,
            'msg'=>'回复成功'
        ]);
    }
}