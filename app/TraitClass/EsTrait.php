<?php

namespace App\TraitClass;

use Elasticsearch\ClientBuilder;

Trait EsTrait
{
    public function esClient(): \Elasticsearch\Client
    {
        return ClientBuilder::create()
            //->setHosts([env('ELASTICSEARCH_HOST')])
            ->build();
    }
}