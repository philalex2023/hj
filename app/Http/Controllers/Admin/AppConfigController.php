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

use App\Models\Config;
use App\Models\User;
use App\TraitClass\AboutEncryptTrait;
use App\TraitClass\AdTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;

class AppConfigController extends BaseCurlController
{
    use PHPRedisTrait,AdTrait,AboutEncryptTrait;
    //去掉公共模板
    public $commonBladePath = '';
    public $pageName = 'APP配置';

    public function indexShareData()
    {
        $config_name = \request()->input('group_type', 'app');
        $config = config_cache($config_name);
        $config = is_array($config) ? $config : [];

        $this->setOutputUiCreateEditForm($config);

    }

    //4.编辑和添加页面表单数据
    public function setOutputUiCreateEditForm($show = '')
    {

        $data = [
            [
                'group_name' => 'APP公告设置',
                'data' => [
                    [
                        'field' => 'announcement',
                        'type' => 'text',
                        //'editor_type' => 'simple',//编辑器类型指定，默认是
                        'name' => '公告内容',
                        'must' => 1,
                        'verify' => '',
                        'tips' => '我支持simple编辑器,还支持截图上传'
                    ],
                    [
                        'field' => 'announcement_action_type',
                        'type' => 'text',
                        'name' => '公告操作',
                        'must' => 1,
                        'verify' => 'rq',
                        'default' => '0',
                        'tips' => '0-无操作,1-打开链接'
                    ],
                    [
                        'field' => 'announcement_url',
                        'type' => 'text',
                        'name' => '公告链接',
                        'verify' => '',
                        'tips' => 'https://xxx.com'
                    ],
                    [
                        'field' => 'announcement_video_id',
                        'type' => 'number',
                        'name' => '公告视频ID',
                        'verify' => '',
                        'default' => 1,
                        'tips' => '公告视频ID'
                    ],
                ]
            ],
            [
                'group_name' => 'APP参数设置',
                'data' => [
                    /*[
                        'field' => 'open_screen_logo',
                        'type' => 'img',
                        'name' => '开屏LOGO图设置',
//                        'default' => 3,
                        'verify' => '',
                        'tips' => ''
                    ],*/
                    [
                        'field' => 'ad_time',
                        'type' => 'number',
                        'name' => '开屏广告时长',
                        'default' => 3,
                        'verify' => '',
                        'tips' => '单位(秒)'
                    ],
                    [
                        'field' => 'free_view_long_video_times',
                        'type' => 'number',
                        'name' => '免费观看次数',
                        'must' => 1,
                        'verify' => 'rq',
                        'default' => '',
                    ],
                    [
                        'field' => 'up_master_profit_percentage',
                        'type' => 'number',
                        'name' => 'up主收益百分比',
                        'must' => 1,
                        'verify' => 'rq',
                        'default' => '',
                    ],
                    [
                        'field' => 'all_game_gold',
                        'type' => 'number',
                        'name' => '一键解锁游戏所需金币',
                        'must' => 1,
                        'verify' => 'rq',
                        'default' => 500,
                    ],
                    [
                        'field' => 'send_sms_intervals',
                        'type' => 'number',
                        'name' => '发送短信间隔时间(秒)',
                        'must' => 1,
                        'verify' => 'rq',
                        'default' => 180,
                    ],
                    [
                        'field' => 'app_version',
                        'type' => 'text',
                        'name' => '应用版本',
                        'must' => 1,
                        'verify' => 'rq',
                        'default' => '1.0.0',
                        'tips' => '作为升级较验使用'
                    ],
                    [
                        'field' => 'reward_rules',
                        'type' => 'text',
                        'editor_type' => '',//编辑器类型指定，默认是
                        'name' => '奖励规则',
                        'verify' => '',
                    ],
                    [
                        'field' => 'kf_url',
                        'type' => 'text',
                        'name' => '客服链接',
                        'must' => 1,
                        'verify' => '',
                    ],
                    [
                        'field' => 'pay_method',
                        'type' => 'textarea',
                        'name' => '支付方式',
                        'must' => 1,
                        'default' => '',
                        'tips' => '配置json,开发用'
                    ],
                    [
                        'field' => 'pay_detail',
                        'type' => 'textarea',
                        'name' => '充值细节(开发用)',
                        'must' => 1,
                        'default' => '',
                        'tips' => '配置json,开发用'
                    ],
                    [
                        'field' => 'pay_channel_codes',
                        'type' => 'textarea',
                        'name' => '支付通道码',
                        'must' => 1,
                        'default' => '',
                        'tips' => '配置支付通道,逗号隔开'
                    ],
                ]
            ]
        ];

        //赋值到ui数组里面必须是`formShowBtn`的key值,这里会显示按钮图标
        $this->uiBlade['formShowBtn'] = $data;
        $this->uiBlade['show'] = $show;

    }

    public function setModel()
    {
        return new Config();
    }

    public function store(Request $request)
    {
        $config_name = $request->input('group_type', 'app');
        $config_values = $request->except(['_token','s']);
        //同步图片到资源服
        /*if(isset($config_values['open_screen_logo'])){
            $this->syncUpload($config_values['open_screen_logo']);
        }*/
        config_cache($config_name, $config_values);
        //
        cache()->delete('payEnv');
        if($this->getConfigDataFromDb()){
            $this->insertLog(lang('系统配置成功'));
        }
        return $this->returnSuccessApi('设置成功');

    }

    //去掉按钮
    public function setOutputHandleBtnTpl($shareData)
    {
        return $this->uiBlade['btn'] = [];
    }

}
