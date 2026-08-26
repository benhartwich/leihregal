<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use Auditable;

    protected $fillable = ['name', 'place', 'sort_order'];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('place')->orderBy('name');
    }

    public function fullLabel(): string
    {
        return $this->place ? "{$this->place} – {$this->name}" : $this->name;
    }
}
