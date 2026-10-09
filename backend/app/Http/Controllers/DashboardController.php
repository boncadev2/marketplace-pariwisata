<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Full dashboard APIs
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_orders' => 100,
                'total_revenue' => 5000000,
            ],
        ]);
    }
}
