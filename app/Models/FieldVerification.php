<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldVerification extends Model
{
    public $timestamps = false;
    protected $fillable = ['verifiable_type','verifiable_id','verifier_id','visited_at','is_verified','discrepancies','notes'];
    protected $casts = ['visited_at'=>'date','is_verified'=>'boolean','discrepancies'=>'array','created_at'=>'datetime'];

    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verifier_id'); }
}
