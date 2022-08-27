<?php

namespace App\Console\Commands;

use App\Exports\Export;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\SDTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportExcelFile extends Command
{
    use PHPRedisTrait, SDTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'download:excel';

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

    public function getTenDaysUsersData($startDate,$endDate): array
    {
        $fields = 'SUM(install) as install,SUM(active_users) as active_users,at_time,
        SUM(keep_1) as keep_1,
        SUM(keep_2) as keep_2,
        SUM(keep_3) as keep_3,
        SUM(keep_4) as keep_4,
        SUM(keep_5) as keep_5,
        SUM(keep_6) as keep_6,
        SUM(keep_7) as keep_7,
        SUM(keep_8) as keep_8,
        SUM(keep_9) as keep_9,
        SUM(keep_10) as keep_10';
        return DB::table('statistic_day')
            ->select(DB::raw($fields))
            ->where('at_time','>=',strtotime($startDate))
            ->where('at_time','<=',strtotime($endDate))
            ->groupBy('at_time')
            ->orderByDesc('at_time')
            ->get()->toArray();
    }

    public function getLastMonthUsersData($startDate,$endDate): array
    {
        $fields = 'SUM(install) as install,SUM(active_users) as active_users,
        SUM(keep_1) as keep_1,
        SUM(keep_2) as keep_2,
        SUM(keep_3) as keep_3,
        SUM(keep_4) as keep_4,
        SUM(keep_5) as keep_5,
        SUM(keep_6) as keep_6,
        SUM(keep_7) as keep_7,
        SUM(keep_8) as keep_8,
        SUM(keep_9) as keep_9,
        SUM(keep_10) as keep_10';
        $buildQuery = DB::table('statistic_day')->select(DB::raw($fields));
        $res = $buildQuery
            ->where('at_time','>=',strtotime($startDate))
            ->where('at_time','<=',strtotime($endDate))
            ->get()[0];
        return (array)$res;
    }

    public function getRechargeDataByDate($startDate,$endDate): \Illuminate\Support\Collection
    {
        return DB::table('recharge')
            ->select(DB::raw('recharge.type,recharge.amount,recharge.uid,recharge.created_at,users.created_at as reg_at'))
            ->join('users','recharge.uid','=','users.id')
            ->where('recharge.created_at','>=',$startDate.' 00:00:00')
            ->where('recharge.created_at','<=',$endDate.' 23:59:59')
            ->get();
    }

    public function columnExtendMonthKeep($lastMonthData,$days=30): array
    {
        $avgInstall = round(((int)$lastMonthData['install'])/$days);
        if($lastMonthData['keep_1']==0){
            $ratio = round(((intval($lastMonthData['active_users'])-(int)$lastMonthData['install'])/(int)$lastMonthData['install'])/30,4);
            return [
                'yesterday_keep'=>$ratio,
                'two_day_keep'=>$ratio,
                'three_day_keep'=>$ratio,
                'four_day_keep'=>$ratio,
                'five_day_keep'=>$ratio,
                'six_day_keep'=>$ratio,
                'server_day_keep'=>$ratio,
                'eight_day_keep'=>$ratio,
                'nine_day_keep'=>$ratio,
                'ten_day_keep'=>$ratio,
            ];
        }
        return [
            'yesterday_keep'=>$this->calcKeepRation($lastMonthData['keep_1'], $avgInstall),
            'two_day_keep'=>$this->calcKeepRation($lastMonthData['keep_2'], $avgInstall),
            'three_day_keep'=>$this->calcKeepRation($lastMonthData['keep_3'], $avgInstall),
            'four_day_keep'=>$this->calcKeepRation($lastMonthData['keep_4'], $avgInstall),
            'five_day_keep'=>$this->calcKeepRation($lastMonthData['keep_5'], $avgInstall),
            'six_day_keep'=>$this->calcKeepRation($lastMonthData['keep_6'], $avgInstall),
            'server_day_keep'=>$this->calcKeepRation($lastMonthData['keep_7'], $avgInstall),
            'eight_day_keep'=>$this->calcKeepRation($lastMonthData['keep_8'], $avgInstall),
            'nine_day_keep'=>$this->calcKeepRation($lastMonthData['keep_9'], $avgInstall),
            'ten_day_keep'=>$this->calcKeepRation($lastMonthData['keep_10'], $avgInstall),
        ];
    }

    public function calcKeepRation($keepDayUsers, $install): float
    {
        if($install==0){
            return 0;
        }
        return round(intval($keepDayUsers)/(int)$install,4);
    }

    public function columnExtendDaysKeep($tenDaysDataCollection): array
    {
        $install = $tenDaysDataCollection[9]->install;
        $yesterdayCollection = $tenDaysDataCollection[0];
        return [
            'yesterday_keep'=>$this->calcKeepRation($yesterdayCollection->keep_1,$install),
            'two_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_2,$install),
            'three_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_3,$install),
            'four_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_4,$install),
            'five_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_5,$install),
            'six_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_6,$install),
            'server_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_7,$install),
            'eight_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_8,$install),
            'nine_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_9,$install),
            'ten_day_keep'=>$this->calcKeepRation($yesterdayCollection->keep_10,$install),
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \RedisException
     */
    public function handle()
    {
        $lastMonthTime = strtotime('-1 month');
        $startDate = date('Y-m',$lastMonthTime).'-01';
        $lastMonthDays = date('t',$lastMonthTime);
        $endDate = date('Y-m',$lastMonthTime).'-'.$lastMonthDays;
        $yesterdayDate = date("Y-m-d",strtotime("-1 day"));

        $lastMonthData = $this->getLastMonthUsersData($startDate,$endDate);
        //昨日
        $tenDaysDataCollection = $this->getTenDaysUsersData(date("Y-m-d",strtotime("-10 day")),$yesterdayDate);
        $yesterdayData = (array)$tenDaysDataCollection[0];

        $lastRecharge = $this->getRechargeDataByDate($startDate,$endDate);

        $yesterdayRecharge = $this->getRechargeDataByDate($yesterdayDate,$yesterdayDate);

        $lastRechargeData = $this->columnExtend($lastRecharge,$lastMonthData);
        $yesterdayRechargeData = $this->columnExtend($yesterdayRecharge,$yesterdayData);

        $lastMonthKeepData = $this->columnExtendMonthKeep($lastMonthData,$lastMonthDays);
        $yesterdayKeepData = $this->columnExtendDaysKeep($tenDaysDataCollection);

        $lastMonthData = [...$lastMonthData,...$lastRechargeData,...$lastMonthKeepData];
        $yesterdayData = [...$yesterdayData,...$yesterdayRechargeData,...$yesterdayKeepData];
        $this->info(json_encode($tenDaysDataCollection));
//        $this->info(json_encode($lastMonthData));
//        $this->info(json_encode($yesterdayData));

        $columns = $this->getSDColumns();
        foreach ($columns as $key => &$item){
            $key=='register' && $key='install';
            $key=='login' && $key='active_users';
            ($key=='item_revenue_amount' || $key=='online_recharge_amount') && $key='total_recharge_amount';

            $c_value = (float)($yesterdayData[$key]??0);
            $l_value = (float)($lastMonthData[$key]??0);

            $keepRatio = str_contains($key,'day_keep');
            $ratio = str_contains($key,'ratio') || str_contains($key,'Ratio') || $keepRatio;
            $avg_value = $ratio ? $l_value : $l_value/(int)$lastMonthDays;
            $keepRatio && $avg_value = $l_value/(int)$lastMonthDays;
            $diffValue = round($c_value - $avg_value,$ratio ? 4 : 0);
            $absValue = abs($diffValue);
            if($ratio){
                $keepRatio && $c_value==0 && $diffValue=0;
                $this->info($c_value.'-'.$avg_value);
                $this->info($key.'=>'.$absValue);
            }
            $ratio && $absValue = ($absValue*100).'%';
            $ratio && $c_value = ($c_value*100).'%';
            $diffText = $absValue.($diffValue>0 ? '↑' : '↓');
            $diffValue==0 && $diffText='-';
            $item['value'] = $c_value!='' ? (str_contains($key,'amount') ? round($c_value/100,2) : $c_value).' ' : '-';
            $item['trend'] = $diffText;
        }

        $this->info(json_encode($columns));
        $downloadData = [
            'header' => ['项目','SAOL'],
            'fields' => ['name','value','trend'],
            'data' => $columns
        ];

        //发送到机器人通知服务器
        $fileName = 'SAOL数据('.$yesterdayDate.')-'.date('His').'.xls';
        $filePath = '/excel/'.$fileName;
        $export = new Export($downloadData);
        $bool = Excel::store($export, $filePath,'local');
//        return 0;
        $content = Storage::get($filePath);
        $putRes = Storage::disk('winSftp')->put($fileName,$content);
        $bool && $this->info('文件'.$filePath.'保存成功');
        $putRes && $this->info('文件'.$filePath.'发送成功');
        !$putRes && $this->info('文件'.$filePath.'发送失败');
        return 0;
    }

    public function columnExtend($data,$userData): array
    {
        $rechargeData = [
            'active_recharge_ratio' => 0,
            'increase_recharge_users' => 0,
            'increase_recharge_amount' => 0,
            'increase_recharge_ratio' => 0,
            'old_user_recharge_users' => 0,
            'old_user_recharge_amount' => 0,
            'recharge_users' => 0,//
            'total_recharge_amount' => 0,//
            'buy_vip_users' => 0,//
            'buy_vip_amount' => 0,//
            'buy_gold_users' => 0,//
            'buy_gold_amount' => 0,//
            'ARPU' => 0,//
            'ARPPU' => 0,//
            'item_revenue_ratio' => 1,//
            'online_recharge_ratio' => 1,//
        ];

        foreach ($data as $item)
        {
            $rechargeData['total_recharge_amount'] += $item->amount;
            ++$rechargeData['recharge_users'];
            if(date('Y-m-d',strtotime($item->created_at)) == date('Y-m-d',strtotime($item->reg_at))){
                $rechargeData['increase_recharge_amount'] += $item->amount;
                ++$rechargeData['increase_recharge_users'];
            }
            if($item->type == 1){
                $rechargeData['buy_vip_amount'] += $item->amount;
                ++$rechargeData['buy_vip_users'];
            }
        }

        if($rechargeData['total_recharge_amount']>0){
            $rechargeData['old_user_recharge_amount'] = $rechargeData['total_recharge_amount'] - $rechargeData['increase_recharge_amount'];
            $rechargeData['old_user_recharge_users'] = $rechargeData['recharge_users'] - $rechargeData['increase_recharge_users'];
            $rechargeData['active_recharge_ratio'] = round($rechargeData['total_recharge_amount'] / $userData['active_users'],4);
            $rechargeData['buy_gold_users'] = $rechargeData['recharge_users'] - $rechargeData['buy_vip_users'];
            $rechargeData['buy_gold_amount'] = $rechargeData['total_recharge_amount'] - $rechargeData['buy_vip_amount'];
            $rechargeData['ARPU'] = round($rechargeData['total_recharge_amount'] / $userData['active_users'],2);
            $rechargeData['ARPPU'] = round($rechargeData['total_recharge_amount'] / $rechargeData['recharge_users'],2);
        }

        if($rechargeData['old_user_recharge_amount'] > 0){
            $rechargeData['increase_recharge_ratio'] = round($rechargeData['old_user_recharge_amount'] / $rechargeData['total_recharge_amount'],4);
        }
        return $rechargeData;
    }

}
