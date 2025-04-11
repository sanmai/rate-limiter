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

namespace Tests\SlidingWindowCounter\RateLimiter;

use SlidingWindowCounter\Cache\CounterCache;
use SlidingWindowCounter\Helper\Frame;
use SlidingWindowCounter\SlidingWindowCounter;
use SlidingWindowCounter\RateLimiter\RateLimiter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\SlidingWindowCounter\Cache\FakeCache;
use Tumblr\Chorus\FakeTimeKeeper;

use function array_keys;
use function iterator_to_array;
use function max;
use function Pipeline\take;
use function range;
use function sprintf;
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
