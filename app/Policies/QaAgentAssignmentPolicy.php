<?php

namespace App\Policies;

use App\Models\QaAgentAssignment;
use App\Models\User;

/**
 * Policy untuk penugasan QA ke agent.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian C
 */
class QaAgentAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa.agent-assignment.view');
    }

    public function view(User $user, QaAgentAssignment $assignment): bool
    {
        if (!$user->can('qa.agent-assignment.view')) {
            return false;
        }
        // QA yg di-assign, agent yg di-assign, admin
        if ($user->hasRole('qa')) {
            return $assignment->qa_id === $user->id;
        }
        if ($user->hasRole('employee')) {
            return $assignment->agent_id === $user->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('qa.agent-assignment.create');
    }

    public function update(User $user, QaAgentAssignment $assignment): bool
    {
        return $user->can('qa.agent-assignment.update');
    }

    public function delete(User $user, QaAgentAssignment $assignment): bool
    {
        return $user->can('qa.agent-assignment.delete');
    }

    public function restore(User $user, QaAgentAssignment $assignment): bool
    {
        return $user->can('qa.agent-assignment.delete');
    }

    public function forceDelete(User $user, QaAgentAssignment $assignment): bool
    {
        return $user->can('qa.agent-assignment.delete');
    }
}