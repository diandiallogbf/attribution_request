@extends('pdf.layout', ['title' => 'PROFIL UTILISATEUR', 'subtitle' => 'Fiche de demande d\'accès'])

@use('App\Enums\ApprovalStep')
@use('App\Enums\EquipmentType')
@use('App\Enums\ProfileAction')
@use('App\Enums\ProfileType')
@use('App\Support\PdfFormat')

@section('content')
@php
    $snap = $request->beneficiary_snapshot ?? [];
    $profile = $request->profileDetail;
    $person = $request->beneficiary;
    $requester = $request->requester;
    $selected = $profile?->applications?->pluck('id') ?? collect();
    $equipment = $profile?->equipment ?? collect();
    $approvals = $request->approvals->sortByDesc('id');
    $managerApproval = $approvals->firstWhere('step', ApprovalStep::Manager);
    $dsiApproval = $approvals->firstWhere('step', ApprovalStep::Dsi);
    $business = fn (array $codes) => $approvals->first(fn ($a) => $a->step === ApprovalStep::Business && in_array($a->direction?->code, $codes, true));
@endphp

<div class="bar">Demandeur</div>
<table>
    <tr>
        <td class="label" style="width: 9%;">Prénom</td><td class="value" style="width: 26%;">{{ $requester->first_name }}</td>
        <td class="label" style="width: 6%;">Nom</td><td class="value" style="width: 26%;">{{ mb_strtoupper($requester->last_name) }}</td>
        <td class="label" style="width: 8%;">Location</td><td class="value">{{ $requester->site?->name }}</td>
    </tr>
    <tr>
        <td class="label">Département</td><td class="value">{{ $requester->department?->name }}</td>
        <td class="label">Fonction</td><td class="value">{{ $requester->job_title }}</td>
        <td class="label">Date</td><td class="value">{{ PdfFormat::date($request->submitted_at) }}</td>
    </tr>
</table>

<div class="bar">Information utilisateur</div>
<table>
    <tr>
        <td class="label" style="width: 9%;">Nom</td><td class="value" style="width: 26%;">{{ mb_strtoupper($snap['last_name'] ?? $person?->last_name ?? '') }}</td>
        <td class="label" style="width: 6%;">Prénom</td><td class="value" style="width: 26%;">{{ $snap['first_name'] ?? $person?->first_name }}</td>
        <td class="label" style="width: 8%;">Location</td><td class="value">{{ $snap['site'] ?? $person?->site?->name }}</td>
    </tr>
    <tr>
        <td class="label">Département</td><td class="value">{{ $snap['department'] ?? $person?->department?->name }}</td>
        <td class="label">Fonction</td><td class="value">{{ $snap['job_title'] ?? $person?->job_title }}</td>
        <td class="label">Date</td><td class="value">{{ PdfFormat::date($request->submitted_at) }}</td>
    </tr>
    <tr>
        <td class="label">Matricule</td><td class="value">{{ $snap['matricule'] ?? $person?->matricule }}</td>
        <td class="label" colspan="2">Profil de référence</td><td class="value" colspan="2">{{ $profile?->reference_profile }}</td>
    </tr>
    <tr>
        <td colspan="6" style="padding-top: 4px;">
            @foreach (ProfileAction::cases() as $case)
                {!! PdfFormat::box($profile?->action === $case) !!} <strong>{{ $case->getLabel() }}</strong>&nbsp;&nbsp;&nbsp;&nbsp;
            @endforeach
            &nbsp;&nbsp;<span class="label">Type de profil :</span>
            {!! PdfFormat::box($profile?->profile_type === ProfileType::Permanent) !!} Permanent&nbsp;&nbsp;
            {!! PdfFormat::box($profile?->profile_type === ProfileType::Internship) !!} Stage (Date de fin : {{ PdfFormat::date($profile?->internship_ends_at) ?: '……' }})
        </td>
    </tr>
</table>

<div class="bar">Accès utilisateur</div>
<p class="small muted" style="margin: 0 0 3px; text-align: center;">Pour e-mail indiquer les groupes auxquels l'utilisateur doit appartenir (liste de diffusion)</p>
<table>
    <tr>
        <td style="width: 20%;">{!! PdfFormat::box((bool) $profile?->needs_email) !!} E-mail</td>
        <td style="width: 20%;">{!! PdfFormat::box((bool) $profile?->needs_as400) !!} AS400</td>
        <td style="width: 20%;">{!! PdfFormat::box((bool) $profile?->needs_internet) !!} Internet</td>
        <td></td>
    </tr>
    <tr><td class="label">Liste de diffusion</td><td class="value" colspan="3">{{ $profile?->distribution_lists }}</td></tr>
</table>

<div class="bar">Applications business (laissez ce champ vide si non applicable)</div>
<table>
    @foreach ($applications->chunk(3) as $row)
        <tr>
            @foreach ($row as $application)
                <td style="width: 33%;">{!! PdfFormat::box($selected->contains($application->id)) !!} {{ $application->name }}</td>
            @endforeach
        </tr>
    @endforeach
    <tr><td class="label">Autre application :</td><td class="value" colspan="2">{{ $profile?->other_application }}</td></tr>
    <tr><td class="label">Commentaires :</td><td class="value" colspan="2">{{ $profile?->comment ?? $request->comment }}</td></tr>
</table>

<div class="bar">Matériels</div>
<table>
    <tr>
        <td>{!! PdfFormat::box($equipment->contains(EquipmentType::Desktop)) !!} Poste de travail : Desktop (PC)</td>
        <td>{!! PdfFormat::box($equipment->contains(EquipmentType::Laptop)) !!} Laptop</td>
        <td>{!! PdfFormat::box($equipment->contains(EquipmentType::Tablet)) !!} Tablette</td>
    </tr>
    <tr>
        <td>Imprimante : {!! PdfFormat::box($equipment->contains(EquipmentType::NetworkPrinter)) !!} Réseau</td>
        <td>{!! PdfFormat::box($equipment->contains(EquipmentType::LocalPrinter)) !!} Local</td>
        <td>{!! PdfFormat::box($equipment->contains(EquipmentType::Scanner)) !!} Scanneur</td>
    </tr>
    <tr><td class="label">Téléphone — Numéro (GSM) :</td><td class="value" colspan="2">{{ $profile?->gsm_number?->international() }}</td></tr>
</table>

<div class="bar">Autorisation</div>
<table class="sign">
    <tr><th>Resp. Service</th><th>DEX / DILS / D.Shipping</th><th>DAF</th><th>DT</th><th>DRH</th><th>DSI</th></tr>
    <tr>
        <td>{!! PdfFormat::signature($managerApproval) !!}</td>
        <td>{!! PdfFormat::signature($business(['DEX', 'DILS', 'DSHIP'])) !!}</td>
        <td>{!! PdfFormat::signature($business(['DAF'])) !!}</td>
        <td>{!! PdfFormat::signature($business(['DT'])) !!}</td>
        <td>{!! PdfFormat::signature($business(['DRH'])) !!}</td>
        <td>{!! PdfFormat::signature($dsiApproval) !!}</td>
    </tr>
</table>
<p class="small" style="margin: 4px 0 0; text-align: justify;">
    Toute création de profil d'accès aux outils métiers, comptables ou de gestion des stocks (IPAKI, SPOT, ALIS, OSCAR, SERVAL, NEPTUNE, IRIS et MAXIMO)
    doit faire l'objet d'une validation préalable par le directeur concerné (DEX, DILS, Directeur Shipping, DAF ou DT) selon l'outil et le périmètre de
    responsabilité. Lorsque la demande porte sur plusieurs outils relevant de différentes directions, l'ensemble des directeurs concernés doit valider et
    signer la demande préalablement à sa mise en œuvre.
</p>

<table style="margin-top: 5px;">
    <tr><td class="label" style="width: 12%;">Commentaires :</td><td class="value" colspan="3">{{ collect([$request->execution_notes, $profile?->execution_ticket ? 'Ticket '.$profile->execution_ticket : null])->filter()->join(' — ') }}</td></tr>
    <tr>
        <td class="label">Date d'exécution</td><td class="value" style="width: 20%;">{{ PdfFormat::date($request->executed_at) }}</td>
        <td class="label" style="width: 24%;">Exécuté par (Nom &amp; Prénom IT)</td><td class="value">{{ $request->executedBy?->name }}</td>
    </tr>
</table>
@endsection
