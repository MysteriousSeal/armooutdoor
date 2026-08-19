<?php

namespace App\Models;

use App\Support\ImageThumbnailer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'note', 'logo'])]
class Marketplace extends Model
{
    public function logoUrl(): string
    {
        return ImageThumbnailer::urlFor($this->logo);
    }
}
