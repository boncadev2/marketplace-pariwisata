<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        if ($request->filled('status')) {
            if ($request->input('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->input('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', $search)
                    ->orWhere('name', 'like', $search);
            });
        }

        return response()->json($query->latest('id')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'minimum_spend' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'global_quota' => ['nullable', 'integer', 'min:1'],
            'user_quota' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['minimum_spend'] = $validated['minimum_spend'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $coupon = Coupon::create($validated);

        return response()->json(['data' => $coupon], 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json(['data' => $coupon->loadCount('redemptions')]);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'minimum_spend' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'global_quota' => ['nullable', 'integer', 'min:1'],
            'user_quota' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $coupon->update($validated);

        return response()->json(['data' => $coupon->fresh()]);
    }

    public function toggle(Coupon $coupon): JsonResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return response()->json(['data' => $coupon->fresh()]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        if ($coupon->redemptions()->exists()) {
            $coupon->update(['is_active' => false]);

            return response()->json([
                'message' => 'Kupon telah digunakan oleh pembeli, status diubah menjadi nonaktif.',
                'data' => $coupon->fresh(),
            ]);
        }

        $coupon->delete();

        return response()->json(null, 204);
    }
}
