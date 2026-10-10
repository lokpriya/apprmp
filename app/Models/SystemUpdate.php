<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemUpdate extends Model
{
    protected $fillable = [
        'version', 'from_version', 'status', 'commit', 'files_count',
        'applied_by', 'applied_by_name', 'log', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'log'         => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** Record the end of an update from the applier's persisted state. */
    public static function recordFromState(array $state): self
    {
        return static::create([
            'version'         => $state['version'],
            'from_version'    => $state['from_version'] ?? null,
            'status'          => $state['status'],
            'commit'          => $state['summary']['commit'] ?? null,
            'files_count'     => (int) ($state['summary']['files'] ?? 0),
            'applied_by'      => $state['user_id'] ?? null,
            'applied_by_name' => $state['user_name'] ?? null,
            'log'             => $state['log'] ?? [],
            'error'           => $state['error'] ?? null,
            'started_at'      => $state['created_at'] ?? null,
            'finished_at'     => now(),
        ]);
    }
}
