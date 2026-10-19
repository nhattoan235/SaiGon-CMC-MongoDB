<?php

/** Final logical schema derived from the archived SQL migrations; item tables map to owned arrays. */
return [
    'users' => [
        'collection' => 'users',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'username' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'phone' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'address' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'role' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'customer',
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'active',
            ],
            'failed_logins' => [
                'type' => 'int',
                'nullable' => false,
                'default' => 0,
            ],
            'locked_until' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'email' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'email_verified_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'password' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'remember_token' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['email'],
            'unique' => true,
            'non_null' => ['email'],
        ], [
            'fields' => ['phone'],
            'unique' => true,
            'non_null' => ['phone'],
        ], [
            'fields' => ['username'],
            'unique' => true,
            'non_null' => ['username'],
        ]],
        'foreign_keys' => [],
    ],
    'password_reset_tokens' => [
        'collection' => 'password_reset_tokens',
        'primary' => 'email',
        'numeric' => false,
        'fields' => [
            'email' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'token' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [],
        'foreign_keys' => [],
    ],
    'sessions' => [
        'collection' => 'sessions',
        'primary' => 'id',
        'numeric' => false,
        'fields' => [
            'id' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'user_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'ip_address' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'user_agent' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'payload' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'last_activity' => [
                'type' => 'int',
                'nullable' => false,
            ],
        ],
        'indexes' => [[
            'fields' => ['last_activity'],
            'unique' => false,
        ], [
            'fields' => ['user_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [],
    ],
    'cache' => [
        'collection' => 'cache',
        'primary' => 'key',
        'numeric' => false,
        'fields' => [
            'key' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'value' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'expiration' => [
                'type' => 'int',
                'nullable' => false,
            ],
        ],
        'indexes' => [[
            'fields' => ['expiration'],
            'unique' => false,
        ]],
        'foreign_keys' => [],
    ],
    'cache_locks' => [
        'collection' => 'cache_locks',
        'primary' => 'key',
        'numeric' => false,
        'fields' => [
            'key' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'owner' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'expiration' => [
                'type' => 'int',
                'nullable' => false,
            ],
        ],
        'indexes' => [[
            'fields' => ['expiration'],
            'unique' => false,
        ]],
        'foreign_keys' => [],
    ],
    'jobs' => [
        'collection' => 'jobs',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'queue' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'payload' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'attempts' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'reserved_at' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'available_at' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'int',
                'nullable' => false,
            ],
        ],
        'indexes' => [[
            'fields' => ['queue'],
            'unique' => false,
        ]],
        'foreign_keys' => [],
    ],
    'job_batches' => [
        'collection' => 'job_batches',
        'primary' => 'id',
        'numeric' => false,
        'fields' => [
            'id' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'total_jobs' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'pending_jobs' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'failed_jobs' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'failed_job_ids' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'options' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'cancelled_at' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'finished_at' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [],
        'foreign_keys' => [],
    ],
    'failed_jobs' => [
        'collection' => 'failed_jobs',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'uuid' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'connection' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'queue' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'payload' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'exception' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'failed_at' => [
                'type' => 'datetime',
                'nullable' => false,
                'default' => '@now',
            ],
        ],
        'indexes' => [[
            'fields' => ['uuid'],
            'unique' => true,
        ], [
            'fields' => ['connection', 'queue', 'failed_at'],
            'unique' => false,
        ]],
        'foreign_keys' => [],
    ],
    'customers' => [
        'collection' => 'customers',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'user_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'company' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'tax_code' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'credit_limit' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['user_id'],
            'unique' => true,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'user_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'CASCADE',
            'match' => 'NONE',
        ]],
    ],
    'categories' => [
        'collection' => 'categories',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'parent_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'description' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'active' => [
                'type' => 'bool',
                'nullable' => false,
                'default' => true,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['parent_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'categories',
            'from' => 'parent_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'SET NULL',
            'match' => 'NONE',
        ]],
    ],
    'products' => [
        'collection' => 'products',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'category_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'unit' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'specification' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'manufacturer' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'price' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'weight_kg' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 12,
                'scale' => 3,
                'default' => null,
            ],
            'minimum_stock' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
                'default' => '0',
            ],
            'image_path' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'description' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'active' => [
                'type' => 'bool',
                'nullable' => false,
                'default' => true,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'direct_purchase_allowed' => [
                'type' => 'bool',
                'nullable' => false,
                'default' => false,
            ],
            'volume_m3' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 12,
                'scale' => 4,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['category_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'categories',
            'from' => 'category_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'SET NULL',
            'match' => 'NONE',
        ]],
    ],
    'price_history' => [
        'collection' => 'price_history',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'price' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'effective_date' => [
                'type' => 'date',
                'nullable' => false,
            ],
            'approved_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['approved_by'],
            'unique' => false,
        ], [
            'fields' => ['product_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'approved_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'catalog_changes' => [
        'collection' => 'catalog_changes',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'requested_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'reviewed_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'type' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'proposed_data' => [
                'type' => 'json',
                'nullable' => false,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'pending',
            ],
            'review_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['reviewed_by'],
            'unique' => false,
        ], [
            'fields' => ['requested_by'],
            'unique' => false,
        ], [
            'fields' => ['product_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'reviewed_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'requested_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'suppliers' => [
        'collection' => 'suppliers',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'tax_code' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'phone' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'email' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'address' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'contact_person' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'active' => [
                'type' => 'bool',
                'nullable' => false,
                'default' => true,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ]],
        'foreign_keys' => [],
    ],
    'warehouses' => [
        'collection' => 'warehouses',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'address' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'active' => [
                'type' => 'bool',
                'nullable' => false,
                'default' => true,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ]],
        'foreign_keys' => [],
    ],
    'inventory' => [
        'collection' => 'inventory',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'warehouse_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
                'default' => '0',
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['warehouse_id', 'product_id'],
            'unique' => true,
        ], [
            'fields' => ['product_id'],
            'unique' => false,
        ], [
            'fields' => ['warehouse_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'warehouses',
            'from' => 'warehouse_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'quote_items' => [
        'collection' => 'quotes',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'quote_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'unit_price' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'line_total' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'specification' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['product_id'],
            'unique' => false,
        ], [
            'fields' => ['quote_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'quotes',
            'from' => 'quote_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'CASCADE',
            'match' => 'NONE',
        ]],
        'parent' => 'quote_id',
    ],
    'order_items' => [
        'collection' => 'orders',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'order_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'unit_price' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'line_total' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['product_id'],
            'unique' => false,
        ], [
            'fields' => ['order_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'orders',
            'from' => 'order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'CASCADE',
            'match' => 'NONE',
        ]],
        'parent' => 'order_id',
    ],
    'purchase_orders' => [
        'collection' => 'purchase_orders',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'supplier_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'warehouse_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'requested_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'approved_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'pending',
            ],
            'total' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['approved_by'],
            'unique' => false,
        ], [
            'fields' => ['requested_by'],
            'unique' => false,
        ], [
            'fields' => ['warehouse_id'],
            'unique' => false,
        ], [
            'fields' => ['supplier_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'approved_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'requested_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'warehouses',
            'from' => 'warehouse_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'suppliers',
            'from' => 'supplier_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'purchase_items' => [
        'collection' => 'purchase_orders',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'purchase_order_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'unit_price' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'received_quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
                'default' => '0',
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['product_id'],
            'unique' => false,
        ], [
            'fields' => ['purchase_order_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'purchase_orders',
            'from' => 'purchase_order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'CASCADE',
            'match' => 'NONE',
        ]],
        'parent' => 'purchase_order_id',
    ],
    'stock_documents' => [
        'collection' => 'stock_documents',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'type' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'warehouse_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'order_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'purchase_order_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'created_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'approved_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'draft',
            ],
            'note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['approved_by'],
            'unique' => false,
        ], [
            'fields' => ['created_by'],
            'unique' => false,
        ], [
            'fields' => ['purchase_order_id'],
            'unique' => false,
        ], [
            'fields' => ['order_id'],
            'unique' => false,
        ], [
            'fields' => ['warehouse_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'approved_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'created_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'purchase_orders',
            'from' => 'purchase_order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'orders',
            'from' => 'order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 4,
            'seq' => 0,
            'table' => 'warehouses',
            'from' => 'warehouse_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'stock_document_items' => [
        'collection' => 'stock_documents',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'stock_document_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'unit_price' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'book_quantity' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 18,
                'scale' => 3,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['product_id'],
            'unique' => false,
        ], [
            'fields' => ['stock_document_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'stock_documents',
            'from' => 'stock_document_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'CASCADE',
            'match' => 'NONE',
        ]],
        'parent' => 'stock_document_id',
    ],
    'stock_movements' => [
        'collection' => 'stock_movements',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'stock_document_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'warehouse_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'change' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'balance_after' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['product_id'],
            'unique' => false,
        ], [
            'fields' => ['warehouse_id'],
            'unique' => false,
        ], [
            'fields' => ['stock_document_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'warehouses',
            'from' => 'warehouse_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'stock_documents',
            'from' => 'stock_document_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'debts' => [
        'collection' => 'debts',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'direction' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'customer_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'supplier_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'order_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'purchase_order_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'amount' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'paid' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'due_on' => [
                'type' => 'date',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'open',
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'settlement_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['purchase_order_id'],
            'unique' => false,
        ], [
            'fields' => ['order_id'],
            'unique' => false,
        ], [
            'fields' => ['supplier_id'],
            'unique' => false,
        ], [
            'fields' => ['customer_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'purchase_orders',
            'from' => 'purchase_order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'orders',
            'from' => 'order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'suppliers',
            'from' => 'supplier_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'users',
            'from' => 'customer_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'payments' => [
        'collection' => 'payments',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'debt_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'recorded_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'amount' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'method' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'reference' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'paid_at' => [
                'type' => 'datetime',
                'nullable' => false,
            ],
            'note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['recorded_by'],
            'unique' => false,
        ], [
            'fields' => ['debt_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'recorded_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'debts',
            'from' => 'debt_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'handovers' => [
        'collection' => 'handovers',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'shipment_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'recipient' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'recipient_phone' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'condition_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'document_path' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'accepted' => [
                'type' => 'bool',
                'nullable' => false,
                'default' => false,
            ],
            'signed_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['shipment_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'shipments',
            'from' => 'shipment_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'reports' => [
        'collection' => 'reports',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'type' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'created_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'approved_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'from_date' => [
                'type' => 'date',
                'nullable' => false,
            ],
            'to_date' => [
                'type' => 'date',
                'nullable' => false,
            ],
            'snapshot' => [
                'type' => 'json',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'draft',
            ],
            'review_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'filters' => [
                'type' => 'json',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['approved_by'],
            'unique' => false,
        ], [
            'fields' => ['created_by'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'approved_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'created_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'estimation_norms' => [
        'collection' => 'estimation_norms',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'building_type' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'quantity_per_square_meter' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 4,
            ],
            'source' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'per_floor' => [
                'type' => 'bool',
                'nullable' => false,
                'default' => false,
            ],
            'rounding' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'whole',
            ],
        ],
        'indexes' => [[
            'fields' => ['building_type', 'product_id'],
            'unique' => true,
        ], [
            'fields' => ['product_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'customer_credits' => [
        'collection' => 'customer_credits',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'customer_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'return_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'amount' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'open',
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['return_id'],
            'unique' => true,
        ], [
            'fields' => ['customer_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'returns',
            'from' => 'return_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'customer_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'invoices' => [
        'collection' => 'invoices',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'order_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'issued_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'total' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'paid_at_issue' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'issued_at' => [
                'type' => 'datetime',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['order_id'],
            'unique' => true,
        ], [
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['issued_by'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'issued_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'orders',
            'from' => 'order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'quotes' => [
        'collection' => 'quotes',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'customer_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'salesperson_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'requested',
            ],
            'delivery_address' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'customer_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'staff_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'total' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'valid_until' => [
                'type' => 'date',
                'nullable' => true,
                'default' => null,
            ],
            'sent_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'accepted_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'discount_approved_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'discount_review_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['discount_approved_by'],
            'unique' => false,
        ], [
            'fields' => ['customer_id'],
            'unique' => false,
        ], [
            'fields' => ['salesperson_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'discount_approved_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'customer_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'users',
            'from' => 'salesperson_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'supplier_incidents' => [
        'collection' => 'supplier_incidents',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'purchase_order_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'purchase_item_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'recorded_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'resolved_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'type' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'note' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'resolution_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'open',
            ],
            'resolved_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['resolved_by'],
            'unique' => false,
        ], [
            'fields' => ['recorded_by'],
            'unique' => false,
        ], [
            'fields' => ['purchase_item_id'],
            'unique' => false,
        ], [
            'fields' => ['purchase_order_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'resolved_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'recorded_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'purchase_items',
            'from' => 'purchase_item_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'purchase_orders',
            'from' => 'purchase_order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'delivery_runs' => [
        'collection' => 'delivery_runs',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'created_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'carrier_name' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'vehicle' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'route_note' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'capacity_kg' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 12,
                'scale' => 3,
                'default' => null,
            ],
            'load_kg' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 12,
                'scale' => 3,
                'default' => null,
            ],
            'capacity_m3' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 12,
                'scale' => 4,
                'default' => null,
            ],
            'load_m3' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 12,
                'scale' => 4,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['created_by'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'created_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'shipments' => [
        'collection' => 'shipments',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'order_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'carrier_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'carrier_name' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'vehicle' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'route_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'planned',
            ],
            'planned_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'delivered_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'return_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'pickup_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'pickup_receiver' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'picked_up_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'delivery_run_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'stop_sequence' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['delivery_run_id'],
            'unique' => false,
        ], [
            'fields' => ['return_id'],
            'unique' => false,
        ], [
            'fields' => ['order_id'],
            'unique' => false,
        ], [
            'fields' => ['carrier_id'],
            'unique' => false,
        ], [
            'fields' => ['pickup_by'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'delivery_runs',
            'from' => 'delivery_run_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'returns',
            'from' => 'return_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'orders',
            'from' => 'order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'users',
            'from' => 'carrier_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 4,
            'seq' => 0,
            'table' => 'users',
            'from' => 'pickup_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'supplier_returns' => [
        'collection' => 'supplier_returns',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'return_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'supplier_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'recorded_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'note' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'sent_at' => [
                'type' => 'datetime',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'debt_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'settled_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'credit_amount' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 18,
                'scale' => 2,
                'default' => null,
            ],
            'settlement_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'settled_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['return_id'],
            'unique' => true,
        ], [
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['settled_by'],
            'unique' => false,
        ], [
            'fields' => ['debt_id'],
            'unique' => false,
        ], [
            'fields' => ['supplier_id'],
            'unique' => false,
        ], [
            'fields' => ['recorded_by'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'settled_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'debts',
            'from' => 'debt_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'returns',
            'from' => 'return_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'suppliers',
            'from' => 'supplier_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 4,
            'seq' => 0,
            'table' => 'users',
            'from' => 'recorded_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'customer_credit_movements' => [
        'collection' => 'customer_credit_movements',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'customer_credit_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'debt_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'recorded_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'type' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'amount' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'method' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'reference' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['recorded_by'],
            'unique' => false,
        ], [
            'fields' => ['debt_id'],
            'unique' => false,
        ], [
            'fields' => ['customer_credit_id'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'users',
            'from' => 'recorded_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'debts',
            'from' => 'debt_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'customer_credits',
            'from' => 'customer_credit_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'orders' => [
        'collection' => 'orders',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'contract_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'customer_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'awaiting_payment',
            ],
            'total' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'paid' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'delivery_address' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'planned_delivery_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'credit_override_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'credit_override_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'released_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'released_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'fulfillment_method' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'delivery',
            ],
            'pickup_warehouse_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'pickup_recipient' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'picked_up_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'picked_up_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'reviewed_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'reviewed_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'rejection_reason' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'quote_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'requires_contract' => [
                'type' => 'bool',
                'nullable' => false,
                'default' => false,
            ],
            'stock_shortage_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'estimated_available_on' => [
                'type' => 'date',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['quote_id'],
            'unique' => true,
            'non_null' => ['quote_id'],
        ], [
            'fields' => ['contract_id'],
            'unique' => true,
            'non_null' => ['contract_id'],
        ], [
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['picked_up_by'],
            'unique' => false,
        ], [
            'fields' => ['pickup_warehouse_id'],
            'unique' => false,
        ], [
            'fields' => ['credit_override_by'],
            'unique' => false,
        ], [
            'fields' => ['customer_id'],
            'unique' => false,
        ], [
            'fields' => ['released_by'],
            'unique' => false,
        ], [
            'fields' => ['reviewed_by'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'quotes',
            'from' => 'quote_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'picked_up_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'warehouses',
            'from' => 'pickup_warehouse_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'users',
            'from' => 'credit_override_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 4,
            'seq' => 0,
            'table' => 'contracts',
            'from' => 'contract_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 5,
            'seq' => 0,
            'table' => 'users',
            'from' => 'customer_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 6,
            'seq' => 0,
            'table' => 'users',
            'from' => 'released_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 7,
            'seq' => 0,
            'table' => 'users',
            'from' => 'reviewed_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'contracts' => [
        'collection' => 'contracts',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'quote_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'created_by' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'approved_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'draft',
            ],
            'total' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
            ],
            'deposit_required' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'payment_terms' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'delivery_terms' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'approval_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'document_path' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'effective_date' => [
                'type' => 'date',
                'nullable' => true,
                'default' => null,
            ],
            'expires_on' => [
                'type' => 'date',
                'nullable' => true,
                'default' => null,
            ],
            'signed_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'customer_confirmed_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'customer_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'cancelled_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'cancelled_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'cancellation_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'order_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['order_id'],
            'unique' => true,
            'non_null' => ['order_id'],
        ], [
            'fields' => ['quote_id'],
            'unique' => true,
            'non_null' => ['quote_id'],
        ], [
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['approved_by'],
            'unique' => false,
        ], [
            'fields' => ['created_by'],
            'unique' => false,
        ], [
            'fields' => ['cancelled_by'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'orders',
            'from' => 'order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'users',
            'from' => 'approved_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'users',
            'from' => 'created_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'quotes',
            'from' => 'quote_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 4,
            'seq' => 0,
            'table' => 'users',
            'from' => 'cancelled_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'returns' => [
        'collection' => 'returns',
        'primary' => 'id',
        'numeric' => true,
        'fields' => [
            'id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'code' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'order_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'product_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'customer_id' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'processed_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'quantity' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 3,
            ],
            'credit_amount' => [
                'type' => 'decimal',
                'nullable' => false,
                'precision' => 18,
                'scale' => 2,
                'default' => '0',
            ],
            'type' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'condition' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'status' => [
                'type' => 'string',
                'nullable' => false,
                'default' => 'requested',
            ],
            'reason' => [
                'type' => 'string',
                'nullable' => false,
            ],
            'inspection_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'collected_by' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'collected_at' => [
                'type' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
            'handed_over_by' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'collected_quantity' => [
                'type' => 'decimal',
                'nullable' => true,
                'precision' => 18,
                'scale' => 3,
                'default' => null,
            ],
            'collection_note' => [
                'type' => 'string',
                'nullable' => true,
                'default' => null,
            ],
            'replacement_warehouse_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'restock_purchase_order_id' => [
                'type' => 'int',
                'nullable' => true,
                'default' => null,
            ],
            'estimated_available_on' => [
                'type' => 'date',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'indexes' => [[
            'fields' => ['code'],
            'unique' => true,
        ], [
            'fields' => ['restock_purchase_order_id'],
            'unique' => false,
        ], [
            'fields' => ['replacement_warehouse_id'],
            'unique' => false,
        ], [
            'fields' => ['processed_by'],
            'unique' => false,
        ], [
            'fields' => ['customer_id'],
            'unique' => false,
        ], [
            'fields' => ['product_id'],
            'unique' => false,
        ], [
            'fields' => ['order_id'],
            'unique' => false,
        ], [
            'fields' => ['collected_by'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'id' => 0,
            'seq' => 0,
            'table' => 'purchase_orders',
            'from' => 'restock_purchase_order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 1,
            'seq' => 0,
            'table' => 'warehouses',
            'from' => 'replacement_warehouse_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 2,
            'seq' => 0,
            'table' => 'users',
            'from' => 'processed_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 3,
            'seq' => 0,
            'table' => 'users',
            'from' => 'customer_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 4,
            'seq' => 0,
            'table' => 'products',
            'from' => 'product_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 5,
            'seq' => 0,
            'table' => 'orders',
            'from' => 'order_id',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ], [
            'id' => 6,
            'seq' => 0,
            'table' => 'users',
            'from' => 'collected_by',
            'to' => 'id',
            'on_update' => 'NO ACTION',
            'on_delete' => 'NO ACTION',
            'match' => 'NONE',
        ]],
    ],
    'migrations' => [
        'collection' => 'migrations', 'primary' => 'id', 'numeric' => true,
        'fields' => [
            'id' => ['type' => 'int', 'nullable' => false],
            'migration' => ['type' => 'string', 'nullable' => false],
            'batch' => ['type' => 'int', 'nullable' => false],
        ],
        'indexes' => [], 'foreign_keys' => [],
    ],
];
