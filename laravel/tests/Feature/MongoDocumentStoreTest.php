<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mongo\DocumentStore;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Decimal128;
use MongoDB\Driver\Exception\BulkWriteException;
use Tests\Concerns\ResetsMongoDatabase;
use Tests\TestCase;

class MongoDocumentStoreTest extends TestCase
{
    use ResetsMongoDatabase;

    public function test_reset_refuses_a_runtime_database_before_any_collection_write(): void
    {
        $original = config('database.connections.mongodb.database');
        DB::purge('mongodb');
        config(['database.connections.mongodb.database' => 'cmc_runtime_forbidden']);
        try {
            $this->setUpResetsMongoDatabase();
            $this->fail('Reset must refuse every database outside the explicit test prefix.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('Test reset refused', $exception->getMessage());
        } finally {
            config(['database.connections.mongodb.database' => $original]);
            DB::purge('mongodb');
        }
    }

    public function test_ignored_existing_inventory_does_not_abort_the_transaction(): void
    {
        $store = new DocumentStore(DB::connection());
        $id = $store->insert('inventory', ['warehouse_id' => 1, 'product_id' => 1, 'quantity' => '4.125']);
        DB::transaction(function () use ($store, $id): void {
            $this->assertSame(0, $store->insertOrIgnore('inventory', ['warehouse_id' => 1, 'product_id' => 1, 'quantity' => 0]));
            $store->update('inventory', ['id' => $id], ['quantity' => '3.125']);
        });
        $this->assertSame('3.125', DB::table('inventory')->find($id)->quantity);
        $this->assertDatabaseCount('inventory', 1);
    }

    public function test_embedded_ignored_id_is_idempotent_without_replacing_the_line(): void
    {
        $store = new DocumentStore(DB::connection());
        $quote = $store->insert('quotes', ['code' => 'BG-OWNED', 'customer_id' => 1, 'delivery_address' => 'A']);
        $values = ['id' => 51, 'quote_id' => $quote, 'product_id' => 1, 'quantity' => '1.125', 'unit_price' => '2.00', 'line_total' => '2.25'];
        DB::transaction(function () use ($store, $values): void {
            $this->assertSame(1, $store->insertOrIgnore('quote_items', $values));
            $this->assertSame(0, $store->insertOrIgnore('quote_items', array_replace($values, ['quantity' => '9.000'])));
            $this->assertSame(1, $store->update('quote_items', ['id' => 51], ['line_total' => '2.26']));
        });
        $this->assertDatabaseCount('quote_items', 1);
        $this->assertSame('1.125', DB::table('quote_items')->find(51)->quantity);
        $this->assertSame('2.26', DB::table('quote_items')->find(51)->line_total);
        $this->assertSame(52, $store->nextId('quote_items'));
    }

    public function test_explicit_root_id_advances_the_counter_and_patch_does_not_reset_defaults(): void
    {
        $store = new DocumentStore(DB::connection());
        $store->insert('products', ['id' => 200, 'code' => 'EXPLICIT', 'name' => 'Cát', 'unit' => 'm³', 'price' => '0.10', 'active' => false]);
        $this->assertTrue($store->updateOrInsert('products', ['id' => '200'], ['price' => '0.20']));
        $product = DB::table('products')->find(200);
        $this->assertFalse($product->active);
        $this->assertSame('Cát', $product->name);
        $this->assertNull($product->category_id);
        $this->assertSame('0.20', $product->price);
        $this->assertSame(201, $store->nextId('products'));
        $this->assertInstanceOf(Decimal128::class, DB::connection()->getCollection('products')->findOne(['_id' => 200])->price);
    }

    public function test_embedded_upsert_patches_only_its_stable_item_and_retains_siblings(): void
    {
        $store = new DocumentStore(DB::connection());
        $quote = $store->insert('quotes', ['code' => 'BG-UPSERT', 'customer_id' => 1, 'delivery_address' => 'A']);
        $base = ['product_id' => 1, 'quantity' => '1.000', 'unit_price' => '2.00', 'line_total' => '2.00'];
        $this->assertTrue($store->updateOrInsert('quote_items', ['quote_id' => $quote, 'id' => 10], $base));
        $store->insert('quote_items', ['quote_id' => $quote, 'id' => 11] + $base);
        $this->assertTrue($store->updateOrInsert('quote_items', ['quote_id' => (string) $quote, 'id' => '10'], ['quantity' => '1.250']));
        $this->assertSame('1.250', DB::table('quote_items')->find(10)->quantity);
        $this->assertSame('1.000', DB::table('quote_items')->find(11)->quantity);
        $this->assertDatabaseCount('quote_items', 2);
    }

    public function test_business_code_unique_index_rejects_case_and_accent_duplicates(): void
    {
        $store = new DocumentStore(DB::connection());
        $store->insert('categories', ['code' => 'CÁT', 'name' => 'Sand']);
        try {
            $store->insert('categories', ['code' => 'cat', 'name' => 'Duplicate']);
            $this->fail('The declared unique code must ignore case and accents.');
        } catch (BulkWriteException $exception) {
            $this->assertSame(11000, $exception->getCode());
        }
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_account_unique_email_and_native_login_comparisons_ignore_case(): void
    {
        $user = User::factory()->create(['email' => 'Person@Example.com', 'username' => 'MiXeD-Login']);
        $this->assertSame($user->id, User::where('username', 'mixed-login')->first()?->id);
        try {
            User::factory()->create(['email' => 'person@example.com']);
            $this->fail('Email uniqueness must ignore case.');
        } catch (BulkWriteException $exception) {
            $this->assertSame(11000, $exception->getCode());
        }
    }

    public function test_collated_insert_or_ignore_uses_the_same_identity_as_its_unique_index(): void
    {
        $store = new DocumentStore(DB::connection());
        $store->insert('categories', ['code' => 'Mixed', 'name' => 'Original']);
        DB::transaction(function () use ($store): void {
            $this->assertSame(0, $store->insertOrIgnore('categories', ['code' => 'MIXED', 'name' => 'Ignored']));
            $store->insert('categories', ['code' => 'NEXT', 'name' => 'Transaction still active']);
        });
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_user_name_shadow_tracks_native_eloquent_saves_and_is_hidden(): void
    {
        $user = User::factory()->create(['name' => 'Nguyễn Văn An']);
        $stored = DB::connection()->getCollection('users')->findOne(['_id' => $user->id]);
        $this->assertSame('nguyen van an', $stored->_search_name ?? null);
        $user->name = 'Trần Bình';
        $user->save();
        $this->assertSame('tran binh', DB::connection()->getCollection('users')->findOne(['_id' => $user->id])->_search_name);
        $this->assertArrayNotHasKey('_search_name', $user->toArray());
    }
}
