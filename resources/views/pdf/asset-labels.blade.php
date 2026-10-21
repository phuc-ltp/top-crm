<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>{{ __('Tem tài sản') }}</title>
<style>
    @page { margin: 10mm; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { margin: 0; color: #111827; }
    table { width: 100%; border-collapse: separate; border-spacing: 3mm; }
    td.label { width: 33%; height: 34mm; border: 1px dashed #9CA3AF; padding: 2mm; vertical-align: top; }
    .qr { width: 26mm; height: 26mm; }
    .company { font-size: 7px; font-weight: bold; color: #E65C00; text-transform: uppercase; }
    .code { font-size: 12px; font-weight: bold; margin-top: 1mm; }
    .name { font-size: 8px; margin-top: 1mm; }
    .muted { font-size: 7px; color: #6B7280; margin-top: 1mm; }
</style>
</head>
<body>
<table>
    @foreach ($labels->chunk(3) as $row)
        <tr>
            @foreach ($row as $l)
                <td class="label">
                    <table style="border-spacing: 0"><tr>
                        <td style="width: 27mm; padding: 0"><img src="{{ $l['qr'] }}" class="qr" alt=""></td>
                        <td style="padding: 0 0 0 1.5mm; vertical-align: top">
                            <div class="company">{{ \Illuminate\Support\Str::limit($settings['company_name'] ?? '', 28) }}</div>
                            <div class="code">{{ $l['asset']->code }}</div>
                            <div class="name">{{ \Illuminate\Support\Str::limit($l['asset']->name, 40) }}</div>
                            @if ($l['asset']->serial_number)<div class="muted">S/N {{ \Illuminate\Support\Str::limit($l['asset']->serial_number, 24) }}</div>@endif
                            @if ($l['asset']->category)<div class="muted">{{ \Illuminate\Support\Str::limit($l['asset']->category->name, 26) }}</div>@endif
                        </td>
                    </tr></table>
                </td>
            @endforeach
            @for ($i = $row->count(); $i < 3; $i++)<td></td>@endfor
        </tr>
    @endforeach
</table>
</body>
</html>
