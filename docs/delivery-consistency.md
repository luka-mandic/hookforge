# Delivery processing consistency

The webhook delivery worker uses a database-backed claim step before sending an outbound HTTP request.

## Strategy

When a worker receives a delivery message, it opens a database transaction and locks the target row with `SELECT ... FOR UPDATE` before checking the delivery status.

The worker can only continue if the row is still in a claimable state such as `queued` or `retry-scheduled` and not already `processing` or `delivered`.

Once claimed, the worker updates the row to `processing` and then sends the HTTP request outside the transaction. This ensures that a second worker cannot claim the same delivery while the first worker is still in flight.

## Why this works

This approach uses the database as the source of truth for delivery ownership. RabbitMQ may deliver the same message more than once, and multiple worker processes may pick up the same delivery at roughly the same time; the row-level lock prevents both from processing the same record concurrently.

Successful deliveries remain in the `delivered` state, which prevents re-sending the same payload unless a new message is explicitly created.

## Limitations

This strategy protects against duplicate processing across multiple worker processes that share the same database, but it does not eliminate all distributed race conditions in a multi-database or multi-region deployment.

If the application is scaled beyond a single primary database, the system should eventually use a stronger distributed lock or a durable coordination mechanism. The current implementation is a practical database-backed consistency boundary for a single Laravel application with PostgreSQL.
