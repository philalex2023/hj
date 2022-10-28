<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearHistoryData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:historyData {day?}';

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
//        $paramDay = $this->argument('day') ?? 3;
        //登录日志
//        $delLoginLogTime = strtotime('-'.$paramDay.' day');
//        DB::table('login_log')->whereDate('created_at', '<',date('Y-m-d H:i:s',$delLoginLogTime))->delete();
        DB::table('users')
            ->where('is_office',0)
            ->where('gold',0)
            ->where('member_card_type',0)
            ->whereDate('updated_at','<',date('Y-m-d',strtotime('-30 day')))
            //->take(5)
            //->orderByDesc('id')
            ->chunkById(1000,function ($users){
                foreach ($users as $user) {
                    //
                    DB::table('users')->where('id',$user->id)->delete();
                }
                $this->info('finished del 1000!');
            });
        $this->info('执行成功');
        return 0;
    }
}
