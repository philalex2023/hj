<?php

namespace App\Http\Controllers\Api;

use App\ExtendClass\CacheUser;
use App\Http\Controllers\Controller;
use App\TraitClass\ApiParamsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommunityController extends Controller
{
    use ApiParamsTrait;

    public function index(Request $request)
    {
        $params = self::parse($request->params??'');
        $validated = Validator::make($params,[
            'cid' => 'required|integer',
            'page' => 'required|integer'
        ])->validated();
        return response()->json([]);
    }

}