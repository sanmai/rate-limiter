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

// Composer autoload
require 'vendor/autoload.php';

use SlidingWindowCounter\RateLimiter\RateLimiter;
use SlidingWindowCounter\Cache\MemcachedAdapter;

// ======================================================
// Step 1: Set up the rate limiter
// ======================================================

// Initialize Memcached connection
$memcached = new \Memcached();
$memcached->addServer('localhost', 11211);

// Create the cache adapter using Memcached
$cache = new MemcachedAdapter($memcached);

// Create a rate limiter for user ID "123" with:
// - 1-minute (60 second) windows
// - 1-hour (3600 second) observation period
$rateLimiter = RateLimiter::create(
    'user-123',           // Subject being rate limited
    'api_requests',       // Name for the rate limiter
    60,                   // Window size: 60 seconds (1 minute)
    3600,                 // Observation period: 3600 seconds (1 hour)
    $cache
);

// ======================================================
// Step 2: Simulate some requests
// ======================================================

// Let's simulate 120 requests in the current window
for ($i = 0; $i < 120; $i++) {
    $rateLimiter->increment();
}

// ======================================================
// Step 3: Check the window limit (100 requests per minute)
// ======================================================

$windowResult = $rateLimiter->checkWindowLimit(100);

if ($windowResult->isLimitExceeded()) {
    echo "WINDOW LIMIT EXCEEDED\n";
    echo $windowResult->getLimitExceededMessage() . "\n";
    echo "Current count: " . $windowResult->getCount() . "\n";
    echo "Window limit: " . $windowResult->getLimit() . "\n";

    // In a real application, you would return a 429 response:
    // header('HTTP/1.1 429 Too Many Requests');
    // header('Retry-After: 60');
    // exit;
} else {
    echo "Window limit not exceeded\n";
}

echo "\n";

// ======================================================
// Step 4: Check the period limit (1000 requests per hour)
// ======================================================

$periodResult = $rateLimiter->checkPeriodLimit(1000);

if ($periodResult->isLimitExceeded()) {
    echo "PERIOD LIMIT EXCEEDED\n";
    echo $periodResult->getLimitExceededMessage() . "\n";
} else {
    echo "Period limit not exceeded (current count: " . $periodResult->getCount() . ")\n";
}

echo "\n";

// ======================================================
// Step 5: Get more detailed information
// ======================================================

echo "Subject being rate limited: " . $windowResult->getSubject() . "\n";
echo "Current count in window: " . $rateLimiter->getLatestValue() . "\n";
echo "Total count in observation period: " . $rateLimiter->getTotal() . "\n";
echo "Limit type: " . $windowResult->getLimitType() . "\n";

echo "\n";

// ======================================================
// Step 6: Demonstrate multiple rate limiters
// ======================================================

// Create a stricter rate limiter for sensitive endpoints
$sensitiveLimiter = RateLimiter::create(
    'user-123',         // Same user
    'sensitive_api',    // Different name for the sensitive API endpoints
    60,                 // Same window size (60 seconds)
    3600,               // Same observation period (1 hour)
    $cache              // Same cache
);

// Simulate 15 requests to sensitive endpoints
for ($i = 0; $i < 15; $i++) {
    $sensitiveLimiter->increment();
}

// Check against a lower limit (10 requests per minute for sensitive endpoints)
$sensitiveResult = $sensitiveLimiter->checkWindowLimit(10);

if ($sensitiveResult->isLimitExceeded()) {
    echo "SENSITIVE API LIMIT EXCEEDED\n";
    echo $sensitiveResult->getLimitExceededMessage() . "\n";
} else {
    echo "Sensitive API limit not exceeded\n";
}

echo "\n";

// ======================================================
// Step 7: Error handling example
// ======================================================

try {
    // Check the window limit again
    $result = $rateLimiter->checkWindowLimit(100);

    if ($result->isLimitExceeded()) {
        // Log the rate limit event (in a real application)
        // $logger->warning('Rate limit exceeded', [
        //     'subject' => $result->getSubject(),
        //     'count' => $result->getCount(),
        //     'limit' => $result->getLimit()
        // ]);

        echo "Limit exceeded, would send 429 response in real application\n";
    } else {
        echo "Request would be processed normally\n";
    }
} catch (Exception $e) {
    // In a real application, you might want to fail open if the rate limiter fails
    echo "Error occurred: " . $e->getMessage() . "\n";
    echo "In a real application, you might want to allow the request to proceed\n";
}

// ======================================================
// Expected output
// ======================================================
// WINDOW LIMIT EXCEEDED
// Rate limit exceeded for user-123: 120 actions in the window (limit: 100)
// Current count: 120
// Window limit: 100
//
// Period limit not exceeded (current count: 120)
//
// Subject being rate limited: user-123
// Current count in window: 120
// Total count in observation period: 120
// Limit type: window
//
// SENSITIVE API LIMIT EXCEEDED
// Rate limit exceeded for user-123: 15 actions in the window (limit: 10)
//
// Limit exceeded, would send 429 response in real application
