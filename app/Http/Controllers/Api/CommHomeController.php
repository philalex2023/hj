<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommFocus;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\BbsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommHomeController extends Controller
{
    use PHPRedisTrait;
    use BbsTrait;
    use ApiParamsTrait;

    /**
     * @throws ValidationException
     */
    public function info(Request $request)
    {
        if (isset($request->params)) {
            $params = self::parse($request->params);
            $validated = Validator::make($params, [
                'id' => 'required|integer',
                'page' => 'required|integer',
            ])->validate();
            $id = $validated['id'];
        } else {
            return [];
        }
        $page = $params['page'] ?? 1;
        $redis = $this->redis();
        $hashKey = 'comm_home_cache_'.$id;
        $raw = $redis->hGet($hashKey, $page);
        if ($raw) {
            $res = json_decode($raw,true);
        } else {
            //二级分类列表
            $perPage = 6;
            $paginator = CommBbs::query()
                ->select($this->bbsFields)
                ->where('author_id', $id)
                ->orderBy('updated_at', 'desc')
                ->simplePaginate($perPage, ['*'], '', $page);
            $secondCateList = $paginator->toArray();
            $data = $secondCateList['data'];
            $user = $request->user();
            $uid = $user->id;
            $result = $this->proProcessData($uid, $data);
            //加入视频列表
            $res['hasMorePages'] = $paginator->hasMorePages();
            $userInfo = CacheUser::user($id);
            if(!$userInfo){
                $res['user_info'] = [];
                $res['bbs_list'] = $result;
            }else{
                if (CommFocus::query()->where(['user_id'=>$uid,'to_user_id'=>$userInfo->id])->exists()) {
                    $userInfo->is_focus = 1;
                } else {
                    $userInfo->is_focus = 0;
                }
                $res['user_info'] = $userInfo;
                $res['bbs_list'] = $result;
                $redis->hSet($hashKey, $page, json_encode($res));
                $redis->expire($hashKey,3600);
            }
        }

        return response()->json([
            'state' => 0,
            'data' => $res
        ]);
    }

}