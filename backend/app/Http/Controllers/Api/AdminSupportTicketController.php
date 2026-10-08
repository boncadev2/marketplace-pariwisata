<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->with(['user:id,name,email', 'order:id,public_id']);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', $search)->orWhere('email', 'like', $search))
                  ->orWhereHas('order', fn ($oq) => $oq->where('public_id', 'like', $search))
                  ->orWhere('id', 'like', $search);
            });
        }

        $tickets = $query->latest('id')->paginate(20);

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (SupportTicket $ticket) => [
                'id' => $ticket->id,
                'order_id' => $ticket->order?->public_id,
                'customer' => [
                    'id' => $ticket->user?->id,
                    'name' => $ticket->user?->name,
                    'email' => $ticket->user?->email,
                ],
                'category' => $ticket->category,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->toIso8601String(),
                'updated_at' => $ticket->updated_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        $ticket->load([
            'user:id,name,email',
            'order:id,public_id,total,status,currency',
            'messages' => fn ($query) => $query->with([
                'user:id,name,platform_role',
                'attachments:id,support_ticket_message_id,mime_type,size_bytes',
            ])->orderBy('id'),
        ]);

        return response()->json([
            'data' => [
                'id' => $ticket->id,
                'order_id' => $ticket->order?->public_id,
                'order_total' => $ticket->order?->total,
                'order_status' => $ticket->order?->status,
                'customer' => [
                    'id' => $ticket->user?->id,
                    'name' => $ticket->user?->name,
                    'email' => $ticket->user?->email,
                ],
                'category' => $ticket->category,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->toIso8601String(),
                'messages' => $ticket->messages->map(fn (SupportTicketMessage $msg) => [
                    'id' => $msg->id,
                    'author' => $msg->user_id === $ticket->user_id ? 'customer' : 'support',
                    'sender_name' => $msg->user?->name,
                    'body' => $msg->body,
                    'created_at' => $msg->created_at->toIso8601String(),
                    'attachments' => $msg->attachments->map(fn (SupportTicketAttachment $att) => [
                        'id' => $att->id,
                        'mime_type' => $att->mime_type,
                        'size_bytes' => $att->size_bytes,
                    ])->all(),
                ]),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $storedPath = null;
        try {
            $message = DB::transaction(function () use ($request, $ticket, $data, &$storedPath): SupportTicketMessage {
                $message = $ticket->messages()->create([
                    'user_id' => $request->user()->id,
                    'body' => $data['message'],
                ]);

                if (isset($data['attachment']) && $data['attachment'] instanceof UploadedFile) {
                    $path = $data['attachment']->store('support-attachments', 'local');
                    $storedPath = $path;
                    $message->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'mime_type' => $data['attachment']->getMimeType(),
                        'size_bytes' => $data['attachment']->getSize(),
                    ]);
                }

                $ticket->touch();

                return $message->load(['user:id,name', 'attachments']);
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return response()->json([
            'data' => [
                'id' => $message->id,
                'author' => 'support',
                'sender_name' => $request->user()->name,
                'body' => $message->body,
                'created_at' => $message->created_at->toIso8601String(),
                'attachments' => $message->attachments->map(fn (SupportTicketAttachment $att) => [
                    'id' => $att->id,
                    'mime_type' => $att->mime_type,
                    'size_bytes' => $att->size_bytes,
                ])->all(),
            ],
        ], 201);
    }

    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:open,closed'],
        ]);

        $ticket->update(['status' => $data['status']]);

        return response()->json([
            'data' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'updated_at' => $ticket->updated_at->toIso8601String(),
            ],
        ]);
    }

    public function download(SupportTicket $ticket, int $attachmentId): StreamedResponse
    {
        $attachment = SupportTicketAttachment::query()
            ->whereHas('message', fn ($query) => $query->where('support_ticket_id', $ticket->id))
            ->findOrFail($attachmentId);

        abort_unless($attachment->disk === 'local' && Storage::disk('local')->exists($attachment->path), 404);

        $extension = match ($attachment->mime_type) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => abort(404),
        };

        return Storage::disk('local')->download($attachment->path, 'lampiran-'.$attachment->id.'.'.$extension, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
