<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Venue extends Model
{
    use HasFactory;
    use HasTranslations;

    public array $translatable = [
        'name',
        'description',
        'address_line_1',
        'address_line_2',
        'city',
    ];

    protected $fillable = [
        'name',
        'description',
        'slug',
        'organizer_id',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'state_id',
        'country_id',
        'latitude',
        'longitude',
        'contact_email',
        'contact_phone',
        'website_url',
        'seating_capacity',
        'images',
        'thumbnail_image_path',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'address_line_1' => 'array',
        'address_line_2' => 'array',
        'city' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'seating_capacity' => 'integer',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
