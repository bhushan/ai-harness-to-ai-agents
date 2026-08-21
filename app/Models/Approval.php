<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
