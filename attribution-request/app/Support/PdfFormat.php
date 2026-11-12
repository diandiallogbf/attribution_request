<?php

namespace App\Support;

use App\Enums\ApprovalDecision;
use App\Models\RequestApproval;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;

/** Petits helpers de rendu pour les fiches PDF (cases à cocher, dates, signatures électroniques). */
final class PdfFormat
{
    public static function box(bool $checked): string
    {
        return $checked ? '<span class="box checked">&#9746;</span>' : '<span class="box">&#9744;</span>';
    }

    public static function date(DateTimeInterface|string|null $date): string
    {
        return $date ? Date::parse($date)->format('d/m/Y') : '';
    }

    /** Cellule de signature : valideur, mention « validé électroniquement », horodatage. */
    public static function signature(?RequestApproval $approval): string
    {
        if ($approval === null) {
            return '<span class="muted">—</span>';
        }

        if ($approval->decision === ApprovalDecision::Approved && $approval->decidedBy) {
            return e($approval->decidedBy->name)
                .'<br><span class="stamp">&#10003; Validé électroniquement</span><br>'
                .$approval->decided_at?->format('d/m/Y H:i');
        }

        return '<span class="pending">'.e($approval->decision->getLabel()).'</span><br>'
            .e($approval->assignedTo?->name ?? 'Équipe DSI');
    }
}
