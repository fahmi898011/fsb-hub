<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

/**
 * Otorisasi Master Channel (PRD Employee KPI/QA §35).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Channel adalah master konfigurasi penilaian, bukan konten - jadi yang
 * mengelola adalah Admin (Application Management), bukan QA. QA hanya MEMAKAI
 * channel saat mengisi assessment (read-only: `kpi_channel.view`).
 *
 * NB: permission sengaja diberi awalan `kpi_channel.` dan BUKAN `channel.`,
 * karena `channel.*` sudah dipakai modul Chat untuk kanal percakapan - dua
 * makna "channel" yang berbeda tidak boleh berbagi satu izin.
 */
class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kpi_channel.view');
    }

    public function view(User $user, Channel $channel): bool
    {
        return $user->can('kpi_channel.view');
    }

    public function create(User $user): bool
    {
        return $user->can('kpi_channel.create');
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->can('kpi_channel.update');
    }

    public function delete(User $user, Channel $channel): bool
    {
        if (! $user->can('kpi_channel.delete')) {
            return false;
        }

        // Channel yang masih dipakai scorecard tidak boleh dihapus.
        return ! $channel->scorecards()->exists();
    }
}
