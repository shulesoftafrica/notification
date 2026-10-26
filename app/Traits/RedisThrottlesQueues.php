<?php

namespace App\Traits;

use App\Services\QueueThrottleService;
use Illuminate\Support\Facades\Log;

trait RedisThrottlesQueues
{
    /**
     * Take a provider send slot, or release the job until the window resets.
     * Returns true if the job was released (caller must stop), false if it can proceed.
     */
    protected function handleQueueThrottling(string $provider, string $channel): bool
    {
        $throttle = app(QueueThrottleService::class);
        $result = $throttle->attempt($provider, $channel);

        if ($result['allowed']) {
            return false;
        }

        $delay = $throttle->releaseDelay($result['retry_after']);

        Log::debug('Queue job throttled - releasing with delay', [
            'provider' => $provider,
            'channel' => $channel,
            'delay_seconds' => $delay,
            'message_id' => $this->messageId ?? 'unknown',
        ]);

        $this->release($delay);

        return true;
    }
}
