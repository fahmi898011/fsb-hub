<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentView extends Model
{
    public $timestamps = false;

    protected $fillable = ['information_id', 'version_id', 'user_id', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime'];

    public function information(): BelongsTo
    {
        return $this->belongsTo(InformationItem::class, 'information_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
