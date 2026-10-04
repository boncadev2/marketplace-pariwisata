<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherRedemptionController extends Controller
{
    public function store(Request $request, VoucherService $vouchers): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:48'], 'override_reason' => ['sometimes', 'required', 'string', 'min:10', 'max:1000']]);
        $voucher = $vouchers->redeem($data['token'], $request->user(), $data['override_reason'] ?? null);

        return response()->json(['data' => ['status' => $voucher->status, 'used_admissions' => $voucher->used_admissions]]);
    }
}
