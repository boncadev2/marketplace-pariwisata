<?php

namespace App\Models;

use Database\Factories\SupportTicketAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAttachment extends Model
{
    /** @use HasFactory<SupportTicketAttachmentFactory> */
    use HasFactory;

    protected $fillable = ['support_ticket_message_id', 'disk', 'path', 'mime_type', 'size_bytes'];

    protected $hidden = ['disk', 'path'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'support_ticket_message_id');
    }
}
