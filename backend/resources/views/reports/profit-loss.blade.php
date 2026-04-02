<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        .meta { text-align: center; font-size: 10px; color: #666; margin-bottom: 16px; }
        h2 { font-size: 13px; margin-top: 20px; margin-bottom: 6px; border-bottom: 2px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th { background-color: #154360; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; }
        tr:nth-child(even) td { background-color: #f5f5f5; }
        .text-right { text-align: right; }
        .summary-box { margin-top: 24px; border: 2px solid #154360; padding: 12px 16px; border-radius: 4px; }
        .summary-box table { width: auto; min-width: 300px; }
        .summary-box td { border: none; padding: 4px 8px; font-size: 12px; }
        .summary-box .label { font-weight: bold; }
        .profit { color: #27ae60; font-weight: bold; font-size: 14px; }
        .loss { color: #c0392b; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Periode: {{ $start_date }} s/d {{ $end_date }} &nbsp;|&nbsp; Digenerate pada: {{ $generated_at }}
    </div>

    <h2>Pemasukan</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th>Sumber</th>
                <th class="text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($income_entries as $entry)
            <tr>
                <td>{{ $entry['date'] }}</td>
                <td>{{ $entry['category'] }}</td>
                <td>{{ $entry['description'] }}</td>
                <td>{{ strtoupper($entry['source']) }}</td>
                <td class="text-right">{{ number_format($entry['amount'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align:center; color:#999;">Tidak ada data pemasukan.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Pengeluaran</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th class="text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expense_entries as $entry)
            <tr>
                <td>{{ $entry['date'] }}</td>
                <td>{{ $entry['category'] }}</td>
                <td>{{ $entry['description'] }}</td>
                <td class="text-right">{{ number_format($entry['amount'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align:center; color:#999;">Tidak ada data pengeluaran.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-box">
        <table>
            <tr>
                <td class="label">Total Pemasukan:</td>
                <td class="text-right">Rp {{ number_format($total_income, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Total Pengeluaran:</td>
                <td class="text-right">Rp {{ number_format($total_expense, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Laba/Rugi Bersih:</td>
                <td class="text-right {{ $is_loss ? 'loss' : 'profit' }}">
                    Rp {{ number_format(abs($net_profit), 0, ',', '.') }}
                    {{ $is_loss ? '(RUGI)' : '(LABA)' }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
