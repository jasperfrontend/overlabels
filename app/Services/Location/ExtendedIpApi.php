<?php

namespace App\Services\Location;

use Illuminate\Support\Fluent;
use Stevebauman\Location\Drivers\IpApi;
use Stevebauman\Location\Position;

class ExtendedIpApi extends IpApi
{
    protected function hydrate(Position $position, Fluent $location): Position
    {
        $position = parent::hydrate($position, $location);

        if ($position instanceof ExtendedPosition) {
            $position->isp = $location->isp;
            $position->org = $location->org;
            $position->asName = $location->as;
            $position->query = $location->query;
        }

        return $position;
    }
}
