<!DOCTYPE html>
<html>

<head>
    <title>Invoice {{ $invoice->reference_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #ccc;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .header p {
            margin: 2px 0;
            font-size: 10px;
        }

        .invoice-info {
            width: 100%;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .client-info,
        .date-info {
            float: left;
            width: 48%;
        }

        .date-info {
            float: right;
            text-align: right;
        }

        .client-info h3,
        .date-info h3 {
            margin: 0 0 5px 0;
            font-size: 12px;
        }

        .details table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .details th,
        .details td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        .details th {
            background-color: #f2f2f2;
            font-size: 10px;
        }

        .details td {
            font-size: 9px;
        }

        .totals {
            width: 300px;
            float: right;
            margin-top: 20px;
        }

        .totals table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals td {
            padding: 5px;
            text-align: right;
            border: none;
        }

        .totals .total-row td {
            border-top: 1px solid #ccc;
            font-weight: bold;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            font-size: 8px;
        }

        .status-paid {
            color: white;
            background-color: green;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <h1>FAKTUR PENJUALAN (SALES INVOICE)</h1>
            <p>PT. SKINNOVA INDONESIA | Alamat Perusahaan: Jalan Contoh No. 123, Jakarta</p>
            <p>Telp: (021) 1234567 | Email: info@skinnova.com</p>
        </div>

        <div class="invoice-info">
            <div class="client-info">
                <h3>Kepada Yth:</h3>
                <p><strong>{{ $invoice->salesOrder->customer->name ?? 'N/A' }}</strong></p>
                <p>{{ $invoice->salesOrder->customer->address ?? 'N/A' }}</p>
                <p>Telp: {{ $invoice->salesOrder->customer->phone ?? 'N/A' }}</p>
            </div>
            <div class="date-info">
                <table>
                    <tr>
                        <td>Nomor Faktur</td>
                        <td>: <strong>{{ $invoice->reference_no }}</strong></td>
                    </tr>
                    <tr>
                        <td>Tanggal Faktur</td>
                        <td>: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d F Y') }}</td>
                    </tr>
                    <tr>
                        <td>Jatuh Tempo</td>
                        <td>: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') }}</td>
                    </tr>
                    <tr>
                        <td>Dari S.O.</td>
                        <td>: {{ $invoice->salesOrder->reference_no ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td>:
                            @if($invoice->status === 'Paid')
                                <span class="status-paid">LUNAS</span>
                            @else
                                <strong>{{ $invoice->status }}</strong>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="details">
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 40%;">Deskripsi Produk</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 15%;">Harga Satuan</th>
                        <th style="width: 10%;">Diskon</th>
                        <th style="width: 20%;">Total Baris</th>
                    </tr>
                </thead>
                <tbody>
                    @php $no = 1; @endphp
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td style="text-align: center;">{{ $no++ }}</td>
                            <td>{{ $item->product->name ?? 'Produk Dihapus' }}</td>
                            <td style="text-align: center;">{{ number_format($item->quantity) }}</td>
                            <td style="text-align: right;">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td style="text-align: right;">0.00</td>
                            <td style="text-align: right;">{{ number_format($item->line_total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="totals">
            <table>
                <tr>
                    <td>Total (Sebelum Pajak)</td>
                    <td>Rp</td>
                    <td>{{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Pajak ({{ $taxRate * 100 }}%)</td>
                    <td>Rp</td>
                    <td>{{ number_format($invoice->tax_amount, 2, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL AKHIR</td>
                    <td>Rp</td>
                    <td>{{ number_format($invoice->grand_total, 2, ',', '.') }}</td>
                </tr>
            </table>
        </div>
        <div style="clear: both;"></div>

        <div style="margin-top: 40px; border-top: 1px solid #ccc; padding-top: 10px;">
            <p><strong>Catatan:</strong> {{ $invoice->notes }}</p>
        </div>

        <div style="width: 100%; margin-top: 40px; overflow: hidden; font-size: 10px;">
            <div style="float: left; width: 30%; text-align: center;">
                <p>Dibuat Oleh,</p>
                <div style="height: 50px;"></div>
                <p>( {{ Auth::user()->name ?? 'Administrator' }} )</p>
            </div>
            <div style="float: right; width: 30%; text-align: center;">
                <p>Penerima,</p>
                <div style="height: 50px;"></div>
                <p>( _________________________ )</p>
            </div>
        </div>
    </div>

    <div class="footer">
        Dokumen ini dibuat secara otomatis dan sah tanpa tanda tangan basah. Harap hubungi kami jika ada pertanyaan.
    </div>

</body>

</html>