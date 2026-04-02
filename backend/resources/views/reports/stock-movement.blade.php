<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; margin: 20px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        .meta { text-align: center; font-size: 10px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #1a5276; color: #fff; padding: 6px 8px; text-align: left; font-size: 9px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; }
        tr:nth-child(even) td { background-color: #f5f5f5; }
        .type-in { color: #27ae60; font-weight: bold; }
        .type-out { color: #c0392b; font-weight: bold; }
        .text-right { text-align: right; }
        .summary { margin-top: 16px; font-size: 11px; }
        .summary table { width: auto; }
        .summary td { border: none; padding: 2px 8px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Periode: {{ $start_date }} s/d {{ $end_date }} &nbsp;|&nbsp; Digenerate pada: {{ $generated_at }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>SKU</th>
                <th>Nama Produk</th>
                <th>Tipe</th>
                <th class="text-right">Jumlah</th>
                <th class="text-right">Stok Sebelum</th>
                <th class="text-right">Stok Sesudah</th>
                <th>Referensi</th>
                <th>Catatan</th>
                <th>Oleh</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
            <tr>
                <td>{{ $movement['date'] }}</td>
                <td>{{ $movement['product_sku'] }}</td>
                <td>{{ $movement['product_name'] }}</td>
                <td class="{{ $movement['type'] === 'in' ? 'type-in' : 'type-out' }}">
                    {{ $movement['type'] === 'in' ? 'Masuk' : 'Keluar' }}
                </td>
                <td class="text-right">{{ number_format($movement['quantity'], 2) }}</td>
                <td class="text-right">{{ number_format($movement['stock_before'], 2) }}</td>
                <td class="text-right">{{ number_format($movement['stock_after'], 2) }}</td>
                <td>{{ $movement['reference_type'] }}{{ $movement['reference_id'] ? ' #' . $movement['reference_id'] : '' }}</td>
                <td>{{ $movement['notes'] }}</td>
                <td>{{ $movement['created_by'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" style="text-align:center; color:#999;">Tidak ada data pergerakan stok pada periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr>
                <td>Total Masuk:</td>
                <td><strong>{{ number_format($total_in, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Total Keluar:</td>
                <td><strong>{{ number_format($total_out, 2) }}</strong></td>
            </tr>
        </table>
    </div>
</body>
</html>
