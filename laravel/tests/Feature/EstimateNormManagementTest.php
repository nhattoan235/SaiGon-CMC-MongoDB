<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\RefreshDocumentDatabase as RefreshDatabase;
use Tests\TestCase;

class EstimateNormManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_or_change_estimate_norm_versions(): void
    {
        $this->seed();
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

        $this->actingAs($manager)->get('/management/estimate-norms')->assertForbidden();
        $this->actingAs($manager)->post('/management/estimate-norms/import')->assertForbidden();
        $this->actingAs($customer)->post('/management/estimate-norms')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get('/management')->assertOk()->assertSee('Quản lý định mức dự toán');
        $this->get('/management/estimate-norms')->assertOk()->assertSee('Định mức đang áp dụng');
    }

    public function test_admin_imports_legacy_norms_as_active_version_one_without_overwriting_legacy_rows(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        // Simulate an existing installation that has legacy norms but predates version tables being populated.
        DB::table('estimation_norm_activation_events')->delete();
        DB::table('estimation_norm_active')->delete();
        DB::table('estimation_norm_version_items')->delete();
        DB::table('estimation_norm_versions')->delete();
        $legacyCount = DB::table('estimation_norms')->count();

        $this->actingAs($admin)->post('/management/estimate-norms/import')
            ->assertRedirect('/management/estimate-norms')->assertSessionHasNoErrors();
        $this->post('/management/estimate-norms/import')
            ->assertRedirect('/management/estimate-norms')->assertSessionHasNoErrors();

        $version = DB::table('estimation_norm_versions')->where('building_type', 'NHA_CAP_4')->first();
        $this->assertNotNull($version);
        $this->assertSame(1, (int) $version->version);
        $this->assertSame(5, DB::table('estimation_norm_version_items')->where('version_id', $version->id)->count());
        $this->assertSame((int) $version->id, (int) DB::table('estimation_norm_active')
            ->where('building_type', 'NHA_CAP_4')->value('version_id'));
        $this->assertSame(1, DB::table('estimation_norm_versions')->where('building_type', 'NHA_CAP_4')->count());
        $this->assertSame($legacyCount, DB::table('estimation_norms')->count());
    }

    public function test_new_version_activates_directly_and_keeps_older_version_recoverable(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post('/management/estimate-norms/import');
        $firstVersionId = DB::table('estimation_norm_versions')->where('building_type', 'NHA_PHO_BTCT')->value('id');
        $cementId = DB::table('products')->where('code', 'PCB40')->value('id');

        $this->post('/management/estimate-norms', [
            'building_type' => 'NHA_PHO_BTCT',
            'label' => 'Nhà phố bê tông cốt thép',
            'change_note' => 'Bản kiểm tra định mức mới',
            'items' => [[
                'product_id' => $cementId,
                'quantity_per_square_meter' => '2.0000',
                'per_floor' => '0',
                'rounding' => 'whole',
                'source' => 'Định mức minh họa phiên bản 2',
            ]],
        ])->assertRedirect('/management/estimate-norms?building_type=NHA_PHO_BTCT')->assertSessionHasNoErrors();

        $secondVersion = DB::table('estimation_norm_versions')
            ->where('building_type', 'NHA_PHO_BTCT')->where('version', 2)->first();
        $this->assertNotNull($secondVersion);
        $this->assertSame((int) $secondVersion->id, (int) DB::table('estimation_norm_active')
            ->where('building_type', 'NHA_PHO_BTCT')->value('version_id'));
        $this->assertSame('1.5000', DB::table('estimation_norm_version_items')
            ->where('version_id', $firstVersionId)->where('product_id', $cementId)->value('quantity_per_square_meter'));
        $this->get('/estimate?building_type=NHA_PHO_BTCT&area=100&floors=2')->assertOk()
            ->assertSee('200 bao')->assertSee('phiên bản 2')->assertSee('Định mức minh họa phiên bản 2');

        $this->post('/management/estimate-norms/'.$firstVersionId.'/activate')
            ->assertRedirect('/management/estimate-norms?building_type=NHA_PHO_BTCT')->assertSessionHasNoErrors();
        $this->assertSame((int) $firstVersionId, (int) DB::table('estimation_norm_active')
            ->where('building_type', 'NHA_PHO_BTCT')->value('version_id'));
        $this->assertSame(3, DB::table('estimation_norm_activation_events')
            ->where('building_type', 'NHA_PHO_BTCT')->count());
        $this->get('/estimate?building_type=NHA_PHO_BTCT&area=100&floors=2')->assertOk()->assertSee('300 bao');
    }

    public function test_invalid_norm_version_does_not_change_active_version(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post('/management/estimate-norms/import');
        $cementId = DB::table('products')->where('code', 'PCB40')->value('id');
        $activeBefore = DB::table('estimation_norm_active')->where('building_type', 'NHA_PHO_BTCT')->value('version_id');

        $this->post('/management/estimate-norms', [
            'building_type' => 'NHA_PHO_BTCT',
            'label' => 'Nhà phố bê tông cốt thép',
            'change_note' => 'Không hợp lệ',
            'items' => [[
                'product_id' => $cementId,
                'quantity_per_square_meter' => '0',
                'per_floor' => '0',
                'rounding' => 'round_up',
                'source' => '',
            ]],
        ])->assertSessionHasErrors([
            'items.0.quantity_per_square_meter',
            'items.0.rounding',
            'items.0.source',
        ]);

        $this->assertSame((int) $activeBefore, (int) DB::table('estimation_norm_active')
            ->where('building_type', 'NHA_PHO_BTCT')->value('version_id'));
        $this->assertSame(1, DB::table('estimation_norm_versions')
            ->where('building_type', 'NHA_PHO_BTCT')->count());
    }
}
