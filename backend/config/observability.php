<?php

return [
    'minimum_free_disk_bytes' => (int) env('HEALTH_MINIMUM_FREE_DISK_BYTES', 536870912),
    'failed_jobs_threshold' => (int) env('HEALTH_FAILED_JOBS_THRESHOLD', env('APP_ENV') === 'production' ? 0 : 10),
    'require_scheduler_heartbeat' => (bool) env('HEALTH_REQUIRE_SCHEDULER', env('APP_ENV') === 'production'),
    'scheduler_stale_seconds' => (int) env('HEALTH_SCHEDULER_STALE_SECONDS', 180),
];
