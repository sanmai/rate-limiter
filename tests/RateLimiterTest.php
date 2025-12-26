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

namespace Tests\SlidingWindowCounter\RateLimiter;

use SlidingWindowCounter\Cache\CounterCache;
use SlidingWindowCounter\SlidingWindowCounter;
use SlidingWindowCounter\RateLimiter\RateLimiter;
use PHPUnit\Framework\TestCase;

use function array_sum;

/**
 * @covers \SlidingWindowCounter\RateLimiter\RateLimiter
 *
 * @internal
 */
final class RateLimiterTest extends TestCase
{
    public function testItBuildsRateLimiter()
    {
        $mock = $this->getMockBuilder(CounterCache::class)
            ->getMock();

        $mock->expects($this->once())
            ->method('increment');

        $rate_limiter = RateLimiter::create(
            'subject',
            'cache_name',
            1,
            2,
            $mock
        );

        $rate_limiter->increment(1);
    }

    public function testIncrementDefault()
    {
        $mock = $this->getMockBuilder(SlidingWindowCounter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('increment')
            ->with('test', 1);

        $rate_limiter = new RateLimiter('test', $mock);
        $rate_limiter->increment();
    }

    public function testIncrement()
    {
        $mock = $this->getMockBuilder(SlidingWindowCounter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('increment')
            ->with('test', 2);

        $rate_limiter = new RateLimiter('test', $mock);
        $rate_limiter->increment(2);
    }

    public function testGetLatest()
    {
        $mock = $this->getMockBuilder(SlidingWindowCounter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('getLatestValue')
            ->with('test')
            ->willReturn(5.4);

        $rate_limiter = new RateLimiter('test', $mock);
        $this->assertSame(5, $rate_limiter->getLatestValue());
    }

    public static function provideTestCheckWindowLimit(): iterable
    {
        yield 'limit not exceeded' => [3.1, 7, false];

        yield 'limit exceeded exactly' => [3.1, 3, true];

        yield 'limit exceeded' => [3.1, 2, true];
    }

    /**
     * @dataProvider provideTestCheckWindowLimit
     */
    public function testCheckWindowLimit(float $latest_value, int $limit, bool $is_limit_exceeded)
    {
        $mock = $this->getMockBuilder(SlidingWindowCounter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('getLatestValue')
            ->with('test')
            ->willReturn($latest_value);

        $rate_limiter = new RateLimiter('test', $mock);
        $result = $rate_limiter->checkWindowLimit($limit);

        $this->assertSame($is_limit_exceeded, $result->isLimitExceeded());
        $this->assertSame('test', $result->getSubject());
        $this->assertSame((int) $latest_value, $result->getCount());
        $this->assertSame($limit, $result->getLimit());
        $this->assertSame('window', $result->getLimitType());
    }

    public function testGetTotal()
    {
        $mock = $this->getMockBuilder(SlidingWindowCounter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('getTimeSeries')
            ->with('test')
            ->willReturn([1.0, 1.9, 3.1]);

        $rate_limiter = new RateLimiter('test', $mock);
        $this->assertSame(6, $rate_limiter->getTotal());
    }

    public static function provideTestCheckPeriodLimit(): iterable
    {
        yield  'limit not exceeded' => [[1.0, 1.9, 3.1], 7, false];

        yield 'limit exceeded exactly' => [[1.0, 1.9, 3.1], 6, true];

        yield 'limit exceeded' => [[1.0, 1.9, 3.1], 4, true];
    }

    /**
     * @dataProvider provideTestCheckPeriodLimit
     */
    public function testCheckPeriodLimit(array $time_series, int $limit, bool $is_limit_exceeded)
    {
        $mock = $this->getMockBuilder(SlidingWindowCounter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('getTimeSeries')
            ->with('test')
            ->willReturn($time_series);

        $rate_limiter = new RateLimiter('test', $mock);
        $result = $rate_limiter->checkPeriodLimit($limit);

        $this->assertSame($is_limit_exceeded, $result->isLimitExceeded());
        $this->assertSame('test', $result->getSubject());
        $this->assertSame((int) array_sum($time_series), $result->getCount());
        $this->assertSame($limit, $result->getLimit());
        $this->assertSame('period', $result->getLimitType());
    }
}
