<?php

namespace Pijler\Impersonation\Models;

use Illuminate\Database\Eloquent\Model;

class ImpersonationLog extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'ended_at' => 'datetime',
            'started_at' => 'datetime',
        ];
    }
}
