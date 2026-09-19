<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

interface ShareCodeGeneratorInterface
{
    public function generate(): string;
}
