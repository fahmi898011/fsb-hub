<?php

namespace App\Policies;

use App\Models\QaAssessmentCategory;
use App\Models\User;

/**
 * Policy untuk kategori penilaian QA.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment
 */
class QaAssessmentCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa.assessment.category.view');
    }

    public function view(User $user, QaAssessmentCategory $category): bool
    {
        return $user->can('qa.assessment.category.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qa.assessment.category.create');
    }

    public function update(User $user, QaAssessmentCategory $category): bool
    {
        return $user->can('qa.assessment.category.update');
    }

    public function delete(User $user, QaAssessmentCategory $category): bool
    {
        return $user->can('qa.assessment.category.delete');
    }

    public function restore(User $user, QaAssessmentCategory $category): bool
    {
        return $user->can('qa.assessment.category.delete');
    }

    public function forceDelete(User $user, QaAssessmentCategory $category): bool
    {
        return $user->can('qa.assessment.category.delete');
    }
}