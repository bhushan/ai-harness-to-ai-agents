<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolCallLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'run_id' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
