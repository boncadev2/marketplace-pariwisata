<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartnerApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ids = PartnerMember::query()->where('user_id', $request->user()->id)->where('role', 'owner')->pluck('partner_id');

        return response()->json([
            'data' => Partner::query()->whereIn('id', $ids)->latest('id')->get()->map(fn (Partner $partner): array => $this->present($partner)),
            'enabled' => (bool) config('app.partner_self_registration'),
            'email_verified' => $request->user()->hasVerifiedEmail(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(config('app.partner_self_registration', false), 403, 'Pendaftaran mitra sedang ditutup.');
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verifikasi email sebelum mengajukan usaha.');
        $data = $request->validate([
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'contact_phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9 ()-]{6,29}$/'],
        ]);
        $partner = DB::transaction(function () use ($request, $data): Partner {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->hasVerifiedEmail(), 403, 'Verifikasi email sebelum mengajukan usaha.');
            $existing = PartnerMember::query()->where('user_id', $user->id)->where('role', 'owner')->whereHas('partner')->exists();
            abort_if($existing, 409, 'Anda sudah memiliki usaha atau pengajuan. Periksa status pengajuan Anda.');
            $partner = Partner::create([...$data, 'slug' => 'mitra-'.Str::uuid(), 'contact_email' => $user->email, 'status' => 'draft']);
            PartnerMember::create(['partner_id' => $partner->id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => false]);
            AuditLog::create(['user_id' => $user->id, 'partner_id' => $partner->id, 'action' => 'partner.application_submitted', 'auditable_type' => Partner::class, 'auditable_id' => $partner->id]);

            return $partner;
        }, 3);

        return response()->json(['data' => $this->present($partner)], 201)->header('Cache-Control', 'private, no-store');
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(['draft', 'approved', 'rejected'])]]);
        $rows = Partner::query()->whereIn('id', PartnerMember::query()->where('role', 'owner')->select('partner_id'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('id')->paginate(12);

        return response()->json(['data' => $rows->getCollection()->map(fn (Partner $partner): array => $this->present($partner)), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage()]])->header('Cache-Control', 'private, no-store');
    }

    public function decide(Request $request, Partner $partner): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'min:5', 'max:500'],
        ]);
        $partner = DB::transaction(function () use ($request, $partner, $data): Partner {
            $partner = Partner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            abort_unless(PartnerMember::query()->where('partner_id', $partner->id)->where('role', 'owner')->exists(), 404);
            if ($partner->status === $data['decision']) {
                return $partner;
            }
            abort_unless($partner->status === 'draft', 409, 'Pengajuan sudah diputuskan. Muat ulang daftar.');
            $partner->update(['status' => $data['decision']]);
            PartnerMember::query()->where('partner_id', $partner->id)->where('role', 'owner')->update(['is_active' => $data['decision'] === 'approved']);
            AuditLog::create(['user_id' => $request->user()->id, 'partner_id' => $partner->id, 'action' => 'partner.application_reviewed', 'auditable_type' => Partner::class, 'auditable_id' => $partner->id, 'metadata' => ['decision' => $data['decision'], 'reason' => $data['reason'] ?? null]]);

            return $partner;
        }, 3);

        return response()->json(['data' => $this->present($partner)])->header('Cache-Control', 'private, no-store');
    }

    private function present(Partner $partner): array
    {
        return ['id' => $partner->id, 'name' => $partner->name, 'region_id' => $partner->region_id, 'status' => $partner->status, 'contact_email' => $partner->contact_email, 'contact_phone' => $partner->contact_phone, 'created_at' => $partner->created_at?->toIso8601String(), 'review_reason' => AuditLog::query()->where('partner_id', $partner->id)->where('action', 'partner.application_reviewed')->latest('id')->first()?->metadata['reason'] ?? null];
    }
}
