<?php

namespace App\TraitClass;

use App\ExtendClass\CacheUser;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait BbsTrait
{
    use UserTrait,AboutEncryptTrait,VideoTrait;

    public array $bbsFields = ['id', 'official_type', 'title', 'content', 'thumbs','game_gold', 'likes', 'comments', 'rewards', 'author_location_name as location_name', 'updated_at', 'sync', 'author_nickname as nickname', 'author_sex as sex', 'author_is_office as is_office', 'video', 'author_id as uid', 'author_avatar as avatar', 'author_level as level', 'author_vip as vipLevel', 'author_member_card_type as member_card_type','video_picture'];

    public function getOfficialUsers()
    {
        return Cache::get('officialUsers');
    }

    public function getAllGameNeedGold(): int
    {
        $appConfig = config_cache('app');
        $allGameGold = $appConfig['all_game_gold']??500;
        return (int)$allGameGold;
    }

    public function existsUserGoldGame($uid,$bid)
    {
        return Cache::remember('busKey.'.$uid.':'.$bid,7200,function () use($uid,$bid){
            $id = $bid << 32 | $uid;
            if($bid==0){
                $id = $uid;
            }
            return DB::table('bus')->where('id',$id)->exists();
        });
    }

    /**
     * @param $uid
     * @param $list
     * @param $help
     * @return array
     */
    private function proProcessData($uid, $list, $help='default',$isDetailPage=false): array
    {
        //$_v = date('Ymd');
        $_v = 1;
        $redis = $this->redis();
        $videoRedis = $this->redis('video');
        foreach ($list as $k => $re) {
            $domainSync = self::getDomain($re['sync']??2);
            if ($redis->get("focus_{$uid}_{$re['uid']}") == 1) {
                $list[$k]['is_focus'] = 1;
            } else {
                $list[$k]['is_focus'] = 0;
            }

            $list[$k]['is_game'] = $help=='game' ? 1 : 0;
            $bought = $redis->sIsMember('api_ugb_' . $uid, $list[$k]['id']) || $this->existsUserGoldGame($uid, $list[$k]['id']);
            $all_game = $redis->sIsMember('api_ugb_' . $uid, 0) || $this->existsUserGoldGame($uid, 0);
            $list[$k]['bought'] = $bought ? 1 : 0;
            if($help=='game'){
                $list[$k]['is_game'] = $all_game ? 2 : $list[$k]['is_game'];
            }else{
                $list[$k]['is_game'] = 0;
            }
            $list[$k]['all_game_gold'] = $this->getAllGameNeedGold();
            $list[$k]['official_type'] = (int)($re['official_type']??0);

            //$list[$k]['video_picture'] = [];
            if($re['id']==2297){
                Log::info('TEST_commBbs',[$re]);
            }
            if (isset($re['video_picture'])) {
                $videoPictures = is_array($re['video_picture']) ? $re['video_picture'] : json_decode($re['video_picture'],true) ;
                isset($videoPictures[0]) && $list[$k]['video_picture'] = [$domainSync . $videoPictures[0]];
            }else{
                $list[$k]['video_picture'] = [];
            }

            if ($videoRedis->sIsMember('bbsLike_'.$uid,$re['id'])) {
                $list[$k]['is_love'] = 1;
            } else {
                $list[$k]['is_love'] = 0;
            }
            if ($re['location_name']) {
                $locationRaw = json_decode($re['location_name'],true);
                $list[$k]['location_name'] = $locationRaw[1]??$locationRaw[0]??'';
            }
            /*if($user!==null){
                $list[$k]['location_name'] = $this->getAreaNameFromUser($user->location_name);
            }*/
            $thumbsRaw = json_decode($re['thumbs'],true);
            if($help=='game' && !$isDetailPage){
                $thumbsRaw = array_slice($thumbsRaw,0,1,true);
            }
            $thumbs = [];
            foreach ($thumbsRaw as $itemP) {
                $thumbs[] =$this->transferImgOut($itemP,$domainSync,$_v);
            }
            $list[$k]['thumbs']  = $thumbs;

            $videoRaw  = json_decode($re['video'],true);
            $video = [];
            foreach ($videoRaw as $itemV) {
                $video[] = $domainSync.$this->transferHlsUrl($itemV);
            }
            $list[$k]['video']  = $video;
            empty($list[$k]['video_picture']) && $list[$k]['video']=[];

            //
            if(isset($re['member_card_type'])){
                str_contains($re['member_card_type'],'6') && $list[$k]['vipLevel'] = -1;
            }
        }
        return $list;
    }

    public function resetBBSItem($model)
    {
        $redis = $this->redis();
        $listKey = 'communityBbsItem:'.$model->id;
        $redis->hMSet($listKey,[
            'id'=>$model->id,
            'category_id'=>$model->category_id,
            'content'=>$model->content,
            'thumbs'=>$model->thumbs,
            'video'=>$model->video,
            'video_picture'=>$model->video_picture,
            'likes'=>$model->likes,
            'comments'=>$model->comments,
            'rewards'=>$model->rewards,
            'updated_at'=>$model->updated_at,
            'game_gold'=>$model->game_gold,
            'wx'=>$model->wx,
            'wy_download_url'=>$model->wy_download_url,
            'ali_download_url'=>$model->ali_download_url,
            'wy_get_code'=>$model->wy_get_code,
            'ali_get_code'=>$model->ali_get_code,
            'location_name'=>$model->author_location_name,
            'nickname'=>$model->author_nickname,
            'avatar'=>$model->author_avatar,
            'user_id'=>$model->author_id,
            'is_office'=>$model->author_is_office,
            'official_type'=>$model->official_type,
            'sex'=>$model->author_sex,
            'level'=>$model->author_level,
            'vipLevel'=>$model->author_vip,
            'member_card_type'=>$model->author_member_card_type,
        ]);
    }
}
