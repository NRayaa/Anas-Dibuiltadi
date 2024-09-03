<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\SalesTarget;
use Carbon\Carbon;
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

    public function tiga(Request $request)
    {
        $userName = $request->input('sales'); // Nullable filter for sales (user)

        // Mendapatkan tahun ini
        $currentYear = now()->year;

        // Daftar bulan dengan format 3 huruf (Jan, Feb, ..., Dec)
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // Query untuk sales orders pada tahun ini
        $salesOrdersQuery = SalesOrder::with(['salesOrderItems' => function ($query) {
            $query->select('selling_price', 'production_price', 'order_id');
        }])
            ->when($userName, function ($query, $userName) {
                // Join dengan tabel users melalui tabel sales untuk filter berdasarkan nama user
                return $query->whereHas('sale.user', function ($q) use ($userName) {
                    $q->where('name', 'LIKE', "%$userName%");
                });
            })
            ->whereYear('created_at', $currentYear)
            ->get();

        // Query untuk sales targets pada tahun ini
        $salesTargetsQuery = Sale::with(['salesTargets' => function ($query) use ($currentYear) {
            $query->whereYear('active_date', $currentYear);
        }])
            ->when($userName, function ($query, $userName) {
                // Join dengan tabel users melalui tabel sales untuk filter berdasarkan nama user
                return $query->whereHas('user', function ($q) use ($userName) {
                    $q->where('name', 'LIKE', "%$userName%");
                });
            })
            ->get();

        // Grouping sales targets berdasarkan bulan
        $targetsData = collect($months)->map(function ($month) use ($salesTargetsQuery) {
            $totalTarget = $salesTargetsQuery->sum(function ($sale) use ($month) {
                return $sale->salesTargets->filter(function ($target) use ($month) {
                    // Pastikan active_date adalah objek Carbon
                    return Carbon::parse($target->active_date)->format('M') === $month;
                })->sum('amount');
            });

            return [
                'x' => $month,
                'y' => number_format($totalTarget, 2, '.', ''),
            ];
        });

        // Grouping revenue and income berdasarkan bulan
        $revenueData = collect($months)->map(function ($month) use ($salesOrdersQuery) {
            $totalRevenue = $salesOrdersQuery->filter(function ($order) use ($month) {
                // Pastikan created_at adalah objek Carbon
                return Carbon::parse($order->created_at)->format('M') === $month;
            })->sum(function ($order) {
                return $order->salesOrderItems->sum('selling_price');
            });

            return [
                'x' => $month,
                'y' => number_format($totalRevenue, 2, '.', ''),
            ];
        });

        $incomeData = collect($months)->map(function ($month) use ($salesOrdersQuery) {
            $totalIncome = $salesOrdersQuery->filter(function ($order) use ($month) {
                // Pastikan created_at adalah objek Carbon
                return Carbon::parse($order->created_at)->format('M') === $month;
            })->sum(function ($order) {
                return $order->salesOrderItems->sum(function ($item) {
                    return $item->selling_price - $item->production_price;
                });
            });

            return [
                'x' => $month,
                'y' => number_format($totalIncome, 2, '.', ''),
            ];
        });

        // Prepare the response format
        $data = [
            [
                'name' => 'Target',
                'data' => $targetsData->all(),
            ],
            [
                'name' => 'Revenue',
                'data' => $revenueData->all(),
            ],
            [
                'name' => 'Income',
                'data' => $incomeData->all(),
            ],
        ];

        return response()->json([
            'sales' => $userName ?: null,
            'year' => $currentYear,
            'items' => $data,
        ]);
    }

    public function empat(Request $request)
    {
        // Mendapatkan bulan dari request, default bulan ini
        $month = $request->input('month', date('Y-m'));
        $isUnderperform = $request->input('is_underperform');

        // Mendapatkan bulan dan tahun dari input
        $year = date('Y', strtotime($month));
        $monthNumber = date('m', strtotime($month));

        // Query untuk mendapatkan data penjualan dan target
        $salesDataQuery = Sale::with(['salesOrders.salesOrderItems', 'salesTargets' => function ($query) use ($year, $monthNumber) {
            $query->whereYear('active_date', $year)
                  ->whereMonth('active_date', $monthNumber);
        }]);

        // Mendapatkan data sales
        $salesData = $salesDataQuery->get()->map(function ($sale) {
            // Menghitung total revenue
            $revenue = $sale->salesOrders->flatMap(function ($order) {
                return $order->salesOrderItems;
            })->sum('selling_price');

            // Menghitung total target
            $target = $sale->salesTargets->sum('amount');

            // Menghitung persentase
            $percentage = $target ? ($revenue / $target) * 100 : 0;

            return [
                'sales' => $sale->user->name,
                'revenue' => [
                    'amount' => number_format($revenue, 2),
                    'abbreviation' => $this->abbreviateAmount($revenue)
                ],
                'target' => [
                    'amount' => number_format($target, 2),
                    'abbreviation' => $this->abbreviateAmount($target)
                ],
                'percentage' => number_format($percentage, 2)
            ];
        });

        // Filter berdasarkan is_underperform jika disediakan
        if ($isUnderperform === 'true') {
            $salesData = $salesData->filter(function ($data) {
                return $data['revenue']['amount'] < $data['target']['amount'];
            });
        } elseif ($isUnderperform === 'false') {
            $salesData = $salesData->filter(function ($data) {
                return $data['revenue']['amount'] >= $data['target']['amount'];
            });
        }

        return response()->json([
            'is_underperform' => $isUnderperform === 'true',
            'month' => date('F Y', strtotime($month)),
            'items' => $salesData->values()
        ]);
    }

    private function abbreviateAmount($amount)
    {
        if ($amount >= 1_000_000_000) {
            return number_format($amount / 1_000_000_000, 2) . 'B';
        } elseif ($amount >= 1_000_000) {
            return number_format($amount / 1_000_000, 2) . 'M';
        } elseif ($amount >= 1_000) {
            return number_format($amount / 1_000, 2) . 'K';
        }
        return number_format($amount, 2);
    }
}
