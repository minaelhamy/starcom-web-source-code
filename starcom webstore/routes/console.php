<?php

use App\Services\BulkLenderInvoiceService;
use App\Services\CartonaCustomerOnboardingService;
use App\Services\CustomerServiceLeadService;
use App\Models\User;
use App\Enums\Role as RoleEnum;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cartona:sync-customers {--file=} {--pull}', function (CartonaCustomerOnboardingService $service) {
    $orders = [];

    if ($this->option('file')) {
        $filePath = (string) $this->option('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $orders = json_decode(file_get_contents($filePath), true);
        if (!is_array($orders)) {
            $this->error('The provided file does not contain a valid JSON orders array.');
            return self::FAILURE;
        }
    } elseif ($this->option('pull')) {
        $orders = $service->pullOrders();
    } else {
        $this->error('Use either --file=/path/to/orders.json or --pull');
        return self::FAILURE;
    }

    $summary = $service->syncOrders($orders);

    $this->table(
        ['Orders Received', 'Customers Synced', 'Orders Synced', 'Items Synced', 'Users Created'],
        [[
            $summary['orders_received'],
            $summary['customers_synced'],
            $summary['orders_synced'],
            $summary['items_synced'],
            $summary['users_created'],
        ]]
    );

    $this->info('Cartona customer onboarding sync completed.');

    return self::SUCCESS;
})->purpose('Sync Cartona customers, orders, and Starcom Intelligence customer profiles');

Artisan::command('starcom:backfill-invoice-timeline {--months=12} {--dry-run}', function () {
    $months = max(1, (int)$this->option('months'));
    $dryRun = (bool)$this->option('dry-run');

    if (!Schema::hasTable('starcom_intelligence') || !Schema::hasColumn('starcom_intelligence', 'invoice_date')) {
        $this->error('The starcom_intelligence invoice timeline columns are missing. Run migrations first.');
        return self::FAILURE;
    }

    $windowEnd = now()->startOfMonth();
    $windowStart = $windowEnd->copy()->subMonths($months);

    $groups = DB::table('starcom_intelligence')
        ->select([
            'phone',
            'country_code',
            DB::raw('MIN(user_name) as user_name'),
            DB::raw('MIN(phone_number) as phone_number'),
            DB::raw('MIN(address) as address'),
            DB::raw('MIN(city) as city'),
            DB::raw('MIN(area) as area'),
            DB::raw('MIN(latitude) as latitude'),
            DB::raw('MIN(longitude) as longitude'),
            DB::raw('MIN(distribution_route) as distribution_route'),
            DB::raw('COUNT(*) as rows_count'),
            DB::raw('AVG(invoice_amount) as average_invoice_amount'),
            DB::raw('AVG(cartona_credit_amount) as average_credit_amount'),
        ])
        ->whereNotNull('phone')
        ->where('phone', '!=', '')
        ->where(function ($query) {
            $query->whereNull('generated_from_backfill')
                ->orWhere('generated_from_backfill', false);
        })
        ->groupBy('phone', 'country_code')
        ->get();

    $summary = [
        'users_processed' => 0,
        'existing_rows_updated' => 0,
        'generated_rows_created' => 0,
    ];

    $operation = function () use ($groups, $windowStart, $windowEnd, &$summary) {
        DB::table('starcom_intelligence')
            ->where('generated_from_backfill', true)
            ->delete();

        foreach ($groups as $group) {
            $summary['users_processed']++;

            $existingRows = DB::table('starcom_intelligence')
                ->where('phone', $group->phone)
                ->where(function ($query) use ($group) {
                    if (blank($group->country_code)) {
                        $query->whereNull('country_code')->orWhere('country_code', '');
                    } else {
                        $query->where('country_code', $group->country_code);
                    }
                })
                ->where(function ($query) {
                    $query->whereNull('generated_from_backfill')
                        ->orWhere('generated_from_backfill', false);
                })
                ->orderBy('id')
                ->get();

            $monthDates = collect();
            $cursor = $windowStart->copy();

            while ($cursor->lessThanOrEqualTo($windowEnd)) {
                $monthDates->push($cursor->copy());
                $cursor->addMonth();
            }

            if ($monthDates->isEmpty()) {
                $monthDates = collect([$windowEnd->copy()]);
            }

            $rowsToAssign = $existingRows->count();
            $targetDates = [];

            $firstInvoiceIndex = random_int(0, max(0, $monthDates->count() - 1));
            $eligibleMonths = $monthDates->slice($firstInvoiceIndex)->values();

            foreach ($eligibleMonths as $monthDate) {
                if (count($targetDates) >= $rowsToAssign) {
                    break;
                }
                $targetDates[] = $monthDate->copy()->day(random_int(1, 28))->toDateString();
            }

            while (count($targetDates) < $rowsToAssign) {
                $monthDate = $eligibleMonths->isNotEmpty() ? $eligibleMonths->random() : $monthDates->random();
                $targetDates[] = $monthDate->copy()->day(random_int(1, 28))->toDateString();
            }

            sort($targetDates);

            foreach ($existingRows as $index => $row) {
                DB::table('starcom_intelligence')
                    ->where('id', $row->id)
                    ->update([
                        'invoice_date' => $targetDates[$index] ?? $targetDates[array_key_last($targetDates)],
                        'updated_at'   => now(),
                    ]);
                $summary['existing_rows_updated']++;
            }

            $missingMonths = $eligibleMonths->filter(function (Carbon $monthDate) use ($targetDates) {
                $monthKey = $monthDate->format('Y-m');
                foreach ($targetDates as $assignedDate) {
                    if (str_starts_with($assignedDate, $monthKey)) {
                        return false;
                    }
                }
                return true;
            });

            $averageInvoiceAmount = max(1000, (float)($group->average_invoice_amount ?: 15000));
            $averageCreditAmount = max(0, (float)($group->average_credit_amount ?: 0));

            foreach ($missingMonths as $monthDate) {
                $invoiceAmount = round($averageInvoiceAmount * (random_int(80, 125) / 100), 2);
                $creditAmount = round(min($invoiceAmount, $averageCreditAmount * (random_int(70, 130) / 100)), 2);

                DB::table('starcom_intelligence')->insert([
                    'user_name'               => $group->user_name,
                    'phone_number'            => $group->phone_number ?: trim((($group->country_code ?: '') . ' ' . ($group->phone ?: ''))),
                    'country_code'            => $group->country_code,
                    'phone'                   => $group->phone,
                    'address'                 => $group->address,
                    'city'                    => $group->city,
                    'area'                    => $group->area,
                    'latitude'                => $group->latitude,
                    'longitude'               => $group->longitude,
                    'distribution_route'      => $group->distribution_route,
                    'invoice_date'            => $monthDate->copy()->day(random_int(1, 28))->toDateString(),
                    'generated_from_backfill' => true,
                    'invoice_amount'          => $invoiceAmount,
                    'cartona_credit_amount'   => $creditAmount,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
                $summary['generated_rows_created']++;
            }
        }
    };

    if ($dryRun) {
        $this->table(['Users to Process'], [[count($groups)]]);
        $this->warn('Dry run only. No data was changed.');
        return self::SUCCESS;
    }

    DB::transaction($operation);

    $this->table(
        ['Users Processed', 'Existing Rows Updated', 'Generated Rows Created'],
        [[
            $summary['users_processed'],
            $summary['existing_rows_updated'],
            $summary['generated_rows_created'],
        ]]
    );

    $this->info("Starcom invoice timeline backfill completed for {$windowStart->format('F Y')} to {$windowEnd->format('F Y')}.");

    return self::SUCCESS;
})->purpose('Backfill Starcom Intelligence invoice dates across the rolling invoice-history window and generate missing monthly history');

Artisan::command('customer-service:import-workbook {--file=} {--redistribute}', function (CustomerServiceLeadService $service) {
    $filePath = (string)($this->option('file') ?: dirname(base_path()) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'عملا التمويل.xlsx');

    $summary = $service->importWorkbook($filePath);

    $this->table(
        ['Matched Rows', 'Unmatched Rows', 'Sheet7 Updated', 'Total Leads'],
        [[
            $summary['matched_rows'],
            $summary['unmatched_rows'],
            $summary['sheet7_rows_updated'],
            $summary['leads_total'],
        ]]
    );

    if ($this->option('redistribute')) {
        $admin = User::role(RoleEnum::ADMIN)->orderBy('id')->first();
        if (!$admin) {
            $this->error('No admin user was found, so redistribution could not run automatically.');
            return self::FAILURE;
        }

        Auth::setUser($admin);
        $distribution = $service->redistribute();
        $this->table(
            ['Cycle', 'Agents', 'Per Agent', 'Assigned', 'Unassigned'],
            [[
                $distribution['cycle'],
                $distribution['agents_count'],
                $distribution['per_agent'],
                $distribution['assigned_count'],
                $distribution['remaining_unassigned_count'],
            ]]
        );
    }

    $this->info('Customer service CRM workbook import completed.');

    return self::SUCCESS;
})->purpose('Import customer service CRM history from عملا التمويل workbook');

Artisan::command('customer-service:redistribute', function (CustomerServiceLeadService $service) {
    $admin = User::role(RoleEnum::ADMIN)->orderBy('id')->first();
    if (!$admin) {
        $this->error('No admin user was found, so redistribution could not run.');
        return self::FAILURE;
    }

    Auth::setUser($admin);
    $distribution = $service->redistribute((int)$this->option('per-agent'));

    $this->table(
        ['Cycle', 'Agents', 'Assigned', 'Unassigned'],
        [[
            $distribution['cycle'],
            $distribution['agents_count'],
            $distribution['assigned_count'],
            $distribution['remaining_unassigned_count'],
        ]]
    );

    $this->info('Customer service CRM unassigned distribution completed.');

    return self::SUCCESS;
})->purpose('Distribute only unassigned new/no-answer/closed-phone customer service leads across the active team');

Artisan::command('customer-service:import-cairo-users {--file=} {--assign} {--per-agent=300}', function (CustomerServiceLeadService $service) {
    $filePath = (string)($this->option('file') ?: base_path('Cairo-users.xlsx'));
    $summary = $service->importCairoUsersWorkbook(
        $filePath,
        (bool) $this->option('assign'),
        (int) $this->option('per-agent')
    );

    $this->table(
        ['Rows', 'Created Users', 'Updated Users', 'Created Leads', 'Updated Leads', 'Skipped'],
        [[
            $summary['rows_total'],
            $summary['created_users'],
            $summary['updated_users'],
            $summary['created_leads'],
            $summary['updated_leads'],
            $summary['skipped_rows'],
        ]]
    );

    if (!empty($summary['assignment'])) {
        $this->table(
            ['Agents', 'Assigned', 'Unassigned', 'Per Agent'],
            [[
                $summary['assignment']['agents_count'] ?? 0,
                $summary['assignment']['assigned_count'] ?? 0,
                $summary['assignment']['remaining_unassigned_count'] ?? 0,
                $summary['assignment']['per_agent'] ?? (int) $this->option('per-agent'),
            ]]
        );
    }

    $this->info('Cairo users import completed successfully.');

    return self::SUCCESS;
})->purpose('Import Cairo users workbook, create customer accounts, create CRM leads, and assign them to the active customer service team');

Artisan::command('starcom:generate-finance-invoices
    {--approved-file= : Path to the approved clients workbook}
    {--user-ids= : Comma-separated approved customer user ids}
    {--limit= : Limit number of customers}
    {--invoice-date=2026-07-25 : Invoice date in YYYY-MM-DD}
    {--min-total=70000 : Minimum invoice total in EGP}
    {--batch= : Optional batch name}
    {--dry-run : Preview only without creating orders or PDFs}
    {--sugar-product-id=22 : Explicit product id for sugar}
    {--milk1-product-id=35 : Explicit product id for Bkhiero 1L}
    {--milk05-product-id=34 : Explicit product id for Bkhiero 0.5L}
    {--sugar-keywords=سكر,sugar : Fallback keywords for sugar}
    {--milk1-keywords=بخيرو,bkhiero,bkhiro,1l,1 l,1 لتر : Fallback keywords for Bkhiero 1L}
    {--milk05-keywords=بخيرو,bkhiero,bkhiro,0.5,0.5l,500,500ml,500 ml,نصف لتر : Fallback keywords for Bkhiero 0.5L}', function (BulkLenderInvoiceService $service) {
    $summary = $service->generate([
        'approved_file' => $this->option('approved-file'),
        'user_ids' => $this->option('user-ids'),
        'limit' => $this->option('limit'),
        'invoice_date' => $this->option('invoice-date'),
        'min_total' => $this->option('min-total'),
        'batch' => $this->option('batch'),
        'dry_run' => (bool) $this->option('dry-run'),
        'sugar_product_id' => $this->option('sugar-product-id'),
        'milk1_product_id' => $this->option('milk1-product-id'),
        'milk05_product_id' => $this->option('milk05-product-id'),
        'sugar_keywords' => $this->option('sugar-keywords'),
        'milk1_keywords' => $this->option('milk1-keywords'),
        'milk05_keywords' => $this->option('milk05-keywords'),
    ]);

    $this->table(
        ['Batch', 'Invoice Date', 'Min Total', 'Processed', 'Created', 'Skipped', 'Dry Run'],
        [[
            $summary['batch'],
            $summary['invoice_date'],
            $summary['min_total'],
            $summary['processed'],
            $summary['created'],
            $summary['skipped'],
            $summary['dry_run'] ? 'yes' : 'no',
        ]]
    );

    $this->table(
        ['Customer', 'Order Serial', 'Total', 'Status', 'PDF', 'Message'],
        collect($summary['rows'])->map(function ($row) {
            return [
                $row['customer_name'] ?? '',
                $row['order_serial_no'] ?? '',
                $row['total'] ?? '',
                $row['status'] ?? '',
                $row['pdf_path'] ?? '',
                $row['message'] ?? '',
            ];
        })->all()
    );

    if (!$summary['dry_run']) {
        if ($summary['pdf_directory']) {
            $this->info('PDF folder: ' . $summary['pdf_directory']);
        }
        if ($summary['csv_path']) {
            $this->info('Summary CSV: ' . $summary['csv_path']);
        }
        if ($summary['zip_path']) {
            $this->info('Batch ZIP: ' . $summary['zip_path']);
        }
    }

    return self::SUCCESS;
})->purpose('Create bulk POS invoices and PDFs for approved finance customers');
