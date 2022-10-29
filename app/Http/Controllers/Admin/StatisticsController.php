<?php

namespace App\Http\Controllers\Admin;

use App\TraitClass\StatisticTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends BaseCurlController
{
    use StatisticTrait;
    //去掉公共模板
    public $commonBladePath = '';
    public $pageName = '统计图表';

    public function index()
    {
        $channels = DB::table('channels')->where('status',1)->pluck('name','id')->all();
        $channels = [ ''=>'全部',0 => '官方'] + $channels;
        return $this->display(['channels' => $channels]);
    }

    public function getList(Request $request)
    {
        $json = [];
        $channelId = $request->input('channel_id');
        $deviceSystem = $request->input('deviceSystem',0);
        //时间范围
        $startDate = '2022-02-01 00:00:00';
        $endDate = date('Y-m-d').' 23:59:59';
        $timeRange = $request->input('range_date', 0);
        if($timeRange > 0){
            $timeRangeArr = explode('~',$timeRange);
            $startDate = trim($timeRangeArr[0].' 00:00:00');
            $endDate = trim($timeRangeArr[1].' 23:59:59');
        }

        $fakeMultiple = 1;
        switch ($request->input('type','')){
            case 'totalStatistic':
                $fields = 'SUM(access) as total_access,SUM(hits) as total_hits,SUM(install) as total_install';
                $queryBuild = DB::table('statistic_day')->select(DB::raw($fields));
                if($channelId!==null){
                    $queryBuild = $queryBuild->where('channel_id',$channelId);
                }

                if( $deviceSystem>0 ){
                    $queryBuild = $queryBuild->where('device_system',$deviceSystem);
                }

                $queryBuild = $queryBuild
                        ->where('at_time','>=',strtotime($startDate))
                        ->where('at_time','<=',strtotime($endDate));

                $totalData = $queryBuild->orderByDesc('at_time')->limit(30)->get()[0];

                // 修正安装量与注册量
                /* $newData = $this->fixDataByUserTable($channelId, $deviceSystem, $timeRange, $startDate, $endDate);
                $totalData->total_install = $newData['newInstall']; */

                $json = [
                    'access' => $totalData->total_access ?? 0,
                    'hits' => $totalData->total_hits ?? 0,
                    'install' => $totalData->total_install ?? 0,
                ];
                break;
            case 'increment':
                $fields = 'SUM(access) as total_access,
                SUM(hits) as total_hits,
                SUM(install) as total_install';
                $queryBuild = DB::table('statistic_day')->select('at_time',DB::raw($fields));
                if($channelId!==null){
                    $queryBuild = $queryBuild->where('channel_id',$channelId);
                }
                if( $deviceSystem>0 ){
                    $queryBuild = $queryBuild->where('device_system',$deviceSystem);
                }
                 $queryBuild = $queryBuild
                        ->where('at_time','>=',strtotime($startDate))
                        ->where('at_time','<=',strtotime($endDate));
                $totalData = $queryBuild->groupBy('at_time')->orderByDesc('at_time')->limit(15)->get();
                $totalData = array_reverse($totalData->toArray());
                foreach ($totalData as $item){
                    $json['x'][] = date('Y-m-d',$item->at_time);
                    $json['series']['total_access'][] = ($item->total_access??0)*$fakeMultiple;
                    $json['series']['total_hits'][] = ($item->total_hits??0)*$fakeMultiple;
                    $json['series']['total_install'][] = ($item->total_install??0)*$fakeMultiple;
                }
                break;
            case 'activeUsers':
                //$queryBuild = DB::table('users_day')->select('at_time',DB::raw('count(uid) as users'));
                //$queryBuild = DB::table('login_log')->select('created_at',DB::raw('count(id) as users,cast(created_at AS date) AS at_date'));
                $queryBuild = DB::table('statistic_day')->select('at_time',DB::raw('sum(active_users) as users'));
                if($channelId!==null){
                    $queryBuild = $queryBuild->where('channel_id',$channelId);
                }
                if( $deviceSystem>0 ){
                    $queryBuild = $queryBuild->where('device_system',$deviceSystem);
                }
                // if($timeRange != 0){
                    $queryBuild = $queryBuild
                        ->where('at_time','>=',strtotime($startDate))
                        ->where('at_time','<=',strtotime($endDate));
                // }
                $activeUsers = $queryBuild->groupBy(['at_time'])->orderByDesc('at_time')->take(15)->get();
                $activeUsers = array_reverse($activeUsers->toArray());
                foreach ($activeUsers as $activeUser){
                    $json['x'][] = date('Y-m-d',$activeUser->at_time) ?? '-';
                    $json['y'][] = round($activeUser->users * $fakeMultiple);
                }
                break;
            case 'recharge':
                $queryBuild = DB::table('orders')->where('status',1)->select(DB::raw('DATE_FORMAT(orders.created_at,"%Y-%m-%d") days'),DB::raw('SUM(amount) as money'));
                if($channelId!==null){
                    $queryBuild = $queryBuild
                        ->where('channel_id',$channelId);
                }
                if( $deviceSystem>0 ){
                    $queryBuild = $queryBuild->where('device_system',$deviceSystem);
                }
                // if($timeRange != 0){
                    $queryBuild = $queryBuild->whereBetween('orders.created_at',[$startDate,$endDate]);
                // }
                $items = $queryBuild->groupBy('days')->orderByDesc('days')->limit(15)->get();
                $items = array_reverse($items->toArray());
                $X = [];
                $Y = [];
                foreach ($items as $item){
                    $X[] = $item->days;
                    $Y[] = $item->money*$fakeMultiple;
                }
                $json = ['x' => $X,'y' => $Y];
                break;
            case 'users':
                $fields = 'sum(install) as value,device_system';
                $queryBuild = DB::table('statistic_day')->select(DB::raw($fields));
                if($channelId!==null){
                    $queryBuild = $queryBuild->where('channel_id',$channelId);
                }

                if( $deviceSystem>0 ){
                    $queryBuild = $queryBuild->where('device_system',$deviceSystem);
                }

                // if($timeRange != 0){
                    $queryBuild = $queryBuild
                        ->where('at_time','>=',strtotime($startDate))
                        ->where('at_time','<=',strtotime($endDate));
                // }

                $json = $queryBuild->groupBy('device_system')->get();
    
                $systemName = [
                    0 => '其它',
                    1 => '苹果(IOS)',
                    2 => '安卓(Android)',
                    3 => '苹果(轻量版)',
                ];

                foreach ($json as &$item){
                    $item->name = $systemName[$item->device_system];
                }
                break;
            case 'IPDistribution':
                $queryBuild = DB::table('users');
                if($timeRange==0){
                    $startDate = date('Y-m-d').' 00:00:00';
                }
                $queryBuild = $queryBuild->whereBetween('created_at',[$startDate,$endDate]);
                if($channelId!==null){
                    $queryBuild = $queryBuild
                        ->select('location_name','create_ip','channel_id','device_system',DB::raw('count(distinct create_ip) as ips'),DB::raw('json_extract(cast(location_name as json),"$[1]") as province'))
                        ->where('users.channel_id',$channelId);
                }else{
                    $queryBuild = $queryBuild
                    ->select('location_name','create_ip','device_system',DB::raw('count(distinct create_ip) as ips'),DB::raw('json_extract(cast(location_name as json),"$[1]") as province'));
                }

                $items = $queryBuild->where('location_name','!=','')
                    ->groupBy(['province','device_system'])
                    ->distinct()
                    ->get();

                $system = [0=>'all',1=>'ios',2=>'android',3 => 'ios轻量版'];
                $json['android'] = [];
                $json['ios'] = [];
                $ips = [];
                //dump($items);
                foreach ($items as $item){
                    $jsonArea = @json_decode($item->location_name,true);
                    if(isset($jsonArea[1])){
                        $json[$system[$item->device_system]][]  = ['name' => $jsonArea[1],'value' => $item->ips];
                        $ips[] = $item->ips;
                    }
                }
                $json['min'] = !empty($ips) ? min($ips) : 0;
                $json['max'] = !empty($ips) ? max($ips) : 0;
                break;
        }
        return response()->json($json);
    }
}