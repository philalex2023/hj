<?php


namespace App\Http\Controllers\Admin;


use App\Models\Order;
use App\Models\RechargeChannel;
use App\Models\RechargeChannels;
use App\TraitClass\ChannelTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;

class RechargeChannelsController extends BaseCurlController
{
    use PHPRedisTrait,ChannelTrait;
    public $pageName = "充值通道";
    public array $payChannel = [];
    public array $pay_type = [
        1=>['id'=>1,'name'=>'支付宝'],
        2=>['id'=>2,'name'=>'微信'],
    ];
    public array $match_amount = [
        1=>['id'=>1,'name'=>'10'],
        2=>['id'=>2,'name'=>'50'],
        3=>['id'=>3,'name'=>'100'],
        4=>['id'=>4,'name'=>'200'],
        5=>['id'=>5,'name'=>'300'],
        6=>['id'=>6,'name'=>'500'],
    ];

    public function getPayChannels(): array
    {
        $channels = RechargeChannel::query()->get(['id','name','remark']);
        $selector = [''=>['id'=>'','name'=>'选择充值渠道']];
        foreach ($channels as $channel){
            $selector[$channel->id] = [
                'id' => $channel->id,
                'name' => $channel->remark,
            ];
        }
        return $selector;
    }

    public function setModel(): RechargeChannels
    {
        $this->payChannel = $this->getPayChannels();
        return $this->model = new RechargeChannels();
    }

    public function indexCols(): array
    {
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
                'field' => 'name',
                'minWidth' => 100,
                'title' => '通道名称',
                'align' => 'center'
            ],
            [
                'field' => 'weights',
                'minWidth' => 100,
                'title' => '权重',
                'align' => 'center'
            ],
            [
                'field' => 'pay_type',
                'minWidth' => 100,
                'title' => '支付方式',
                'align' => 'center'
            ],
            [
                'field' => 'success_rate',
                'minWidth' => 100,
                'title' => '成功率',
                'align' => 'center'
            ],
            [
                'field' => 'send_order',
                'minWidth' => 100,
                'title' => '发起请求',
                'align' => 'center'
            ],
            [
                'field' => 'success_order',
                'minWidth' => 100,
                'title' => '成功请求',
                'align' => 'center'
            ],
            [
                'field' => 'order_price',
                'minWidth' => 100,
                'title' => '订单金额',
                'align' => 'center'
            ],
            [
                'field' => 'match_amount',
                'minWidth' => 100,
                'title' => '匹配额度',
                'align' => 'center'
            ],
            [
                'field' => 'status',
                'minWidth' => 100,
                'title' => '状态',
                'align' => 'center'
            ],
            /*[
                'field' => 'remark',
                'minWidth' => 100,
                'title' => '备注',
                'align' => 'center'
            ],*/

            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ],
            [
                'field' => 'updated_at',
                'minWidth' => 150,
                'title' => '更新时间',
                'hide' => true,
                'align' => 'center'
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'align' => 'center'
            ]
        ];
    }

    public function setListOutputItemExtend($item)
    {
        $item->pay_type = match ($item->pay_type){
            $item->pay_type => $this->pay_type[$item->pay_type]['name'],
            default => '-',
        };
        $item->status = match ($item->status){
            0 => '关闭',
            1 => '开启',
            default => '-',
        };
//
        $redis = $this->redis();
        $rechargeChannelsKey = 'rechargeChannels_'.$item->pay_method.'_'.$item->pay_channel_code;
        $cacheItem = $redis->hGetAll($rechargeChannelsKey);

        if(!$cacheItem){
            $ordersBuild = Order::query()->where('pay_method',$item->pay_method)->where('pay_channel_code',$item->pay_channel_code);
            $sendOrder = $ordersBuild->count();
            $success_order = $ordersBuild->where('status',1)->count();
            $totalAmount = $ordersBuild->where('status',1)->sum('amount');
            dump($sendOrder);
            $redis->hMset($rechargeChannelsKey,[
                'send_order' => $sendOrder,
                'success_order' => $success_order,
                'order_price' => $totalAmount,
            ]);
            $redis->expire($rechargeChannelsKey,7200);
        }

        $item->send_order = $cacheItem['send_order']??'-';
        $item->success_order = $cacheItem['success_order']??'-';
        $item->success_rate = $cacheItem['order_price']??0 ? round($cacheItem['success_order']*100/$cacheItem['send_order'],2).'%' : '-';
        $item->order_price = $cacheItem['order_price']??'-';
        return $item;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $match_amount = $this->rq->input('match_nums',[]);
        $model->match_amount = json_encode($match_amount);
    }

    protected function afterSaveSuccessEvent($model, $id = '')
    {
        $this->redis()->del('recharge_channels_Z_1');
        $this->redis()->del('recharge_channels_Z_2');
        return $model;
    }

    public function setOutputUiCreateEditForm($show = '')
    {
        //dd(1);
        $data = [
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '通道名称',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'weights',
                'type' => 'number',
                'name' => '权重',
                'tips' => '范围0~100的区间值',
                'must' => 0,
            ],
            [
                'field' => 'prepayments',
                'type' => 'number',
                'name' => '预付款',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'match_nums',
                'type' => 'checkbox',
                'name' => '匹配金额',
                'value' => ($show && ($show->match_amount)) ? json_decode($show->match_amount,true) : [],
                'data' => $this->match_amount,
            ],
            [
                'field' => 'pay_channel',
                'type' => 'select',
                'name' => '支付渠道',
                'data' => $this->payChannel,
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'pay_type',
                'type' => 'select',
                'name' => '支付方式',
                'data' => $this->pay_type,
                'must' => 0,
            ],
            [
                'field' => 'remark',
                'type' => 'text',
                'name' => '备注',
                'must' => 0,
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '状态',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ]

        ];
        //赋值给UI数组里面,必须是form为key
        $this->uiBlade['form'] = $data;

    }

    //弹窗大小
    public function layuiOpenWidth(): string
    {
        return '55%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight(): string
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

    /*protected function afterSaveSuccessEvent($model, $id = ''): mixed
    {
        //设置缓存
        $cacheData = RechargeChannels::query()->where('status',1)->get();
        Cache::forever('recharge_channel',$cacheData);
        return $model;
    }*/
}