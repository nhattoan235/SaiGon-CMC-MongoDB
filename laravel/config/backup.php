<?php

return [
    'no_billing_confirmed' => (bool) env('BACKUP_GOOGLE_NO_BILLING_CONFIRMED', false),
    'root' => env('BACKUP_STAGING_ROOT', base_path('../.runtime/backups')),
    'key' => env('BACKUP_ENCRYPTION_KEY', ''),
    'instance' => env('BACKUP_INSTANCE_ID', 'saigon-cmc-local'),
    'time' => env('BACKUP_AT', '20:00'),
    'timezone' => 'Asia/Ho_Chi_Minh',
    'cloud_copies' => 7,
    'local_copies' => 2,
    'max_extract_bytes' => 2 * 1024 * 1024 * 1024,
    'max_artifact_bytes' => 512 * 1024 * 1024,
    'sources' => ['private' => storage_path('app/private'), 'public' => storage_path('app/public')],
    'dump' => env('MONGODUMP_PATH', 'mongodump'),
    'restore' => env('MONGORESTORE_PATH', 'mongorestore'),
    'google' => [
        'client_id' => env('BACKUP_GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('BACKUP_GOOGLE_CLIENT_SECRET', ''),
        'redirect' => env('BACKUP_GOOGLE_REDIRECT_URI', 'http://127.0.0.1:8011/staff/backups/google/callback'),
    ],
    'excluded' => ['sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'],
];
