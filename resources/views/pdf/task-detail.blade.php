<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>{{ $templateName }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #000; text-transform: uppercase; margin: 0; }
        .header p { margin: 5px 0 0; color: #666; font-size: 10px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 5px; border: 1px solid #ddd; font-size: 10px; }
        .info-table .label { font-weight: bold; background-color: #f8f9fa; width: 30%; }
        
        .form-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .form-table th, .form-table td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; vertical-align: top; }
        .form-table th { background-color: #f1f5f9; font-weight: bold; width: 40%; color: #475569; }
        .form-table td { width: 60%; color: #0f172a; }
        .section-title { background-color: #e2e8f0; font-weight: bold; padding: 10px; margin-top: 20px; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ $templateName }}</h1>
        @if($description)
            <p>{{ $description }}</p>
        @endif
    </div>

    <!-- Belge Bilgileri -->
    <table class="info-table">
        <tr>
            <td class="label">Doküman No:</td>
            <td>{{ $documentNo ?? '-' }}</td>
            <td class="label">Yayın Tarihi:</td>
            <td>{{ $publishDate ? \Carbon\Carbon::parse($publishDate)->format('d.m.Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Rev. No / Tarih:</td>
            <td>{{ $revisionNo ?? '0' }} / {{ $revisionDate ? \Carbon\Carbon::parse($revisionDate)->format('d.m.Y') : '-' }}</td>
            <td class="label">İndirme Tarihi:</td>
            <td>{{ now()->format('d.m.Y H:i') }}</td>
        </tr>
    </table>

    <!-- Form Verileri -->
    <table class="form-table">
        <tbody>
            @foreach($elements as $element)
                @if($element['type'] === 'header')
                    <tr>
                        <td colspan="2" class="section-title">{{ $element['label'] }}</td>
                    </tr>
                @else
                    <tr>
                        <th>{{ $element['label'] }}</th>
                        <td>
                            @php
                                $val = $formData[$element['id']] ?? '';
                                if(is_array($val)) {
                                    $val = implode(', ', $val);
                                } elseif(is_bool($val)) {
                                    $val = $val ? 'Evet / Onaylıyorum' : 'Hayır';
                                }
                            @endphp
                            {{ empty($val) ? '-' : $val }}
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

</body>
</html>