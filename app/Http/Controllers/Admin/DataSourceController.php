<?php

namespace App\Http\Controllers\Admin;

use App\Models\DataSource;
use App\TraitClass\CatTrait;
use App\TraitClass\CommTrait;
use App\TraitClass\TagTrait;


class DataSourceController extends BaseCurlController
{
    use CatTrait,TagTrait,CommTrait;

    public $pageName = '数据源';

    public array $cats=[];

    public array $tags=[];

    public array $deviceType=[
        0 => ['id'=>0,'name'=>'横版'],
        1 => ['id'=>1,'name'=>'竖版'],
    ];

    public array $dataType = [
        1 => ['id'=>1,'name'=>'标签'],
        2 => ['id'=>2,'name'=>'关键字'],
        3 => ['id'=>3,'name'=>'分类'],
        4 => ['id'=>4,'name'=>'最新上架'],
        5 => ['id'=>5,'name'=>'自定义'],
    ];

    public function setModel(): DataSource
    {
        $this->cats = $this->getCatNavData();
        $this->tags = $this->getTagData();
        return $this->model = new DataSource();
    }

    public function indexCols(): array
    {
        return [
            /*[
                'type' => 'checkbox'
            ],*/
            [
                'field' => 'id',
                'width' => 80,
                'title' => '编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'name',
                'minWidth' => 150,
                'title' => '数据源名称',
                'align' => 'center',
            ],
            [
                'field' => 'video_type',
                'minWidth' => 150,
                'title' => '视频类型',
                'align' => 'center',
            ],
            [
                'field' => 'data_type',
                'minWidth' => 150,
                'title' => '数据类型',
                'align' => 'center',
            ],
            [
                'field' => 'data_value',
                'minWidth' => 150,
                'title' => '数据值',
                'align' => 'center',
            ],
            [
                'field' => 'show_num',
                'minWidth' => 150,
                'title' => '首页展示量',
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ],
            [
                'field' => 'updated_at',
                'minWidth' => 150,
                'hide' => true,
                'title' => '更新时间',
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

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '数据源名称',
                'must' => 1,
                'verify' => 'rq',
                'default' => '',
            ],
            [
                'field' => 'video_type',
                'type' => 'select',
                'name' => '视频类型',
                'must' => 1,
                'verify' => 'rq',
                'data' => $this->deviceType
            ],
            [
                'field' => 'data_type',
                'type' => 'select',
                'name' => '数据类型',
                'data' => $this->dataType
            ],
            [
                'field' => 'data_value',
                'type' => 'text',
                'name' => '数据值',
            ],
            [
                'field' => 'cid',
                'type' => 'select',
                'name' => '分类',
                'must' => 0,
                'verify' => '',
                'data' => $this->cats
            ],
            [
                'field' => 'tags',
                'type' => 'checkbox',
                'name' => '标签',
                'value' => ($show && ($show->tag)) ? json_decode($show->tag,true) : [],
                'data' => $this->tags
            ],
            /*[
                'field' => 'sort',
                'type' => 'number',
                'name' => '排序',
                'default' => 0,
            ],*/
            /*[
                'field' => 'status',
                'type' => 'radio',
                'name' => '状态',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ],*/
        ];
        $this->uiBlade['form'] = $data;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $model->tag = $this->rq->input('tags',[]);
        if(!empty($model->tag)){
            $tagName = [];
            foreach ($model->tag as $v){
                $tagName[] = $this->tags[$v]['name'];
            }
            $model->data_value = implode(',',$tagName);
        }
        $model->tag = json_encode($model->tag);
    }

    protected function afterSaveSuccessEvent($model, $id = '')
    {

        return $model;
    }
    //表单验证
   /* public function checkRule($id = '')
    {
        $data = [
            'name'=>'required|unique:tag,name',
        ];
        //$id值存在表示编辑的验证
        if ($id) {
            $data['name'] = 'required|unique:tag,name,' . $id;
        }
        return $data;
    }

    public function checkRuleFieldName($id = '')
    {
        return [
            'name'=>'标签名称',
        ];
    }*/

    public function setListOutputItemExtend($item)
    {
        $item->video_type = $this->deviceType[$item->video_type]['name'];
        $item->data_type = $this->dataType[$item->data_type]['name'];
        $item->show_num = match ($item->show_num){
            0 => '-',
            default => $item->show_num,
        };
        return $item;
    }

    //弹窗大小
    public function layuiOpenWidth()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

}