<?php

namespace App\Enums;

enum AttachmentStatus: string
{
    case Pending = 'pending';
    case Attached = 'attached';
    case Orphaned = 'orphaned';
}
