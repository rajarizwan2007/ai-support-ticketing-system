<?php

namespace App\Enums;

enum KbArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
