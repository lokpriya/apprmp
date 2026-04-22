<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberMigration extends Model
{
    protected $table = 'member_migration';
    protected $fillable = [
        'member_id','migration_status','internal_destination','destination_country',
        'years_abroad','occupation_abroad','annual_remittance','last_visit_year',
    ];
    public function member(): BelongsTo { return $this->belongsTo(HouseholdMember::class, 'member_id'); }
}
