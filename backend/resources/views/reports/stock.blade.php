<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        .meta { text-align: center; font-size: 10px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #2d6a4f; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; }
        tr:nth-child(even) td { background-color: #f5f5f5; }
        .low-stock { color: #c0392b; font-weight: bold; }
        .ok { color: #27ae60; }
        .text-right { text-align: right; }
        .summary { margin-top: 16px; text-align: right; font-size: 12px; }
        .summary strong { font-size: 13px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Digenerate pada: {{ $generated_at }}</div>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th class="text-right">Stok</th>
                <th class="text-right">Harga Beli</th>
                <th class="text-right">Nilai Stok</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
            <tr>
                <td>{{ $product['sku'] }}</td>
                <td>{{ $product['name'] }}</td>
                <td>{{ $product['category'] }}</td>
                <td>{{ $product['unit'] }}</td>
                <td class="text-right">{{ number_format($product['stock'], 2) }}</td>
                <td class="text-right">Rp {{ number_format($product['buy_price'], 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($product['stock_value'], 0, ',', '.') }}</td>
                <td class="{{ $product['is_low_stock'] ? 'low-stock' : 'ok' }}">
                    {{ $product['is_low_stock'] ? 'Low Stock' : 'OK' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        Total Nilai Stok: <strong>Rp {{ number_format($total_stock_value, 0, ',', '.') }}</strong>
    </div>
</body>
</html>
