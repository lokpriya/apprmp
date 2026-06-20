<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicFeedback extends Model
{
    protected $table = 'public_feedbacks';

    protected $fillable = [
        'name', 'phone', 'email', 'ward_number', 'category',
        'message', 'status', 'admin_note', 'reviewed_by',
        'reviewed_at', 'ip_address', 'form_token',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'ward_number' => 'integer',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function categories(): array
    {
        return [
            'household'      => 'घरधुरी तथ्याङ्क',
            'population'     => 'जनसंख्या तथ्याङ्क',
            'education'      => 'शिक्षा तथ्याङ्क',
            'institution'    => 'संस्थागत तथ्याङ्क',
            'business'       => 'व्यवसाय तथ्याङ्क',
            'infrastructure' => 'पूर्वाधार तथ्याङ्क',
            'general'        => 'सामान्य सुझाव',
        ];
    }

    public function categoryLabel(): string
    {
        return static::categories()[$this->category] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'new'      => 'नयाँ',
            'reviewed' => 'समीक्षित',
            'resolved' => 'समाधान',
            default    => $this->status,
        };
    }
}
