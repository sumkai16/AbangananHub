<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\PaymentQrShare;
use App\Support\QrPh;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The landlord shares a payment QR in a chat. The browser reads the screenshot (jsQR) and posts only the
 * decoded text; QrPh::parse() decides whether it is a real QR Ph code, so a photo of something else, or a
 * QR that points to a website, never reaches a tenant. Nothing is uploaded and no image is stored.
 */
class PaymentQrShareController extends Controller
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sharePaymentQr', $conversation);

        $data = $request->validate([
            'payload' => ['required_without:reuse_share_id', 'nullable', 'string', 'max:512'],
            'reuse_share_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        // "Use the QR I shared before": the landlord's own earlier share stands in for a new screenshot.
        // Only their own, so one landlord's QR can never be re-posted by another.
        if (! empty($data['reuse_share_id'])) {
            $earlier = PaymentQrShare::where('shared_by', $request->user()->user_id)->find($data['reuse_share_id']);

            if (! $earlier) {
                return response()->json(['errors' => ['payload' => ['That earlier QR is no longer available. Choose a picture of your QR.']]], 422);
            }

            $data['payload'] = $earlier->payload;
        }

        $parsed = QrPh::parse($data['payload']);

        if (! $parsed) {
            return response()->json(['errors' => ['payload' => [
                'That is not a payment QR. Use the "Receive money" or "My QR" screen of GCash, Maya, GoTyme or your bank app, and share a screenshot of it.',
            ]]], 422);
        }

        [$share, $message] = DB::transaction(function () use ($conversation, $data, $parsed, $request) {
            // The newest QR is the only one a tenant should pay to: mark every earlier one from this landlord
            // to this tenant for this property as replaced, in whichever thread it was shared.
            PaymentQrShare::whereNull('replaced_at')
                ->whereHas('conversation', fn ($q) => $q
                    ->where('tenant_id', $conversation->tenant_id)
                    ->where('landlord_id', $conversation->landlord_id)
                    ->where('property_id', $conversation->property_id))
                ->update(['replaced_at' => now()]);

            $share = PaymentQrShare::create([
                'conversation_id' => $conversation->conversation_id,
                'shared_by' => $request->user()->user_id,
                'payload' => trim($data['payload']),
                'account_name' => $parsed['account_name'],
                'note' => $data['note'] ?? null,
            ]);

            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->user_id,
                'message' => 'Shared a payment QR ('.$parsed['account_name'].')',
                'kind' => 'payment_qr',
                'payment_qr_share_id' => $share->payment_qr_share_id,
            ]);

            return [$share, $message];
        });

        $conversation->broadcastQuietly($message->load('sender'));

        Notification::notify(
            $conversation->tenant_id,
            'payment',
            'How to pay your landlord',
            $request->user()->first_name.' shared a payment QR. Only pay to a QR shared here in your chat.',
            route('conversations.index', array_filter([
                'box' => $conversation->isTenancy() ? 'tenancy' : null,
                'active' => $conversation->conversation_id,
            ])),
            $conversation->conversation_id,
        );

        return response()->json(['payment_qr_share_id' => $share->payment_qr_share_id], 201);
    }
}
