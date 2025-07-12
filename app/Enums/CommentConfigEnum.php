<?php

namespace App\Enums;

enum CommentConfigEnum: string
{
    case DISABLED = 'disabled';
    case ENABLED = 'enabled';
    case MODERATED = 'moderated';
}
