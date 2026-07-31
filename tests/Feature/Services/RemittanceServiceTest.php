<?php

namespace Tests\Feature\Services;

use App\Models\CashAccount;
use App\Models\Warehouse;
use App\Services\RemittanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * PRD Story 5: Setoran Kas. Submit moves branch cash to IN_TRANSIT and locks it as
 * pending; verify moves it to the destination and marks VERIFIED.
 */
class RemittanceServiceTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    private RemittanceService $service;
    private Warehouse $fromWarehouse;
    private Warehouse $toWarehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
        $this->service = app(RemittanceService::class);
        $this->fromWarehouse = Warehouse::factory()->create();
        $this->toWarehouse = Warehouse::factory()->create();
    }

    public function test_submit_moves_balance_to_in_transit_and_marks_pending(): void
    {
        $source = CashAccount::factory()->create(['balance' => 500000]);
        $supervisor = $this->makeUser('Supervisor', $this->fromWarehouse->id);

        $remittance = $this->service->submit($this->fromWarehouse->id, $this->toWarehouse->id, $source->id, 200000, $supervisor->id);

        $this->assertEquals('pending', $remittance->status);
        $this->assertEquals(300000, $source->fresh()->balance);
        $this->assertEquals(200000, \App\Models\CashAccount::where('code', 'IN_TRANSIT')->value('balance'));
    }

    public function test_submit_rejects_amount_exceeding_source_balance(): void
    {
        // PRD AC: amount exceeding drawer balance is rejected, not partially processed.
        $source = CashAccount::factory()->create(['balance' => 100000]);
        $supervisor = $this->makeUser('Supervisor', $this->fromWarehouse->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Amount exceeds available drawer cash balance.');

        $this->service->submit($this->fromWarehouse->id, $this->toWarehouse->id, $source->id, 150000, $supervisor->id);

        $this->assertEquals(100000, $source->fresh()->balance);
    }

    public function test_verify_moves_in_transit_to_destination_and_marks_verified(): void
    {
        $source = CashAccount::factory()->create(['balance' => 500000]);
        $destination = CashAccount::factory()->create(['balance' => 0]);
        $supervisor = $this->makeUser('Supervisor', $this->fromWarehouse->id);
        $manager = $this->makeUser('Manager');

        $remittance = $this->service->submit($this->fromWarehouse->id, $this->toWarehouse->id, $source->id, 200000, $supervisor->id);
        $verified = $this->service->verify($remittance, $destination->id, $manager->id);

        $this->assertEquals('verified', $verified->status);
        $this->assertNotNull($verified->verified_at);
        $this->assertEquals(200000, $destination->fresh()->balance);
        $this->assertEquals(0, \App\Models\CashAccount::where('code', 'IN_TRANSIT')->value('balance'));
    }

    public function test_cannot_verify_an_already_verified_remittance(): void
    {
        $source = CashAccount::factory()->create(['balance' => 500000]);
        $destination = CashAccount::factory()->create(['balance' => 0]);
        $supervisor = $this->makeUser('Supervisor', $this->fromWarehouse->id);
        $manager = $this->makeUser('Manager');

        $remittance = $this->service->submit($this->fromWarehouse->id, $this->toWarehouse->id, $source->id, 100000, $supervisor->id);
        $this->service->verify($remittance, $destination->id, $manager->id);

        $this->expectException(\RuntimeException::class);
        $this->service->verify($remittance, $destination->id, $manager->id);
    }
}
