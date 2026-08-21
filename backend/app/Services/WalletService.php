<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Deposit;
use App\Models\Seller;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * WalletService
 *
 * Encapsulates all financial mutation logic for both clients and sellers.
 * Every method that touches a wallet runs inside a DB transaction to
 * guarantee atomicity — either everything succeeds or nothing changes.
 *
 * Calling convention:
 *   - Never call wallet helpers directly from controllers.
 *   - Controllers delegate to this service; this service owns the money.
 */
class WalletService
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    // =========================================================================
    // DEPOSIT FLOW (Client → Platform)
    // =========================================================================

    /**
     * Create a pending deposit record after the client uploads a receipt.
     *
     * The receipt file is validated upstream in the Form Request.
     * This method stores the file and persists the DB record only.
     *
     * @param  User   $user          The authenticated client user
     * @param  float  $amount        Requested deposit amount
     * @param  object $receiptFile   Validated UploadedFile instance
     * @param  string $paymentMethod e.g. 'bank_transfer'
     * @return Deposit
     */
    public function createDeposit(
        User   $user,
        float  $amount,
        object $receiptFile,
        string $paymentMethod = 'bank_transfer'
    ): Deposit {
        // Store receipt under a private, non-publicly-guessable path
        $path = $receiptFile->store("receipts/deposits/{$user->id}", 'private');

        $deposit = Deposit::create([
            'user_id'             => $user->id,
            'amount'              => $amount,
            'currency'            => 'USD',
            'status'              => Deposit::STATUS_PENDING,
            'transaction_receipt' => $path,
            'payment_method'      => $paymentMethod,
        ]);

        // Notify the client that the request is under review
        $this->notifications->depositPending($user, $deposit);

        return $deposit;
    }

    /**
     * Admin approves a deposit: credit the client's wallet and mark done.
     *
     * @param  Deposit $deposit     The pending deposit to approve
     * @param  User    $admin       The admin performing the action
     * @param  string  $notes       Optional admin notes
     * @return Deposit
     * @throws \RuntimeException    If the deposit is not in pending state
     */
    public function approveDeposit(Deposit $deposit, User $admin, string $notes = ''): Deposit
    {
        if (! $deposit->isPending()) {
            throw new \RuntimeException("Deposit #{$deposit->id} is not in a pending state.");
        }

        return DB::transaction(function () use ($deposit, $admin, $notes): Deposit {
            // Credit the client's wallet atomically
            $client = Client::where('user_id', $deposit->user_id)->lockForUpdate()->firstOrFail();
            $client->creditWallet($deposit->amount);

            // Update deposit record
            $deposit->update([
                'status'       => Deposit::STATUS_APPROVED,
                'admin_notes'  => $notes,
                'approved_at'  => now(),
                'processed_by' => $admin->id,
            ]);

            // Notify the client
            $this->notifications->depositApproved($deposit->user, $deposit);

            return $deposit->fresh();
        });
    }

    /**
     * Admin rejects a deposit.
     *
     * @param  Deposit $deposit
     * @param  User    $admin
     * @param  string  $notes   Required: admin must state reason for rejection
     * @return Deposit
     */
    public function rejectDeposit(Deposit $deposit, User $admin, string $notes): Deposit
    {
        if (! $deposit->isPending()) {
            throw new \RuntimeException("Deposit #{$deposit->id} is not in a pending state.");
        }

        $deposit->update([
            'status'       => Deposit::STATUS_REJECTED,
            'admin_notes'  => $notes,
            'rejected_at'  => now(),
            'processed_by' => $admin->id,
        ]);

        $this->notifications->depositRejected($deposit->user, $deposit);

        return $deposit->fresh();
    }

    // =========================================================================
    // WITHDRAWAL FLOW (Seller → External)
    // =========================================================================

    /**
     * Seller places a withdrawal request.
     * Funds are immediately reserved (deducted from wallet) to prevent
     * double-spending while the request is pending admin review.
     *
     * @param  User   $user
     * @param  float  $amount
     * @param  string $payoutMethod  e.g. 'bank_transfer', 'paypal'
     * @param  array  $payoutDetails Account info (IBAN, PayPal email, etc.)
     * @param  string $notes         Optional note from seller
     * @return Withdrawal
     */
    public function createWithdrawal(
        User   $user,
        float  $amount,
        string $payoutMethod,
        array  $payoutDetails = [],
        string $notes = ''
    ): Withdrawal {
        return DB::transaction(function () use ($user, $amount, $payoutMethod, $payoutDetails, $notes): Withdrawal {
            // Lock the seller row to prevent concurrent withdrawal races
            $seller = Seller::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $seller->debitWallet($amount); // Throws if insufficient balance

            $withdrawal = Withdrawal::create([
                'user_id'        => $user->id,
                'amount'         => $amount,
                'currency'       => 'USD',
                'status'         => Withdrawal::STATUS_PENDING,
                'payout_method'  => $payoutMethod,
                'payout_details' => $payoutDetails,
                'seller_notes'   => $notes,
            ]);

            $this->notifications->withdrawalPending($user, $withdrawal);

            return $withdrawal;
        });
    }

    /**
     * Admin approves a withdrawal and MUST upload a payout receipt as proof.
     * This receipt is visible to the seller as confirmation of payment.
     *
     * @param  Withdrawal $withdrawal
     * @param  User       $admin
     * @param  object     $receiptFile   Validated UploadedFile
     * @param  string     $notes
     * @return Withdrawal
     */
    public function approveWithdrawal(
        Withdrawal $withdrawal,
        User       $admin,
        object     $receiptFile,
        string     $notes = ''
    ): Withdrawal {
        if (! $withdrawal->isPending()) {
            throw new \RuntimeException("Withdrawal #{$withdrawal->id} is not in a pending state.");
        }

        // Store payout receipt proof uploaded by admin
        $path = $receiptFile->store("receipts/withdrawals/{$withdrawal->user_id}", 'private');

        $withdrawal->update([
            'status'              => Withdrawal::STATUS_APPROVED,
            'transaction_receipt' => $path,
            'admin_notes'         => $notes,
            'approved_at'         => now(),
            'processed_by'        => $admin->id,
        ]);

        $this->notifications->withdrawalApproved($withdrawal->user, $withdrawal);

        return $withdrawal->fresh();
    }

    /**
     * Admin rejects a withdrawal: restore the reserved funds to the seller.
     *
     * @param  Withdrawal $withdrawal
     * @param  User       $admin
     * @param  string     $notes   Required: state reason for rejection
     * @return Withdrawal
     */
    public function rejectWithdrawal(Withdrawal $withdrawal, User $admin, string $notes): Withdrawal
    {
        if (! $withdrawal->isPending()) {
            throw new \RuntimeException("Withdrawal #{$withdrawal->id} is not in a pending state.");
        }

        return DB::transaction(function () use ($withdrawal, $admin, $notes): Withdrawal {
            // Restore the reserved amount to the seller's wallet
            $seller = Seller::where('user_id', $withdrawal->user_id)->lockForUpdate()->firstOrFail();
            $seller->creditWallet($withdrawal->amount);

            $withdrawal->update([
                'status'       => Withdrawal::STATUS_REJECTED,
                'admin_notes'  => $notes,
                'rejected_at'  => now(),
                'processed_by' => $admin->id,
            ]);

            $this->notifications->withdrawalRejected($withdrawal->user, $withdrawal);

            return $withdrawal->fresh();
        });
    }

    // =========================================================================
    // STORAGE HELPERS
    // =========================================================================

    /**
     * Generate a temporary signed URL for a private receipt file.
     * Used by both clients (deposit receipts) and sellers (withdrawal receipts).
     *
     * NOTE: We deliberately do NOT call Storage::disk('private')->temporaryUrl()
     * here — that method only works on cloud disks (S3 and similar) that support
     * pre-signed URLs natively. Our 'private' disk uses the 'local' driver, which
     * throws a RuntimeException if you call temporaryUrl() on it. Instead we
     * generate a Laravel-signed route that streams the file through our own
     * ReceiptController, which works identically regardless of disk driver.
     *
     * @param  string $path    Storage path stored in the DB
     * @param  int    $minutes How long the URL should be valid (default 15 min)
     * @return string          Temporary signed URL
     */
    public function temporaryReceiptUrl(string $path, int $minutes = 15): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'receipts.show',
            now()->addMinutes($minutes),
            ['path' => $path]
        );
    }
}
