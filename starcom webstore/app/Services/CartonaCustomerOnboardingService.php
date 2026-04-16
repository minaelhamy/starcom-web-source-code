<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Models\Address;
use App\Models\CartonaCustomer;
use App\Models\CartonaOrder;
use App\Models\StarcomIntelligenceCustomer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CartonaCustomerOnboardingService
{
    public function pullOrders(): array
    {
        $baseUrl = rtrim((string) config('cartona.base_url'), '/');
        $token = (string) config('cartona.authorization_token');

        if ($baseUrl === '' || $token === '') {
            throw new \RuntimeException('Cartona API credentials are not configured.');
        }

        $response = Http::timeout((int) config('cartona.timeout', 30))
            ->withHeaders([
                'AuthorizationToken' => $token,
                'Accept' => 'application/json',
            ])
            ->get($baseUrl . '/api/v1/orders/pull-orders');

        $response->throw();

        return $response->json() ?: [];
    }

    public function syncOrders(array $orders): array
    {
        $summary = [
            'orders_received' => count($orders),
            'customers_synced' => 0,
            'orders_synced' => 0,
            'items_synced' => 0,
            'users_created' => 0,
        ];

        foreach ($orders as $orderPayload) {
            $result = $this->syncOrder($orderPayload);
            $summary['customers_synced'] += $result['customer_synced'];
            $summary['orders_synced'] += $result['order_synced'];
            $summary['items_synced'] += $result['items_synced'];
            $summary['users_created'] += $result['user_created'];
        }

        return $summary;
    }

    public function syncOrder(array $orderPayload): array
    {
        return DB::transaction(function () use ($orderPayload) {
            $retailer = $orderPayload['retailer'] ?? [];
            $primaryPhoneParts = $this->normalizePhoneParts($retailer['retailer_number'] ?? null);
            $secondaryPhoneParts = $this->normalizePhoneParts($retailer['retailer_number2'] ?? null);
            $location = $this->extractLocation($retailer['retailer_address'] ?? null);

            $userCreated = 0;
            $user = $this->findOrCreateUser(
                $retailer['retailer_name'] ?? 'عميل كارتونا',
                $primaryPhoneParts['country_code'],
                $primaryPhoneParts['phone']
            );

            if ($user->wasRecentlyCreated) {
                $userCreated = 1;
            }

            $customer = CartonaCustomer::updateOrCreate(
                [
                    'retailer_code' => $retailer['retailer_code'] ?? null,
                    'retailer_number' => $retailer['retailer_number'] ?? null,
                ],
                [
                    'user_id' => $user->id,
                    'retailer_name' => $retailer['retailer_name'] ?? $user->name,
                    'retailer_number2' => $retailer['retailer_number2'] ?? null,
                    'country_code' => $primaryPhoneParts['country_code'],
                    'phone' => $primaryPhoneParts['phone'],
                    'secondary_country_code' => $secondaryPhoneParts['country_code'],
                    'secondary_phone' => $secondaryPhoneParts['phone'],
                    'retailer_address' => $retailer['retailer_address'] ?? null,
                    'address_notes' => $retailer['address_notes'] ?? null,
                    'city' => $location['city'],
                    'state' => $location['state'],
                    'country' => $location['country'],
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'google_map_url' => $location['google_map_url'],
                    'distribution_route_code' => $orderPayload['distribution_route_code'] ?? null,
                    'supplier_code' => $orderPayload['supplier_code'] ?? null,
                    'latest_payload' => $orderPayload,
                    'first_order_at' => $this->resolveFirstOrderAt($orderPayload),
                    'last_order_at' => now(),
                    'last_synced_at' => now(),
                ]
            );

            $this->syncPrimaryAddress($user, $customer, $retailer, $orderPayload, $location);

            $cartonaOrder = CartonaOrder::updateOrCreate(
                ['hashed_id' => $orderPayload['hashed_id']],
                [
                    'cartona_customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'supplier_code' => $orderPayload['supplier_code'] ?? null,
                    'distribution_route_code' => $orderPayload['distribution_route_code'] ?? null,
                    'status' => $orderPayload['status'] ?? null,
                    'delivery_day' => $orderPayload['delivery_day'] ?? null,
                    'estimated_delivery_day' => $orderPayload['estimated_delivery_day'] ?? null,
                    'cancellation_reason' => $orderPayload['cancellation_reason'] ?? null,
                    'return_reason' => $orderPayload['return_reason'] ?? null,
                    'total_price' => $orderPayload['total_price'] ?? 0,
                    'cartona_credit' => $orderPayload['cartona_credit'] ?? 0,
                    'installment_cost' => $orderPayload['installment_cost'] ?? 0,
                    'wallet_top_up' => $orderPayload['wallet_top_up'] ?? 0,
                    'note_message' => $orderPayload['note_message'] ?? null,
                    'payload' => $orderPayload,
                    'pulled_at' => now(),
                ]
            );

            $itemsSynced = 0;
            foreach (($orderPayload['order_details'] ?? []) as $itemPayload) {
                $cartonaOrder->items()->updateOrCreate(
                    ['cartona_order_detail_id' => $itemPayload['id'] ?? null],
                    [
                        'internal_product_id' => $itemPayload['internal_product_id'] ?? null,
                        'product_name' => $itemPayload['product_name'] ?? null,
                        'amount' => $itemPayload['amount'] ?? 0,
                        'selling_price' => $itemPayload['selling_price'] ?? 0,
                        'applied_supplier_discount' => $itemPayload['applied_supplier_discount'] ?? 0,
                        'applied_cartona_discount' => $itemPayload['applied_cartona_discount'] ?? 0,
                        'comment' => $itemPayload['comment'] ?? null,
                        'payload' => $itemPayload,
                    ]
                );
                $itemsSynced++;
            }

            $this->syncIntelligenceCustomer($user, $customer);

            return [
                'customer_synced' => 1,
                'order_synced' => 1,
                'items_synced' => $itemsSynced,
                'user_created' => $userCreated,
            ];
        });
    }

    protected function findOrCreateUser(string $name, ?string $countryCode, ?string $phone): User
    {
        $user = User::query()
            ->where('country_code', $countryCode)
            ->where('phone', $phone)
            ->first();

        if ($user) {
            if (blank($user->name)) {
                $user->name = $name;
                $user->save();
            }

            if (!$user->hasRole(RoleEnum::CUSTOMER)) {
                $user->assignRole(RoleEnum::CUSTOMER);
            }

            return $user;
        }

        $user = User::create([
            'name' => $name,
            'username' => Str::slug($name) . rand(1000, 999999),
            'email' => null,
            'phone' => $phone,
            'country_code' => $countryCode,
            'password' => Str::password(16),
        ]);
        $user->assignRole(RoleEnum::CUSTOMER);

        return $user;
    }

    protected function syncPrimaryAddress(
        User $user,
        CartonaCustomer $customer,
        array $retailer,
        array $orderPayload,
        array $location
    ): void {
        Address::updateOrCreate(
            [
                'user_id' => $user->id,
                'phone' => $customer->phone,
            ],
            [
                'full_name' => $retailer['retailer_name'] ?? $user->name,
                'email' => null,
                'country_code' => $customer->country_code ?: config('cartona.default_country_code'),
                'country' => $location['country'] ?: config('cartona.country'),
                'address' => $retailer['retailer_address'] ?? '',
                'state' => $location['state'],
                'city' => $location['city'],
                'zip_code' => null,
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'google_map_url' => $location['google_map_url'],
                'route_code' => $orderPayload['distribution_route_code'] ?? null,
                'route_name' => null,
                'address_notes' => $retailer['address_notes'] ?? null,
                'source' => 'cartona',
            ]
        );
    }

    protected function syncIntelligenceCustomer(User $user, CartonaCustomer $customer): void
    {
        $orders = $customer->orders()->get();
        $ordersCount = $orders->count();
        $totalPurchaseValue = (float) $orders->sum('total_price');

        StarcomIntelligenceCustomer::updateOrCreate(
            [
                'source' => 'cartona',
                'external_customer_code' => $customer->retailer_code,
                'phone' => $customer->phone,
            ],
            [
                'user_id' => $user->id,
                'cartona_customer_id' => $customer->id,
                'full_name' => $customer->retailer_name,
                'country_code' => $customer->country_code,
                'secondary_phone' => $customer->secondary_phone,
                'address' => $customer->retailer_address,
                'address_notes' => $customer->address_notes,
                'city' => $customer->city,
                'state' => $customer->state,
                'country' => $customer->country ?: config('cartona.country'),
                'latitude' => $customer->latitude,
                'longitude' => $customer->longitude,
                'google_map_url' => $customer->google_map_url,
                'route_code' => $customer->distribution_route_code,
                'route_name' => null,
                'supplier_code' => $customer->supplier_code,
                'orders_count' => $ordersCount,
                'total_purchase_value' => $totalPurchaseValue,
                'average_order_value' => $ordersCount > 0 ? $totalPurchaseValue / $ordersCount : 0,
                'first_order_at' => $customer->first_order_at,
                'last_order_at' => $customer->last_order_at,
                'source_payload' => $customer->latest_payload,
                'metrics_payload' => [
                    'onboarding_status' => 'synced_from_cartona',
                    'has_secondary_phone' => !blank($customer->secondary_phone),
                ],
                'last_synced_at' => now(),
            ]
        );
    }

    protected function normalizePhoneParts(?string $rawPhone): array
    {
        $value = trim((string) $rawPhone);
        if ($value === '') {
            return ['country_code' => config('cartona.default_country_code'), 'phone' => null];
        }

        $normalized = preg_replace('/\s+/', '', $value);
        $normalized = str_replace('-', '', $normalized);

        if (Str::startsWith($normalized, '0020')) {
            return ['country_code' => '+20', 'phone' => substr($normalized, 4)];
        }

        if (Str::startsWith($normalized, '+20')) {
            return ['country_code' => '+20', 'phone' => substr($normalized, 3)];
        }

        if (Str::startsWith($normalized, '20') && strlen($normalized) > 10) {
            return ['country_code' => '+20', 'phone' => substr($normalized, 2)];
        }

        if (Str::startsWith($normalized, '0')) {
            return ['country_code' => config('cartona.default_country_code'), 'phone' => $normalized];
        }

        return ['country_code' => config('cartona.default_country_code'), 'phone' => $normalized];
    }

    protected function extractLocation(?string $address): array
    {
        $cleanAddress = trim((string) $address);
        $segments = collect(preg_split('/[,،]/u', $cleanAddress ?: '') ?: [])
            ->map(fn ($segment) => trim($segment))
            ->filter()
            ->values();

        return [
            'city' => $segments->count() > 0 ? $segments->last() : null,
            'state' => null,
            'country' => config('cartona.country'),
            'latitude' => null,
            'longitude' => null,
            'google_map_url' => null,
        ];
    }

    protected function resolveFirstOrderAt(array $orderPayload): ?string
    {
        return $orderPayload['delivery_day']
            ?? $orderPayload['estimated_delivery_day']
            ?? now()->toDateString();
    }
}
