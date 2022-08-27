<?php
// +----------------------------------------------------------------------
// | KQAdmin [ 基于Laravel后台快速开发后台 ]
// | 快速laravel后台管理系统，集成了，图片上传，多图上传，批量Excel导入，批量插入，修改，添加，搜索，权限管理RBAC,验证码，助你开发快人一步。
// +----------------------------------------------------------------------
// | Copyright (c) 2012~2019 www.haoxuekeji.cn All rights reserved.
// +----------------------------------------------------------------------
// | Laravel 原创视频教程，文档教程请关注 www.heibaiketang.com
// +----------------------------------------------------------------------
// | Author: kongqi <531833998@qq.com>`
// +----------------------------------------------------------------------

namespace App\Http\Controllers\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\CommReward;
use App\Models\AdminGoldLog;

class CommGameGoldController extends BaseCurlController
{
    //那些页面不共享，需要单独设置的方法
    //public $denyCommonBladePathActionName = ['create'];
    //设置页面的名称
    public $pageName = '游戏购买记录';

    //1.设置模型
    public function setModel(): AdminGoldLog
    {
        return $this->model = new AdminGoldLog();
    }

    //2.首页的数据表格数组
    public function indexCols(): array
    {
        //要返回给数组
        return [
            [
                'type' => 'checkbox'
            ],
            [
                'field' => 'id',
                'width' => 80,
                'title' => '编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'uid',
                'minWidth' => 150,
                'title' => '用户ID',
                'align' => 'center',

            ],
            [
                'field' => 'cash',
                'minWidth' => 120,
                'title' => '花费金额',
                'totalRow' => true,
                'align' => 'center'
            ],
            [
                'field' => 'goods_info',
                'minWidth' => 80,
                'title' => '商品信息',
                'align' => 'center',
            ],
            [
                'field' => 'use_type',
                'minWidth' => 80,
                'title' => '消费类型',
                'align' => 'center',
            ],
            [
                'field' => 'device_system',
                'minWidth' => 80,
                'title' => '设备系统平台',
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ]
        ];
    }

    //3.设置搜索数据表单
    public function setOutputSearchFormTpl($shareData)
    {
        $data = [
            [
                'field' => 'uid',
                'type' => 'text',
                'name' => '用户ID',
            ],
            [
                'field' => 'created_at',
                'type' => 'datetime',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '创建时间',
            ],
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setListOutputItemExtend($item)
    {
        $use_type = '';
        if($item->use_type==3){
            $use_type = '所有游戏';
        }
        if($item->use_type==2){
            $use_type = '单个游戏';
        }
        $item->use_type = $use_type;
        $item->device_system = match ($item->device_system) {
            1 => '苹果',
            2 => '安卓',
            default => '',
        };
        return $item;
    }

    public function setOutputHandleBtnTpl($shareData): array
    {
        return [];
    }

    public function handleResultModel($model)
    {
        $model = $model->where('use_type','>',1);
        $page = $this->rq->input('page', 1);
        $pagesize = $this->rq->input('limit', 30);
        $created_at = $this->rq->input('created_at',null);
        $uid = $this->rq->input('uid',null);
        $order_by_name = $this->orderByName();
        $order_by_type = $this->orderByType();
        if($uid!==null){
            $model = $model->where('uid', $uid);
        }
        if($created_at!==null){
            $dateArr = explode('~',$created_at);
            if(isset($dateArr[0]) && isset($dateArr[1])){
                $model = $model->whereBetween('created_at', [trim($dateArr[0]),trim($dateArr[1])]);
            }
        }

        $model = $this->orderBy($model, $order_by_name, $order_by_type);
        $totalAmount = $model->sum('cash');
        $total = $model->count();
        $result = $model->forPage($page, $pagesize)->get();
        return [
            'total' => $total,
            'totalRow' => ['cash'=>$totalAmount],
            'result' => $result
        ];
    }

    //首页共享数据
    public function indexShareData()
    {
        //设置首页数据替换
        $this->setListConfig(['open_width' => '600px', 'open_height' => '700px','tableConfig' => ['totalRow' => true]]);
    }

}