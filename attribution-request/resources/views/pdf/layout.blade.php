@use('App\Support\PdfFormat')
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $request->displayReference() }}</title>
    <style>
        @page { margin: 18px 22px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 7.9pt; color: #1e293b; }
        .frame { border: 3px solid #a9cbe9; padding: 7px 9px 5px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1.5px 4px; }
        .head td { border: 1px solid #1c355e; vertical-align: middle; }
        .head .logo { width: 22%; text-align: center; padding: 6px; }
        .head .title { font-size: 11pt; font-weight: bold; text-align: center; color: #1c355e; letter-spacing: .5px; }
        .head .meta { width: 34%; text-align: right; font-size: 7pt; line-height: 1.5; }
        .meta strong { color: #1c355e; }
        .bar { background: #d9d9d9; text-align: center; font-weight: bold; text-transform: uppercase; font-size: 8pt;
               letter-spacing: .6px; padding: 2.5px; margin: 5px 0 3px; border-top: 1px solid #94a3b8; }
        .label { color: #475569; font-size: 7.4pt; white-space: nowrap; }
        .value { border-bottom: 1px solid #94a3b8; font-weight: bold; }
        .box { font-size: 9.5pt; line-height: 1; }
        .checked { color: #1c355e; }
        .muted { color: #64748b; }
        .small { font-size: 6.6pt; }
        .sign th { border: 1px solid #94a3b8; background: #f1f5f9; font-size: 7.2pt; padding: 3px; }
        .sign td { border: 1px solid #94a3b8; height: 38px; text-align: center; vertical-align: top; font-size: 7pt; }
        .stamp { color: #047857; font-weight: bold; }
        .pending { color: #b45309; }
        .footer { margin-top: 6px; text-align: center; font-weight: bold; color: #1c355e; font-size: 7.6pt; }
        .generated { margin-top: 4px; font-size: 6.2pt; color: #64748b; text-align: center; }
    </style>
</head>
<body>
<div class="frame">
    <table class="head">
        <tr>
            <td class="logo"><img src="{{ $logo }}" style="height: 44px;"></td>
            <td class="title">{{ $title }}</td>
            <td class="meta">
                <strong>DÉPARTEMENT INFORMATIQUE</strong><br>
                {{ $subtitle }}<br>
                Document N° : {{ $request->type->formCode() }}
            </td>
        </tr>
    </table>

    <table style="margin-top: 5px;">
        <tr>
            <td style="width: 34%;"><span class="label">N° Fiche :</span> <strong>{{ $request->displayReference() }}</strong></td>
            <td><span class="label">SITE :</span>
                @foreach ($sites as $site)
                    {!! PdfFormat::box($request->site_id === $site->id) !!} {{ $site->name }}&nbsp;&nbsp;
                @endforeach
            </td>
        </tr>
    </table>

    @yield('content')

    <div class="footer">Cette fiche ne doit contenir ni rature ni surcharge</div>
    <div class="generated">
        Document généré électroniquement par le Portail IT AGL le {{ $generatedAt->format('d/m/Y à H:i') }}.
        Validations horodatées et tracées dans le journal d'audit de la demande {{ $request->displayReference() }}. — Sensitivity: Internal
    </div>
</div>
</body>
</html>
