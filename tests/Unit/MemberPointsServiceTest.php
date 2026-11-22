<?php

namespace Tests\Unit;

use App\Models\BonusRule;
use App\Models\ConversionSetting;
use App\Models\Member;
use App\Models\PointTransaction;
use App\Services\MemberPointsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPointsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedConversion(float $rate = 1.0): void
    {
        ConversionSetting::create([
            'effective_date' => now()->toDateString(),
            'currency_unit' => 'MYR',
            'points_per_currency' => $rate,
            'is_active' => true,
        ]);
    }

    private function createMember(int $startingPoints = 0): Member
    {
        return Member::create([
            'full_name' => 'Test Member',
            'email' => 'points-test-' . uniqid() . '@example.com',
            'total_points' => $startingPoints,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function awards_points_using_conversion_rate(): void
    {
        $this->seedConversion(2.0);
        $member = $this->createMember();

        MemberPointsService::awardForPayment(
            member: $member,
            amount: 50.0,
            reason: 'Subscription payment',
            ref: 'SUB-TEST-1',
        );

        $member->refresh();

        $this->assertSame(100, $member->total_points);

        $txn = PointTransaction::where('reference_number', 'SUB-TEST-1')->first();
        $this->assertNotNull($txn);
        $this->assertSame($member->id, $txn->member_id);
        $this->assertNull($txn->merchant_id);
        $this->assertSame('allocation', $txn->type);
        $this->assertSame(100, $txn->points);
        $this->assertSame('Subscription payment', $txn->reason);
        $this->assertEquals(50.0, (float) $txn->currency_amount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function applies_bonus_multiplier_when_enabled(): void
    {
        $this->seedConversion(1.0);
        BonusRule::create([
            'name' => 'Test Bonus',
            'multiplier' => 2.0,
            'is_enabled' => true,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $member = $this->createMember();

        MemberPointsService::awardForPayment(
            member: $member,
            amount: 25.0,
            reason: 'Donation payment',
            ref: 'DON-TEST-1',
        );

        $member->refresh();

        $this->assertSame(50, $member->total_points);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_idempotent_for_same_reference(): void
    {
        $this->seedConversion(1.0);
        $member = $this->createMember();

        MemberPointsService::awardForPayment($member, 10.0, 'Subscription payment', 'SUB-IDEM');
        MemberPointsService::awardForPayment($member, 10.0, 'Subscription payment', 'SUB-IDEM');

        $member->refresh();

        $this->assertSame(10, $member->total_points);
        $this->assertSame(1, PointTransaction::where('reference_number', 'SUB-IDEM')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function skips_award_when_no_conversion_setting(): void
    {
        $member = $this->createMember();

        MemberPointsService::awardForPayment($member, 100.0, 'Subscription payment', 'SUB-NOCONV');

        $member->refresh();

        $this->assertSame(0, $member->total_points);
        $this->assertDatabaseMissing('point_transactions', ['reference_number' => 'SUB-NOCONV']);
    }
}
