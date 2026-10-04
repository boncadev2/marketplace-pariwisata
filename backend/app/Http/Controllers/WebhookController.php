<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Webhook integrations
        return response()->json(['status' => 'processed']);
    }
}
