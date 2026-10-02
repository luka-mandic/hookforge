# Webhook retry policy

Webhook deliveries use a configurable retry policy to decide whether a failed delivery should be retried and how long to wait before the next attempt.

## Defaults

The default policy is configured in the application service provider:

- maximum attempts: 5
- initial delay: 1000 ms
- maximum delay: 30000 ms
- multiplier: 2x

## Retryable outcomes

The worker retries transient failures, including:

- network errors and timeouts
- HTTP 429 (rate limiting)
- HTTP 500, 502, 503, and 504 responses

Permanent failures are not retried, including common client-side validation problems such as:

- HTTP 400
- HTTP 401
- HTTP 403
- HTTP 404
- HTTP 405
- HTTP 410
- HTTP 422

## Scheduling

Each failed retryable attempt records the delivery attempt and then reschedules the delivery with `next_attempt_at` set to `now + backoff delay`.

The delay grows exponentially using the configured multiplier until it reaches the configured maximum delay.
