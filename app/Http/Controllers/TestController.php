<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\UserRole;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test()
    {
        try{
            $test = UserRole::all();
            if ($test) {
                return response()->json([
                    'success' => true,
                    'message' => 'success',
                    'data' => $test
                ], 200);
            }
            return response()->json([
                'success' => false,
                'message' => 'failed',
                'data' => 'not found'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'failed',
                'data' => $e
            ]);
        }
    }

    public function test2()
    {
        try{
            $test = SalesOrder::all();
            if ($test) {
                return response()->json([
                    'success' => true,
                    'message' => 'success',
                    'data' => $test
                ], 200);
            }
            return response()->json([
                'success' => false,
                'message' => 'failed',
                'data' => 'not found'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'failed',
                'data' => $e
            ]);
        }
    }
}

