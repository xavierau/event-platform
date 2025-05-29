<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EventOccurrence extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia;

    protected $fillable = [
        'event_id',
        'venue_id',
        'name',
        'description',
        'start_at',
        'end_at',
        'start_at_utc',
        'end_at_utc',
        'timezone',
        'is_online',
        'online_meeting_link',
        'status',
        'capacity',
        'max_tickets_per_user',
        'parent_occurrence_id',
        // 'created_by', // Assuming these are handled by a global scope or trait
        // 'updated_by',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'start_at' => 'string',
        'end_at' => 'string',
        'start_at_utc' => 'datetime',
        'end_at_utc' => 'datetime',
        'is_online' => 'boolean',
        'capacity' => 'integer',
        'timezone' => 'string',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * The TicketDefinitions available for this EventOccurrence.
     */
    public function ticketDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(TicketDefinition::class, 'event_occurrence_ticket_definition')
            ->withPivot(['quantity_for_occurrence', 'price_override', 'availability_status']) // As per TCKD-001.1
            ->withTimestamps(); // If the pivot table has timestamps
    }

    public function getPublicData(): array
    {

        // Convert UTC time to the occurrence's timezone
        $localStartTime = $this->start_at_utc?->setTimezone($this->timezone ?? 'UTC');

        return [
            'id' => $this->id,
            'name' => $this->name ?: 'Event this',
            'date_short' => $localStartTime?->format('m.d'),
            'full_date_time' => $this->formatFullDateTime($localStartTime),
            'status_tag' => $this->status,
            'venue_name' => $this->venue?->name,
            'venue_address' => $this->venue?->address,
            'tickets' => $this->mapTicketDefinitions($this->ticketDefinitions),
        ];
    }

    // public function creator() // Assuming these are handled by a global scope or trait if needed
    // {
    //     return $this->belongsTo(User::class, 'created_by');
    // }

    // public function updater() // Assuming these are handled by a global scope or trait if needed
    // {
    //     return $this->belongsTo(User::class, 'updated_by');
    // }
}
