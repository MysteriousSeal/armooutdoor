<?php

namespace App\Enums;

enum ShootingDistance: string
{
    case TenMeters = '10m';
    case TwentyFiveMeters = '25m';
    case FiftyMeters = '50m';
    case HundredMeters = '100m';
}
