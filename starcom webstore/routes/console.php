<?php

use App\Services\CartonaCustomerOnboardingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
