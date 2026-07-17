<?php

declare(strict_types=1);

namespace App\Enum\Contact;

enum ContactCategoryEnum: string
{
    case BUG = 'bug';
    case SUGGESTION = 'suggestion';
    case QUESTION = 'question';
    case LOVE = 'love';
    case OTHER = 'other';
    case INFORMATIVE = 'informative';
}
