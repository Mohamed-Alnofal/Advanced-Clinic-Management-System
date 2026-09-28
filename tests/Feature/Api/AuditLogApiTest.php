<?php

namespace Tests\Feature\Api;

use Modules\Audit\Models\AuditLog;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    public function test_admin_can_list_audit_logs(): void
    {
        $admin = $this->createAdmin();

        AuditLog::create([
            'user_id' => $admin->id,
            'user_email' => $admin->email,
            'user_name' => $admin->name,
            'role' => 'admin',
            'action' => 'create',
            'entity_type' => 'users',
            'entity_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'url' => 'http://localhost/api/admin/users',
            'method' => 'POST',
            'new_values' => ['sample' => true],
        ]);

        $this->authenticateAs($admin)
            ->getJson('/api/admin/audit/logs')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'action',
                            'entity_type',
                            'user_email',
                            'created_at',
                        ],
                    ],
                ],
            ]);
    }

    public function test_admin_can_view_audit_statistics(): void
    {
        $admin = $this->createAdmin();

        AuditLog::create([
            'user_id' => $admin->id,
            'user_email' => $admin->email,
            'user_name' => $admin->name,
            'role' => 'admin',
            'action' => 'update',
            'entity_type' => 'verification',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'url' => 'http://localhost/api/admin/verification/doctors/1/approve',
            'method' => 'POST',
            'new_values' => [],
        ]);

        $this->authenticateAs($admin)
            ->getJson('/api/admin/audit/statistics')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total_logs',
                    'today_logs',
                    'this_week_logs',
                    'this_month_logs',
                    'by_action',
                    'by_role',
                    'top_users',
                ],
            ]);
    }

    public function test_admin_mutation_writes_audit_log_via_middleware(): void
    {
        $admin = $this->createAdmin();
        $doctorSetup = $this->createDoctorUser(
            ['is_verified' => false, 'status' => 'pending'],
            ['is_verified' => false]
        );

        $this->authenticateAs($admin)
            ->postJson('/api/admin/verification/doctors/'.$doctorSetup['doctor']->id.'/approve')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'role' => 'admin',
            'action' => 'create',
            'method' => 'POST',
        ]);
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/admin/audit/logs')
            ->assertForbidden();
    }

    public function test_audit_logs_require_authentication(): void
    {
        $this->getJson('/api/admin/audit/logs')->assertUnauthorized();
    }
}
