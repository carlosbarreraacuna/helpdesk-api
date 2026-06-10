<?php

namespace Tests\Feature;

use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Tests\TestCase;

class TicketTest extends TestCase
{
    private function seedStatuses(): void
    {
        foreach (['nuevo', 'asignado', 'en_progreso', 'cerrado'] as $i => $name) {
            TicketStatus::firstOrCreate(['name' => $name], [
                'name'  => $name,
                'color' => '#000000',
                'order' => $i + 1,
            ]);
        }
    }

    private function seedSlaConfigs(): void
    {
        foreach (['alta', 'media', 'baja'] as $priority) {
            SlaConfig::firstOrCreate(['priority' => $priority], [
                'priority'              => $priority,
                'response_time_hours'   => 4,
                'resolution_time_hours' => 8,
                'alert_threshold'       => 80,
                'work_start_hour'       => 8,
                'work_end_hour'         => 18,
            ]);
        }
    }

    public function test_public_portal_can_create_ticket(): void
    {
        $this->seedStatuses();
        $this->seedSlaConfigs();

        $response = $this->postJson('/api/portal/tickets', [
            'requester_name'  => 'Juan Pérez',
            'requester_email' => 'juan@entidad.gov.co',
            'requester_area'  => 'Sistemas',
            'description'     => 'El computador no enciende desde ayer.',
            'priority'        => 'alta',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'ticket_number']);

        $this->assertDatabaseHas('tickets', [
            'requester_email' => 'juan@entidad.gov.co',
            'priority'        => 'alta',
        ]);
    }

    public function test_ticket_sla_dates_are_set_on_creation(): void
    {
        $this->seedStatuses();
        $this->seedSlaConfigs();

        $this->postJson('/api/portal/tickets', [
            'requester_name'  => 'Ana Gómez',
            'requester_email' => 'ana@entidad.gov.co',
            'requester_area'  => 'Contabilidad',
            'description'     => 'No puedo acceder al sistema de nómina.',
            'priority'        => 'alta',
        ]);

        $ticket = Ticket::where('requester_email', 'ana@entidad.gov.co')->first();

        $this->assertNotNull($ticket->sla_response_due_at);
        $this->assertNotNull($ticket->sla_resolution_due_at);
    }

    public function test_authenticated_user_can_list_their_tickets(): void
    {
        $this->seedStatuses();
        $user = $this->createUser('usuario');

        $status = TicketStatus::first();
        Ticket::factory()->create([
            'requester_email' => $user->email,
            'status_id'       => $status->id,
            'created_by'      => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_agent_can_update_ticket_status(): void
    {
        $this->seedStatuses();
        $agent  = $this->createUser('agente');
        $status = TicketStatus::where('name', 'nuevo')->first();

        $ticket = Ticket::factory()->create([
            'status_id'   => $status->id,
            'assigned_to' => $agent->id,
        ]);

        $newStatus = TicketStatus::where('name', 'en_progreso')->first();

        $this->actingAs($agent, 'sanctum')
            ->patchJson("/api/tickets/{$ticket->id}/status", [
                'status' => $newStatus->name,
            ])
            ->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id'        => $ticket->id,
            'status_id' => $newStatus->id,
        ]);
    }

    public function test_ticket_history_is_recorded_on_status_change(): void
    {
        $this->seedStatuses();
        $agent  = $this->createUser('agente');
        $status = TicketStatus::where('name', 'nuevo')->first();

        $ticket = Ticket::factory()->create([
            'status_id'   => $status->id,
            'assigned_to' => $agent->id,
        ]);

        $newStatus = TicketStatus::where('name', 'en_progreso')->first();

        $this->actingAs($agent, 'sanctum')
            ->patchJson("/api/tickets/{$ticket->id}/status", [
                'status' => $newStatus->name,
            ]);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_user_role_can_only_see_own_tickets(): void
    {
        $this->seedStatuses();
        $user   = $this->createUser('usuario');
        $other  = $this->createUser('usuario');
        $status = TicketStatus::first();

        Ticket::factory()->create(['requester_email' => $user->email, 'status_id' => $status->id]);
        Ticket::factory()->create(['requester_email' => $other->email, 'status_id' => $status->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tickets');

        $response->assertOk();

        $tickets = $response->json('data');
        foreach ($tickets as $ticket) {
            $this->assertEquals($user->email, $ticket['requester_email']);
        }
    }

    public function test_add_comment_to_ticket(): void
    {
        $this->seedStatuses();
        $agent  = $this->createUser('agente');
        $status = TicketStatus::first();

        $ticket = Ticket::factory()->create([
            'status_id'   => $status->id,
            'assigned_to' => $agent->id,
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/comments", [
                'comment'     => 'Revisando el problema.',
                'is_internal' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'comment'   => 'Revisando el problema.',
        ]);
    }
}
