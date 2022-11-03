<?php

namespace App\TraitClass;

use App\Models\Ad;
use App\Models\AdSet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait AdTrait
{
    use AboutEncryptTrait,PHPRedisTrait;

    public function getAdSet(): array
    {
        $adSetCollection = Cache::remember('ad_set',7200,function (){
            return AdSet::query()->get();
        });
        return array_column($adSetCollection->toArray(),null,'flag');
    }

    public function getConfigDataFromDb($force=false): array
    {
        $appConfig = config_cache('app');
        if(!empty($appConfig) || $force){
            //Log::info('==ConfigAnnouncement==',[$appConfig['announcement']]);
            isset($appConfig['open_screen_logo']) && $res['open_screen_logo'] = $this->transferImgOut($appConfig['open_screen_logo']);
            $res['announcement'] = stripslashes(addslashes($appConfig['announcement']));
            $res['anActionType'] = $appConfig['announcement_action_type'];
            //视频ID
            $res['videoId'] = $appConfig['announcement_video_id'];
            $res['obUrl'] = $appConfig['announcement_url'];
            $res['adTime'] = (int)$appConfig['ad_time'];
            $res['version'] = $appConfig['app_version'];
            $res['kf_url'] = $appConfig['kf_url'];
            $res['send_sms_intervals'] = (int)$appConfig['send_sms_intervals'];
            //广告部分
            $ads = $this->weightGet('open_screen','weight',true);
            Log::info('open_screen_ads==',[$ads]);
            $res['activity_ads'] = $this->weightGet('activity','weight',true);
            $res['md_ads'] = $this->weightGet('md_ads','weight',true);
            $res['short_video_ads'] = $this->weightGet('short_video_ads','weight',true);
            $res['my_home_ads'] = $this->weightGet('my_home_ads','weight',true);
            $res['community_ads'] = $this->weightGet('community_ads','weight',true);
//            $res['live_ads'] = $this->weightGet('live_ads','weight',true);
            $res['open_screen_ads'] = $ads;

            $payConf = json_decode($appConfig['pay_method']??'',true);
            $currentSecond = strval(date(date('s')%10));
            $res['pay_method'] = intval($payConf[$currentSecond]??2);
            $res['pay_detail'] = json_decode($appConfig['pay_detail']??'',true);
            if(!empty($res)){
                $this->redis()->set('api_config',json_encode($res,JSON_UNESCAPED_UNICODE));
                return $res;
            }
        }
        return [];
    }

    public function weightGet($flag='',$sortFiled='sort',$more=false): array
    {
        $ads = Ad::query()
            ->where('name',$flag)
            ->where('status',1)
            ->orderByDesc($sortFiled)
            ->get(['id','name','weight','title','img','position','url','play_url','type','status','action_type','vid','end_at'])
            ->toArray();
        $domain = VideoTrait::getDomain(env('SFTP_SYNC',1));
        $_v = 1;

        if($more){
            foreach ($ads as &$item){
                $item['img'] = $this->transferImgOut($item['img'],$domain,$_v,'auto');
                $item['action_type'] = (string) $item['action_type'];
                $item['vid'] = (string) $item['vid'];
            }
            return $ads;
        }

        return [];
    }

    public function getAds($flag='',$groupByPosition=false): array
    {
        $getAds = Cache::get('ads_key_'.$flag);
        $ads = $getAds ? $getAds->toArray() : [];
        //$domain = VideoTrait::getDomain(env('SFTP_SYNC',1));
        $domain = '';
        //$_v = date('YmdH');
        $_v = 1;
        $filterAds = [];
        foreach ($ads as &$ad){
            if($ad['status']==1){
                $ad['img'] = $this->transferImgOut($ad['img'],$domain,$_v,'auto');
                $ad['action_type'] = (string)$ad['action_type'];
                $ad['vid'] = (string)$ad['vid'];
                $filterAds[] = $ad;
            }
        }
        if($groupByPosition){ //有位置的多一维
            $newAds = [];
            foreach ($filterAds as $item){
                $newAds[$item['position']][]= $item;
            }
            $filterAds = $newAds;
        }
        return !empty($filterAds) ? $filterAds : [];
    }

    public function insertAds($data, $flag='', $usePage=false, $page=1, $perPage=6): array
    {
        $adSet = $this->getAdSet();
        if (!$adSet || !isset($adSet[$flag])) {
            return $data;
        }
        $res = $data;
        $rawPos = $adSet[$flag]['position'];
        if ($rawPos == 0) {
            $ads = $this->getAds($flag,$usePage);
            foreach ($res as $k=>$v){
                $tmpK = $usePage ? (($page-1) * $perPage + $k) : $k;
                $res[$k]['ad_list'] = $ads[$tmpK] ?? [];
            }
            return $res;
        } else {
            $ads = $this->getAds($flag);
        }
        $position = explode(':',$rawPos);
        $adCount = count($ads);
        if ($position[1]??false) {
            $position = rand($position[0],$position[1]);
        } else {
            // 不启用分组
            $position = $position[0];
        }
        $counter = 0;
        unset($k,$v);
        foreach ($res as $k=>$v){
            $cur = ($page-1) * $perPage + $k + 1;
            if ($position != 0 && $adCount>0) {
                if (($cur % $position == 0) && ($cur != 0)) {
                    $adsKey = $counter%$adCount;
                    $counter++;
                    $res[$k]['ad_list'] = [];
                    $tmpAd = $ads[$adsKey]??[];
                    if ($tmpAd) {
                        $res[$k]['ad_list'] = [$tmpAd];
                    }
                } else {
                    $res[$k]['ad_list'] = [];
                }
                continue;
            }
            $tmpK = $usePage ? $cur : $k;
            $res[$k]['ad_list'] = $ads[$tmpK] ?? [];
        }
        return $res;
    }

    public function frontFilterAd(&$Items,$domain=''): array
    {
        $ads = [];
        $nowTime = time();
        foreach ($Items as $ad){
            if($ad['status']==1){
                if(!$ad['end_at']){
                    $ad['img'] = $domain . $ad['img'];
                    $ads[] = $ad;
                } elseif ($nowTime < strtotime($ad['end_at'])){
                    $ad['img'] = $domain . $ad['img'];
                    $ads[] = $ad;
                }
            }
        }

        $Items = $ads;
        return $ads;
    }

    public function resetAdsData($flag): void
    {
        Cache::forever('ads_key_'.$flag,Ad::query()
            ->where('name',$flag)
            ->where('status',1)
            ->orderBy('sort')
            ->get(['id','sort','name','title','img','position','url','play_url','type','status','action_type','vid','end_at']));
    }
}
