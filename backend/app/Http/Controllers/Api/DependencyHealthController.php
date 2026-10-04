<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DependencyHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('SELECT 1')),
            'cache' => $this->check(function (): void {
                $key = 'health:cache:'.bin2hex(random_bytes(8));
                Cache::put($key, 'ok', 10);

                if (Cache::get($key) !== 'ok') {
                    throw new \RuntimeException('Cache round-trip failed.');
                }

                Cache::forget($key);
            }),
            'queue' => $this->check(fn () => Queue::size()),
            'storage' => $this->storageCheck(),
            'scheduler' => $this->schedulerCheck(),
        ];

        $checks['failed_jobs'] = $this->check(function (): void {
            if (! Schema::hasTable('failed_jobs') || DB::table('failed_jobs')->count() > (int) config('observability.failed_jobs_threshold')) {
                throw new \RuntimeException('Failed job threshold exceeded.');
            }
        });

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    private function storageCheck(): bool
    {
        return $this->check(function (): void {
            $path = storage_path();
            $freeBytes = disk_free_space($path);

            if (! is_writable($path) || $freeBytes === false || $freeBytes < (int) config('observability.minimum_free_disk_bytes')) {
                throw new \RuntimeException('Storage is unavailable or below the free-space threshold.');
            }
        });
    }

    private function schedulerCheck(): bool
    {
        if (! config('observability.require_scheduler_heartbeat')) {
            return true;
        }

        $lastSeen = Cache::get('health:scheduler:last_seen');

        return is_numeric($lastSeen)
            && now()->timestamp - (int) $lastSeen <= (int) config('observability.scheduler_stale_seconds');
    }

    private function check(callable $check): bool
    {
        try {
            $check();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
