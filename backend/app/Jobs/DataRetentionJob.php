<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Models\SupportTicketAttachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataRetentionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        DB::table('sessions')
            ->where('last_activity', '<', now()->subDays((int) config('privacy.session_retention_days'))->getTimestamp())
            ->delete();

        DB::table('password_reset_tokens')->where('created_at', '<', now()->subDay())->delete();
        DB::table('personal_access_tokens')->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();

        NotificationDelivery::query()
            ->whereNull('personal_data_redacted_at')
            ->where('created_at', '<', now()->subDays((int) config('privacy.notification_personal_data_days')))
            ->chunkById(100, function ($deliveries): void {
                foreach ($deliveries as $delivery) {
                    $delivery->update([
                        'recipient' => '[redacted]',
                        'snapshot' => [],
                        'delivery_log' => ['personal_data_redacted' => true],
                        'personal_data_redacted_at' => now(),
                    ]);
                }
            });

        SupportTicketAttachment::query()
            ->whereHas('message.ticket', fn ($query) => $query
                ->where('status', 'closed')
                ->where('updated_at', '<', now()->subDays((int) config('privacy.support_attachment_days_after_close'))))
            ->chunkById(100, function ($attachments): void {
                foreach ($attachments as $attachment) {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                    $attachment->delete();
                }
            });
    }
}
