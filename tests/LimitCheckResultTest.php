<?php declare(strict_types=1);
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
        $result = new LimitCheckResult('test', now(1), 2, 'window');

        $this->assertFalse($result->isLimitExceeded());
        $this->assertSame('test', $result->getSubject());
        $this->assertSame(1, $result->getCount());
        $this->assertSame(2, $result->getLimit());
        $this->assertSame('window', $result->getLimitType());
        $this->assertNull($result->getLimitExceededMessage());
    }

    public function testLimitExceeded()
    {
        $result = new LimitCheckResult('127.0.0.1', now(3), 2, 'window');

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
}
