<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $role = $this->getRole();

        if ($role !== 'merchant') {
            return response()->json([
                'success' => false,
                'message' => 'Performance data is only available for merchant accounts.',
            ], 403);
        }

        $merchant = $request->user();
        $baseQuery = PointTransaction::query()
            ->where('merchant_id', $merchant->id)
            ->where('type', 'allocation');

        $dailyStart = now()->startOfDay();
        $dailyEnd = now()->endOfDay();

        $weeklyStart = now()->startOfWeek(Carbon::MONDAY);
        $weeklyEnd = (clone $weeklyStart)->endOfWeek(Carbon::SUNDAY);

        $monthlyStart = now()->startOfMonth();
        $monthlyEnd = (clone $monthlyStart)->endOfMonth();

        $yearlyStart = now()->startOfYear();
        $yearlyEnd = (clone $yearlyStart)->endOfYear();

        $dailyData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $dailyData[] = [
                'key' => sprintf('h%02d', $hour),
                'label' => sprintf('%02d:00', $hour),
                'points' => 0,
                'members' => 0,
                'member_ids' => [],
            ];
        }

        $weeklyData = [
            ['key' => 'monday', 'label' => 'Monday', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'tuesday', 'label' => 'Tuesday', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'wednesday', 'label' => 'Wednesday', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'thursday', 'label' => 'Thursday', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'friday', 'label' => 'Friday', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'saturday', 'label' => 'Saturday', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'sunday', 'label' => 'Sunday', 'points' => 0, 'members' => 0, 'member_ids' => []],
        ];

        $monthlyData = [
            ['key' => 'week_1', 'label' => 'Week 1', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'week_2', 'label' => 'Week 2', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'week_3', 'label' => 'Week 3', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'week_4', 'label' => 'Week 4', 'points' => 0, 'members' => 0, 'member_ids' => []],
        ];

        $yearlyData = [
            ['key' => 'jan', 'label' => 'Jan', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'feb', 'label' => 'Feb', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'mar', 'label' => 'Mar', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'apr', 'label' => 'Apr', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'may', 'label' => 'May', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'jun', 'label' => 'Jun', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'jul', 'label' => 'Jul', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'aug', 'label' => 'Aug', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'sep', 'label' => 'Sep', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'oct', 'label' => 'Oct', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'nov', 'label' => 'Nov', 'points' => 0, 'members' => 0, 'member_ids' => []],
            ['key' => 'dec', 'label' => 'Dec', 'points' => 0, 'members' => 0, 'member_ids' => []],
        ];

        $dailyTransactions = (clone $baseQuery)
            ->whereBetween('occurred_at', [$dailyStart, $dailyEnd])
            ->get(['member_id', 'points', 'occurred_at']);

        foreach ($dailyTransactions as $txn) {
            $bucketIndex = (int) $txn->occurred_at->hour; // 0..23
            if (! isset($dailyData[$bucketIndex])) {
                continue;
            }
            $dailyData[$bucketIndex]['points'] += (int) $txn->points;
            $dailyData[$bucketIndex]['member_ids'][$txn->member_id] = true;
        }

        $weeklyTransactions = (clone $baseQuery)
            ->whereBetween('occurred_at', [$weeklyStart, $weeklyEnd])
            ->get(['member_id', 'points', 'occurred_at']);

        foreach ($weeklyTransactions as $txn) {
            $bucketIndex = (int) $txn->occurred_at->dayOfWeekIso - 1; // Monday=1 ... Sunday=7
            if (! isset($weeklyData[$bucketIndex])) {
                continue;
            }
            $weeklyData[$bucketIndex]['points'] += (int) $txn->points;
            $weeklyData[$bucketIndex]['member_ids'][$txn->member_id] = true;
        }

        $monthlyTransactions = (clone $baseQuery)
            ->whereBetween('occurred_at', [$monthlyStart, $monthlyEnd])
            ->get(['member_id', 'points', 'occurred_at']);

        foreach ($monthlyTransactions as $txn) {
            $weekNumber = (int) floor(($txn->occurred_at->day - 1) / 7) + 1; // 1..5
            $bucketIndex = max(1, min(4, $weekNumber)) - 1; // Week 5 folds into Week 4
            if (! isset($monthlyData[$bucketIndex])) {
                continue;
            }
            $monthlyData[$bucketIndex]['points'] += (int) $txn->points;
            $monthlyData[$bucketIndex]['member_ids'][$txn->member_id] = true;
        }

        $yearlyTransactions = (clone $baseQuery)
            ->whereBetween('occurred_at', [$yearlyStart, $yearlyEnd])
            ->get(['member_id', 'points', 'occurred_at']);

        foreach ($yearlyTransactions as $txn) {
            $bucketIndex = (int) $txn->occurred_at->month - 1; // Jan=1 ... Dec=12
            if (! isset($yearlyData[$bucketIndex])) {
                continue;
            }
            $yearlyData[$bucketIndex]['points'] += (int) $txn->points;
            $yearlyData[$bucketIndex]['member_ids'][$txn->member_id] = true;
        }

        $normalize = function (array $buckets): array {
            return array_map(function (array $bucket) {
                $memberIds = $bucket['member_ids'] ?? [];
                $bucket['members'] = count($memberIds);
                unset($bucket['member_ids']);
                return $bucket;
            }, $buckets);
        };

        $daily = $normalize($dailyData);
        $weekly = $normalize($weeklyData);
        $monthly = $normalize($monthlyData);
        $yearly = $normalize($yearlyData);

        $memberScanSummary = [
            'today' => (clone $baseQuery)
                ->whereBetween('occurred_at', [$dailyStart, $dailyEnd])
                ->count(),
            'weekly' => (clone $baseQuery)
                ->whereBetween('occurred_at', [$weeklyStart, $weeklyEnd])
                ->count(),
            'monthly' => (clone $baseQuery)
                ->whereBetween('occurred_at', [$monthlyStart, $monthlyEnd])
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'member_scan_summary' => $memberScanSummary,
                'daily' => [
                    'range' => [
                        'from' => $dailyStart->toDateString(),
                        'to' => $dailyEnd->toDateString(),
                    ],
                    'buckets' => $daily,
                ],
                'weekly' => [
                    'range' => [
                        'from' => $weeklyStart->toDateString(),
                        'to' => $weeklyEnd->toDateString(),
                    ],
                    'buckets' => $weekly,
                ],
                'monthly' => [
                    'range' => [
                        'from' => $monthlyStart->toDateString(),
                        'to' => $monthlyEnd->toDateString(),
                    ],
                    'buckets' => $monthly,
                ],
                'yearly' => [
                    'range' => [
                        'from' => $yearlyStart->toDateString(),
                        'to' => $yearlyEnd->toDateString(),
                    ],
                    'buckets' => $yearly,
                ],
            ],
        ]);
    }
}
