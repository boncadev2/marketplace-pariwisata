<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DataDeletionRequest;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DataDeletionService
{
    public function process(DataDeletionRequest $deletionRequest, User $operator, ?string $ipAddress = null): DataDeletionRequest
    {
        abort_if($operator->is($deletionRequest->user), 409, 'Permintaan harus diproses oleh administrator lain.');

        $attachmentPaths = [];

        $processed = DB::transaction(function () use ($deletionRequest, $operator, $ipAddress, &$attachmentPaths): DataDeletionRequest {
            $request = DataDeletionRequest::query()->lockForUpdate()->findOrFail($deletionRequest->id);
            abort_unless($request->status === 'requested', 409, 'Permintaan tidak berstatus requested.');

            $user = User::query()->lockForUpdate()->findOrFail($request->user_id);
            abort_if($user->platform_role === 'super_admin', 409, 'Akun administrator tidak dapat dihapus melalui alur mandiri.');

            $ticketIds = SupportTicket::query()->where('user_id', $user->id)->pluck('id');
            $attachmentPaths = SupportTicketAttachment::query()
                ->whereHas('message', fn ($query) => $query->whereIn('support_ticket_id', $ticketIds))
                ->where('disk', 'local')
                ->pluck('path')
                ->all();

            $retainedRecords = [
                'orders' => Order::query()->where('user_id', $user->id)->count(),
                'partner_memberships' => $user->partnerMemberships()->count(),
                'reason' => 'Catatan transaksi, keuangan, audit, dan kewajiban operasional dipertahankan sesuai kewajiban pencatatan.',
            ];

            SupportTicket::query()->whereIn('id', $ticketIds)->delete();
            DB::table('wishlist_items')->where('user_id', $user->id)->delete();
            DB::table('reviews')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->forceFill([
                'name' => 'Deleted User '.$user->id,
                'email' => 'deleted+'.$user->id.'@example.invalid',
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
            ])->save();

            $request->update([
                'status' => 'completed',
                'reason' => null,
                'processed_at' => now(),
                'processed_by' => $operator->id,
                'processing_notes' => 'Data profil dianonimkan dan data non-transaksional dihapus.',
                'retained_records' => $retainedRecords,
            ]);

            AuditLog::create([
                'user_id' => $operator->id,
                'action' => 'privacy.data_deletion_completed',
                'auditable_type' => DataDeletionRequest::class,
                'auditable_id' => $request->id,
                'metadata' => ['retained_counts' => collect($retainedRecords)->only(['orders', 'partner_memberships'])->all()],
                'ip_hash' => $ipAddress ? hash_hmac('sha256', $ipAddress, (string) config('app.key')) : null,
            ]);

            return $request->fresh();
        });

        if ($attachmentPaths !== []) {
            Storage::disk('local')->delete($attachmentPaths);
        }

        return $processed;
    }
}
