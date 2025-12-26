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

use function sprintf;

/**
 * Class representing the result of a rate limit check.
 * @final
 */
class LimitCheckResult
{
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
        private readonly string $limit_type
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
}
