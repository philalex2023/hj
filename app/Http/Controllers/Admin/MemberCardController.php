<?php

namespace App\Http\Controllers\Admin;

use App\Models\MemberCard;
use App\Models\RechargeChannel;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PayTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;

class MemberCardController extends BaseCurlController
{
    use PHPRedisTrait;
    use MemberCardTrait;
    use PayTrait;
    //设置页面的名称
    public $pageName = '会员卡';

//    public $denyCommonBladePathActionName = ['index','create','edit'];


    //1.设置模型
    public function setModel(): MemberCard
    {
        return $this->model = new MemberCard();
    }

    public function indexCols(): array
    {
        return [
            [
                'type' => 'checkbox'
            ],
            [
                'field' => 'id',
                'minWidth' => 80,
                'title' => '编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'sort',
                'minWidth' => 80,
                'title' => '排序',
//                'edit' => 1,
                'sort' => 1,
                'align' => 'center',
            ],
            /*[
                'field' => 'bg_img',
                'minWidth' => 80,
                'edit' => 1,
                'title' => '背景',
                'align' => 'center',
            ],*/
            [
                'field' => 'status',
                'minWidth' => 100,
                'title' => '状态',
                'align' => 'center',
            ],
            [
                'field' => 'name',
                'minWidth' => 100,
                'title' => '卡名',
                'align' => 'center'
            ],
            [
                'field' => 'name_day',
                'minWidth' => 100,
                'title' => '天数解释',
                'align' => 'center'
            ],

            [
                'field' => 'remark',
                'minWidth' => 150,
                'title' => '卡信息描述',
                'align' => 'center',
            ],
            [
                'field' => 'value',
                'minWidth' => 100,
                'title' => '展示面值',
                'align' => 'center',
            ],
            [
                'field' => 'real_value',
                'minWidth' => 100,
                'title' => '活动面值',
                'align' => 'center',
            ],
            [
                'field' => 'rights',
                'minWidth' => 200,
                'title' => '权益',
                'hide' => true,
                'align' => 'center',
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'align' => 'center'
            ]
        ];
    }

    //4.编辑和添加页面表单数据
    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '会员卡名称',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'name_day',
                'type' => 'text',
                'name' => '天数解释',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'remark',
                'type' => 'text',
                'name' => '卡备注/标题/描述',
                'must' => 1,
            ],
            [
                'field' => 'value',
                'type' => 'number',
                'name' => '面值',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'rights_checkbox',
                'type' => 'checkbox',
                'name' => '权益',
                'must' => 1,
                'verify' => 'rq',
                'value'=> ($show && ($show->rights)) ? $this->numToRights($show->rights) : [],
                'data' => $this->cardRights
            ],
            [
                'field' => 'expired_hours',
                'type' => 'text',
                'name' => '过期时间周期(小时):不填或填0为永久',
                'must' => 0,
                'tips' => '单位(小时)',
            ],
            [
                'field' => 'remain_hours',
                'type' => 'text',
                'name' => '能优惠的VIP剩余时间内(单位:小时)',
                'must' => 0,
                'tips' => '单位(小时)',
            ],
            [
                'field' => 'hours',
                'type' => 'text',
                'name' => '优惠活动注册时间周期(单位:小时)',
                'must' => 0,
                'tips' => '单位(小时)',
            ],
            [
                'field' => 'real_value',
                'type' => 'number',
                'name' => '优惠活动面值',
                'must' => 0,
            ],
            [
                'field' => 'sort',
                'type' => 'number',
                'name' => '排序',
                'must' => 0,
            ],
            [
                'field' => 'bg_img',
                'type' => 'number',
                'name' => '背景',
                'must' => 0,
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '状态',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ],
        ];
        $this->uiBlade['form'] = $data;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $rights = $this->rq->input('rights_checkbox',[]);
        if(!empty($rights)){
            $model->rights = $this->binTypeToNum($rights);
        }
    }

    public function setListOutputItemExtend($item)
    {
        $item->real_value == 0  && $item->real_value = '无';
        $item->status = match ($item->status){
            0 => '关闭',
            1 => '开启',
        };
        return $item;
    }

    //弹窗大小
    public function layuiOpenWidth()
    {
        return '65%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

    public function getPayChannelData()
    {
        $res = RechargeChannel::query()
            ->where('status',1)
            ->get(['id','name','remark']);
        $data = $this->uiService->allDataArr('请选择支付方式');
        foreach ($res as $item) {
            $data[$item->id] = [
                'id' => $item->id,
                'name' => $item->remark,
            ];
        }
        return $data;
    }

    /**
     * 清理缓存
     * @param $model
     * @param string $id
     * @return mixed
     */
    protected function afterSaveSuccessEvent($model, $id = ''): mixed
    {
        //写入缓存
        Cache::forever('member_card_key',MemberCard::query()->get());
        return $model;
    }

}