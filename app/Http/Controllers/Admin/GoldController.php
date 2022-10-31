<?php

namespace App\Http\Controllers\Admin;

use App\Models\Gold;
use App\Models\RechargeChannel;
use App\Services\UiService;
use App\TraitClass\PayTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;

class GoldController extends BaseCurlController
{
    use PayTrait;
    use PHPRedisTrait;
    //设置页面的名称
    public $pageName = '金币设置';

    //1.设置模型
    public function setModel(): Gold
    {
        return $this->model = new Gold();
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
                'sort' => 1,
//                'edit' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'money',
                'minWidth' => 100,
                'title' => '价格',
                'align' => 'center',
//                'edit' => 1
            ],
            [
                'field' => 'bonus',
                'minWidth' => 100,
                'title' => '额外赠送',
                'align' => 'center',
//                'edit' => 1
            ],
            /*[
                'field' => 'zfb_action_name',
                'minWidth' => 100,
                'title' => '支付宝充值方式',
                'align' => 'center',
            ],
            [
                'field' => 'zfb_channel',
                'minWidth' => 100,
                'title' => '支付宝通道',
                'align' => 'center',
            ],
            [
                'field' => 'wx_action_name',
                'minWidth' => 100,
                'title' => '微信充值方式',
                'align' => 'center',
            ],
            [
                'field' => 'wx_channel',
                'minWidth' => 100,
                'title' => '微信通道',
                'align' => 'center',
            ],*/
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '状态',
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

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'money',
                'type' => 'text',
                'name' => '价格/数量',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'zfb_action_id',
                'minWidth' => 100,
                'name' => '支付宝充值方式',
                'type' => 'select',
                'data' => $this->getPayChannelData()
            ],
            [
                'field' => 'zfb_channel',
                'minWidth' => 100,
                'name' => '支付宝渠道',
                'type' => 'select',
                'data' => $this->getPayTypeCode()
            ],
            [
                'field' => 'wx_action_id',
                'minWidth' => 100,
                'name' => '微信充值方式',
                'type' => 'select',
                'data' => $this->getPayChannelData()
            ],
            [
                'field' => 'wx_channel',
                'minWidth' => 100,
                'name' => '微信通道',
                'type' => 'select',
                'data' => $this->getPayTypeCode()
            ],
            [
                'field' => 'remark',
                'type' => 'text',
                'name' => '备注信息',
            ],
            [
                'field' => 'bonus',
                'type' => 'text',
                'name' => '额外赠送',
            ],
            /*[
                'field' => 'proportion',
                'type' => 'text',
                'name' => '充值比例',
            ],*/
            [
                'field' => 'user_type',
                'type' => 'select',
                'name' => '用户类型',
                'data' => [
                    0 => ['id'=>0,'name'=>'全部'],
                    1 => ['id'=>1,'name'=>'vip用户'],
                    2 => ['id'=>2,'name'=>'金币用户'],
                ]
            ],
            [
                'field' => 'sort',
                'type' => 'number',
                'name' => '排序',
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

    public function setListOutputItemExtend($item)
    {
        $item->status = UiService::switchTpl('status', $item,'');
        $rechargeData = RechargeChannel::query()
            ->where('status',1)
            ->pluck('remark','id')
            ->toArray();
        $item->wx_action_name = $rechargeData[$item->wx_action_id??1];
        $item->zfb_action_name = $rechargeData[$item->zfb_action_id??1];
        return $item;
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
        //设置缓存
        $goldCardKey = "member_gold";
        $goldCardInfo = Gold::query()->get();
        Cache::forever($goldCardKey,$goldCardInfo);
        return $model;
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
}