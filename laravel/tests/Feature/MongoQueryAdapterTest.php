<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mongo\Builder;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Decimal128;
use Tests\Concerns\ResetsMongoDatabase;
use Tests\TestCase;

class MongoQueryAdapterTest extends TestCase
{
    use ResetsMongoDatabase;

    public function test_application_queries_use_the_native_domain_adapter(): void
    {
        $this->assertInstanceOf(Builder::class, DB::table('products'));
    }

    public function test_embedded_items_join_without_overwriting_their_identity_and_left_join_defaults_to_null(): void
    {
        $product = DB::table('products')->insertGetId(['code' => 'JOIN', 'name' => 'Gạch', 'unit' => 'viên']);
        $quote = DB::table('quotes')->insertGetId(['code' => 'BG-JOIN', 'customer_id' => 44, 'delivery_address' => 'A']);
        $item = DB::table('quote_items')->insertGetId(['quote_id' => $quote, 'product_id' => $product, 'quantity' => '1.250', 'unit_price' => '12.50', 'line_total' => '15.62']);
        $row = DB::table('quote_items')->join('products', 'products.id', '=', 'quote_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('quote_items.id', $item)->select('quote_items.*', 'products.name as product_name', 'categories.name as category_name')->first();
        $this->assertSame($item, $row->id);
        $this->assertSame($quote, $row->quote_id);
        $this->assertSame('1.250', $row->quantity);
        $this->assertSame('Gạch', $row->product_name);
        $this->assertNull($row->category_name);
        $this->assertNull(DB::table('products')->find($product)->category_id);
        $this->assertInstanceOf(Decimal128::class, DB::connection()->getCollection('quotes')->findOne(['_id' => $quote])['items'][0]['quantity']);
    }

    public function test_grouped_decimal_lookup_and_coalesce_preserve_stock_per_product(): void
    {
        $product = DB::table('products')->insertGetId(['code' => 'SUM', 'name' => 'Cát', 'unit' => 'm³']);
        $other = DB::table('products')->insertGetId(['code' => 'NONE', 'name' => 'Đá', 'unit' => 'm³']);
        $quote = DB::table('quotes')->insertGetId(['code' => 'BG-SUM', 'customer_id' => 44, 'delivery_address' => 'A']);
        foreach ([$product, $other] as $id) {
            DB::table('quote_items')->insert(['quote_id' => $quote, 'product_id' => $id, 'quantity' => '2.000', 'unit_price' => '1.00', 'line_total' => '2.00']);
        }
        DB::table('inventory')->insert([
            ['warehouse_id' => 1, 'product_id' => $product, 'quantity' => '1.100'],
            ['warehouse_id' => 2, 'product_id' => $product, 'quantity' => '2.200'],
        ]);
        $rows = DB::table('quote_items')->leftJoinSub(DB::table('inventory')->select('product_id')->selectRaw('SUM(quantity) as available')->groupBy('product_id'),
            'stock', 'stock.product_id', '=', 'quote_items.product_id')
            ->select('quote_items.product_id')->selectRaw('COALESCE(stock.available, 0) as available')->orderBy('quote_items.product_id')->get();
        $this->assertEquals('3.300', $rows[0]->available);
        $this->assertEquals(0, $rows[1]->available);
        $this->assertSame(2, $rows->count());
        $this->assertEquals('3.300', DB::table('inventory')->sum('quantity'));
    }

    public function test_nested_filters_and_subqueries_execute_with_pagination_and_distinct_shapes(): void
    {
        foreach (['Alpha', 'Beta', 'Gamma'] as $index => $name) {
            DB::table('products')->insert(['code' => 'P-'.$index, 'name' => $name, 'unit' => 'bao', 'price' => (string) (($index + 1) * 10)]);
        }
        $products = DB::table('products')->whereIn('id', DB::table('products')->where('price', '>=', 20)->select('id'))
            ->where(fn ($query) => $query->where('name', 'Beta')->orWhere('name', 'Gamma'))->orderBy('price')->paginate(1, ['id', 'name'], 'page', 2);
        $this->assertSame(2, $products->total());
        $this->assertSame('Gamma', $products->first()->name);
        $this->assertSame(['Beta', 'Gamma'], DB::table('products')->where('price', '>=', 20)->orderBy('price')->pluck('name')->all());
        $this->assertSame(['bao'], DB::table('products')->select('unit')->distinct()->pluck('unit')->all());
    }

    public function test_case_and_accent_insensitive_search_keeps_sql_wildcard_semantics(): void
    {
        DB::table('products')->insert(['code' => 'XM-1', 'name' => 'Xi Măng Đỏ', 'unit' => 'bao']);
        $this->assertSame(1, DB::table('products')->where('name', 'like', '%mang do%')->count());
        $this->assertSame(1, DB::table('products')->where('code', 'like', 'xm-_')->count());
        $this->assertSame(0, DB::table('products')->where('name', 'like', '%sắt%')->count());
    }

    public function test_embedded_updates_deletes_and_parent_transaction_rollback_are_consistent(): void
    {
        $quote = DB::table('quotes')->insertGetId(['code' => 'BG-WRITE', 'customer_id' => 44, 'delivery_address' => 'A']);
        $item = DB::table('quote_items')->insertGetId(['quote_id' => $quote, 'product_id' => 1, 'quantity' => 2, 'unit_price' => 3, 'line_total' => 6]);
        $this->assertSame(1, DB::table('quote_items')->where('id', $item)->update(['quantity' => '3.125']));
        DB::beginTransaction();
        DB::table('quote_items')->where('id', $item)->lockForUpdate()->first();
        DB::table('quote_items')->where('id', $item)->update(['quantity' => '9.000']);
        DB::rollBack();
        $this->assertSame('3.125', DB::table('quote_items')->find($item)->quantity);
        $this->assertSame(1, DB::table('quote_items')->where('id', $item)->delete());
        $this->assertFalse(DB::table('quote_items')->where('id', $item)->exists());
    }

    public function test_finance_aggregates_preserve_exact_amounts_dates_and_snapshot_column_order(): void
    {
        DB::table('payments')->insert([
            ['code' => 'PAY-A', 'debt_id' => 1, 'recorded_by' => 1, 'amount' => '0.10', 'method' => 'cash', 'paid_at' => '2026-01-31 23:59:59'],
            ['code' => 'PAY-B', 'debt_id' => 1, 'recorded_by' => 1, 'amount' => '0.20', 'method' => 'cash', 'paid_at' => '2026-01-01 00:00:00'],
            ['code' => 'PAY-C', 'debt_id' => 1, 'recorded_by' => 1, 'amount' => '99.00', 'method' => 'cash', 'paid_at' => '2026-02-01 00:00:00'],
        ]);
        $row = DB::table('payments')->whereBetween('paid_at', ['2026-01-01 00:00:00', '2026-01-31 23:59:59'])
            ->select('method')->selectRaw('COUNT(*) as transactions, SUM(amount) as amount')->groupBy('method')->first();
        $this->assertSame(['method', 'transactions', 'amount'], array_keys((array) $row));
        $this->assertSame(2, $row->transactions);
        $this->assertSame('0.30', $row->amount);
        $this->assertSame('2026-01-31 23:59:59', DB::table('payments')->where('code', 'PAY-A')->value('paid_at'));
    }

    public function test_negative_join_predicates_exclude_sql_null_rows(): void
    {
        DB::table('products')->insert([
            ['code' => 'NULL', 'name' => 'Unknown category', 'unit' => 'bao'],
            ['code' => 'MATCH', 'name' => 'Known category', 'unit' => 'bao', 'category_id' => 1],
        ]);
        DB::table('categories')->insert(['id' => 1, 'code' => 'CAT', 'name' => 'Material']);
        $query = DB::table('products')->leftJoin('categories', 'categories.id', '=', 'products.category_id');
        $this->assertSame(1, (clone $query)->whereNotIn('categories.name', ['Other'])->count());
        $this->assertSame(1, (clone $query)->where('categories.name', '!=', 'Other')->count());
        $this->assertSame(0, (clone $query)->whereNotIn('categories.name', ['Other', null])->count());
        $this->assertSame(2, (clone $query)->whereNotIn('categories.name', [])->count());
        $this->assertSame(0, (clone $query)->whereIn('categories.name', [null])->count());
    }

    public function test_ignored_duplicate_insert_does_not_abort_the_business_transaction(): void
    {
        DB::table('inventory')->insert(['warehouse_id' => 1, 'product_id' => 1, 'quantity' => '5.000']);
        DB::transaction(function (): void {
            $this->assertSame(0, DB::table('inventory')->insertOrIgnore(['warehouse_id' => 1, 'product_id' => 1, 'quantity' => '0.000']));
            DB::table('inventory')->where('warehouse_id', 1)->where('product_id', 1)->update(['quantity' => '4.000']);
        });
        $this->assertSame('4.000', DB::table('inventory')->value('quantity'));
        $this->assertTrue(DB::table('inventory')->updateOrInsert(['warehouse_id' => 1, 'product_id' => 1], ['quantity' => '3.000']));
        $this->assertSame(1, DB::table('inventory')->count());
        $this->assertSame('3.000', DB::table('inventory')->value('quantity'));
    }

    public function test_new_eloquent_customer_names_are_searchable_through_joined_quotes(): void
    {
        $customer = User::factory()->create(['name' => 'Khách An', 'role' => 'customer']);
        DB::table('quotes')->insert(['code' => 'BG-NAME', 'customer_id' => $customer->id, 'delivery_address' => 'A']);
        $rows = DB::table('quotes')->join('users', 'users.id', '=', 'quotes.customer_id')
            ->where(fn ($matches) => $matches->where('quotes.code', 'like', '%khach an%')->orWhere('users.name', 'like', '%khach an%'))
            ->select('quotes.*', 'users.name as customer_name')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Khách An', $rows->first()->customer_name);
    }

    public function test_the_mongo_connection_rejects_sql_execution_explicitly(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SQL execution is not supported by the MongoDB connection.');
        DB::select('SELECT 1');
    }

    public function test_unknown_raw_sql_fails_instead_of_silently_executing_a_partial_query(): void
    {
        $this->expectException(\LogicException::class);
        DB::table('products')->selectRaw('AVG(price) as price')->get();
    }
}
