<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherRedemptionController extends Controller
{
    public function check(Request $request, VoucherService $vouchers): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
        ]);

        $details = $vouchers->inspect($data['token'], $request->user());

        return response()->json(['data' => $details]);
    }

    public function store(Request $request, VoucherService $vouchers): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'override_reason' => ['sometimes', 'nullable', 'string', 'min:5', 'max:1000'],
        ]);

        $voucher = $vouchers->redeem(
            $data['token'],
            $request->user(),
            ! empty($data['override_reason']) ? $data['override_reason'] : null
        );

        return response()->json([
            'data' => [
                'status' => $voucher->status,
                'used_admissions' => $voucher->used_admissions,
            ],
        ]);
    }
}
