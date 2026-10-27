<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformationVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'information_id',
        'version',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'content',
        'change_notes',
        'created_by',
    ];

    public function information(): BelongsTo
    {
        return $this->belongsTo(InformationItem::class, 'information_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
    }
}
