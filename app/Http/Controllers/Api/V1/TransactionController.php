<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use App\Support\ApiLocale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $role = $this->getRole();
        $user = auth()->user();

        if ($role === 'member') {
            return $this->memberTransactions($request, $user);
        }

        if ($role === 'merchant') {
            return $this->merchantTransactions($request, $user);
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
    }

    private function memberTransactions(Request $request, $member): JsonResponse
    {
        [$sortField, $sortDirection] = $this->resolveSort($request->get('sort_by'));

        $query = PointTransaction::with('merchant:id,membership_id,company_name,company_name_ms,company_name_zh,cover_image')
            ->where('member_id', $member->id);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->date_to);
        }

        $transactions = $query->orderBy($sortField, $sortDirection)
            ->paginate($request->get('per_page', 15));

        $transactions->setCollection(
            $transactions->getCollection()->map(fn ($txn) => [
                'id' => $txn->id,
                'merchant_id' => $txn->merchant_id,
                'member_id' => $txn->member_id,
                'member_membership_id' => $member->membership_id,
                'merchant_membership_id' => $txn->merchant?->membership_id,
                'type' => $txn->type,
                'currency_amount' => $txn->currency_amount,
                'points' => $txn->points,
                'reference_number' => $txn->reference_number,
                'reason' => $txn->reason,
                'status' => $txn->status,
                'occurred_at' => optional($txn->occurred_at)?->toIso8601String(),
                'time_ago' => $this->formatTimeAgo($txn->occurred_at),
                'merchant' => $txn->merchant ? [
                    'id' => $txn->merchant->id,
                    'membership_id' => $txn->merchant->membership_id,
                    'company_name' => ApiLocale::resolve($txn->merchant->company_name_i18n, (string) $txn->merchant->company_name),
                    'cover_image' => $txn->merchant->cover_image,
                    'company_name_i18n' => $txn->merchant->company_name_i18n,
                ] : null,
            ])
        );

        return $this->paginatedResponse($transactions, 'transactions');
    }

    private function merchantTransactions(Request $request, $merchant): JsonResponse
    {
        [$sortField, $sortDirection] = $this->resolveSort($request->get('sort_by'));

        $query = PointTransaction::with('member:id,membership_id,full_name,email')
            ->where('merchant_id', $merchant->id);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->date_to);
        }

        $transactions = $query->orderBy($sortField, $sortDirection)
            ->paginate($request->get('per_page', 15));

        $transactions->setCollection(
            $transactions->getCollection()->map(fn ($txn) => [
                'id' => $txn->id,
                'merchant_id' => $txn->merchant_id,
                'member_id' => $txn->member_id,
                'member_membership_id' => $txn->member?->membership_id,
                'merchant_membership_id' => $merchant->membership_id,
                'type' => $txn->type,
                'currency_amount' => $txn->currency_amount,
                'points' => $txn->points,
                'reference_number' => $txn->reference_number,
                'reason' => $txn->reason,
                'status' => $txn->status,
                'occurred_at' => optional($txn->occurred_at)?->toIso8601String(),
                'time_ago' => $this->formatTimeAgo($txn->occurred_at),
                'member' => $txn->member ? [
                    'id' => $txn->member->id,
                    'membership_id' => $txn->member->membership_id,
                    'full_name' => $txn->member->full_name,
                    'email' => $txn->member->email,
                ] : null,
            ])
        );

        return $this->paginatedResponse($transactions, 'transactions');
    }

    private function formatTimeAgo(?Carbon $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }

        $now = now();

        if ($dateTime->isToday()) {
            $minutes = (int) floor($dateTime->diffInMinutes($now));
            if ($minutes < 60) {
                $value = max(1, $minutes);

                return $value.' min';
            }

            $hours = (int) floor($dateTime->diffInHours($now));

            return $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        if ($dateTime->isYesterday()) {
            return 'Yesterday';
        }

        $days = (int) floor($dateTime->diffInDays($now));
        if ($days < 7) {
            $value = max(1, $days);

            return $value.' '.($value === 1 ? 'day' : 'days');
        }

        return $dateTime->format('d M Y');
    }

    private function resolveSort(?string $sortBy): array
    {
        return match ($sortBy) {
            'older_first' => ['occurred_at', 'asc'],
            'most_points' => ['points', 'desc'],
            'least_points' => ['points', 'asc'],
            'newest_first', null, '' => ['occurred_at', 'desc'],
            default => ['occurred_at', 'desc'],
        };
    }
}
