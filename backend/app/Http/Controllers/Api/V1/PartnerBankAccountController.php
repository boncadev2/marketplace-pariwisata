<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PartnerBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PartnerBankAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->platform_role === 'super_admin' && ! $request->filled('partner_id')) {
            $accounts = PartnerBankAccount::query()->with('partner:id,name')->latest('id')->get();

            return response()->json([
                'data' => $accounts->map(fn (PartnerBankAccount $account) => [
                    ...$this->summary($account),
                    'partner' => $account->partner ? ['id' => $account->partner->id, 'name' => $account->partner->name] : null,
                ])->all(),
            ]);
        }

        $partnerId = (int) $request->integer('partner_id');
        if ($partnerId === 0 && $user->platform_role !== 'super_admin') {
            $membership = $user->partnerMemberships()->where('is_active', true)->first();
            if ($membership) {
                $partnerId = $membership->partner_id;
            }
        }

        $partnerId = $this->authorizedPartnerId($request, $partnerId);
        $accounts = PartnerBankAccount::query()->where('partner_id', $partnerId)->get();

        return response()->json(['data' => $this->summaries($accounts)]);
    }

    public function store(Request $request): JsonResponse
    {
        $inputPartnerId = $request->input('partner_id');
        if (! $inputPartnerId && $request->user()->platform_role !== 'super_admin') {
            $membership = $request->user()->partnerMemberships()->where('is_active', true)->whereIn('role', ['owner', 'manager'])->first();
            if ($membership) {
                $request->merge(['partner_id' => $membership->partner_id]);
            }
        }

        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
        ]);

        $this->authorizedPartnerId($request, (int) $validated['partner_id'], true);
        $account = PartnerBankAccount::create([
            ...$validated,
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        $this->audit($request, $account, 'partner_bank_account.created');

        return response()->json(['data' => $this->summary($account)], 201);
    }

    public function verify(Request $request, PartnerBankAccount $account): JsonResponse
    {
        $account->update([
            'is_verified' => true,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $this->audit($request, $account, 'partner_bank_account.verified');

        return response()->json(['data' => $this->summary($account->fresh())]);
    }

    private function authorizedPartnerId(Request $request, int $partnerId, bool $write = false): int
    {
        if ($request->user()->platform_role === 'super_admin') {
            abort_if($partnerId < 1, 422, 'partner_id wajib diisi.');

            return $partnerId;
        }

        $membership = $request->user()->partnerMemberships()
            ->where('partner_id', $partnerId)
            ->where('is_active', true)
            ->when($write, fn ($query) => $query->whereIn('role', ['owner', 'manager']))
            ->first();

        abort_unless($membership, 404);

        return $partnerId;
    }

    private function summaries(Collection $accounts): array
    {
        return $accounts->map(fn (PartnerBankAccount $account) => $this->summary($account))->all();
    }

    private function summary(PartnerBankAccount $account): array
    {
        $number = (string) $account->account_number;

        return [
            'id' => $account->id,
            'partner_id' => $account->partner_id,
            'bank_name' => $account->bank_name,
            'account_name' => $account->account_name,
            'account_number_masked' => str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4),
            'is_verified' => $account->is_verified,
            'is_active' => $account->is_active,
            'verified_at' => $account->verified_at,
        ];
    }

    private function audit(Request $request, PartnerBankAccount $account, string $action): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'partner_id' => $account->partner_id,
            'action' => $action,
            'auditable_type' => PartnerBankAccount::class,
            'auditable_id' => $account->id,
            'metadata' => ['bank_name' => $account->bank_name],
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);
    }
}
