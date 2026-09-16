<?php

namespace App\Enums;

enum Caliber: string
{
    case Rimfire22LR = '.22LR';
    case Rimfire22WMR = '.22 WMR';
    case Rem222 = '.222 Rem';
    case Rem223 = '.223 Rem';
    case Win243 = '.243 Win';
    case Win308 = '.308 Win';
    case Springfield3006 = '30-06 Springfield';
    case Magnum357 = '.357 Magnum';
    case Special38 = '.38 Special';
    case Luger9x19 = '9x19mm';
    case SW40 = '.40 S&W';
    case ACP45 = '.45 ACP';
    case Magnum44 = '.44 Magnum';
    case Gauge12 = '12GA';
    case Gauge20 = '20GA';
}
