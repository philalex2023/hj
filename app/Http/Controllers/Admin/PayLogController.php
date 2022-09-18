<?php

namespace App\Http\Controllers\Admin;

use App\Models\PayLog;
use App\TraitClass\PayTrait;
use Illuminate\Support\Facades\DB;

class PayLogController extends BaseCurlController
{
    use PayTrait;

    //设置页面的名称
    public $pageName = '支付日志';

    //1.设置模型
    public function setModel(): PayLog
    {
        return $this->model = new PayLog();
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
                'field' => 'uid',
                'minWidth' => 80,
                'title' => '用户ID',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'channel_id',
                'minWidth' => 80,
                'title' => '渠道',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'number',
                'width' => 150,
                'title' => '订单编号',
                'align' => 'center',
            ],
            [
                'field' => 'amount',
                'width' => 150,
                'title' => '订单金额',
                'align' => 'center',
                'edit' => 1
            ],
            [
                'field' => 'type',
                'minWidth' => 80,
                'title' => '订单类型',
                'align' => 'center',
            ],
            [
                'field' => 'request_info',
                'minWidth' => 150,
                'title' => '支付请求参数',
                'hide' => true,
                'align' => 'center',
            ],
            [
                'field' => 'remark',
                'minWidth' => 150,
                'title' => '备注',
                'hide' => true,
                'align' => 'center',
            ],
            /*[
                'field' => 'status',
                'minWidth' => 80,
                'title' => '状态',
                'align' => 'center',
            ],*/
            [
                'field' => 'systemPlatform',
                'minWidth' => 150,
                'title' => '手机系统平台',
                'align' => 'center',
            ],
            [
                'field' => 'pay_method_name',
                'minWidth' => 80,
                'title' => '充值类型',
                'align' => 'center',
            ],
            [
                'field' => 'channel_code',
                'minWidth' => 80,
                'title' => '通道码',
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ],
        ];

    }

    public function setOutputHandleBtnTpl($shareData)
    {
        $this->uiBlade['btn'] = [];
    }

    public function setListOutputItemExtend($item)
    {
        $deviceSystems = [
            0 => '',
            1 => 'ios轻量版',
            2 => '安卓',
            3 => 'ios轻量版',
        ];
        $types = [
            1 => '会员卡',
            2 => '骚豆',
        ];
        $item->type = $types[$item->order->type];
        $item->amount = $item->order->amount;
        //$item->amount = round($item->amount/100,2);

        /*if ($item->status == 1) {
            $item->status = '完成';
        } else {
            $item->status = '未付';
        }*/
        $channel_name = $item->channel_name;
        $item->channel_id = $channel_name . '(' . $item->order->channel_id . ')';
        $payChannels = $this->getPayChannels();
        $item->pay_method_name = $payChannels[$item->pay_method]??'-';
        $item->systemPlatform = $deviceSystems[$item->device_system];
        return $item;
    }

    public function setOutputSearchFormTpl($shareData)
    {

        $data = [
            [
                'field' => 'query_uid',
                'type' => 'text',
                'name' => '会员ID',
            ],
            [
                'field' => 'number',
                'type' => 'text',
                'name' => '订单编号',
            ],
            /*[
                'field' => 'status',
                'type' => 'select',
                'name' => '状态',
                'default' => '',
                'data' => [
                    '' => [
                        'id' => '',
                        'name' => '全部',
                    ], 0 => [
                        'id' => 0,
                        'name' => '未付',
                    ], 1 => [
                        'id' => 1,
                        'name' => '完成',
                    ],
                ]
            ],*/
            [
                'field' => 'created_at',
                'type' => 'datetime',
//                'attr' => 'data-range=true',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '时间范围',
            ],
            [
                'field' => 'query_pay_method',
                'type' => 'select',
                'name' => '充值类型',
                'data' => $this->getAllPayChannel()
            ],
            [
                'field' => 'query_channel_code',
                'type' => 'select',
                'name' => '通道码',
                'data' => array_merge(['0' => ['id' => '0', 'name' => '全部']], $this->getPayTypeCode())
            ],
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function handleResultModel($model): array
    {
        $page = $this->rq->input('page', 1);
        $pagesize = $this->rq->input('limit', 30);

        $order_by_name = $this->orderByName();
        $order_by_type = $this->orderByType();
        $model = $this->orderBy($model, $order_by_name, $order_by_type);

        $build = $model;

        $queryPayMethod = $this->rq->input('query_pay_method', 0);
        if ($queryPayMethod > 0) {
            $build = $build->where('pay_method', $queryPayMethod);
        }
        $queryChannelCode = $this->rq->input('query_channel_code', 0);
        if ($queryChannelCode > 0) {
            $build = $build->where('channel_code', $queryChannelCode);
        }
        $queryUid = $this->rq->input('query_uid', 0);
        if ($queryUid > 0) {
            $build = $build->where('uid', $queryUid);
        }
        $queryStatus = $this->rq->input('status', '');
        if ($queryStatus != '') {
            $build = $build->where('status', $queryStatus);
        }
        $queryNumber = $this->rq->input('number', '');
        if ($queryNumber != '') {
            $build = $build->where('number', $queryNumber);
        }
        $created_at = $this->rq->input('created_at', null);
        if ($created_at !== null) {
            $dateArr = explode('~', $created_at);
            if (isset($dateArr[0]) && isset($dateArr[1])) {
                $build = $build->whereBetween('created_at', [trim($dateArr[0]), trim($dateArr[1])]);
            }
        }

        $total = $build->count();

        $currentPageData = $build->forPage($page, $pagesize)->get();
        //$this->listOutputJson($total, $currentPageData, 0);
        return [
            'total' => $total,
            'result' => $currentPageData
        ];
    }

}