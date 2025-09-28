<?php

namespace App\Actions\EventSeo;

use App\Models\EventSeo;

class DeleteEventSeoAction
{
    public function execute(EventSeo $eventSeo): bool
    {
        return $eventSeo->delete();
    }
}
