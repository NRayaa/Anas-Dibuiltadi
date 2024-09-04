<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SalesArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SupportController extends Controller
{
    public function users()
    {
        $data = Cache::remember('users_list', now()->addMinutes(30), function () {
            return [
                'success' => true,
                'message' => 'List of all users',
                'data' => User::all()
            ];
        });

        return response()->json($data);
    }


    public function customers()
    {
        $data = Cache::remember('customers_list', now()->addMinutes(30), function () {
            return [
                'success' => true,
                'message' => 'List of all customers',
                'data' => Customer::all()
            ];
        });

        return response()->json($data);
    }

    public function areas()
    {
        $data = Cache::remember('sales_areas_list', now()->addMinutes(30), function () {
            return [
                'success' => true,
                'message' => 'List of all sales areas',
                'data' => SalesArea::all()
            ];
        });

        return response()->json($data);
    }
}
