<?php declare(strict_types=1);
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
