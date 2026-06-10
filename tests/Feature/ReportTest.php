<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketStatus;
use Tests\TestCase;

class ReportTest extends TestCase
{
    private function seedStatus(): TicketStatus
    {
        return TicketStatus::firstOrCreate(
            ['name' => 'nuevo'],
            ['name' => 'nuevo', 'color' => '#000', 'order' => 1]
        );
    }

    public function test_total_tickets_metric_returns_count(): void
    {
        $status = $this->seedStatus();
        $admin  = $this->createAdmin();

        Ticket::factory()->count(3)->create(['status_id' => $status->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/metrics/total-tickets');

        $response->assertOk()
            ->assertJsonStructure(['value']);

        $this->assertGreaterThanOrEqual(3, $response->json('value'));
    }

    public function test_tickets_by_status_chart_returns_data(): void
    {
        $status = $this->seedStatus();
        $admin  = $this->createAdmin();

        Ticket::factory()->count(2)->create(['status_id' => $status->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/charts/tickets-by-status')
            ->assertOk()
            ->assertJsonStructure(['labels', 'datasets']);
    }

    public function test_export_requires_permission(): void
    {
        $agent = $this->createUser('agente');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/reports/export/all-tickets')
            ->assertForbidden();
    }

    public function test_admin_with_permission_can_export_tickets(): void
    {
        $admin = $this->createAdmin();
        $this->grantPermission($admin, 'reports.export');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/reports/export/all-tickets')
            ->assertOk();
    }

    public function test_sla_compliance_report_is_accessible(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/tables/sla-compliance')
            ->assertOk();
    }
}
