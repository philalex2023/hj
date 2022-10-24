<?php

namespace App\TraitClass;

use Elasticsearch\ClientBuilder;

Trait EsTrait
{
    public function esClient(): \Elasticsearch\Client
    {
        return ClientBuilder::create()
            ->setHosts([env('ELASTICSEARCH_HOST')])
            ->build();
    }

    public function getVideoByIdsForEs($ids,$source,$size=10000,$page=1): array
    {
        $offset = ($page-1)*$size;
        $searchParams = [
            'index' => 'video_index',
            'body' => [
                'track_total_hits' => true,
                'size' => $size,
                'from' => $offset,
                '_source' => $source,
                'query' => [
                    'bool'=>[
                        'must' => [
                            ['terms' => ['id'=>$ids]],
                            //['term' => ['dev_type'=>0]],
                        ]
                    ]
                ]
            ],
        ];

        $es = $this->esClient();
        $response = $es->search($searchParams);
        //Log::info('searchParam_home_list',[json_encode($searchParams)]);
        $videoList = [];
        if(isset($response['hits']) && isset($response['hits']['hits'])){
            foreach ($response['hits']['hits'] as $item) {
                $videoList[] = $item['_source'];
            }
        }
        return $videoList;
    }
}