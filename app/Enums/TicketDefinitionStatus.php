<?php

namespace App\Enums;

enum TicketDefinitionStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';
    // case SCHEDULED = 'scheduled'; // If tickets can be scheduled to go live
    // case SOLD_OUT = 'sold_out'; // Might be a dynamic status rather than set directly

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::ARCHIVED => 'Archived',
        };
    }
}
