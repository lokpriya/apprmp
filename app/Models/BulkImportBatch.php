<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkImportBatch extends Model
{
    protected $fillable = ['user_id','filename','record_type','total_rows','processed_rows','error_rows','status','mapping_config','error_log','completed_at'];
    protected $casts = ['mapping_config'=>'array','error_log'=>'array','completed_at'=>'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function progressPercent(): int {
        if ($this->total_rows === 0) return 0;
        return (int)(($this->processed_rows / $this->total_rows) * 100);
    }
}
