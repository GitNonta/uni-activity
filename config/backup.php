<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Define the local storage directory and retention rules for backups.
    |
    */
    'path' => storage_path('app/backups'),

    'disk' => env('BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Retention Policy
    |--------------------------------------------------------------------------
    |
    | - keep_days: Number of days to keep regular backups before cleanup.
    | - keep_minimum_count: Always preserve at least this number of most recent backups.
    |
    */
    'retention' => [
        'keep_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
        'keep_minimum_count' => (int) env('BACKUP_KEEP_MINIMUM_COUNT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables & Directories
    |--------------------------------------------------------------------------
    |
    | Tables or paths that should be skipped during dump or file compression.
    |
    */
    'exclude_tables' => [
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
    ],

    'exclude_storage_paths' => [
        '.gitignore',
        '.DS_Store',
        'temp',
        'cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Enable notifications when automated backups complete or fail.
    |
    */
    'notifications' => [
        'line' => (bool) env('BACKUP_NOTIFY_LINE', true),
        'mail' => (bool) env('BACKUP_NOTIFY_MAIL', false),
    ],
];
