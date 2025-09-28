<?php

namespace App\Actions\EventSeo;

use App\DTOs\EventSeoData;
use App\Models\EventSeo;

class UpdateEventSeoAction
{
    public function execute(EventSeo $eventSeo, EventSeoData $data): EventSeo
    {
        $eventSeo->update($data->except('event_id')->toArray());

        return $eventSeo->fresh();
    }
}
