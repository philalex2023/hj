<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommChat;
use App\Models\User;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommMessageController extends Controller
{
    use PHPRedisTrait,ApiParamsTrait;
    /**
     * 列表
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function lists(Request $request)
    {
        $res['list'] = [];
        $res['hasMorePages'] = false;
        return response()->json([
            'state' => 0,
            'data' => $res
        ]);
    }
}