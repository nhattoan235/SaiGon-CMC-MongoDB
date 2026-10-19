<?php

namespace Tests\Unit;

use App\Mongo\DocumentStore;
use MongoDB\BSON\Decimal128;
use PHPUnit\Framework\TestCase;

class DocumentStoreTest extends TestCase
{
    public function test_embedded_items_expose_stable_legacy_keys(): void
    {
        $this->assertTrue(is_callable([DocumentStore::class, 'source']), 'The Mongo document mapper must expose logical item sources.');
        $source = DocumentStore::source('order_items');
        $this->assertSame('orders', $source['collection']);
        $this->assertContains(['$unwind' => '$items'], $source['pipeline']);
    }

    public function test_decimal_normalization_keeps_aliases_nulls_and_order(): void
    {
        $this->assertTrue(is_callable([DocumentStore::class, 'normalize']));
        $row = DocumentStore::normalize('orders', ['customer_name' => null, 'total' => new Decimal128('1234567890123456.78'), 'id' => 9]);
        $this->assertSame(['customer_name' => null, 'total' => '1234567890123456.78', 'id' => 9], (array) $row);
    }
}
