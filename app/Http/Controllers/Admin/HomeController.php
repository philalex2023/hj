<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\TraitClass\DayStatisticTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends BaseController
{
    use DayStatisticTrait;
    /**
     * 首页
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function index(){
        return $this->display();
    }

    public function home(){


        return $this->display(['data'=> $this->getDayStatisticHashData()]);
    }

    public function map($type,Request $request){
        $this->setViewPath($type.'Map');
        return $this->display();
    }
}
