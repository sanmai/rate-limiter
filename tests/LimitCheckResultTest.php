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

namespace Tests\SlidingWindowCounter;

use SlidingWindowCounter\RateLimiter\LimitCheckResult;
use PHPUnit\Framework\TestCase;

use function Later\now;

/**
 * @covers \SlidingWindowCounter\RateLimiter\LimitCheckResult
 *
 * @internal
 */
final class LimitCheckResultTest extends TestCase
{
    private const WINDOW_SIZE = 60; // 60 seconds

    public function testLimitNotExceeded(): void
    {
        $result = new LimitCheckResult('test', now(1), 2, 'window', self::WINDOW_SIZE);

        $this->assertFalse($result->isLimitExceeded());
        $this->assertSame('test', $result->getSubject());
        $this->assertSame(1, $result->getCount());
        $this->assertSame(2, $result->getLimit());
        $this->assertSame('window', $result->getLimitType());
        $this->assertNull($result->getLimitExceededMessage());
    }

    public function testLimitExceeded(): void
    {
        $result = new LimitCheckResult('127.0.0.1', now(3), 2, 'window', self::WINDOW_SIZE);

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame('127.0.0.1', $result->getSubject());
        $this->assertSame(3, $result->getCount());
        $this->assertSame(2, $result->getLimit());
        $this->assertSame('window', $result->getLimitType());
        $this->assertSame(
            'Rate limit exceeded for 127.0.0.1: 3 actions in the window (limit: 2)',
            $result->getLimitExceededMessage()
        );
    }

    public function testGetWaitTimeReturnsZeroWhenLimitNotExceeded(): void
    {
        $result = new LimitCheckResult('test', now(1), 2, 'window', self::WINDOW_SIZE);

        $this->assertFalse($result->isLimitExceeded());
        $this->assertSame(0, $result->getWaitTime());
    }

    public function testGetWaitTimeCalculation(): void
    {
        // count=120, limit=100, window=60s
        // wait = (120 - 100) / 120 * 60s = 20/120 * 60 = 10 seconds
        $window_size = 60;
        $result = new LimitCheckResult('test', now(120), 100, 'window', $window_size);

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame(10_000_000_000, $result->getWaitTime());
    }

    public function testGetWaitTimeWithJitter(): void
    {
        // count=120, limit=100, window=60s => base wait = 10s
        $window_size = 60;
        $result = new LimitCheckResult('test', now(120), 100, 'window', $window_size);

        $this->assertTrue($result->isLimitExceeded());

        $base_wait = 10_000_000_000;

        // With 0.5 jitter factor, wait time should be between 10s and 15s (in nanoseconds)
        $wait_with_jitter = $result->getWaitTime(0.5);
        $this->assertGreaterThanOrEqual($base_wait, $wait_with_jitter);
        $this->assertLessThanOrEqual((int) ($base_wait * 1.5), $wait_with_jitter);
    }

    public function testGetWaitTimeWithZeroJitterReturnsExactValue(): void
    {
        // count=120, limit=100, window=60s => wait = 10s
        $window_size = 60;
        $result = new LimitCheckResult('test', now(120), 100, 'window', $window_size);

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame(10_000_000_000, $result->getWaitTime(0.0));
    }

    public function testGetWaitTimeSecondsReturnsZeroWhenLimitNotExceeded(): void
    {
        $result = new LimitCheckResult('test', now(1), 2, 'window', self::WINDOW_SIZE);

        $this->assertFalse($result->isLimitExceeded());
        $this->assertSame(0, $result->getWaitTimeSeconds());
    }

    public function testGetWaitTimeSecondsRoundsUp(): void
    {
        // count=150, limit=100, window=60s
        // wait = (150 - 100) / 150 * 60 = 50/150 * 60 = 20 seconds exactly
        // But let's use count=140: (140-100)/140 * 60 = 40/140 * 60 = 17.14... seconds => rounds to 18
        $window_size = 60;
        $result = new LimitCheckResult('test', now(140), 100, 'window', $window_size);

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame(18, $result->getWaitTimeSeconds());
    }

    public function testGetWaitTimeSecondsExact(): void
    {
        // count=120, limit=100, window=60s => wait = 10s exactly
        $window_size = 60;
        $result = new LimitCheckResult('test', now(120), 100, 'window', $window_size);

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame(10, $result->getWaitTimeSeconds());
    }

    public function testGetWaitTimeWithSmallExcess(): void
    {
        // count=101, limit=100, window=60s
        // wait = (101 - 100) / 101 * 60 = 1/101 * 60 = 0.594059405... seconds
        $window_size = 60;
        $result = new LimitCheckResult('test', now(101), 100, 'window', $window_size);

        $this->assertTrue($result->isLimitExceeded());
        // 0.594... seconds = 594_059_406 nanoseconds (rounded up)
        $this->assertSame(594_059_406, $result->getWaitTime());
        // Rounds up to 1 second
        $this->assertSame(1, $result->getWaitTimeSeconds());
    }
}
