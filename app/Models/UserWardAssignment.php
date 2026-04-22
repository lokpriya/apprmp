<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWardAssignment extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id','ward_id','assigned_at','assigned_by'];
    protected $casts = ['assigned_at'=>'datetime'];

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function ward(): BelongsTo    { return $this->belongsTo(Ward::class); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
