<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerApplicationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(config('app.partner_self_registration', false), 404);
        $data = $request->validate(['region_id' => ['required', 'integer', 'exists:regions,id'], 'name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:255', 'unique:partners,slug'], 'contact_email' => ['nullable', 'email'], 'contact_phone' => ['nullable', 'string', 'max:32']]);
        $partner = Partner::create([...$data, 'status' => 'draft']);

        return response()->json(['data' => $partner], 201);
    }
}
