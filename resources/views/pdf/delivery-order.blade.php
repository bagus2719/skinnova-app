<!DOCTYPE html>
<html>
<head>
    <title>Surat Jalan {{ $deliveryOrder->reference_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #000;
        }

        .container {
            width: 100%;
            padding: 20px;
            padding-bottom: 120px;
        }

        /* HEADER */
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .header p {
            margin: 4px 0 0;
            font-size: 9px;
        }

        /* INFO UTAMA */
        .info-layout-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 20px;
        }

        .info-layout-table td {
            width: 50%;
            vertical-align: top;
        }

        .info-box {
            border: 1px solid #ccc;
            padding: 10px;
            height: 120px;
        }

        .info-box h3 {
            font-size: 11px;
            margin: 0 0 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #ddd;
        }

        .info-box p {
            margin: 3px 0;
        }

        /* DETAIL ITEM */
        .details table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .details th,
        .details td {
            border: 1px solid #333;
            padding: 6px;
            font-size: 9px;
        }

        .details th {
            background-color: #e9ecef;
            text-align: center;
            font-weight: bold;
        }

        /* CATATAN */
        .notes-box {
            margin-top: 30px;
            padding: 10px;
            border: 1px dashed #999;
            font-size: 9px;
        }

        /* TANDA TANGAN */
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
            font-size: 9px;
            text-align: center;
        }

        .signatures-table td {
            width: 50%;
        }

        .sign-placeholder {
            height: 45px;
            border-bottom: 1px solid #333;
            margin: 0 auto;
            width: 70%;
        }

        /* FOOTER */
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            border-top: 1px solid #ccc;
            padding: 5px 0;
        }
    </style>
</head>

<body>
<div class="container">

    {{-- HEADER --}}
    <div class="header">
        <h1>SURAT JALAN / DELIVERY ORDER</h1>
        <p>PT. SKINNOVA INDONESIA · Jalan Contoh No. 123, Jakarta</p>
    </div>

    {{-- INFORMASI UTAMA --}}
    <table class="info-layout-table">
        <tr>
            <td style="padding-right:6px;">
                <div class="info-box">
                    <h3>Dikirim Kepada</h3>
                    <p><strong>{{ optional(optional($deliveryOrder->salesOrder)->customer)->name ?? 'N/A' }}</strong></p>
                    <p>Alamat: {{ optional(optional($deliveryOrder->salesOrder)->customer)->address ?? 'N/A' }}</p>
                    <p>Telepon: {{ optional(optional($deliveryOrder->salesOrder)->customer)->phone ?? 'N/A' }}</p>
                    <p style="margin-top:6px;"><strong>Alamat Kirim:</strong></p>
                    <p>{{ $deliveryOrder->salesOrder->shipping_address ?? 'Sama dengan alamat utama' }}</p>
                </div>
            </td>

            <td style="padding-left:6px;">
                <div class="info-box">
                    <h3>Informasi Dokumen</h3>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td>Nomor</td>
                            <td>: <strong>{{ $deliveryOrder->reference_no }}</strong></td>
                        </tr>
                        <tr>
                            <td>Tanggal</td>
                            <td>: {{ optional($deliveryOrder->delivery_date) ? \Carbon\Carbon::parse($deliveryOrder->delivery_date)->format('d F Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td>Dari SO</td>
                            <td>: {{ $deliveryOrder->salesOrder->reference_no ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- DETAIL ITEM --}}
    <div class="details">
        <table>
            <thead>
            <tr>
                <th width="5%">No</th>
                <th width="45%">Produk</th>
                <th width="15%">Qty SO</th>
                <th width="20%">Qty Kirim</th>
                <th width="15%">Satuan</th>
            </tr>
            </thead>
            <tbody>
            @forelse($deliveryOrder->items as $i => $item)
                <tr>
                    <td align="center">{{ $i + 1 }}</td>
                    <td>{{ $item->product->name ?? 'Produk dihapus' }}</td>
                    <td align="center">{{ number_format($item->sales_order_quantity ?? 0, 2, ',', '.') }}</td>
                    <td align="center">{{ number_format($item->delivered_quantity ?? 0, 2, ',', '.') }}</td>
                    <td align="center">PCS</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" align="center"><strong>DATA ITEM KOSONG</strong></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- CATATAN --}}
    <div class="notes-box">
        <p><strong>Catatan:</strong> {{ $deliveryOrder->notes ?? '-' }}</p>
        <p><strong>Konfirmasi:</strong> Barang diterima lengkap dan dalam kondisi baik.</p>
        <p>Tanggal Terima: ____________  Jam: ____________</p>
    </div>

    {{-- TANDA TANGAN --}}
    <table class="signatures-table">
        <tr>
            <td>Pengirim</td>
            <td>Penerima</td>
        </tr>
        <tr>
            <td><div class="sign-placeholder"></div></td>
            <td><div class="sign-placeholder"></div></td>
        </tr>
        <tr>
            <td><strong>PT. SKINNOVA INDONESIA</strong></td>
            <td><strong>{{ optional(optional($deliveryOrder->salesOrder)->customer)->name ?? 'N/A' }}</strong></td>
        </tr>
        <tr>
            <td>( Admin )</td>
            <td>( Pelanggan )</td>
        </tr>
    </table>

</div>

<div class="footer">
    Dokumen ini sah dan dicetak secara sistem.
</div>

</body>
</html>
