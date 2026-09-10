<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Support\Collection;

class QuotePdfPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Quote $quote): array
    {
        $quote->loadMissing(['items', 'customer', 'user']);
        $user = $quote->user;

        $items = $quote->items ?? collect();
        $lineSubtotal = (float) $items->sum(fn (QuoteItem $item) => (float) $item->subtotal);
        $discountAmount = self::parseOrderDiscountAmount(
            $quote->order_discount_raw,
            $lineSubtotal
        );
        $shipping = max(0, (float) ($quote->shipping_cost ?? 0));
        $grandTotal = max(0, $lineSubtotal - $discountAmount + $shipping);

        $currency = trim((string) ($quote->currency ?? 'USD'));
        $customer = $quote->customer;
        $companyName = trim((string) ($user->company_name ?? ''));
        $companyLogoRelative = trim((string) ($user->company_logo ?? ''));
        $companyLogoPath = $companyLogoRelative !== '' && file_exists(storage_path('app/public/' . $companyLogoRelative))
            ? storage_path('app/public/' . $companyLogoRelative)
            : null;

        $platformLogoPath = file_exists(public_path('logo.png')) ? public_path('logo.png') : null;

        return [
            'quote' => $quote,
            'currency' => $currency,
            'formatMoney' => fn (float $amount): string => self::formatMoney($amount, $currency),
            'displayDate' => optional($quote->estimate_date)->format('d M Y')
                ?? optional($quote->created_at)->format('d M Y'),
            'lineItems' => self::mapLineItems($items),
            'lineSubtotal' => $lineSubtotal,
            'discountAmount' => $discountAmount,
            'discountRaw' => trim((string) ($quote->order_discount_raw ?? '')),
            'shipping' => $shipping,
            'shippingMethod' => trim((string) ($quote->shipping_method ?? '')),
            'grandTotal' => $grandTotal,
            'customerName' => self::customerDisplayName($customer),
            'customerEmail' => trim((string) ($customer->email ?? '')),
            'customerPhone' => trim((string) ($customer->phone ?? '')),
            'customerAddress' => self::resolveCustomerAddress($quote, $customer),
            'companyName' => $companyName,
            'companyLogoPath' => $companyLogoPath,
            'platformLogoPath' => $platformLogoPath,
            'projectName' => trim((string) ($quote->name ?? '')),
            'projectSubtitle' => trim((string) ($quote->project_name ?? '')),
            'projectAddress' => trim((string) ($quote->project_address ?? '')),
            'notes' => trim((string) ($quote->notes ?? '')),
            'terms' => trim((string) ($quote->terms_and_conditions ?? '')),
            'hasDiscount' => $discountAmount > 0,
            'hasShipping' => $shipping > 0,
        ];
    }

    public static function formatMoney(float $amount, string $currency): string
    {
        $code = strtoupper(trim($currency));

        $symbol = match ($code) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'PKR' => 'Rs ',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            default => $code !== '' ? $code.' ' : '$',
        };

        return $symbol.number_format($amount, 2);
    }

    public static function parseOrderDiscountAmount(?string $raw, float $subtotal): float
    {
        $text = trim((string) $raw);
        if ($text === '' || $subtotal <= 0) {
            return 0.0;
        }

        if (str_ends_with($text, '%')) {
            $pct = (float) str_replace(['%', ',', ' '], '', $text);
            if ($pct <= 0) {
                return 0.0;
            }

            return min($subtotal, round($subtotal * ($pct / 100), 2));
        }

        $flat = (float) preg_replace('/[^0-9.]/', '', $text);

        return min($subtotal, max(0, $flat));
    }

    private static function isPlaceholderLineItem(QuoteItem $item): bool
    {
        $desc = strtolower(trim((string) ($item->description ?? '')));
        $qty = (int) ($item->quantity ?? 0);
        $sub = (float) ($item->subtotal ?? 0);

        return $qty === 0
            && abs($sub) < 0.005
            && ($desc === '' || $desc === '-' || $desc === 'service' || $desc === 'product');
    }

    /**
     * @param  Collection<int, QuoteItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function mapLineItems(Collection $items): array
    {
        return $items
            ->reject(fn (QuoteItem $item) => self::isPlaceholderLineItem($item))
            ->values()
            ->map(function (QuoteItem $item, int $index) {
            $type = strtolower((string) ($item->item_type ?? ''));
            $isService = $type === 'service'
                || ($type !== 'product' && empty($item->product_variation_color_id));

            $description = trim((string) ($item->description ?? ''));
            if ($description === '') {
                $description = $isService ? 'Service' : 'Product';
            }

            return [
                'index' => $index + 1,
                'description' => $description,
                'notes' => trim((string) ($item->item_notes ?? '')),
                'quantity' => (int) ($item->quantity ?? 0),
                'unit_price' => (float) ($item->unit_price ?? 0),
                'subtotal' => (float) ($item->subtotal ?? 0),
                'is_service' => $isService,
                'type_label' => $isService ? 'Service' : 'Product',
            ];
        })->all();
    }

    private static function customerDisplayName(?Customer $customer): string
    {
        if (! $customer) {
            return 'Customer';
        }

        $company = trim((string) ($customer->company_name ?? ''));
        if ($company !== '') {
            return $company;
        }

        $person = trim(trim((string) ($customer->first_name ?? '')).' '.trim((string) ($customer->last_name ?? '')));

        return $person !== '' ? $person : 'Customer';
    }

    private static function resolveCustomerAddress(Quote $quote, ?Customer $customer): string
    {
        $saved = trim((string) ($quote->customer_address ?? ''));
        if ($saved !== '') {
            return $saved;
        }

        if (! $customer) {
            return '';
        }

        return trim(implode("\n", array_filter([
            $customer->billing_street_1,
            $customer->billing_street_2,
            trim(implode(', ', array_filter([$customer->billing_city, $customer->billing_state]))),
            trim(implode(' ', array_filter([$customer->billing_zip, $customer->billing_country]))),
        ])));
    }
}
