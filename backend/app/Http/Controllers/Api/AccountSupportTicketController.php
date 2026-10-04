<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountSupportTicketController extends Controller
{
    private const CATEGORIES = ['booking', 'payment', 'voucher', 'visit', 'other'];

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()->where('user_id', $request->user()->id)->with('order:id,public_id')->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (SupportTicket $ticket) => $this->summary($ticket)),
            'meta' => ['current_page' => $tickets->currentPage(), 'last_page' => $tickets->lastPage(), 'total' => $tickets->total()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $ticketId): JsonResponse
    {
        $ticket = $this->ownedTicket($request, $ticketId)->load(['order:id,public_id', 'messages' => fn ($query) => $query->with('attachments:id,support_ticket_message_id,mime_type,size_bytes')->orderBy('id')]);

        return response()->json(['data' => [
            ...$this->summary($ticket),
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $message) => $this->message($ticket, $message)),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $publicId): JsonResponse
    {
        $data = $request->validate($this->messageRules(true));
        $order = Order::query()->where('user_id', $request->user()->id)->where('public_id', $publicId)->firstOrFail();
        $storedPath = null;
        try {
            $ticket = DB::transaction(function () use ($request, $order, $data, &$storedPath): SupportTicket {
                $ticket = SupportTicket::create(['user_id' => $request->user()->id, 'order_id' => $order->id, 'category' => $data['category'], 'status' => 'open']);
                $message = $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['message']]);
                $this->saveAttachment($data['attachment'] ?? null, $message, $storedPath);

                return $ticket->load(['order:id,public_id', 'messages.attachments']);
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return response()->json(['data' => [
            ...$this->summary($ticket),
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $message) => $this->message($ticket, $message)),
        ]], 201)->header('Cache-Control', 'private, no-store');
    }

    public function addMessage(Request $request, int $ticketId): JsonResponse
    {
        $data = $request->validate($this->messageRules(false));
        $storedPath = null;
        try {
            [$ticket, $message] = DB::transaction(function () use ($request, $ticketId, $data, &$storedPath): array {
                $ticket = SupportTicket::query()->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($ticketId);
                abort_unless($ticket->status === 'open', 409, 'Tiket sudah ditutup.');
                $message = $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['message']]);
                $this->saveAttachment($data['attachment'] ?? null, $message, $storedPath);

                return [$ticket, $message->load('attachments')];
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return response()->json(['data' => $this->message($ticket, $message)], 201)->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, int $ticketId, int $attachmentId): StreamedResponse
    {
        $ticket = $this->ownedTicket($request, $ticketId);
        $attachment = SupportTicketAttachment::query()->whereHas('message', fn ($query) => $query->where('support_ticket_id', $ticket->id))->findOrFail($attachmentId);
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

    private function ownedTicket(Request $request, int $ticketId): SupportTicket
    {
        return SupportTicket::query()->where('user_id', $request->user()->id)->findOrFail($ticketId);
    }

    private function messageRules(bool $creating): array
    {
        return [
            ...($creating ? ['category' => ['required', 'string', Rule::in(self::CATEGORIES)]] : []),
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    private function saveAttachment(?UploadedFile $file, SupportTicketMessage $message, ?string &$storedPath): void
    {
        if ($file === null) {
            return;
        }
        $path = $file->store('support-attachments', 'local');
        if ($path === false) {
            throw new \RuntimeException('Lampiran tidak dapat disimpan.');
        }
        $storedPath = $path;
        $message->attachments()->create(['disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize()]);
    }

    private function summary(SupportTicket $ticket): array
    {
        return ['id' => $ticket->id, 'order_id' => $ticket->order->public_id, 'category' => $ticket->category, 'status' => $ticket->status, 'created_at' => $ticket->created_at->toIso8601String()];
    }

    private function message(SupportTicket $ticket, SupportTicketMessage $message): array
    {
        return [
            'id' => $message->id,
            'author' => $message->user_id === $ticket->user_id ? 'customer' : 'support',
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'attachments' => $message->attachments->map(fn (SupportTicketAttachment $attachment) => ['id' => $attachment->id, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes])->all(),
        ];
    }
}
