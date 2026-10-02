<?php

namespace App\Models;

use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'name',
        'url',
        'secret',
        'description',
        'status',
        'last_delivery_at',
    ];

    protected function casts(): array
    {
        return [
            'last_delivery_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<WebhookSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class, 'endpoint_id');
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }

    public function isEnabled(): bool
    {
        return $this->status === 'active';
    }
}
