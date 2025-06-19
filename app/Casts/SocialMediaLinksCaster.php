<?php

namespace App\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Creation\CreationContext;

class SocialMediaLinksCaster implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        // If already an array, return as-is
        if (is_array($value)) {
            return $value;
        }

        // If it's a JSON string, try to decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        // For any other type, return as-is (null, etc.)
        return $value;
    }
}
