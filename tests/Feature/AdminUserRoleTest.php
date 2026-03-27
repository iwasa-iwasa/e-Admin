<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_promote_user_to_company_admin(): void
    {
        $dept = Department::create(['name' => '総務部']);

        $companyAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
            'role_type' => 'company_admin',
            'department_id' => $dept->id,
        ]);

        $target = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'user',
            'role_type' => 'member',
            'department_id' => $dept->id,
        ]);

        $res = $this->actingAs($companyAdmin)->patch("/admin/users/{$target->id}/role", [
            'role_type' => 'company_admin',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'admin',
            'role_type' => 'company_admin',
        ]);
    }

    public function test_company_admin_cannot_change_own_role(): void
    {
        $dept = Department::create(['name' => '総務部']);

        $onlyCompanyAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
            'role_type' => 'company_admin',
            'department_id' => $dept->id,
        ]);

        $res = $this->actingAs($onlyCompanyAdmin)->patch("/admin/users/{$onlyCompanyAdmin->id}/role", [
            'role_type' => 'member',
        ]);

        $res->assertRedirect();
        $res->assertSessionHasErrors();
        $this->assertDatabaseHas('users', [
            'id' => $onlyCompanyAdmin->id,
            'role_type' => 'company_admin',
        ]);
    }
}

