<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

        // Membuat key cache dinamis berdasarkan filter
        $cacheKey = "task_2_" . $customerName . '_' . $userName;

        // Attempt to get the data from the cache
        $salesOrdersQuery = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($customerName, $userName) {
            return SalesOrder::selectRaw('YEAR(sales_orders.created_at) as year, MONTH(sales_orders.created_at) as month, SUM(sales_order_items.selling_price) as total')
                ->join('sales_order_items', 'sales_orders.id', '=', 'sales_order_items.order_id')
                ->when($customerName, function ($query, $customerName) {
                    return $query->join('customers', 'sales_orders.customer_id', '=', 'customers.id')
                        ->where('customers.name', 'LIKE', "%$customerName%");
                })
                ->when($userName, function ($query, $userName) {
                    return $query->join('sales', 'sales_orders.sales_id', '=', 'sales.id') // Gunakan kolom 'sales_id' dari SalesOrder dan 'id' dari Sale
                        ->join('users', 'sales.user_id', '=', 'users.id')
                        ->where('users.name', 'LIKE', "%$userName%");
                })
                ->whereYear('sales_orders.created_at', '>=', now()->subYears(3)->year)
                ->groupBy('year', 'month')
                ->get();
        });

        // Transform the data into the desired format
        $groupedData = $salesOrdersQuery->groupBy('year')->map(function ($yearlyOrders, $year) use ($months) {
            $monthlyData = collect($months)->map(function ($month) use ($yearlyOrders) {
                $total = $yearlyOrders->firstWhere('month', date('m', strtotime($month)))->total ?? 0;
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

        // Generate a unique cache key based on the input parameters
        $cacheKey = 'sales_data_' . $userName . "_" . $currentYear;

        // Attempt to get the data from the cache
        $data = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($userName, $currentYear, $months) {
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
            return [
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
        });

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

        // Generate a unique cache key based on the input parameters
        $cacheKey = 'sales_data_' . $month . "_" . $isUnderperform;

        // Attempt to get the data from the cache
        $salesData = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($year, $monthNumber, $isUnderperform) {
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

            return $salesData;
        });

        return response()->json([
            'is_underperform' => $isUnderperform === 'true',
            'month' => date('F Y', strtotime($month)),
            'items' => $salesData->values()
        ]);
    }


    public function createCustomerLimaEnam(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'address' => 'required',
            'phone' => 'required',
        ], [
            'name.required' => 'Name is required',
            'address.required' => 'Address is required',
            'phone.required' => 'Phone is required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(),
            ], 400);
        }

        $customer = new Customer();
        $customer->name = $request->name;
        $customer->address = $request->address;
        $customer->phone = $request->phone;
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer
        ]);
    }

    public function updateCustomerLimaEnam(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'address' => 'required',
            'phone' => 'required',
        ], [
            'name.required' => 'Name is required',
            'address.required' => 'Address is required',
            'phone.required' => 'Phone is required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(),
            ], 400);
        }

        $customer->name = $request->name;
        $customer->address = $request->address;
        $customer->phone = $request->phone;
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    public function tujuh(Request $request)
    {
        // Validasi request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'area_id' => 'required|exists:sales_areas,id',
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Mulai transaksi database
        DB::beginTransaction();

        try {
            // Create Sale
            $sale = Sale::create([
                'user_id' => $request->user_id,
                'area_id' => $request->area_id,
            ]);

            // Create Sales Order
            $salesOrder = SalesOrder::create([
                'sales_id' => $sale->id,
                'customer_id' => $request->customer_id,
                // No need to include reference_no, it's auto-generated
            ]);

            // Create Sales Order Items
            foreach ($request->items as $itemData) {
                $product = Product::find($itemData['product_id']);
                $salesOrderItem = SalesOrderItem::create([
                    'quantity' => $itemData['quantity'],
                    'production_price' => $product->production_price * $itemData['quantity'],
                    'selling_price' => $product->selling_price * $itemData['quantity'],
                    'product_id' => $itemData['product_id'],
                    'order_id' => $salesOrder->id,
                ]);
            }

            // Commit transaksi
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sales created successfully',
                'data' => [
                    'sale' => $sale,
                    'sales_order' => [
                        $salesOrder,
                        'items' => $salesOrderItem
                    ],
                ]
            ], 201);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
