<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AnalyticsCache extends Model
{
    public $timestamps = false;
    protected $fillable = ['cache_key','local_government_id','ward_id','data_json','generated_at','expires_at'];
    protected $casts = ['generated_at'=>'datetime','expires_at'=>'datetime'];

    public function getData(): array { return json_decode($this->data_json, true) ?? []; }
    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }
}
