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
    public function testLimitNotExceeded(): void
    {
        $result = new LimitCheckResult('test', now(1), 2, 'window', now(1_000_000_000));

        $this->assertFalse($result->isLimitExceeded());
        $this->assertSame('test', $result->getSubject());
        $this->assertSame(1, $result->getCount());
        $this->assertSame(2, $result->getLimit());
        $this->assertSame('window', $result->getLimitType());
        $this->assertNull($result->getLimitExceededMessage());
    }

    public function testLimitExceeded(): void
    {
        $result = new LimitCheckResult('127.0.0.1', now(3), 2, 'window', now(1_000_000_000));

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
        $result = new LimitCheckResult('test', now(1), 2, 'window', now(500_000_000));

        $this->assertFalse($result->isLimitExceeded());
        $this->assertSame(0, $result->getWaitTime());
    }

    public function testGetWaitTimeReturnsValueWhenLimitExceeded(): void
    {
        $wait_time_ns = 1_500_000_000;
        $result = new LimitCheckResult('test', now(3), 2, 'window', now($wait_time_ns));

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame($wait_time_ns, $result->getWaitTime());
    }

    public function testGetWaitTimeWithDeferredValue(): void
    {
        $deferred = \Later\later(function () {
            yield 1_000_000_000;
        });

        $result = new LimitCheckResult('test', now(3), 2, 'window', $deferred);

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame(1_000_000_000, $result->getWaitTime());
    }

    public function testGetWaitTimeWithJitter(): void
    {
        $wait_time_ns = 10_000_000_000; // 10 seconds
        $result = new LimitCheckResult('test', now(3), 2, 'window', now($wait_time_ns));

        $this->assertTrue($result->isLimitExceeded());

        // With 0.5 jitter factor, wait time should be between 10s and 15s (in nanoseconds)
        $wait_with_jitter = $result->getWaitTime(0.5);
        $this->assertGreaterThanOrEqual($wait_time_ns, $wait_with_jitter);
        $this->assertLessThanOrEqual((int) ($wait_time_ns * 1.5), $wait_with_jitter);
    }

    public function testGetWaitTimeWithZeroJitterReturnsExactValue(): void
    {
        $wait_time_ns = 5_000_000_000;
        $result = new LimitCheckResult('test', now(3), 2, 'window', now($wait_time_ns));

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame($wait_time_ns, $result->getWaitTime(0.0));
    }

    public function testGetWaitTimeSecondsReturnsZeroWhenLimitNotExceeded(): void
    {
        $result = new LimitCheckResult('test', now(1), 2, 'window', now(500_000_000));

        $this->assertFalse($result->isLimitExceeded());
        $this->assertSame(0, $result->getWaitTimeSeconds());
    }

    public function testGetWaitTimeSecondsRoundsUp(): void
    {
        // 1.5 seconds should round up to 2
        $result = new LimitCheckResult('test', now(3), 2, 'window', now(1_500_000_000));

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame(2, $result->getWaitTimeSeconds());
    }

    public function testGetWaitTimeSecondsExact(): void
    {
        // Exactly 3 seconds
        $result = new LimitCheckResult('test', now(3), 2, 'window', now(3_000_000_000));

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame(3, $result->getWaitTimeSeconds());
    }

    public function testGetWaitTimeSecondsRoundsUpSmallFraction(): void
    {
        // 1 nanosecond should round up to 1 second
        $result = new LimitCheckResult('test', now(3), 2, 'window', now(1));

        $this->assertTrue($result->isLimitExceeded());
        $this->assertSame(1, $result->getWaitTimeSeconds());
    }
}
