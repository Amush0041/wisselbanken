<?php

namespace App\Services;

use App\Models\UserProduct;
use App\Models\UserService;
use Illuminate\Support\Str;

/**
 * When an estimate is saved, upsert rows into user_products / user_services for the owning user.
 */
class PersistUserCatalogFromQuoteItemPayload
{
    public function sync(int $userId, array $items): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pvcRaw = $item['product_variation_color_id'] ?? null;
            $pvcId = ($pvcRaw !== null && $pvcRaw !== '') ? (int) $pvcRaw : null;
            $lineType = $this->normalizeLineType($item['type'] ?? null, $pvcId);

            $description = trim((string) ($item['description'] ?? ''));
            $rate = (float) ($item['rate'] ?? 0);
            $notes = isset($item['notes']) ? trim((string) $item['notes']) : null;
            $notes = $notes === '' ? null : $notes;

            if ($lineType === 'product') {
                $name = $this->firstLine($description) ?: 'Product';
                $match = ['user_id' => $userId];
                if ($pvcId) {
                    $match['product_variation_color_id'] = $pvcId;
                } else {
                    // Custom product row (not linked to catalog variation) still belongs to products module.
                    $match['product_variation_color_id'] = null;
                    $match['name'] = Str::limit($name, 500);
                    $match['description'] = $description !== '' ? $description : null;
                }

                UserProduct::query()->updateOrCreate($match, [
                    'name' => Str::limit($name, 500),
                    'description' => $description !== '' ? $description : null,
                    'default_unit_price' => $rate,
                    'sell_price' => $rate,
                    'item_notes' => $notes,
                ]);

                continue;
            }

            if ($description === '' && $rate <= 0) {
                continue;
            }

            $hash = hash('sha256', $userId."\n".$description);
            $title = Str::limit($this->firstLine($description) ?: 'Service', 500);

            UserService::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'content_hash' => $hash,
                ],
                [
                    'title' => $title,
                    'service_name' => $title,
                    'description' => $description !== '' ? $description : null,
                    'default_unit_price' => $rate,
                    'item_notes' => $notes,
                ]
            );
        }
    }

    private function firstLine(string $text): string
    {
        $parts = preg_split("/\R/u", $text, 2);

        return trim($parts[0] ?? '');
    }

    private function normalizeLineType($typeRaw, ?int $pvcId): string
    {
        if ($pvcId) {
            return 'product';
        }

        $type = strtolower(trim((string) ($typeRaw ?? '')));
        if ($type === 'service') {
            return 'service';
        }

        return 'product';
    }
}
