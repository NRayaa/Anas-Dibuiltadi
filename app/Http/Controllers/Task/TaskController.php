<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\Request;


class TaskController extends Controller
{
    public function dua(Request $request)
    {
        $customerName = $request->input('customer'); // Nullable filter for customer
        $userName = $request->input('sales'); // Nullable filter for user (sales)

        // Mendapatkan tahun saat ini dan tahun-tahun dalam 3 tahun terakhir
        $currentYear = now()->year;
        $years = range($currentYear - 2, $currentYear); // 3 tahun terakhir (misal: 2022, 2023, 2024)

        // Daftar bulan dengan format 3 huruf (Jan, Feb, ..., Dec)
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // Query untuk sales orders dalam 3 tahun terakhir
        $salesOrdersQuery = SalesOrder::with(['salesOrderItems' => function ($query) {
            $query->select('selling_price', 'order_id');
        }])
            ->when($customerName, function ($query, $customerName) {
                // Join dengan tabel customers untuk filter berdasarkan nama customer
                return $query->whereHas('customer', function ($q) use ($customerName) {
                    $q->where('name', 'LIKE', "%$customerName%");
                });
            })
            ->when($userName, function ($query, $userName) {
                // Join dengan tabel users melalui tabel sales untuk filter berdasarkan nama user
                return $query->whereHas('sale.user', function ($q) use ($userName) {
                    $q->where('name', 'LIKE', "%$userName%");
                });
            })
            ->whereYear('created_at', '>=', now()->subYears(3)->year)
            ->get();

        // Group the data by year and month, summing up the selling_price
        $groupedData = $salesOrdersQuery->groupBy(function ($order) {
            return $order->created_at->format('Y');
        })->map(function ($yearlyOrders, $year) use ($months) {
            // Map data untuk setiap bulan dalam satu tahun
            $monthlyData = collect($months)->map(function ($month) use ($yearlyOrders) {
                $total = $yearlyOrders->filter(function ($order) use ($month) {
                    return $order->created_at->format('M') === $month;
                })->sum(function ($order) {
                    return $order->salesOrderItems->sum('selling_price');
                });

                return [
                    'x' => $month,
                    'y' => number_format($total, 2, '.', ''),
                ];
            });

            return [
                'name' => $year,
                'data' => $monthlyData->all(),
            ];
        });

        // Ensure all years are present and add missing years with zeroed data
        $data = collect($years)->map(function ($year) use ($groupedData, $months) {
            // Jika data untuk tahun ini tidak ada, buat data dengan nilai 0.00
            if (!$groupedData->has($year)) {
                $emptyData = collect($months)->map(function ($month) {
                    return [
                        'x' => $month,
                        'y' => '0.00',
                    ];
                });

                return [
                    'name' => $year,
                    'data' => $emptyData->all(),
                ];
            }

            // Jika tahun ini ada dalam data, gunakan data yang ada
            return $groupedData->get($year);
        });

        // Prepare the response format
        return response()->json([
            'customer' => $customerName ?: null,
            'sales' => $userName ?: null,
            'items' => $data->values()->all(),
        ]);
    }
}
