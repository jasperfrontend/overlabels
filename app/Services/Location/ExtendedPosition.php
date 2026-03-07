<?php

namespace App\Services\Location;

use Stevebauman\Location\Position;

class ExtendedPosition extends Position
{
    public ?string $isp = null;

    public ?string $org = null;

    public ?string $asName = null;

    public ?string $query = null;
}
