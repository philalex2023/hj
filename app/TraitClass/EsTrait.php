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

    public function handleEsResponse($response): array
    {
        $videoList = [];
        if(isset($response['hits']) && isset($response['hits']['hits'])){
            foreach ($response['hits']['hits'] as $item) {
                $videoList[] = $item['_source'];
            }
        }
        return $videoList;
    }

    public function getVideoByRandomForEs($limit,$source): array
    {
        $body = [
//            'track_total_hits' => true,
            'size' => $limit,
            '_source' => $source,
            'sort' => [
                '_script'=>[
                    'script' => 'Math.random()',
                    'type' => 'number',
                    'order' => 'asc',
                ]
            ]
        ];
        $searchParams = [
            'index' => 'video_index',
            'body' => $body
        ];

        $es = $this->esClient();
        $response = $es->search($searchParams);
        //Log::info('searchParam_home_list',[json_encode($searchParams)]);
        return $this->handleEsResponse($response);
    }

    public function getVideoByIdsForEs($ids,$source,$size=200): array
    {
        $body = [
//            'track_total_hits' => true,
            'size' => $size,
            '_source' => $source,
            'query' => [
                'bool'=>[
                    'must' => [
                        ['terms' => ['id'=>$ids]],
                        //['term' => ['dev_type'=>0]],
                    ]
                ]
            ]
        ];

        $searchParams = [
            'index' => 'video_index',
            'body' => $body
        ];

        $es = $this->esClient();
        $response = $es->search($searchParams);
        return $this->handleEsResponse($response);
    }
}