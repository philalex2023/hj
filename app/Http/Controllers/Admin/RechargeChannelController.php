<?php


namespace App\Http\Controllers\Admin;


use App\Models\RechargeChannel;
use App\Services\UiService;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;

class RechargeChannelController extends BaseCurlController
{
    use PHPRedisTrait;
    public $pageName = "充值渠道";

    public function setModel()
    {
        return $this->model = new RechargeChannel();
    }

    public function indexCols()
    {
        $cols = [
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
                'title' => '渠道名称',
                'align' => 'center'
            ],
            [
                'field' => 'merchant_id',
                'minWidth' => 100,
                'title' => '商户ID',
                'align' => 'center'
            ],
            [
                'field' => 'secret',
                'minWidth' => 100,
                'title' => '密钥',
                'align' => 'center'
            ],
            [
                'field' => 'pay_url',
                'minWidth' => 100,
                'title' => '支付请求地址',
                'align' => 'center'
            ],
            [
                'field' => 'notify_url',
                'minWidth' => 100,
                'title' => '回调地址',
                'align' => 'center'
            ],
            /*[
                'field' => 'query_url',
                'minWidth' => 100,
                'title' => '查询接口地址',
                'align' => 'center'
            ],*/
            [
                'field' => 'status',
                'minWidth' => 100,
                'title' => '状态',
                'align' => 'center',
            ],
            /*[
                'field' => 'other_url',
                'minWidth' => 100,
                'title' => '其它地址',
                'align' => 'center'
            ],*/
            [
                'field' => 'whitelist',
                'minWidth' => 100,
                'title' => '白名单',
                'align' => 'center'
            ],
            [
                'field' => 'remark',
                'minWidth' => 100,
                'title' => '支付名称',
                'align' => 'center'
            ],
            /*[
                'field' => 'action_url',
                'minWidth' => 100,
                'title' => '支付接口地址',
                'align' => 'center'
            ],*/
            /*[
                'field' => 'type',
                'minWidth' => 100,
                'title' => '渠道类型',
                'align' => 'center'
            ],*/
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'align' => 'center'
            ]
        ];
        return $cols;
    }

    public function setListOutputItemExtend($item)
    {
        $item->status = UiService::switchTpl('status', $item,'');
        return $item;
    }

    public function setOutputUiCreateEditForm($show = '')
    {

        $data = [
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '渠道名称',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'merchant_id',
                'type' => 'text',
                'name' => '商户号',
                'must' => 1,
            ],
            [
                'field' => 'secret',
                'type' => 'text',
                'name' => '密钥',
                'must' => 1,
            ],
            [
                'field' => 'pay_url',
                'type' => 'text',
                'name' => '充值请求地址',
                'must' => 1,
            ],
            [
                'field' => 'notify_url',
                'type' => 'text',
                'name' => '回调地址',
                'must' => 1,
            ],
            [
                'field' => 'query_url',
                'type' => 'text',
                'name' => '查询接口地址',
                'must' => 0,
            ],
            [
                'field' => 'other_url',
                'type' => 'text',
                'name' => '其它地址',
                'must' => 0,
            ],
            [
                'field' => 'whitelist',
                'type' => 'text',
                'name' => '白名单',
                'tips' => '(多个逗号分隔)',
                'must' => 1,
            ],
            [
                'field' => 'remark',
                'type' => 'text',
                'name' => '支付名称',
                'must' => 0,
            ],
            /*[
                'field' => 'action_url',
                'type' => 'text',
                'name' => '支付接口地址',
                'must' => 0,
            ],*/
            [
                'field' => 'wx_code',
                'type' => 'text',
                'name' => '微信通道码',
                'must' => 0,
            ],
            [
                'field' => 'zfb_code',
                'type' => 'text',
                'name' => '支付宝通道码',
                'must' => 0,
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '是否启用',
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

    /**
     * 清理缓存
     * @param $model
     * @param string $id
     * @return mixed
     */
    protected function afterSaveSuccessEvent($model, $id = ''): mixed
    {
        //清除缓存
        Cache::forget('recharge_channel');
        $this->redis()->del('recharge_channel_'.$id);
        return $model;
    }
}