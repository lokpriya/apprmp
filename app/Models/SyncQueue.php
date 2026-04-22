<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncQueue extends Model
{
    public $timestamps = false;
    protected $fillable = ['device_id','user_id','record_type','payload','sync_status','retry_count','error_message','queued_at','synced_at'];
    protected $casts = ['queued_at'=>'datetime','synced_at'=>'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function getPayloadArray(): array { return json_decode($this->payload, true) ?? []; }
}
