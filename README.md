# Rate Limiter 🚦⏱️

[![Latest Stable Version](https://img.shields.io/packagist/v/sanmai/rate-limiter.svg)](https://packagist.org/packages/sanmai/rate-limiter)
[![License](https://img.shields.io/badge/license-GPL--2.0-blue.svg)](https://github.com/sanmai/rate-limiter/blob/master/LICENSE)

## Cache-based API rate limiting for PHP applications

A lightweight, efficient PHP library for controlling request rates and preventing API abuse.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [How It Works](#how-it-works-the-simple-version)
- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Setting up a rate limiter](#setting-up-a-rate-limiter)
  - [Tracking requests](#tracking-requests)
  - [Checking rate limits](#checking-rate-limits)
  - [Getting more information](#getting-more-information)
- [Advanced Usage](#advanced-usage)
- [Cache Adapters](#cache-adapters)
- [Technical Details](#technical-details)
- [Contributing](#contributing)
- [License](#license)

## Installation

```bash
composer require sanmai/rate-limiter
```

## Overview

Need to prevent API abuse, protect your services from excessive traffic, or implement fair usage policies? This library provides a simple solution for rate limiting with the sliding window approach.

**Real-world example:** Imagine you need to limit API requests to 100 per minute and 1000 per hour per client. This library lets you create a rate limiter with a 1-minute window and 1-hour observation period, then check if a client exceeds either of these limits.

## Features

✅ **Two-level limiting** - Window-based and period-based limits<br>
✅ **Lazy evaluation** - Calculates limits only when needed<br>
✅ **Simple API** - Easy-to-use interface for rate limiting<br>
✅ **Detailed feedback** - Clear information about rate limit status<br>
✅ **Compatible with PHP 8.1+** - Works with modern PHP versions<br>
✅ **PSR-compatible** - Easily integrates with PSR-15 middleware

## How it works (the simple version)

This rate limiter provides two types of limits:

1. **Window limits** - Controls request rates in the most recent time window (e.g., 100 requests per minute)
2. **Period limits** - Controls total requests over a longer observation period (e.g., 1000 requests per hour)

The rate limiter itself tracks requests, while the limits are set when checking if they've been exceeded.

## Quick Start

### Setting up a rate limiter

```php
// Import necessary classes
use SlidingWindowCounter\RateLimiter\RateLimiter;
use SlidingWindowCounter\Cache\MemcachedAdapter;

// Create a rate limiter for an IP address with 1-minute windows and 1-hour observation period
$rateLimiter = RateLimiter::create(
    '192.168.1.1',          // Subject being rate limited (e.g., IP address)
    'api_requests',         // Name for your rate limiter
    60,                     // Window size: 60 seconds (1 minute)
    3600,                   // Observation period: 3600 seconds (1 hour)
    new MemcachedAdapter($memcached)
);
```

### Tracking requests

```php
// Record a request from this client
$rateLimiter->increment();

// You can also increment by a specific amount (for weighted actions)
$rateLimiter->increment(2); // Count this action as 2 requests
```

### Checking rate limits

```php
// Check if the client has exceeded window limit (100 requests per minute)
$windowResult = $rateLimiter->checkWindowLimit(100);

if ($windowResult->isLimitExceeded()) {
    // Window limit exceeded - client is sending requests too quickly
    echo $windowResult->getLimitExceededMessage();
    // Example output: "Rate limit exceeded for 192.168.1.1: 120 actions in the window (limit: 100)"
    
    // Return 429 Too Many Requests response
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: 60');
    exit;
}

// Check if the client has exceeded period limit (1000 requests per hour)
$periodResult = $rateLimiter->checkPeriodLimit(1000);

if ($periodResult->isLimitExceeded()) {
    // Period limit exceeded - client has sent too many requests in the observation period
    echo $periodResult->getLimitExceededMessage();
    
    // Return 429 Too Many Requests response
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: 3600');
    exit;
}
```

### Getting more information

```php
// Get information about the current rate limit status
$windowResult = $rateLimiter->checkWindowLimit(100);

// Subject being rate limited
$subject = $windowResult->getSubject(); // e.g., "192.168.1.1"

// Current count in the window
$count = $windowResult->getCount();

// Maximum limit
$limit = $windowResult->getLimit();

// Type of limit
$limitType = $windowResult->getLimitType(); // "window" or "period"

// Get the limit message (only if exceeded)
$message = $windowResult->getLimitExceededMessage();

// Get the latest value in the current window
$currentValue = $rateLimiter->getLatestValue();

// Get the total across all windows in the observation period
$totalRequests = $rateLimiter->getTotal();
```

## Advanced Usage

### Using multiple rate limiters for different constraints

You can create different rate limiters for different types of constraints:

```php
// General rate limiter with 1-minute windows and 1-hour observation period
$generalLimiter = RateLimiter::create($clientIp, 'general_api', 60, 3600, $cache);

// Check if client exceeds 100 requests per minute
$windowResult = $generalLimiter->checkWindowLimit(100);

// Check if client exceeds 1000 requests per hour
$periodResult = $generalLimiter->checkPeriodLimit(1000);

// Stricter limiter for sensitive endpoints with same time parameters
$sensitiveLimiter = RateLimiter::create($clientIp, 'sensitive_api', 60, 3600, $cache);

// Check if client exceeds 10 requests per minute for sensitive endpoints
$sensitiveWindowResult = $sensitiveLimiter->checkWindowLimit(10);

// Check if client exceeds 50 requests per hour for sensitive endpoints
$sensitivePeriodResult = $sensitiveLimiter->checkPeriodLimit(50);
```

### Implementing in middleware

Here's how you might implement rate limiting in a PSR-15 middleware:

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
{
    $ip = $request->getServerParams()['REMOTE_ADDR'];
    
    // Create rate limiter
    $rateLimiter = RateLimiter::create($ip, 'api_requests', 60, 3600, $this->cache);
    
    // Increment the counter
    $rateLimiter->increment();
    
    // Check window limit (e.g., 100 requests per minute)
    $windowResult = $rateLimiter->checkWindowLimit(100);
    if ($windowResult->isLimitExceeded()) {
        return $this->createRateLimitResponse(
            $windowResult->getLimitExceededMessage(),
            60
        );
    }
    
    // Check period limit (e.g., 1000 requests per hour)
    $periodResult = $rateLimiter->checkPeriodLimit(1000);
    if ($periodResult->isLimitExceeded()) {
        return $this->createRateLimitResponse(
            $periodResult->getLimitExceededMessage(),
            3600
        );
    }
    
    // Limits not exceeded, continue with the request
    return $handler->handle($request);
}
```

### Error Handling

Here are some common scenarios and how to handle them:

```php
try {
    // Create the rate limiter
    $rateLimiter = RateLimiter::create($ip, 'api_requests', 60, 3600, $cache);
    
    // Increment and check limits
    $rateLimiter->increment();
    $windowResult = $rateLimiter->checkWindowLimit(100);
    
    // Handle rate limit exceeded
    if ($windowResult->isLimitExceeded()) {
        // Log the rate limit event
        $this->logger->warning('Rate limit exceeded', [
            'ip' => $ip,
            'count' => $windowResult->getCount(),
            'limit' => $windowResult->getLimit(),
            'type' => $windowResult->getLimitType()
        ]);
        
        // Return appropriate response
        return $this->createRateLimitResponse(
            $windowResult->getLimitExceededMessage(),
            60
        );
    }
} catch (Exception $e) {
    // If the cache service is unavailable, fail open (allow the request)
    $this->logger->error('Rate limiter error', ['exception' => $e]);
    
    // Continue processing the request
    return $handler->handle($request);
}
```

## Cache Adapters

This library uses the cache adapters provided by the `sanmai/sliding-window-counter` package. For information about available adapters and how to create your own, please refer to the [sliding window counter documentation](https://github.com/sanmai/sliding-window-counter).

## Technical Details

This rate limiter is built on top of the [sliding window counter](https://github.com/sanmai/sliding-window-counter) library, which provides an efficient implementation of the sliding window algorithm.

### How the Sliding Window Algorithm Works

Unlike fixed window rate limiting (which can allow bursts of traffic at window boundaries), the sliding window approach provides smoother rate limiting by considering a continuously moving time window.

The key components of this rate limiter are:

- **Window Size**: Defines the duration of a single time window (e.g., 60 seconds)
- **Observation Period**: Defines the total period over which to track requests (e.g., 3600 seconds)
- **Window Limit**: Controls the rate of requests in the most recent time window
- **Period Limit**: Controls the total number of requests over the entire observation period
- **Lazy Evaluation**: Limits are only calculated when checked, improving performance

The library uses deferred evaluation through the `sanmai/later` package to ensure efficient processing, only calculating limits when they're actually needed.

### Requirements

- PHP 8.1 or higher

For more detailed technical information about how the sliding window algorithm works, please refer to the [sliding window counter documentation](https://github.com/sanmai/sliding-window-counter).

## Contributing

Contributions are welcome! Here are some ways you can contribute:

- Report bugs by creating an issue
- Suggest new features or improvements
- Submit pull requests with bug fixes or new features
- Improve documentation
- Write tests

Please ensure your code follows the existing style and includes appropriate tests.

## License

This library is licensed under the GNU General Public License v2.0. See the [LICENSE](LICENSE) file for details.
