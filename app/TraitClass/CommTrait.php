<?php

namespace App\TraitClass;

use App\Models\Ad;
use App\Models\Category;
use App\Models\CommCate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait CommTrait
{
    use PHPRedisTrait,AdTrait;

    public function getAppModuleShowType(): array
    {
        return [
            0 => [
                'id' => 0,
                'name' => '无'
            ],
            1 => [
                'id' => 1,
                'name' => '横中'
            ],
            2 => [
                'id' => 2,
                'name' => '横中大'
            ],
            3 => [
                'id' => 3,
                'name' => '横小'
            ],
            4 => [
                'id' => 4,
                'name' => '横大二'
            ],
            5 => [
                'id' => 5,
                'name' => '横大一'
            ],
            6 => [
                'id' => 6,
                'name' => '双连'
            ],
            7 => [
                'id' => 7,
                'name' => '大一偶小'
            ],
            8 => [
                'id' => 8,
                'name' => '新横大二'
            ],
            9 => [
                'id' => 9,
                'name' => '横小二'
            ],
        ];
    }

    public function getCommCate()
    {
        $redis = $this->redis();
        $raw = $redis->get('common_cate');
        $data = @json_decode($raw, true);
        $data = $data ?? [];
        if(!empty($data)){
            return $data;
        }
        //$data = [];
        $raw = CommCate::query()->orderBy('order', 'desc')
            ->select('id','name','parent_id','mark','order','is_allow_post','can_select_city')
            ->get()->toArray();
        foreach ($raw as $v1) {
            $redis->hSet('common_cate_help', "c_{$v1['id']}", $v1['mark']);
            if ($v1['parent_id'] == 0) {
                $data[] = $v1;
            }
        }
        foreach ($raw as $v2) {
            if ($v2['parent_id'] > 0) {
                $redis->hSet('common_cate_help', "c_{$v2['id']}", $v2['mark']);
                foreach ($data as $k3=>$v3) {
                    if ($v2['parent_id'] == $v3['id']) {
                        $data[$k3]['childs'][] = $v2;
                    }
                }
            }
        }
        $redis->set('common_cate',json_encode($data));
        return $data;
    }

    public function resetHomeCategory(): \Illuminate\Support\Collection
    {
        $categories = DB::table('categories')
            ->where('parent_id',2)
            ->where('is_checked',1)
            ->orderBy('sort')
            ->get(['id','name','sort']);
        Cache::forever('api_home_category',$categories);
        return $categories;
    }

    public function resetHomeRedisData(): void
    {
        $homeCats = $this->resetHomeCategory();
        $redis = $this->redis();

        foreach ($homeCats as $homeCat){
            $perPage = 4;
            $page = 1;
            do {
                $paginator = Category::query()
                    ->where('parent_id',$homeCat->id)
                    ->where('is_checked',1)
                    ->orderBy('sort')
                    ->simplePaginate($perPage,['id','name','seo_title as title','is_rand','is_free','limit_display_num','group_type as style','group_bg_img as bg_img','local_bg_img','sort'],'homeContent',$page);
                $sectionKey = 'homeLists_'.$homeCat->id.'-'.$page;
                $data['hasMorePages'] = $paginator->hasMorePages();
                $blockCat = $paginator->toArray()['data'];
                foreach ($blockCat as &$item){
                    $item['small_video_list'] = [];
                    //获取模块数据
                    $ids = $redis->sMembers('catForVideo:'.$item['id']);
                    if(!empty($ids)){
                        $videoBuild = DB::table('video')->where('status',1)->whereIn('id',$ids);
                        if($item['is_rand']==1){
                            $videoBuild = $videoBuild->inRandomOrder();
                        }else{
                            $videoBuild = $videoBuild->orderByRaw('video.sort DESC,video.updated_at DESC,video.id DESC');
                        }
                        $limit = ($item['limit_display_num']>0 && $item['limit_display_num']<33) ? $item['limit_display_num'] : 8;
                        $videoList = $videoBuild->limit($limit)->get(['video.id','video.is_top','name','gold','cat','tag_kv','sync','title','dash_url','hls_url','duration','type','restricted','cover_img','views','likes','updated_at'])->toArray();
                        $item['small_video_list'] = $videoList;
                    }
                }

                //广告
                $blockCat = $this->insertAds($blockCat,'home_page',true,$page,$perPage);
                //存入redis
                $data['list'] = $blockCat;
                $redis->set($sectionKey,json_encode($data,JSON_UNESCAPED_UNICODE));
                ++$page;

            } while ($data['hasMorePages']);
        }
        //Cache::put('updateHomePage',1);
    }

    public function resetShortCate(): array
    {
        $raw = Category::query()->where('parent_id', 10000)
            ->where('is_checked',1)
            ->orderBy('sort', 'desc')
            ->select('id', 'name')
            ->get();
        $rawArr = $raw->toArray();
        $this->redis()->set('short_category',json_encode($rawArr,JSON_UNESCAPED_UNICODE));
        return $rawArr;
    }

}