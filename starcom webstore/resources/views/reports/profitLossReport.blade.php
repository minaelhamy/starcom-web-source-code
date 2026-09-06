<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1F1F39; font-size: 11px; }
        .header { text-align: center; margin-bottom: 18px; }
        .header img { max-height: 38px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dfe3ea; padding: 7px; text-align: left; }
        th { background: #f5f7fa; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        @if ($themeLogo)<img src="{{ $themeLogo }}" alt="logo">@endif
        <h3>Profit and Loss Report</h3>
        <p>{{ $company['company_name'] ?? '' }}</p>
    </div>
    @php($totals = ['quantity' => 0, 'cost' => 0, 'sales' => 0, 'profit' => 0])
    <table>
        <thead><tr>
            <th>Product</th><th>Category</th><th>Quantity</th><th>Unit Cost</th><th>Total Cost</th><th>Avg. Selling Price</th><th>Sales Total</th><th>Gross Profit</th>
        </tr></thead>
        <tbody>
        @foreach ($reports as $report)
            @php
                $totals['quantity'] += (float) $report->sold_quantity;
                $totals['cost'] += (float) $report->cost_total;
                $totals['sales'] += (float) $report->sales_total;
                $totals['profit'] += (float) $report->gross_profit;
            @endphp
            <tr>
                <td>{{ $report->name }}</td><td>{{ $report->category?->name }}</td><td>{{ abs((float) $report->sold_quantity) }}</td>
                <td>{{ number_format($report->unit_cost, 2) }}</td><td>{{ number_format($report->cost_total, 2) }}</td>
                <td>{{ number_format($report->selling_unit_price, 2) }}</td><td>{{ number_format($report->sales_total, 2) }}</td><td>{{ number_format($report->gross_profit, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="2">Total</td><td>{{ number_format($totals['quantity'], 2) }}</td><td></td><td>{{ number_format($totals['cost'], 2) }}</td><td></td><td>{{ number_format($totals['sales'], 2) }}</td><td>{{ number_format($totals['profit'], 2) }}</td></tr>
        </tbody>
    </table>
    <p>{{ $copyright }}</p>
</body>
</html>
