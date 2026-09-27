<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ReportResponsiblesSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_normalized_names_and_clear_them_without_changing_another_company(): void
    {
        $admin = User::factory()->create(['account_type' => UserAccountType::CompanyAdmin]);
        $other = Organization::factory()->create(['report_reviewer_name' => 'Outra Revisora']);
        $edit = route('settings.inspection-report.responsibles.edit');
        $update = route('settings.inspection-report.responsibles.update');
        $this->actingAs($admin)->get($edit)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/InspectionReports/Responsibles')->where('names.report_reviewer_name', null));
        $this->put($update, [
            'organization_id' => $other->id,
            'report_reviewer_name' => "  João   da\n Silva  ",
            'report_releaser_name' => '  Lúcia Gonçalves ',
        ])->assertRedirect($edit)->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->get($edit)->assertInertia(fn (Assert $page) => $page
            ->where('names.report_reviewer_name', 'João da Silva')
            ->where('names.report_releaser_name', 'Lúcia Gonçalves'));
        $this->assertSame('Outra Revisora', $other->fresh()->report_reviewer_name);
        $this->assertSame('João da Silva', $admin->organization->fresh()->report_reviewer_name);
        $this->put($update, ['report_reviewer_name' => '   ', 'report_releaser_name' => null])->assertSessionHasNoErrors();
        $this->assertNull($admin->organization->fresh()->report_reviewer_name);
        $this->assertNull($admin->organization->fresh()->report_releaser_name);
    }

    public function test_names_validate_types_and_length_after_normalization(): void
    {
        $admin = User::factory()->create(['account_type' => UserAccountType::CompanyAdmin]);
        $url = route('settings.inspection-report.responsibles.update');
        $this->actingAs($admin)->put($url, ['report_reviewer_name' => str_repeat('á', 151), 'report_releaser_name' => ['invalid']])
            ->assertSessionHasErrors(['report_reviewer_name', 'report_releaser_name']);
        $this->put($url, [])->assertSessionHasErrors(['report_reviewer_name', 'report_releaser_name']);
        $this->put($url, ['report_reviewer_name' => '  '.str_repeat('á', 150).'  ', 'report_releaser_name' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame(str_repeat('á', 150), $admin->organization->fresh()->report_reviewer_name);
    }

    public function test_only_active_company_admins_can_access_the_configuration(): void
    {
        $edit = route('settings.inspection-report.responsibles.edit');
        $update = route('settings.inspection-report.responsibles.update');
        foreach (OperationalRole::cases() as $role) {
            $member = User::factory()->create(['operational_role' => $role]);
            $this->actingAs($member)->get($edit)->assertForbidden();
            $this->put($update, ['report_reviewer_name' => 'Nome', 'report_releaser_name' => null])->assertForbidden();
        }
        $inactive = User::factory()->create(['account_type' => UserAccountType::CompanyAdmin, 'status' => UserStatus::Inactive]);
        $this->actingAs($inactive)->get($edit)->assertRedirect(route('login'));
        $this->actingAs($inactive)->put($update, ['report_reviewer_name' => 'Nome', 'report_releaser_name' => null])->assertRedirect(route('login'));
        $this->assertNull($inactive->organization->fresh()->report_reviewer_name);
    }
}
