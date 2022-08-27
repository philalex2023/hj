<?php

namespace App\ExtendClass;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class ClientRepository extends \Laravel\Passport\ClientRepository
{
    use PHPRedisTrait;
    /**
     * Get a client by the given ID.
     *
     * @param  int  $id
     * @return \Laravel\Passport\Client|null
     */
    public function find($id)
    {
        $key = 'api_passport_client_'.$id;
        return Cache::remember($key, 86400,
            function () use ($id) {
                $client = Passport::client();
                return $client->where($client->getKeyName(), $id)->first();
            }
        );
    }
}