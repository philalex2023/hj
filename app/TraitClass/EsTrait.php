<?php

namespace App\TraitClass;

use Elasticsearch\ClientBuilder;
use GuzzleHttp\Client;

Trait EsTrait
{
    public function esClient(): \Elasticsearch\Client
    {
        return ClientBuilder::create()
            ->setHosts([env('ELASTICSEARCH_HOST')])
            ->build();
    }

    public function esGet(array $params): array
    {
        $curl = (new Client([
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => false,
        ]))->get('http://'.env('ELASTICSEARCH_HOST'), ['json' => $params]);
        $response = $curl->getBody();
        $res = @json_decode($response, true);
        return !$res? [] : (array)$res;
    }
}