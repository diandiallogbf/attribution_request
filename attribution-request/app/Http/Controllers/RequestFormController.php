<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Services\RequestFormPdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fiche officielle en PDF : version archivée (figée, avec empreinte SHA-256) si la demande est clôturée,
 * sinon aperçu généré à la volée.
 */
class RequestFormController extends Controller
{
    public function __invoke(ServiceRequest $serviceRequest, RequestFormPdf $pdf): Response
    {
        $serviceRequest->load(['beneficiary', 'approvals']);
        Gate::authorize('view', $serviceRequest);
        abort_if($serviceRequest->status === RequestStatus::Draft, 404);

        $archived = $serviceRequest->archivedForms()->first();

        if ($archived !== null && Storage::disk($archived->disk)->exists($archived->path)) {
            return Storage::disk($archived->disk)->download($archived->path, $archived->original_name, [
                'X-Checksum-SHA256' => (string) $archived->checksum,
            ]);
        }

        return response($pdf->render($serviceRequest), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Fiche '.$serviceRequest->displayReference().' (apercu).pdf"',
        ]);
    }
}
