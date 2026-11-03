<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\RefreshDocumentDatabase as RefreshDatabase;
use Tests\TestCase;

class EstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_does_not_overwrite_approved_product_price(): void
    {
        $this->seed();
        DB::table('products')->where('code', 'PCB40')->update(['price' => 123456]);
        $this->seed();
        $this->assertDatabaseHas('products', ['code' => 'PCB40', 'price' => 123456]);
    }

    public function test_legacy_norms_are_calculated_and_guest_prices_are_hidden(): void
    {
        $this->seed();
        $url = '/estimate?building_type=NHA_PHO_BTCT&area=100&floors=2';
        $this->get($url)->assertOk()->assertSee('300 bao')->assertSee('5 m³')->assertDontSee('Tạm tính:');
        $this->actingAs(User::factory()->create(['role' => 'customer', 'status' => 'active']))
            ->get($url)->assertOk()->assertSee('Tạm tính tham khảo:');
    }

    public function test_estimate_does_not_preselect_an_unrequested_building_type(): void
    {
        $this->seed();
        $response = $this->get('/estimate')->assertOk()->assertSee('Chọn loại công trình');

        $this->assertDoesNotMatchRegularExpression('/<option[^>]+selected[^>]*>\s*Biệt thự/su', $response->getContent());
    }

    public function test_estimate_exposes_only_the_manual_calculator(): void
    {
        $this->seed();

        $this->get('/estimate')->assertOk()
            ->assertSee('Loại công trình')
            ->assertSee('Tính vật liệu')
            ->assertDontSee('estimate-ai-form')
            ->assertDontSee('Phân tích mô tả')
            ->assertDontSee('Hoặc nhập thông tin bên dưới');
    }

    public function test_estimate_ai_suggestion_endpoint_is_removed(): void
    {
        $this->postJson('/estimate/suggest', ['description' => 'Nhà phố 80 m2 mỗi tầng, 2 tầng'])
            ->assertNotFound();
    }

    public function test_estimate_building_types_are_loaded_from_active_database_norms(): void
    {
        $this->seed();
        $product = DB::table('products')->insertGetId([
            'code' => 'P-NHA-VUON', 'name' => 'Vật liệu nhà vườn', 'unit' => 'bao', 'price' => 100,
            'active' => true,
        ]);
        DB::table('estimation_norms')->insert([
            'building_type' => 'NHA_SAN_VUON', 'product_id' => $product,
            'quantity_per_square_meter' => '2.5000', 'per_floor' => false, 'rounding' => 'whole',
            'source' => 'Định mức minh họa kiểm thử',
        ]);

        $this->get('/estimate?building_type=NHA_SAN_VUON&area=10&floors=1')->assertOk()
            ->assertSee('NHA SAN VUON')->assertSee('Vật liệu nhà vườn')
            ->assertSee('25 bao')->assertSee('Định mức minh họa kiểm thử');
    }


}
