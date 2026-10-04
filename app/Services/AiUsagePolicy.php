<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Central guard for explicit, cacheable AI operations. */
class AiUsagePolicy
{
    public function enabled(): bool
    {
        return (bool) config('services.openai.enabled', true);
    }

    /**
     * Execute an operation once for its input identity. Exceptions are never cached.
     * The lock also prevents a burst of identical requests from creating paid calls.
     */
    public function remember(string $operation, array $inputs, callable $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        $identity = hash('sha256', $operation.'|'.json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $key = 'ai:result:'.$operation.':'.$identity;
        $lock = Cache::lock('ai:lock:'.$operation.':'.$identity, (int) config('services.openai.lock_seconds', 180));

        return $lock->block((int) config('services.openai.lock_wait_seconds', 5), function () use ($key, $callback) {
            $missing = new \stdClass;
            $cached = Cache::get($key, $missing);
            if ($cached !== $missing) {
                return $cached;
            }
            $result = $callback();
            Cache::put($key, $result, now()->addSeconds((int) config('services.openai.cache_ttl', 604800)));
            return $result;
        });
    }
}
