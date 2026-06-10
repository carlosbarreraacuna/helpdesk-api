<?php

namespace Tests\Feature;

use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\SlaService;
use Carbon\Carbon;
use Tests\TestCase;

class SlaTest extends TestCase
{
    private function makeConfig(string $priority): SlaConfig
    {
        return SlaConfig::firstOrCreate(['priority' => $priority], [
            'priority'              => $priority,
            'response_time_hours'   => 4,
            'resolution_time_hours' => 8,
            'alert_threshold'       => 80,
            'work_start_hour'       => 8,
            'work_end_hour'         => 18,
        ]);
    }

    public function test_add_business_hours_within_same_day(): void
    {
        $service = new SlaService();
        $config  = $this->makeConfig('alta');

        // Monday 9 AM + 4h = Monday 1 PM
        $from   = Carbon::create(2026, 6, 8, 9, 0, 0, 'America/Bogota');
        $result = $service->addBusinessHours($from, 4, $config);

        $this->assertEquals(13, $result->hour);
        $this->assertEquals(1, $result->dayOfWeek); // Monday (Carbon: 0=Sun, 1=Mon)
    }

    public function test_add_business_hours_spanning_end_of_day(): void
    {
        $service = new SlaService();
        $config  = $this->makeConfig('alta');

        // Monday 5 PM + 4h should land Tuesday 9 AM (1h left Monday + 3h Tuesday)
        $from   = Carbon::create(2026, 6, 8, 17, 0, 0, 'America/Bogota');
        $result = $service->addBusinessHours($from, 4, $config);

        $this->assertEquals(2, $result->dayOfWeek); // Tuesday
        $this->assertEquals(11, $result->hour);
    }

    public function test_add_business_hours_skips_weekend(): void
    {
        $service = new SlaService();
        $config  = $this->makeConfig('baja');

        // Friday 5 PM + 1h should land Monday 9 AM
        $from   = Carbon::create(2026, 6, 12, 17, 0, 0, 'America/Bogota');
        $result = $service->addBusinessHours($from, 1, $config);

        $this->assertEquals(1, $result->dayOfWeek); // Monday
        $this->assertEquals(8, $result->hour);
    }

    public function test_sla_configs_endpoint_returns_three_priorities(): void
    {
        foreach (['alta', 'media', 'baja'] as $p) {
            $this->makeConfig($p);
        }

        $user = $this->createAdmin();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/sla/configs')
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_admin_can_update_sla_config(): void
    {
        $config = $this->makeConfig('alta');
        $admin  = $this->createAdmin();
        $this->grantPermission($admin, 'settings.update');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/sla/configs/{$config->id}", [
                'response_time_hours'   => 2,
                'resolution_time_hours' => 6,
            ])
            ->assertOk()
            ->assertJsonPath('response_time_hours', 2)
            ->assertJsonPath('resolution_time_hours', 6);
    }

    public function test_non_admin_cannot_update_sla_config(): void
    {
        $config = $this->makeConfig('alta');
        $agent  = $this->createUser('agente');

        $this->actingAs($agent, 'sanctum')
            ->patchJson("/api/sla/configs/{$config->id}", [
                'response_time_hours' => 1,
            ])
            ->assertForbidden();
    }

    public function test_sla_status_is_on_track_for_new_ticket(): void
    {
        $this->makeConfig('alta');
        $status = TicketStatus::firstOrCreate(['name' => 'nuevo'], ['name' => 'nuevo', 'color' => '#000', 'order' => 1]);

        $ticket = Ticket::factory()->create([
            'priority'              => 'alta',
            'status_id'             => $status->id,
            'sla_resolution_due_at' => now()->addHours(6),
        ]);

        $this->assertEquals('en_tiempo', $ticket->sla_status);
    }

    public function test_sla_status_is_breached_for_overdue_ticket(): void
    {
        $this->makeConfig('alta');
        $status = TicketStatus::firstOrCreate(['name' => 'nuevo'], ['name' => 'nuevo', 'color' => '#000', 'order' => 1]);

        $ticket = Ticket::factory()->create([
            'priority'              => 'alta',
            'status_id'             => $status->id,
            'sla_resolution_due_at' => now()->subHour(),
        ]);

        $this->assertEquals('vencido', $ticket->sla_status);
    }

    public function test_sla_status_is_met_when_closed_within_sla(): void
    {
        $status = TicketStatus::firstOrCreate(['name' => 'cerrado'], ['name' => 'cerrado', 'color' => '#000', 'order' => 4]);

        $ticket = Ticket::factory()->create([
            'status_id'             => $status->id,
            'sla_resolution_due_at' => now()->addHour(),
            'closed_at'             => now()->subMinutes(30),
        ]);

        $this->assertEquals('cumplido', $ticket->sla_status);
    }
}
