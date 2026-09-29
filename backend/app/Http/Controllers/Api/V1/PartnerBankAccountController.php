<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PartnerBankAccount;
use Illuminate\Http\Request;

class PartnerBankAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = PartnerBankAccount::where('partner_id', $request->user()->partner_id ?? $request->partner_id)->get();
        return response()->json(['data' => $accounts]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
        ]);

        $account = PartnerBankAccount::create($validated);
        return response()->json(['data' => $account], 201);
    }

    public function verify(Request $request, PartnerBankAccount $account)
    {
        $account->update([
            'is_verified' => true,
            'verified_by' => $request->user()->id ?? 1,
            'verified_at' => now(),
        ]);

        return response()->json(['data' => $account]);
    }
}
