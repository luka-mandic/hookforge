<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filters shared by the global and the project-scoped delivery dashboards.
 *
 * Every filter is applied in the database query so pagination stays correct.
 */
final readonly class DeliveryFilters
{
    public function __construct(
        public string $status = '',
        public string $eventType = '',
        public string $endpointId = '',
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            status: $request->string('status')->toString(),
            eventType: $request->string('event_type')->toString(),
            endpointId: $request->string('endpoint')->toString(),
            dateFrom: self::date($request->input('date_from')),
            dateTo: self::date($request->input('date_to')),
        );
    }

    /**
     * @param  Builder<WebhookDelivery>  $query
     * @return Builder<WebhookDelivery>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->endpointId !== '', fn ($q) => $q->where('endpoint_id', $this->endpointId))
            ->when($this->eventType !== '', fn ($q) => $q->whereRelation('event', 'event_type', $this->eventType))
            ->when($this->dateFrom !== null, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== null, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
