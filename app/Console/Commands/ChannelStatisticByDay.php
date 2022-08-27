<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChannelStatisticByDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channelInit:dayData';

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
//        $this->initStatisticsByDay();
        $currentDate = date('Y-m-d');
        $statistic_table = 'channel_day_statistics';
        $channels = DB::connection('master_mysql')->table('channels')->where('status',1)->get();

        foreach ($channels as $channel) {
            $exists = DB::connection('master_mysql')->table($statistic_table)->where('channel_id', $channel->id)->where('date_at', $currentDate)->exists();
            if (!$exists) {
                $insertData = [
                    'principal' => $channel->principal,
                    'channel_name' => $channel->name,
                    'channel_id' => $channel->id,
                    'channel_pid' => $channel->pid,
                    'channel_type' => $channel->type,
                    'channel_promotion_code' => $channel->promotion_code,
                    'channel_code' => $channel->number,
                    'channel_status' => 1,
                    'unit_price' => $channel->unit_price,
                    'share_ratio' => $channel->share_ratio,
                    'total_recharge_amount' => 0,
                    'total_amount' => 0,
                    'total_orders' => 0,
                    'order_index' => 0,
                    'usage_index' => 0,
                    'share_amount' => 0,
                    'date_at' => $currentDate,
                ];
                DB::table($statistic_table)->insert($insertData);
            }
        }
        $this->info('######渠道日统计初始化数据执行成功######');
        //更新负责人信息
        DB::connection('master_mysql')->update('update channel_day_statistics a inner join channels c on a.channel_id=c.id set a.principal=c.principal');
        return 0;
    }
}
