<?php

namespace App\Services\Inspections;

use App\Models\User;

final class ClientInspectionView
{
    private const INTERNAL_KEYS = [
        'internal_notes', 'correction_requests', 'general_correction_requests',
        'has_pending_correction_for_current_user', 'reinspection_action',
        'history', 'responsibles', 'stage_responsibles', 'status_histories',
        'context_snapshot', 'snapshot_version', 'assignment_options',
        'reinspection_checklist_url', 'history_url', 'assessment_store_url',
        'self_assign', 'updated_by', 'creator', 'processing_error',
        'show_url', 'overview_url', 'report_overview_url', 'defects_url', 'photos_url', 'assessment_url',
    ];

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function sanitize(array $payload, User $user): array
    {
        if (! $user->isClient()) {
            return $payload;
        }

        $clean = $this->clean($payload);
        if (isset($clean['tabs']) && is_array($clean['tabs'])) {
            $clean['tabs'] = [];
        }
        if (isset($clean['inspection']['next_inspections'])) {
            $clean['inspection']['next_inspections'] = array_values(array_filter(
                $clean['inspection']['next_inspections'],
                fn (array $inspection): bool => ($inspection['status'] ?? null) === 'released',
            ));
        }
        if (isset($clean['inspection']['equipment'])) {
            unset($clean['inspection']['equipment']['show_url']);
            if (isset($clean['inspection']['equipment']['client'])) {
                unset($clean['inspection']['equipment']['client']['show_url']);
            }
        }
        if (isset($clean['assessment']['defect']['equipment'])) {
            unset($clean['assessment']['defect']['equipment']['show_url']);
        }
        if (isset($clean['inspection']['previous_inspection'])
            && ($clean['inspection']['previous_inspection']['status'] ?? null) !== 'released') {
            $clean['inspection']['previous_inspection'] = null;
        }
        if (isset($clean['capabilities']['can_move_to_draft'])) {
            $clean['capabilities']['can_move_to_draft'] = false;
        }

        return $clean;
    }

    private function clean(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array($key, self::INTERNAL_KEYS, true)) {
                unset($payload[$key]);
            } elseif (is_array($value)) {
                $payload[$key] = $this->clean($value);
            }
        }

        return $payload;
    }
}
