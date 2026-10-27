<?php

namespace App\Policies;

use App\Models\QaAssessment;
use App\Models\User;

/**
 * Policy untuk penilaian kualitatif QA.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 */
class QaAssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa.assessment.view');
    }

    public function view(User $user, QaAssessment $assessment): bool
    {
        if (!$user->can('qa.assessment.view')) {
            return false;
        }
        // QA hanya bisa lihat assessment yg dia buat, agent hanya assessment miliknya
        if ($user->hasRole('qa')) {
            return $assessment->qa_id === $user->id;
        }
        if ($user->hasRole('employee')) {
            return $assessment->agent_id === $user->id;
        }
        return true; // admin/supervisor
    }

    public function create(User $user): bool
    {
        return $user->can('qa.assessment.create');
    }

    public function update(User $user, QaAssessment $assessment): bool
    {
        if (!$user->can('qa.assessment.update')) {
            return false;
        }
        // Hanya QA pembuat yang bisa update draft
        if ($assessment->status === 'published') {
            return false;
        }
        return $assessment->qa_id === $user->id;
    }

    public function delete(User $user, QaAssessment $assessment): bool
    {
        if (!$user->can('qa.assessment.delete')) {
            return false;
        }
        // Hanya QA pembuat yang bisa hapus draft
        if ($assessment->status === 'published') {
            return false;
        }
        return $assessment->qa_id === $user->id;
    }

    public function publish(User $user, QaAssessment $assessment): bool
    {
        if (!$user->can('qa.assessment.publish')) {
            return false;
        }
        // Hanya QA pembuat yang bisa publish draft
        return $assessment->status === 'draft' && $assessment->qa_id === $user->id;
    }

    public function restore(User $user, QaAssessment $assessment): bool
    {
        return $user->can('qa.assessment.delete');
    }

    public function forceDelete(User $user, QaAssessment $assessment): bool
    {
        return $user->can('qa.assessment.delete');
    }
}