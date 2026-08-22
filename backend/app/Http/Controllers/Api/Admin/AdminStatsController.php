<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\Seller;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/admin/stats
 * Returns platform-wide statistics for the admin dashboard home.
 */
class AdminStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $totalUsers        = User::count();
        $totalClients      = Client::count();
        $totalSellers      = Seller::count();
        $activeUsers       = User::where('is_active', true)->count();
        $blockedUsers      = User::where('is_active', false)->count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        $totalServices     = Service::count();
        $activeServices    = Service::where('status', 'active')->count();
        $pausedServices    = Service::where('status', 'paused')->count();

        $totalOrders       = ServiceRequest::count();
        $pendingOrders     = ServiceRequest::where('status', 'pending')->count();
        $inProgressOrders  = ServiceRequest::where('status', 'in_progress')->count();
        $completedOrders   = ServiceRequest::where('status', 'completed')->count();
        $cancelledOrders   = ServiceRequest::where('status', 'cancelled')->count();
        $disputedOrders    = ServiceRequest::whereHas('dispute', fn($q) => $q->whereIn('status', ['open', 'under_review']))->count();
        $ordersThisMonth   = ServiceRequest::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        $totalDepositsApproved    = Deposit::where('status', 'approved')->sum('amount');
        $totalWithdrawalsApproved = Withdrawal::where('status', 'approved')->sum('amount');
        $pendingDeposits          = Deposit::where('status', 'pending')->count();
        $pendingWithdrawals       = Withdrawal::where('status', 'pending')->count();
        $platformRevenue          = ServiceRequest::where('status', 'completed')->sum('platform_fee');
        $platformRevenueMonth     = ServiceRequest::where('status', 'completed')->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->sum('platform_fee');
        $totalEscrow              = ServiceRequest::whereIn('status', ['pending', 'in_progress', 'delivered'])->sum('escrow_amount');

        $openDisputes       = Dispute::where('status', 'open')->count();
        $underReviewDisputes = Dispute::where('status', 'under_review')->count();
        $resolvedDisputes   = Dispute::whereIn('status', ['resolved_client', 'resolved_seller'])->count();

        return response()->json([
            'users' => [
                'total' => $totalUsers, 'clients' => $totalClients, 'sellers' => $totalSellers,
                'active' => $activeUsers, 'blocked' => $blockedUsers, 'new_this_month' => $newUsersThisMonth,
            ],
            'services' => ['total' => $totalServices, 'active' => $activeServices, 'paused' => $pausedServices],
            'orders' => [
                'total' => $totalOrders, 'pending' => $pendingOrders, 'in_progress' => $inProgressOrders,
                'completed' => $completedOrders, 'cancelled' => $cancelledOrders,
                'disputed' => $disputedOrders, 'this_month' => $ordersThisMonth,
            ],
            'financials' => [
                'total_deposits_approved'    => round($totalDepositsApproved, 2),
                'total_withdrawals_approved' => round($totalWithdrawalsApproved, 2),
                'platform_revenue_total'     => round($platformRevenue, 2),
                'platform_revenue_month'     => round($platformRevenueMonth, 2),
                'total_escrow_held'          => round($totalEscrow, 2),
                'pending_deposits'           => $pendingDeposits,
                'pending_withdrawals'        => $pendingWithdrawals,
            ],
            'disputes' => ['open' => $openDisputes, 'under_review' => $underReviewDisputes, 'resolved' => $resolvedDisputes],
        ]);
    }
}
