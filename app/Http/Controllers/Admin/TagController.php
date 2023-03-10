<?php

namespace App\Http\Controllers\Admin;

use App\Models\Tag;
use App\TraitClass\PHPRedisTrait;

class TagController extends BaseCurlController
{
    use PHPRedisTrait;

    public $pageName = '标签';

    public function setModel()
    {
        return $this->model = new Tag();
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
                'field' => 'sort',
                'width' => 80,
                'title' => '排序',
                'sort' => 1,
                'align' => 'center',
                'edit' => 1
            ],
            [
                'field' => 'hits',
                'minWidth' => 80,
                'title' => '点击数',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'name',
                'minWidth' => 150,
                'title' => '标签名称',
                'align' => 'center',
            ],
            [
                'field' => 'usageName',
                'minWidth' => 150,
                'title' => '标签用途',
                'align' => 'center',
            ],
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

    }

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '标签名称',
                'must' => 1,
                'default' => '',
            ],
            [
                'field' => 'usage',
                'type' => 'select',
                'name' => '标签用途',
                'data' => [['id' => '1', 'name' => '长视频'],['id' => '2', 'name' => '小视频']]

            ],
            [
                'field' => 'sort',
                'type' => 'number',
                'name' => '排序',
                'must' => 0,
                'default' => 0,
            ],
        ];
        $this->uiBlade['form'] = $data;
    }

    //表单验证
    public function checkRule($id = '')
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
    }

    public function setListOutputItemExtend($item)
    {
        $item->usageName = ($item->usage==1)?"长视频":"小视频";
        return $item;
    }

    /*public function afterSaveSuccessEvent($model, $id = '')
    {
        //请除缓存
        $redis = $this->redis();
        $redis->set('tag_fresh',1);
        $redis->expire('tag_fresh',3600*24);
    }*/
}