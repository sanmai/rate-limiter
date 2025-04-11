<?php
/**
 * Copyright 2025 Alexey Kopytko
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace SlidingWindowCounter\RateLimiter;

use SlidingWindowCounter\SlidingWindowCounter;
use SlidingWindowCounter\Cache;

use function Later\later;
use function Pipeline\take;

/**
 * A rate limiter.
 */
final class RateLimiter
{
    /**
     * Creates a new RateLimiter instance.
     */
    public function __construct(
        /**
         * The subject being rate limited (e.g., IP address, ASN).
         */
        private readonly string $subject,
        /**
         * The sliding window counter instance.
         */
        private readonly SlidingWindowCounter $counter
    ) {
    }

    /**
     * Builds a new RateLimiter instance using the provided counter cache.
     * @param string $subject The subject being rate limited (e.g., IP address, ASN).
     * @param string $cache_name The name of the cache.
     * @param int $window_size The size of the sliding window.
     * @param int $observation_period The observation period.
     * @param Cache\CounterCache $counter_cache The counter cache instance.
     */
    public static function create(
        string $subject,
        string $cache_name,
        int $window_size,
        int $observation_period,
        Cache\CounterCache $counter_cache
    ): self {
        return new self(
            $subject,
            new SlidingWindowCounter(
                $cache_name,
                $window_size,
                $observation_period,
                $counter_cache
            )
        );
    }

    /**
     * Increments the counter by the given step.
     * @param int $step The step to increment the counter by.
     * @return void
     */
    public function increment(int $step = 1): void
    {
        $this->counter->increment($this->subject, $step);
    }

    /**
     * Returns the latest value of the counter for the given window size.
     * @return int
     */
    public function getLatestValue(): int
    {
        return (int) $this->counter->getLatestValue($this->subject);
    }

    /**
     * Returns the total value across all windows in the observation period.
     * @return int
     */
    public function getTotal(): int
    {
        return (int) take($this->counter->getTimeSeries($this->subject))->fold(0.0);
    }

    /**
     * Checks if the rate limit for the current window is exceeded.
     *
     * The window limit controls the rate of requests in the most recent time window
     * (determined by the window_size parameter passed to the constructor). This is
     * useful for preventing sudden bursts of traffic.
     *
     * This method only evaluates the latest window count when the result object's
     * methods are called, providing efficient performance through lazy evaluation.
     *
     * @param int $window_limit The maximum number of requests allowed in the current window.
     * @return LimitCheckResult A result object containing the limit check information.
     *                          Use isLimitExceeded() on this object to determine if the limit was exceeded.
     *
     * @example
     * $limiter = new RateLimiter('api', '192.168.1.1', 60, 3600, $cache);
     * $result = $limiter->checkWindowLimit(100);
     * if ($result->isLimitExceeded()) {
     *     echo $result->getLimitExceededMessage();
     *     // Handle the exceeded limit (e.g., return an error response)
     * }
     */
    public function checkWindowLimit(int $window_limit): LimitCheckResult
    {
        return new LimitCheckResult(
            $this->subject,
            later(fn () => yield $this->getLatestValue()),
            $window_limit,
            'window'
        );
    }

    /**
     * Checks if the rate limit for the entire observation period is exceeded.
     *
     * The period limit controls the total number of requests over the entire
     * observation period (determined by the observation_period parameter passed to
     * the constructor). This is useful for enforcing longer-term usage quotas.
     *
     * This method only evaluates the total count when the result object's
     * methods are called, providing efficient performance through lazy evaluation.
     *
     * Note that calculating the period limit may be more resource-intensive than
     * the window limit as it aggregates data across multiple windows.
     *
     * @param int $period_limit The maximum number of requests allowed in the entire observation period.
     * @return LimitCheckResult A result object containing the limit check information.
     *                          Use isLimitExceeded() on this object to determine if the limit was exceeded.
     *
     * @example
     * $limiter = new RateLimiter('api', '192.168.1.1', 60, 3600, $cache);
     * $result = $limiter->checkPeriodLimit(1000);
     * if ($result->isLimitExceeded()) {
     *     echo $result->getLimitExceededMessage();
     *     // Handle the exceeded limit (e.g., return an error response)
     * }
     */
    public function checkPeriodLimit(int $period_limit): LimitCheckResult
    {
        return new LimitCheckResult(
            $this->subject,
            later(fn () => yield $this->getTotal()),
            $period_limit,
            'period'
        );
    }
}
