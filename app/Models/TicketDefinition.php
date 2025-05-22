<?php

namespace App\Models;

use App\Enums\TicketDefinitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TicketDefinition extends Model
{
    use HasFactory, SoftDeletes, HasTranslations;

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'total_quantity',
        'availability_window_start',
        'availability_window_end',
        'availability_window_start_utc',
        'availability_window_end_utc',
        'min_per_order',
        'max_per_order',
        'status',
        'metadata',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'price' => 'integer',
        'total_quantity' => 'integer',
        'availability_window_start' => 'string',
        'availability_window_end' => 'string',
        'availability_window_start_utc' => 'datetime',
        'availability_window_end_utc' => 'datetime',
        'min_per_order' => 'integer',
        'max_per_order' => 'integer',
        'status' => TicketDefinitionStatus::class,
        'metadata' => 'json',
    ];

    /**
     * T
     * e EventOccurrences that this TicketDefinition is available for.
     */
    public function eventOccurrences(): BelongsToMany
    {
        return $this->belongsToMany(EventOccurrence::class, 'event_occurrence_ticket_definition')
            ->withPivot(['quantity_for_occurrence', 'price_override', 'availability_status'])
            ->withTimestamps();
    }

    // Removed eventOccurrence() belongsTo relationship
    // Removed event() relationship

    // public function creator() // Assuming these are handled by a global scope or trait if needed
    // {
    //     return $this->belongsTo(User::class, 'created_by');
    // }

    // public function updater() // Assuming these are handled by a global scope or trait if needed
    // {
    //     return $this->belongsTo(User::class, 'updated_by');
    // }
}
