<?php

namespace App\Services;

use App\Enums\AttachmentKind;
use App\Enums\RequestType;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\Device;
use App\Models\RechargeAmount;
use App\Models\ServiceRequest;
use App\Models\Site;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Fiche officielle (F-0015 / F-0008) au format PDF, fidèle à la fiche papier.
 * Les signatures manuscrites sont remplacées par les validations électroniques horodatées.
 */
final class RequestFormPdf
{
    public function render(ServiceRequest $request): string
    {
        $request->loadMissing([
            'requester.department', 'requester.site', 'beneficiary.department', 'beneficiary.site', 'site', 'executedBy',
            'approvals.decidedBy', 'approvals.assignedTo.department', 'approvals.direction',
            'phoneDetail.previousDevice.model', 'phoneDetail.approvedDeviceModel',
            'simDetail.existingSimLine', 'assignments.assignable',
            'profileDetail.applications', 'profileDetail.department', 'profileDetail.site', 'profileDetail.manager',
        ]);

        $request->assignments->loadMorph('assignable', [Device::class => ['model']]);

        $view = $request->type === RequestType::Telephony ? 'pdf.telephony' : 'pdf.user-profile';

        return Pdf::loadView($view, [
            'request' => $request,
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/brand/agl-logo.png'))),
            'sites' => Site::query()->orderBy('id')->get(),
            'rechargeAmounts' => RechargeAmount::query()->orderBy('amount_gnf')->get(),
            'applications' => Application::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'generatedAt' => now(),
        ])->setPaper('a4')->output();
    }

    /** Archive la fiche (GED locale en attendant SharePoint) avec son empreinte SHA-256. */
    public function archive(ServiceRequest $request, ?User $actor): Attachment
    {
        $bytes = $this->render($request);
        $path = sprintf('ged/%d/%s/%s.pdf', ($request->submitted_at ?? now())->year, $request->type->archiveFolder(), $request->displayReference());

        Storage::disk('local')->put($path, $bytes);

        return $request->attachments()->create([
            'kind' => AttachmentKind::RequestForm,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'Fiche '.$request->displayReference().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'uploaded_by_id' => $actor?->id,
        ]);
    }
}
