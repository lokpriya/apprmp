<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['loggable_type','loggable_id','from_status','to_status','actor_id','comment'];
    protected $casts = ['created_at'=>'datetime'];

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
