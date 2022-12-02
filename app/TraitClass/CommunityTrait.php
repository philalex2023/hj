<?php

namespace App\TraitClass;

use App\Models\Category;

trait CommunityTrait
{
    public function getCircleTopicCat(): array
    {
        $allCat = Category::query()
            ->where('parent_id',10074)
            ->where('is_checked',1)
            ->pluck('name','id')->all();
        $cats = [];
        foreach ($allCat as $id=>$name){
            $cats[$id] = ['id'=>$id,'name'=>$name];
        }
        return $cats;
    }

    public function getCommunityCat(): array
    {
        return [
            [
                'id'=>1,
                'sort'=>1,
                'selected'=>0,
                'name'=>'关注',
            ],
            [
                'id'=>2,
                'sort'=>2,
                'selected'=>1,
                'name'=>'广场',
            ],
            [
                'id'=>3,
                'sort'=>3,
                'selected'=>0,
                'name'=>'话题中心',
            ],
        ];
    }

    public function mdate($time = NULL): string
    {
        $time  =  $time === NULL ||  $time  > time() ? time() :  intval ( $time );
        $t  = time() -  $time ;  //时间差 （秒）
        if($t == 0){
            $txt = '刚刚';
        } elseif ($t < 60){
            $txt = $t . '秒前';
        } elseif ($t < 60 * 60){
            $txt = floor($t / 60) . '分钟前';
        } elseif ($t < 60 * 60 * 24){
            $txt = floor($t / (60 * 60)) . '小时前';
        } elseif (60 * 60 * 24 * 7){
            $txt = floor($t / (60 * 60 * 24)) . '天前';
        } elseif (60 * 60 * 24 * 30){
            $txt = floor($t / (60 * 60 * 24 * 7)) . '周前';
        } elseif (60 * 60 * 24 * 365){
            $txt = floor($t / (60 * 60 * 24 * 30)) . '月前';
        } else {
            $txt = date('Y年m月d日', $time);
        }
        return $txt;
    }

}