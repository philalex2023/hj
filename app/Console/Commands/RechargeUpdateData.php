<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RechargeUpdateData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:recharge {field?}';

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
        $field = $this->argument('field') ?? '';
        if($field=='user_type'){
            DB::table('recharge')->chunkById(1000,function ($recharge){
                foreach ($recharge as $item) {
                    //
                    $diffTime = strtotime($item->created_at)-strtotime($item->reg_at);
                    if( $diffTime >= 24*3600){
                        DB::table('recharge')->where('id',$item->id)->update(['user_type'=>1]);
                    }
                }
                $this->info('finished update records 1000!');
            });

        }
        $this->info('执行成功');
        return 0;
    }
}
