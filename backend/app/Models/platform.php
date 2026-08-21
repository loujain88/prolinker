<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Commission Fee
    |--------------------------------------------------------------------------
    | The percentage the platform takes from each completed order.
    | E.g. 10 = 10% commission deducted from final_price before
    | releasing the remainder to the seller's wallet.
    |
    | Change via .env: PLATFORM_FEE_PERCENT=10
    */
    'fee_percent' => (float) env('PLATFORM_FEE_PERCENT', 10),

    /*
    |--------------------------------------------------------------------------
    | Escrow Auto-Release (days)
    |--------------------------------------------------------------------------
    | Number of days after delivery before escrow is automatically released
    | to the seller if the client has not approved or opened a dispute.
    | Set to null to disable auto-release (manual approval only).
    |
    | Change via .env: ESCROW_AUTO_RELEASE_DAYS=3
    |
    | AI Hook: Replace this fixed window with a dynamic model that
    | adjusts the hold period based on order risk score.
    */
    'escrow_auto_release_days' => (int) env('ESCROW_AUTO_RELEASE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Minimum Withdrawal Amount
    |--------------------------------------------------------------------------
    */
    'min_withdrawal' => (float) env('MIN_WITHDRAWAL_AMOUNT', 10.00),

    /*
    |--------------------------------------------------------------------------
    | Maximum Deposit Amount (per request)
    |--------------------------------------------------------------------------
    */
    'max_deposit' => (float) env('MAX_DEPOSIT_AMOUNT', 50000.00),

];
