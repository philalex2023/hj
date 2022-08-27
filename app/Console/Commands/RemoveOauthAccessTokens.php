<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveOauthAccessTokens extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:tokens {day?}';

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
        $paramDay = $this->argument('day');
        $data_at = $paramDay!==null ? date('Y-m-d',strtotime('-'.$paramDay.' day')) : date('Y-m-d');
        $items = DB::table('oauth_access_tokens')->where('scopes','["check-user"]')->whereDate('expires_at','<',$data_at)->get(['id']);
        $bar = $this->output->createProgressBar(count($items));
        $bar->start();
        foreach ($items as $item){
            DB::table('oauth_access_tokens')->where('id',$item->id)->delete();
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行完成######');
        return 0;
    }
}
