<?php

namespace App\Models;

use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'endpoint_id',
        'event_id',
        'status',
        'attempt_count',
        'next_attempt_at',
        'last_error',
        'response_status',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'next_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<WebhookEndpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class);
    }

    /** @return BelongsTo<WebhookEndpoint, $this> */
    public function webhookEndpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    /** @return BelongsTo<WebhookEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'event_id');
    }

    /** @return BelongsTo<WebhookEvent, $this> */
    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'event_id');
    }

    /** @return HasMany<DeliveryAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class, 'delivery_id');
    }
}
