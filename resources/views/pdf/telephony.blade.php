@extends('pdf.layout', ['title' => "FICHE D'ATTRIBUTION DE TÉLÉPHONE", 'subtitle' => 'Téléphonie mobile'])

@use('App\Enums\ApprovalStep')
@use('App\Enums\EmployeeCategory')
@use('App\Enums\LineOption')
@use('App\Enums\PhoneReason')
@use('App\Enums\PhoneType')
@use('App\Enums\PlanType')
@use('App\Enums\SimAction')
@use('App\Models\Device')
@use('App\Models\SimLine')
@use('App\Support\PdfFormat')

@section('content')
@php
    $snap = $request->beneficiary_snapshot ?? [];
    $person = $request->beneficiary;
    $phone = $request->phoneDetail;
    $sim = $request->simDetail;
    $managerApproval = $request->approvals->where('step', ApprovalStep::Manager)->sortByDesc('id')->first();
    $dsiApproval = $request->approvals->where('step', ApprovalStep::Dsi)->sortByDesc('id')->first();
    $chief = $managerApproval?->assignedTo;
    $category = EmployeeCategory::tryFrom($snap['category'] ?? '') ?? $person?->category;
    $recharge = $sim?->approved_monthly_recharge_gnf ?? $sim?->requested_monthly_recharge_gnf;
    $device = $request->assignments->first(fn ($a) => $a->assignable instanceof Device)?->assignable;
    $deviceAssignment = $request->assignments->first(fn ($a) => $a->assignable instanceof Device);
    $line = $request->assignments->first(fn ($a) => $a->assignable instanceof SimLine)?->assignable ?? $sim?->existingSimLine;
    $receivedAt = $request->assignments->max('received_at');
@endphp

<div class="bar">Chef de service</div>
<table>
    <tr>
        <td class="label" style="width: 9%;">Prénom</td><td class="value" style="width: 24%;">{{ $chief?->first_name }}</td>
        <td class="label" style="width: 9%;">Surnom</td><td class="value" style="width: 22%;">{{ $chief?->nickname }}</td>
        <td class="label" style="width: 6%;">Nom</td><td class="value">{{ $chief ? mb_strtoupper($chief->last_name) : '' }}</td>
    </tr>
    <tr>
        <td class="label">Service</td><td class="value">{{ $chief?->department?->name }}</td>
        <td class="label">Fonction</td><td class="value">{{ $chief?->job_title }}</td>
        <td class="label">Date</td><td class="value">{{ PdfFormat::date($request->submitted_at) }}</td>
    </tr>
</table>

<div class="bar">Information de l'utilisateur</div>
<table>
    <tr>
        <td class="label" style="width: 9%;">Prénom</td><td class="value" style="width: 24%;">{{ $snap['first_name'] ?? $person?->first_name }}</td>
        <td class="label" style="width: 9%;">Surnom</td><td class="value" style="width: 22%;">{{ $snap['nickname'] ?? $person?->nickname }}</td>
        <td class="label" style="width: 6%;">Nom</td><td class="value">{{ mb_strtoupper($snap['last_name'] ?? $person?->last_name ?? '') }}</td>
    </tr>
    <tr>
        <td class="label">Service</td><td class="value">{{ $snap['department'] ?? $person?->department?->name }}</td>
        <td class="label">Fonction</td><td class="value">{{ $snap['job_title'] ?? $person?->job_title }}</td>
        <td class="label">Date</td><td class="value">{{ PdfFormat::date($request->submitted_at) }}</td>
    </tr>
    <tr>
        <td class="label">Matricule</td><td class="value">{{ $snap['matricule'] ?? $person?->matricule }}</td>
        <td class="label" colspan="2">Date d'attribution du dernier téléphone</td>
        <td class="value" colspan="2">{{ PdfFormat::date($snap['last_phone_assigned_at'] ?? null) ?: 'Aucun' }}</td>
    </tr>
    <tr>
        <td colspan="6" style="padding-top: 4px;">
            @foreach (EmployeeCategory::cases() as $case)
                {!! PdfFormat::box($category === $case) !!} {{ $case->getLabel() }}&nbsp;&nbsp;&nbsp;&nbsp;
            @endforeach
        </td>
    </tr>
</table>

<div class="bar">Attribution</div>
<table>
    <tr>
        <td style="width: 22%;">{!! PdfFormat::box($phone !== null) !!} Téléphone</td>
        <td style="width: 26%;">{!! PdfFormat::box($sim !== null) !!} Numéro de flotte</td>
        <td class="label" style="width: 14%;">Numéro existant :</td>
        <td class="value">{{ $sim?->existingSimLine?->msisdn->international() ?? $sim?->existing_msisdn?->international() }}</td>
    </tr>
</table>

<div class="bar">Information numéro (SIM)</div>
<table>
    <tr>
        @foreach (SimAction::cases() as $case)
            <td>{!! PdfFormat::box($sim?->action === $case) !!} {{ $case->getLabel() }}</td>
        @endforeach
    </tr>
    <tr>
        <td>{!! PdfFormat::box($sim?->plan_type === PlanType::Postpaid) !!} Post payé</td>
        <td>{!! PdfFormat::box($sim?->plan_type === PlanType::Prepaid) !!} Pré payé</td>
        @foreach (LineOption::cases() as $option)
            <td>{!! PdfFormat::box((bool) $sim?->options?->contains($option)) !!} {{ $option->code() }}</td>
        @endforeach
    </tr>
    <tr>
        <td class="label" colspan="2">Préciser l'entité :</td>
        <td class="value" colspan="3">{{ $sim?->entity }}</td>
    </tr>
</table>

<div class="bar">Montant à recharger (mensuel)</div>
<table>
    @foreach ($rechargeAmounts->chunk(6) as $row)
        <tr>
            @foreach ($row as $amount)
                <td style="width: 16.6%;">{!! PdfFormat::box($recharge !== null && $recharge->amount === $amount->amount_gnf->amount) !!} {{ $amount->amount_gnf->format() }}</td>
            @endforeach
        </tr>
    @endforeach
</table>

<table style="margin-top: 3px;"><tr><td class="label" style="width: 12%;">Commentaires :</td><td class="value">{{ $request->comment }}</td></tr></table>

<div class="bar">Information téléphone</div>
<table>
    <tr>
        @foreach (PhoneReason::cases() as $case)
            <td>{!! PdfFormat::box($phone?->reason === $case) !!} {{ $case->getLabel() }}</td>
        @endforeach
    </tr>
    <tr>
        @foreach ([PhoneType::SamsungBasic, PhoneType::SamsungAndroid, PhoneType::Iphone, PhoneType::Tablet] as $type)
            <td>{!! PdfFormat::box($phone?->requested_phone_type === $type) !!} {{ $type->getLabel() }}</td>
        @endforeach
    </tr>
    <tr>
        <td colspan="4">{!! PdfFormat::box($phone?->requested_phone_type === PhoneType::Other) !!} Autres : <strong>{{ $phone?->other_type_label }}</strong>
            @if ($phone?->approvedDeviceModel) &nbsp;&nbsp;<span class="label">Modèle retenu par la DSI :</span> <strong>{{ $phone->approvedDeviceModel->label }}</strong> @endif
        </td>
    </tr>
</table>
<table>
    <tr><td class="label" style="width: 18%;">Motif détaillé (obligatoire) :</td><td class="value">{{ $phone?->detailed_reason }}</td></tr>
</table>
<table>
    <tr>
        <td class="label" style="width: 13%;">Ancien téléphone :</td>
        <td class="label" style="width: 6%;">Modèle</td><td class="value" style="width: 25%;">{{ $phone?->previousDevice?->model?->label ?? $phone?->previous_device_label }}</td>
        <td class="label" style="width: 7%;">N° de série</td><td class="value" style="width: 22%;">{{ $phone?->previousDevice?->imei?->formatted() ?? $phone?->previousDevice?->serial_number ?? $phone?->previous_device_serial }}</td>
        <td class="label" style="width: 4%;">Type</td><td class="value">{{ $phone?->previousDevice?->model?->phone_type?->getLabel() }}</td>
    </tr>
</table>

<div class="bar">Validation responsable</div>
<table class="sign">
    <tr><th style="width: 50%;">Chef de service</th><th>Responsable IT (approbation DSI)</th></tr>
    <tr><td>{!! PdfFormat::signature($managerApproval) !!}</td><td>{!! PdfFormat::signature($dsiApproval) !!}</td></tr>
</table>
<table style="margin-top: 3px;">
    <tr>
        <td class="label" style="width: 12%;">Commentaires :</td><td class="value" colspan="3">{{ $dsiApproval?->comment ?? $request->execution_notes }}</td>
    </tr>
    <tr>
        <td class="label">Date d'exécution</td><td class="value" style="width: 20%;">{{ PdfFormat::date($request->executed_at) }}</td>
        <td class="label" style="width: 22%;">Exécuté par (Nom &amp; Prénom IT)</td><td class="value">{{ $request->executedBy?->name }}</td>
    </tr>
</table>

<div class="bar">Validation demandeur / téléphone</div>
<table class="sign">
    <tr><th>Date de réception</th><th>Modèle</th><th>N° de série</th><th>Capacité</th><th>Signature</th></tr>
    <tr>
        <td>{{ PdfFormat::date($receivedAt) }}</td>
        <td>{{ $device?->model?->brand }} {{ $device?->model?->name }}{!! $line ? '<br><span class="small">Ligne '.e($line->msisdn->international()).'</span>' : '' !!}</td>
        <td>{{ $device?->imei?->formatted() ?? $device?->serial_number }}</td>
        <td>{{ $device?->model?->storage_gb ? $device->model->storage_gb.' Go' : '' }}</td>
        <td>@if ($receivedAt) <span class="stamp">&#10003; Accepté électroniquement</span><br>{{ \Illuminate\Support\Facades\Date::parse($receivedAt)->format('d/m/Y H:i') }} @endif</td>
    </tr>
</table>

<div class="footer" style="margin-top: 8px;">
    {!! PdfFormat::box($request->policy_accepted_at !== null) !!} Je m'engage sans réserve à respecter la procédure de gestion de la téléphonie
    @if ($request->policy_accepted_at) <span class="small muted">(accepté le {{ $request->policy_accepted_at->format('d/m/Y H:i') }})</span> @endif
</div>
@endsection
