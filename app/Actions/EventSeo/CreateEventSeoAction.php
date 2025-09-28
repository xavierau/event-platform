<?php

namespace App\Actions\EventSeo;

use App\DTOs\EventSeoData;
use App\Models\EventSeo;

class CreateEventSeoAction
{
    public function execute(EventSeoData $data): EventSeo
    {
        return EventSeo::create($data->toArray());
    }
}
