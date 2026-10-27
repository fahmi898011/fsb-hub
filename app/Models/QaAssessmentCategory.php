<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kategori penilaian kualitatif (PRD QA Assessment §4).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 * AGENT: asisten | 2026-10-20 | isi slug otomatis saat create | modul QA Assessment, kategori
 */
class QaAssessmentCategory extends Model
{
    use HasFactory;

    protected $table = 'qa_assessment_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Kolom `slug` NOT NULL dan unik di DB. Tanpa hook ini, insert dari form
     * "Tambah Kategori" gagal dengan SQLSTATE 1364 (Field 'slug' doesn't have
     * a default value) karena controller hanya mengirim name/description/
     * status/sort_order. Factory mengisi slug sendiri, sehingga bug ini tidak
     * pernah tertangkap test sebelumnya.
     *
     * AGENT: asisten | 2026-10-20 | tambah hook slug | modul QA Assessment, kategori
     */
    protected static function booted(): void
    {
        static::creating(function (self $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name) ?: 'kategori';
            }
        });
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(QaAssessmentItem::class, 'category_id');
    }

    public function questionBank(): HasMany
    {
        return $this->hasMany(QuestionBank::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}