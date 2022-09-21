<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCollectionBbs;
use App\Jobs\ProcessGetApiVideo;
use App\Models\CommBbs;
use App\TraitClass\CurlTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use AWS\CRT\Log;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CollectionBbs extends Command
{
    use PHPRedisTrait,DispatchesJobs,CurlTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:bbs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $calc = 0;
        $limit = 5;
        for ($i=487807; $i<499807; ++$i){
            $apiUrl = 'https://www.hjedd.com/api/topic/'.$i;
            $response = (new Client([
                'headers' => ['Content-Type' => 'application/json']
            ]))->get($apiUrl, [
                'verify' => false
            ])->getBody()->getContents();
            //
//        print_r(json_decode($response,true));
            $resArr = json_decode($response,true);
            if($resArr['success']==1){
                $job = new ProcessCollectionBbs($resArr,$i);
                $this->dispatch($job->onQueue('high'));
                ++$calc;
                if($calc==$limit){
                    break;
                }
                $this->info('doing ID:'.$i);
            }else{
                $this->info('error:'.$response);
            }
        }

        return 0;


    }
}
