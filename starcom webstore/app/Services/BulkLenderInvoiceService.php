<?php

namespace App\Services;

use App\Enums\Ask;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PosPaymentMethod;
use App\Enums\Status;
use App\Models\CreditApplication;
use App\Models\CreditFacility;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class BulkLenderInvoiceService
{
    public function generate(array $options): array
    {
        $invoiceDate = Carbon::parse($options['invoice_date'])->setTime(12, 0, 0);
        $batchName = $this->normalizeBatchName($options['batch'] ?? null);
        $minTotal = round((float) ($options['min_total'] ?? 70000), 2);
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $requiredProducts = $this->resolveRequiredProducts($options);
        $customers = $this->resolveCustomers($options);

        $extraProducts = $this->resolveExtraProducts($requiredProducts->pluck('id')->all());

        $summary = [
            'batch' => $batchName,
            'invoice_date' => $invoiceDate->toDateString(),
            'min_total' => $minTotal,
            'dry_run' => $dryRun,
            'required_products' => $requiredProducts->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ])->values()->all(),
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'rows' => [],
            'pdf_directory' => null,
            'zip_path' => null,
            'csv_path' => null,
        ];

        foreach ($customers as $customer) {
            $summary['processed']++;

            try {
                $lineItems = $this->buildInvoiceLines(
                    customer: $customer,
                    requiredProducts: $requiredProducts,
                    extraProducts: $extraProducts,
                    minTotal: $minTotal
                );

                $totals = $this->calculateTotals($lineItems);

                if ($dryRun) {
                    $summary['rows'][] = [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'total' => $totals['total'],
                        'order_serial_no' => null,
                        'pdf_path' => null,
                        'status' => 'dry_run',
                    ];
                    continue;
                }

                $order = DB::transaction(function () use ($customer, $lineItems, $invoiceDate, $totals) {
                    $order = new Order();
                    $order->fill([
                        'user_id' => $customer->id,
                        'subtotal' => $totals['subtotal'],
                        'discount' => $totals['discount'],
                        'tax' => $totals['tax'],
                        'total' => $totals['total'],
                        'wallet_paid_amount' => 0,
                        'cash_on_delivery_amount' => $totals['total'],
                        'shipping_charge' => 0,
                        'order_type' => OrderType::POS,
                        'order_datetime' => $invoiceDate->copy(),
                        'payment_method' => PaymentGateway::CASH_ON_DELIVERY,
                        'payment_status' => PaymentStatus::PAID,
                        'status' => OrderStatus::CONFIRMED,
                        'source' => OrderType::POS,
                        'pos_payment_method' => PosPaymentMethod::CASH,
                        'pos_payment_note' => '',
                        'pos_received_amount' => $totals['total'],
                        'active' => Ask::YES,
                    ]);
                    $order->save();

                    app(OrderStockService::class)->assertProductsAvailable($lineItems, true);

                    foreach ($lineItems as $lineItem) {
                        $stock = Stock::create([
                            'product_id' => $lineItem->product_id,
                            'model_type' => Order::class,
                            'model_id' => $order->id,
                            'item_type' => Product::class,
                            'item_id' => $lineItem->product_id,
                            'variation_names' => '',
                            'sku' => $lineItem->sku,
                            'price' => $lineItem->price,
                            'quantity' => -$lineItem->quantity,
                            'discount' => $lineItem->discount,
                            'tax' => $lineItem->total_tax,
                            'subtotal' => $lineItem->subtotal,
                            'total' => $lineItem->total,
                            'status' => Status::ACTIVE,
                        ]);

                        foreach ($lineItem->taxes as $tax) {
                            DB::table('stock_taxes')->insert([
                                'stock_id' => $stock->id,
                                'product_id' => $lineItem->product_id,
                                'tax_id' => $tax['id'],
                                'name' => $tax['name'],
                                'code' => $tax['code'],
                                'tax_rate' => $tax['tax_rate'],
                                'tax_amount' => $tax['tax_amount'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $order->order_serial_no = date('dmy', $invoiceDate->timestamp) . $order->id;
                    $order->save();

                    return $order->fresh(['user.latestAddress', 'orderProducts.product', 'orderProducts.stockTaxes']);
                });

                $pdfPath = $this->storeInvoicePdf($order, $batchName);
                $summary['created']++;
                $summary['pdf_directory'] = 'storage/generated-bulk-invoices/' . $batchName;

                $summary['rows'][] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'total' => $totals['total'],
                    'order_serial_no' => $order->order_serial_no,
                    'pdf_path' => $pdfPath,
                    'status' => 'created',
                ];
            } catch (Exception $exception) {
                $summary['skipped']++;
                $summary['rows'][] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'total' => null,
                    'order_serial_no' => null,
                    'pdf_path' => null,
                    'status' => 'skipped',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        if (!$dryRun && !empty($summary['rows'])) {
            $summary['csv_path'] = $this->storeSummaryCsv($summary['rows'], $batchName);
            $summary['zip_path'] = $this->createBatchZip($batchName);
        }

        return $summary;
    }

    protected function resolveCustomers(array $options): Collection
    {
        if (!blank($options['approved_file'] ?? null)) {
            return $this->resolveCustomersFromWorkbook((string) $options['approved_file'], $options);
        }

        $query = CreditFacility::query()
            ->with(['user.latestAddress'])
            ->where('status', 'approved')
            ->select('user_id')
            ->distinct();

        $userIds = collect(explode(',', (string) ($options['user_ids'] ?? '')))
            ->map(fn ($value) => (int) trim($value))
            ->filter()
            ->values();

        if ($userIds->isNotEmpty()) {
            $query->whereIn('user_id', $userIds);
        }

        $resolvedUserIds = $query->pluck('user_id');

        $customers = \App\Models\User::query()
            ->with('latestAddress')
            ->whereIn('id', $resolvedUserIds)
            ->orderBy('name')
            ->get();

        $limit = (int) ($options['limit'] ?? 0);
        if ($limit > 0) {
            $customers = $customers->take($limit)->values();
        }

        return $customers;
    }

    protected function resolveCustomersFromWorkbook(string $path, array $options): Collection
    {
        if (!file_exists($path)) {
            throw new Exception("Approved clients workbook not found: {$path}");
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) {
            return collect();
        }

        $header = array_map(fn ($item) => trim((string) $item), $rows[0]);
        $records = collect(array_slice($rows, 1))
            ->map(function ($row) use ($header) {
                $assoc = [];
                foreach ($header as $index => $key) {
                    $assoc[$key] = $row[$index] ?? null;
                }
                return $assoc;
            })
            ->filter(function ($row) {
                return trim((string) ($row['الرقم القومي'] ?? '')) !== '' || trim((string) ($row['الاسم رباعي'] ?? '')) !== '';
            })
            ->values();

        $approvedUserIds = CreditFacility::query()
            ->where('status', 'approved')
            ->distinct()
            ->pluck('user_id');

        $customers = User::query()
            ->with(['latestAddress', 'creditApplications'])
            ->whereIn('id', $approvedUserIds)
            ->get();

        $applications = CreditApplication::query()
            ->whereIn('user_id', $approvedUserIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('user_id');

        $customers = $customers->map(function (User $user) use ($applications) {
            $user->setRelation('creditApplications', $applications->get($user->id, collect()));
            return $user;
        });

        $matched = collect();
        foreach ($records as $record) {
            $match = $this->matchWorkbookCustomer($record, $customers);
            if ($match) {
                $matched->push($match->setAttribute('invoice_seed_meta', [
                    'sheet_customer_name' => $this->normalizeWorkbookValue($record['العميل'] ?? null),
                    'sheet_full_name' => $this->normalizeWorkbookValue($record['الاسم رباعي'] ?? null),
                    'sheet_national_id' => $this->normalizeDigits((string) ($record['الرقم القومي'] ?? '')),
                    'sheet_address' => $this->normalizeWorkbookValue($record['العنوان'] ?? null),
                    'sheet_city' => $this->normalizeWorkbookValue($record['المدينة'] ?? null),
                    'sheet_area' => $this->normalizeWorkbookValue($record['المنطقة'] ?? null),
                    'sheet_average_monthly_purchase' => (float) ($record['متوسط الشراء الشهري من ستاركوم في آخر ١٢ شهر'] ?? 0),
                    'sheet_limit_approved' => (float) ($record['Limit approved'] ?? 0),
                    'sheet_reason' => $this->normalizeWorkbookValue($record['السبب'] ?? null),
                ]));
            }
        }

        $limit = (int) ($options['limit'] ?? 0);
        if ($limit > 0) {
            $matched = $matched->take($limit)->values();
        }

        return $matched
            ->unique('id')
            ->values();
    }

    protected function resolveRequiredProducts(array $options): Collection
    {
        return collect([
            'sugar' => $this->resolveProduct(
                explicitId: $options['sugar_product_id'] ?? 22,
                keywords: $this->keywords($options['sugar_keywords'] ?? 'سكر,sugar')
            ),
            'milk_1l' => $this->resolveProduct(
                explicitId: $options['milk1_product_id'] ?? 35,
                keywords: $this->keywords($options['milk1_keywords'] ?? 'بخيرو,bkhiero,bkhiro,1l,1 l,1 لتر')
            ),
            'milk_05l' => $this->resolveProduct(
                explicitId: $options['milk05_product_id'] ?? 34,
                keywords: $this->keywords($options['milk05_keywords'] ?? 'بخيرو,bkhiero,bkhiro,0.5,0.5l,500,500ml,500 ml,نصف لتر')
            ),
        ]);
    }

    protected function resolveExtraProducts(array $excludedIds): Collection
    {
        return Product::query()
            ->with(['taxes.tax', 'unit'])
            ->withSum('stockItems', 'quantity')
            ->where('status', Status::ACTIVE)
            ->where('can_purchasable', Ask::YES)
            ->whereNotIn('id', $excludedIds)
            ->get()
            ->filter(fn (Product $product) => $this->availableStock($product) > 0 && $this->currentPrice($product) > 0)
            ->shuffle()
            ->values();
    }

    protected function resolveProduct(?string $explicitId, array $keywords): Product
    {
        $query = Product::query()
            ->with(['taxes.tax', 'unit'])
            ->withSum('stockItems', 'quantity')
            ->where('status', Status::ACTIVE)
            ->where('can_purchasable', Ask::YES);

        if (!blank($explicitId)) {
            $product = $query->find((int) $explicitId);
            if ($product && $this->availableStock($product) > 0) {
                return $product;
            }

            throw new Exception("Required product id {$explicitId} is missing or out of stock.");
        }

        $product = $query
            ->where(function ($builder) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $builder->orWhere('name', 'like', '%' . $keyword . '%')
                        ->orWhere('sku', 'like', '%' . $keyword . '%');
                }
            })
            ->get()
            ->sortByDesc(fn (Product $product) => $this->availableStock($product))
            ->first(fn (Product $product) => $this->availableStock($product) > 0);

        if (!$product) {
            throw new Exception('Could not find one of the required products in stock. Use explicit product ids for the command.');
        }

        return $product;
    }

    protected function buildInvoiceLines($customer, Collection $requiredProducts, Collection $extraProducts, float $minTotal): array
    {
        $lines = [];

        $sugar = $requiredProducts['sugar'];
        $milk1 = $requiredProducts['milk_1l'];
        $milk05 = $requiredProducts['milk_05l'];

        $lines[] = $this->buildLineItem($sugar, $this->defaultSugarQuantity($sugar));
        $lines[] = $this->buildLineItem($milk1, min($this->availableStock($milk1), 24));
        $lines[] = $this->buildLineItem($milk05, min($this->availableStock($milk05), 24));

        $totals = $this->calculateTotals($lines);
        if ($totals['total'] >= $minTotal) {
            return $lines;
        }

        $usedProductIds = collect($lines)->pluck('product_id')->all();
        $remainingTarget = $minTotal - $totals['total'];

        foreach ($extraProducts as $product) {
            if (in_array($product->id, $usedProductIds, true)) {
                continue;
            }

            $price = $this->currentPrice($product);
            $stock = $this->availableStock($product);
            if ($price <= 0 || $stock <= 0) {
                continue;
            }

            $targetContribution = max($remainingTarget / 3, $price);
            $quantity = min($stock, max(1, (float) ceil($targetContribution / max($price, 0.01))));
            $lines[] = $this->buildLineItem($product, $quantity);
            $usedProductIds[] = $product->id;

            $totals = $this->calculateTotals($lines);
            $remainingTarget = $minTotal - $totals['total'];

            if ($totals['total'] >= $minTotal && count($lines) >= 5) {
                break;
            }
        }

        $totals = $this->calculateTotals($lines);
        if ($totals['total'] < $minTotal) {
            throw new Exception("Could not reach {$minTotal} EGP for {$customer->name} with the current in-stock products.");
        }

        return $lines;
    }

    protected function buildLineItem(Product $product, float $quantity): object
    {
        $quantity = round(min($this->availableStock($product), $quantity), 2);
        if ($quantity <= 0) {
            throw new Exception("{$product->name} is out of stock.");
        }

        $price = round($this->currentPrice($product), 2);
        $subtotal = round($price * $quantity, 2);
        $taxes = [];
        $taxPerUnit = 0.0;

        foreach ($product->taxes as $index => $productTax) {
            if ((int) ($productTax->tax?->status ?? 0) !== Status::ACTIVE) {
                continue;
            }

            $taxRate = round((float) ($productTax->tax?->tax_rate ?? 0), 2);
            $taxAmount = round(($price / 100) * $taxRate, 2);
            $taxPerUnit += $taxAmount;
            $taxes[] = [
                'id' => $productTax->id,
                'name' => (string) ($productTax->tax?->name ?? ''),
                'code' => (string) ($productTax->tax?->code ?? ''),
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
            ];
        }

        $totalTax = round($taxPerUnit * $quantity, 2);

        return (object) [
            'name' => $product->name,
            'product_id' => $product->id,
            'variation_names' => '',
            'variation_id' => null,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'discount' => 0,
            'price' => $price,
            'old_price' => round((float) $product->selling_price, 2),
            'subtotal' => $subtotal,
            'total_tax' => $totalTax,
            'taxes' => $taxes,
            'total' => round($subtotal + $totalTax, 2),
            'stock' => $this->availableStock($product),
        ];
    }

    protected function calculateTotals(array $lineItems): array
    {
        return [
            'subtotal' => round(collect($lineItems)->sum('subtotal'), 2),
            'discount' => round(collect($lineItems)->sum('discount'), 2),
            'tax' => round(collect($lineItems)->sum('total_tax'), 2),
            'total' => round(collect($lineItems)->sum('total'), 2),
        ];
    }

    protected function currentPrice(Product $product): float
    {
        $basePrice = (float) ($product->variations->count() > 0 ? $product->variation_price : $product->selling_price);

        if (
            !blank($product->offer_start_date) &&
            !blank($product->offer_end_date) &&
            now()->between(Carbon::parse($product->offer_start_date), Carbon::parse($product->offer_end_date))
        ) {
            return round($basePrice - (($basePrice / 100) * (float) $product->discount), 2);
        }

        return round($basePrice, 2);
    }

    protected function availableStock(Product $product): float
    {
        return max(0, round((float) ($product->stock_items_sum_quantity ?? 0), 2));
    }

    protected function defaultSugarQuantity(Product $product): float
    {
        $unit = Str::lower((string) ($product->unit?->name ?? ''));

        if (Str::contains($unit, ['طن', 'ton'])) {
            return min($this->availableStock($product), 1);
        }

        if (Str::contains($unit, ['كجم', 'كيلو', 'kg', 'kilo'])) {
            return min($this->availableStock($product), 1000);
        }

        return min($this->availableStock($product), 1000);
    }

    protected function matchWorkbookCustomer(array $record, Collection $customers): ?User
    {
        $nationalId = $this->normalizeDigits((string) ($record['الرقم القومي'] ?? ''));
        $fullName = $this->normalizeLooseText((string) ($record['الاسم رباعي'] ?? ''));
        $customerName = $this->normalizeLooseText((string) ($record['العميل'] ?? ''));
        $address = $this->normalizeLooseText((string) ($record['العنوان'] ?? ''));

        if ($nationalId !== '') {
            $byNationalId = $customers->first(function (User $user) use ($nationalId) {
                return $user->creditApplications->contains(function (CreditApplication $application) use ($nationalId) {
                    return $this->normalizeDigits((string) $application->national_id_number) === $nationalId;
                });
            });

            if ($byNationalId) {
                return $byNationalId;
            }
        }

        $fullNameCandidates = $customers->filter(function (User $user) use ($fullName) {
            if ($fullName === '') {
                return false;
            }

            return $user->creditApplications->contains(function (CreditApplication $application) use ($fullName) {
                return $this->normalizeLooseText((string) $application->full_name) === $fullName;
            });
        });

        if ($fullNameCandidates->count() === 1) {
            return $fullNameCandidates->first();
        }

        $scored = $customers->map(function (User $user) use ($customerName, $fullName, $address) {
            $score = 0;
            $userName = $this->normalizeLooseText((string) $user->name);
            $userAddress = $this->normalizeLooseText((string) $user->display_address);
            $applicationFullNames = $user->creditApplications
                ->pluck('full_name')
                ->map(fn ($value) => $this->normalizeLooseText((string) $value))
                ->filter();

            if ($customerName !== '' && $userName === $customerName) {
                $score += 3;
            } elseif ($customerName !== '' && Str::contains($userName, $customerName)) {
                $score += 2;
            }

            if ($fullName !== '' && $applicationFullNames->contains($fullName)) {
                $score += 4;
            }

            if ($address !== '' && $userAddress !== '') {
                similar_text($address, $userAddress, $percent);
                if ($percent >= 70) {
                    $score += 3;
                } elseif ($percent >= 50) {
                    $score += 1;
                }
            }

            return ['user' => $user, 'score' => $score];
        })->sortByDesc('score')->values();

        if (($scored[0]['score'] ?? 0) >= 5) {
            return $scored[0]['user'];
        }

        return null;
    }

    protected function storeInvoicePdf(Order $order, string $batchName): string
    {
        $directory = "generated-bulk-invoices/{$batchName}";
        $fileName = $order->order_serial_no . '-' . Str::slug($order->user?->name ?: 'customer') . '.pdf';
        $storagePath = "{$directory}/{$fileName}";

        $pdf = Pdf::loadView('pdf.bulk-pos-invoice', [
            'order' => $order,
            'customer' => $order->user,
            'items' => $order->orderProducts,
        ])->setPaper('a4');

        Storage::disk('public')->put($storagePath, $pdf->output());

        return 'storage/' . $storagePath;
    }

    protected function storeSummaryCsv(array $rows, string $batchName): string
    {
        $directory = storage_path('app/public/generated-bulk-invoices/' . $batchName);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . '/summary.csv';
        $handle = fopen($path, 'w');

        fputcsv($handle, ['customer_id', 'customer_name', 'order_serial_no', 'total', 'status', 'message', 'pdf_path']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['customer_id'] ?? '',
                $row['customer_name'] ?? '',
                $row['order_serial_no'] ?? '',
                $row['total'] ?? '',
                $row['status'] ?? '',
                $row['message'] ?? '',
                $row['pdf_path'] ?? '',
            ]);
        }

        fclose($handle);

        return 'storage/generated-bulk-invoices/' . $batchName . '/summary.csv';
    }

    protected function createBatchZip(string $batchName): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }

        $directory = storage_path('app/public/generated-bulk-invoices/' . $batchName);
        if (!is_dir($directory)) {
            return null;
        }

        $zipPath = $directory . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach (scandir($directory) ?: [] as $file) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }

            $fullPath = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_file($fullPath)) {
                $zip->addFile($fullPath, $file);
            }
        }

        $zip->close();

        return 'storage/generated-bulk-invoices/' . $batchName . '.zip';
    }

    protected function normalizeBatchName(?string $batch): string
    {
        return Str::slug($batch ?: 'batch-' . now()->format('Ymd-His'));
    }

    protected function keywords(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeArabicDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    protected function normalizeDigits(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $this->normalizeArabicDigits($value)) ?: '';
    }

    protected function normalizeWorkbookValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeLooseText(string $value): string
    {
        $value = $this->normalizeArabicDigits($value);
        $value = str_replace(["\n", "\r", "\t"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        return trim((string) $value);
    }
}
