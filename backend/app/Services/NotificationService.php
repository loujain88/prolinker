<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

/**
 * NotificationService
 *
 * Central factory for creating in-app notifications.
 * Every platform event that should surface in the UI goes through here.
 * The typed methods provide a compile-time contract that prevents typos
 * in notification types and ensures consistent content formatting.
 *
 * AI Hook: Add a `priority_score` payload parameter here when you
 * integrate your notification-ranking model.
 */
class NotificationService
{
    /**
     * Core factory: create a notification record.
     *
     * @param  User        $user          Recipient
     * @param  string      $type          Machine-readable event key (e.g. 'order.placed')
     * @param  string      $content       Human-readable message body
     * @param  string|null $actionUrl     Optional frontend route to deep-link on click
     * @param  object|null $notifiable    Optional related Eloquent model (polymorphic)
     */
    public function send(
        User    $user,
        string  $type,
        string  $content,
        ?string $actionUrl  = null,
        ?object $notifiable = null
    ): Notification {
        return Notification::create([
            'user_id'          => $user->id,
            'type'             => $type,
            'content'          => $content,
            'action_url'       => $actionUrl,
            'notifiable_type'  => $notifiable ? get_class($notifiable) : null,
            'notifiable_id'    => $notifiable?->id,
            'read_at'          => null,
        ]);
    }

    // ── Order notifications ───────────────────────────────────────────────────

    public function orderPlaced(User $seller, object $order): void
    {
        $this->send(
            $seller,
            'order.placed',
            "You have a new order #{$order->id} for \"{$order->service->title}\". Please review and accept.",
            "/dashboard/orders?highlight={$order->id}",
            $order
        );
    }

    public function orderAccepted(User $client, object $order): void
    {
        $this->send(
            $client,
            'order.accepted',
            "Your order #{$order->id} has been accepted! Work is now in progress.",
            "/dashboard/orders?highlight={$order->id}",
            $order
        );
    }

    public function orderDelivered(User $client, object $order): void
    {
        $this->send(
            $client,
            'order.delivered',
            "Order #{$order->id} has been marked as delivered. Please review and approve.",
            "/dashboard/orders?highlight={$order->id}",
            $order
        );
    }

    public function orderCompleted(User $seller, object $order, float $payout): void
    {
        $this->send(
            $seller,
            'order.completed',
            "Order #{$order->id} completed! \${$payout} has been credited to your wallet.",
            "/dashboard/orders?highlight={$order->id}",
            $order
        );
    }

    public function orderCancelled(User $recipient, object $order, string $reason = ''): void
    {
        $this->send(
            $recipient,
            'order.cancelled',
            "Order #{$order->id} has been cancelled." . ($reason ? " Reason: {$reason}" : ''),
            null,
            $order
        );
    }

    public function orderRejectedBySeller(User $client, object $order, string $reason = ''): void
    {
        $this->send(
            $client,
            'order.rejected',
            "رفض المستقل طلبك #{$order->id}." . ($reason ? " السبب: {$reason}" : '') . " تم رد المبلغ لمحفظتك.",
            "/dashboard/orders?highlight={$order->id}",
            $order
        );
    }

    // ── Dispute notifications ─────────────────────────────────────────────────

    public function disputeOpened(User $admin, object $dispute): void
    {
        $this->send(
            $admin,
            'dispute.opened',
            "A new dispute has been opened for Order #{$dispute->request_id}. Requires your review.",
            "/admin/disputes/{$dispute->id}",
            $dispute
        );
    }

    public function disputeOpenedForSeller(User $seller, object $dispute): void
    {
        $preview = mb_strlen($dispute->reason) > 100 ? mb_substr($dispute->reason, 0, 100) . '…' : $dispute->reason;

        $this->send(
            $seller,
            'dispute.opened',
            "فتح العميل نزاعاً على الطلب #{$dispute->request_id}. السبب: \"{$preview}\"",
            "/dashboard/orders/{$dispute->request_id}",
            $dispute
        );
    }

    public function disputeResolved(User $recipient, object $dispute, string $outcome): void
    {
        $this->send(
            $recipient,
            'dispute.resolved',
            "Dispute for Order #{$dispute->request_id} has been resolved: {$outcome}.",
            null,
            $dispute
        );
    }

    // ── Wallet notifications ──────────────────────────────────────────────────

    public function depositPending(User $client, object $deposit): void
    {
        $this->send(
            $client,
            'deposit.pending',
            "Your deposit request of \${$deposit->amount} is under review. You will be notified once it is approved.",
            "/client/wallet",
            $deposit
        );
    }

    public function depositApproved(User $client, object $deposit): void
    {
        $this->send(
            $client,
            'deposit.approved',
            "\${$deposit->amount} has been approved and added to your wallet.",
            "/client/wallet",
            $deposit
        );
    }

    public function depositRejected(User $client, object $deposit): void
    {
        $this->send(
            $client,
            'deposit.rejected',
            "Your deposit request of \${$deposit->amount} was rejected. Please contact support.",
            "/client/wallet",
            $deposit
        );
    }

    public function withdrawalPending(User $seller, object $withdrawal): void
    {
        $this->send(
            $seller,
            'withdrawal.pending',
            "Your withdrawal request of \${$withdrawal->amount} is pending admin approval.",
            "/seller/wallet",
            $withdrawal
        );
    }

    public function withdrawalApproved(User $seller, object $withdrawal): void
    {
        $this->send(
            $seller,
            'withdrawal.approved',
            "\${$withdrawal->amount} withdrawal approved. Check your uploaded receipt for payout proof.",
            "/seller/wallet",
            $withdrawal
        );
    }

    public function withdrawalRejected(User $seller, object $withdrawal): void
    {
        $this->send(
            $seller,
            'withdrawal.rejected',
            "Your withdrawal request of \${$withdrawal->amount} was rejected. Your balance has been restored.",
            "/seller/wallet",
            $withdrawal
        );
    }

    // ── Message notifications ─────────────────────────────────────────────────

    public function newMessage(User $recipient, object $message): void
    {
        $preview = mb_strlen($message->body) > 60
            ? mb_substr($message->body, 0, 60) . '…'
            : $message->body;

        $this->send(
            $recipient,
            'message.new',
            "رسالة جديدة: \"{$preview}\"",
            "/dashboard/messages/{$message->conversation_id}",
            $message
        );
    }
}
