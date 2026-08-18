<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'website', 'lead_time_days'])]
class Supplier extends Model
{
    protected function casts(): array
    {
        return [
            'lead_time_days' => 'integer',
        ];
    }
}
