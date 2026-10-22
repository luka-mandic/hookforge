# Structured application logging

Hookforge logs the most important webhook lifecycle transitions as structured log entries. The goal is to provide enough correlation data for debugging delivery behavior without exposing sensitive webhook content or credentials.

## Log scope

The application emits structured log entries for:

- webhook event published
- delivery created
- delivery started
- delivery succeeded
- delivery failed
- delivery retried
- delivery dead-lettered

Each log entry includes stable identifiers such as:

- project_id
- event_id
- delivery_id
- endpoint_id
- attempt_number

This makes it possible to trace a single webhook lifecycle across the event publisher, delivery queue, and worker process.

## What is not logged

The application intentionally avoids logging:

- webhook secrets
- authorization headers or credentials
- event payload contents
- delivery response bodies
- any sensitive customer data embedded in webhook payloads

This keeps the logs useful for operations and debugging while preserving security and privacy.

## Example log entry

```php
logger()->info('webhook.delivery.started', [
    'project_id' => $delivery->project_id,
    'event_id' => $delivery->event_id,
    'delivery_id' => $delivery->getKey(),
    'endpoint_id' => $delivery->endpoint_id,
    'attempt_number' => $delivery->attempt_count + 1,
]);
```

The Laravel logger serializes this as structured key/value context, which works well with log aggregation systems such as JSON log collectors or centralized log platforms.

## Operational guidance

When investigating webhook issues, correlate the `delivery_id` and `event_id` values in the log stream. For retry scenarios, the `attempt_number` field helps identify whether the delivery is being requeued, failed, or dead-lettered after exhausting the retry budget.
