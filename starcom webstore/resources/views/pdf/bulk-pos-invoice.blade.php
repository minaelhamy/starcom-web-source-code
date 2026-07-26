<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فاتورة {{ $order->order_serial_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
        }
        .wrap {
            padding: 24px;
        }
        .header {
            margin-bottom: 20px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 8px;
        }
        .muted {
            color: #666;
        }
        .grid {
            width: 100%;
            margin-bottom: 18px;
        }
        .grid td {
            padding: 4px 0;
            vertical-align: top;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        table.items th,
        table.items td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        table.items th {
            background: #f5f5f5;
        }
        .totals {
            width: 320px;
            margin-right: auto;
            margin-top: 18px;
            border-collapse: collapse;
        }
        .totals td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .totals tr:last-child td {
            font-weight: bold;
            background: #f8f8f8;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <p class="title">فاتورة مبيعات</p>
        <p class="muted">رقم الفاتورة: {{ $order->order_serial_no }}</p>
        <p class="muted">تاريخ الفاتورة: {{ \App\Libraries\AppLibrary::date($order->order_datetime) }}</p>
    </div>

    <table class="grid">
        <tr>
            <td style="width: 50%;">
                <strong>العميل:</strong> {{ $customer?->name }}<br>
                <strong>الهاتف:</strong> {{ trim(($customer?->country_code ?? '') . ' ' . ($customer?->phone ?? '')) }}<br>
                <strong>العنوان:</strong> {{ $customer?->display_address ?: '--' }}<br>
                <strong>المدينة:</strong> {{ $customer?->display_city ?: '--' }}<br>
                <strong>المنطقة:</strong> {{ $customer?->display_area ?: '--' }}
            </td>
            <td style="width: 50%;">
                <strong>حالة الطلب:</strong> مؤكد<br>
                <strong>نوع الفاتورة:</strong> نقاط بيع<br>
                <strong>طريقة الدفع:</strong> نقدي<br>
                <strong>حالة الدفع:</strong> مدفوع
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th>المنتج</th>
            <th>SKU</th>
            <th>الكمية</th>
            <th>السعر</th>
            <th>الضريبة</th>
            <th>الإجمالي</th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr>
                <td>{{ $item->product?->name ?? '--' }}</td>
                <td>{{ $item->sku }}</td>
                <td>{{ \App\Libraries\AppLibrary::flatAmountFormat(abs((float) $item->quantity)) }}</td>
                <td>{{ \App\Libraries\AppLibrary::flatAmountFormat((float) $item->price) }}</td>
                <td>{{ \App\Libraries\AppLibrary::flatAmountFormat((float) $item->tax) }}</td>
                <td>{{ \App\Libraries\AppLibrary::flatAmountFormat((float) $item->total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>الإجمالي قبل الضريبة</td>
            <td>{{ \App\Libraries\AppLibrary::currencyAmountFormat((float) $order->subtotal) }}</td>
        </tr>
        <tr>
            <td>الضريبة</td>
            <td>{{ \App\Libraries\AppLibrary::currencyAmountFormat((float) $order->tax) }}</td>
        </tr>
        <tr>
            <td>الخصم</td>
            <td>{{ \App\Libraries\AppLibrary::currencyAmountFormat((float) $order->discount) }}</td>
        </tr>
        <tr>
            <td>الإجمالي النهائي</td>
            <td>{{ \App\Libraries\AppLibrary::currencyAmountFormat((float) $order->total) }}</td>
        </tr>
    </table>
</div>
</body>
</html>
