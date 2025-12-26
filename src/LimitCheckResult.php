<?php

/**
 * Copyright 2025 Alexey Kopytko <alexey@kopytko.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace SlidingWindowCounter\RateLimiter;

use Later\Interfaces\Deferred;

use function ceil;
use function random_int;
use function sprintf;

/**
 * Class representing the result of a rate limit check.
 * @final
 */
class LimitCheckResult
{
    private const NANOSECONDS_PER_SECOND = 1_000_000_000;
    private const JITTER_PRECISION = 1000;

    /**
     * Creates a new limit check result.
     */
    public function __construct(
        /**
         * The subject being rate limited (e.g., IP address, ASN).
         */
        private readonly string $subject,

        /**
         * Current count for this limit type.
         * @var Deferred<int>
         */
        private readonly Deferred $count,

        /**
         * Maximum limit value.
         */
        private readonly int $limit,

        /**
         * A descriptive name for this limit type (e.g., "window", "period").
         */
        private readonly string $limit_type,

        /**
         * Window size in seconds for wait time calculation.
         * @var int<1, max>
         */
        private readonly int $window_size
    ) {}

    /**
     * Checks if the limit is exceeded.
     *
     * @return bool True if the limit is exceeded.
     */
    public function isLimitExceeded(): bool
    {
        return $this->count->get() >= $this->limit;
    }

    /**
     * Gets the subject being rate limited.
     *
     * @return string The subject identifier.
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Gets the current count value.
     *
     * @return int The current count.
     */
    public function getCount(): int
    {
        return $this->count->get();
    }

    /**
     * Gets the limit value.
     *
     * @return int The limit.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Gets the type of limit that was checked.
     *
     * @return string The limit type (e.g., "window", "period").
     */
    public function getLimitType(): string
    {
        return $this->limit_type;
    }

    /**
     * Gets a descriptive message about the limit status.
     *
     * @return string|null A message describing the limit status, or null if the limit is not exceeded.
     */
    public function getLimitExceededMessage(): ?string
    {
        if (!$this->isLimitExceeded()) {
            return null;
        }

        return sprintf(
            'Rate limit exceeded for %s: %d actions in the %s (limit: %d)',
            $this->subject,
            $this->count->get(),
            $this->limit_type,
            $this->limit
        );
    }

    private function getWaitTimeRaw(int|float $scale = 1.0): int
    {
        // Assuming uniform distribution: we need to wait out X% of the window
        $excessRatio = ($this->count->get() - $this->limit) / $this->count->get();

        // To maintain numeric stability multiply only after
        return (int) ceil($scale * $this->window_size * $excessRatio);
    }

    /**
     * Returns nanoseconds to wait before the rate limit resets.
     * Returns 0 if limit is not exceeded.
     *
     * When multiple workers compete for the same time slot, use the jitter_factor
     * parameter to spread out retries and avoid thundering herd problems.
     * The jitter adds a random delay of up to (wait_time * jitter_factor).
     *
     * Usage with DuoClock (recommended):
     *   $clock->nanosleep($result->getWaitTime());
     *   $clock->nanosleep($result->getWaitTime(0.5)); // with jitter
     *
     * Usage with PHP's time_nanosleep():
     *   $ns = $result->getWaitTime();
     *   time_nanosleep(intdiv($ns, 1_000_000_000), $ns % 1_000_000_000);
     *
     * @param float $jitter_factor Jitter factor (0.0 = no jitter, 0.5 = up to 50% extra delay).
     * @return int Nanoseconds to wait, or 0 if limit is not exceeded.
     */
    public function getWaitTime(float $jitter_factor = 0.0): int
    {
        if (!$this->isLimitExceeded()) {
            return 0;
        }

        // Assuming uniform distribution: wait_time = (count - limit) / count * window_size
        $wait = $this->getWaitTimeRaw(self::NANOSECONDS_PER_SECOND);

        if ($jitter_factor > 0.0) {
            $jitter = (int) ($wait * $jitter_factor * random_int(0, self::JITTER_PRECISION) / self::JITTER_PRECISION);
            $wait += $jitter;
        }

        return $wait;
    }

    /**
     * Returns seconds to wait before the rate limit resets (rounded up).
     * Returns 0 if limit is not exceeded.
     *
     * Usage:
     *   sleep($result->getWaitTimeSeconds());
     *   // or for Retry-After header
     *   header(sprintf('Retry-After: %d', $result->getWaitTimeSeconds()));
     *
     * @return int Seconds to wait (rounded up), or 0 if limit is not exceeded.
     */
    public function getWaitTimeSeconds(): int
    {
        if (!$this->isLimitExceeded()) {
            return 0;
        }


        return $this->getWaitTimeRaw();
    }
}
