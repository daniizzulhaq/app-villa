<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\User;

class MonthlyReportPolicy
{
    /** Staff tidak dapat melihat laporan keuntungan. */
    public function viewAny(User $user): bool
    {
        return $user->isManager() || $user->isOwner();
    }

    /** Manager: villa di wilayahnya. Owner: hanya laporan Final milik villanya. */
    public function view(User $user, MonthlyReport $report): bool
    {
        if ($user->isManager()) {
            return $user->canAccessVilla($report->villa_id);
        }

        return $user->isOwner()
            && $user->canAccessVilla($report->villa_id)
            && $report->status === ReportStatus::Final;
    }

    public function export(User $user, MonthlyReport $report): bool
    {
        return $this->view($user, $report);
    }

    /** Tandai Final: Master, atau Manager untuk villa di wilayahnya. */
    public function finalize(User $user, MonthlyReport $report): bool
    {
        return $user->isManager() && $user->canAccessVilla($report->villa_id) && $report->status !== ReportStatus::Final;
    }

    /** Buka kunci bulan — khusus Master, tercatat di audit log. */
    public function unlock(User $user, MonthlyReport $report): bool
    {
        return $user->isMaster() && $report->status === ReportStatus::Final;
    }
}
