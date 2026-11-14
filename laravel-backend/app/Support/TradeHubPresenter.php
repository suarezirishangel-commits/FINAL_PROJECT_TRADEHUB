<?php

namespace App\Support;

class TradeHubPresenter
{
    public static function price(float|string $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    public static function productImageUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        return preg_match('~^https?://~i', $path)
            ? $path
            : asset(ltrim($path, '/'));
    }

    public static function productTone(int $id): int
    {
        return $id % 5;
    }

    public static function productIcon(?string $categoryName): string
    {
        $name = mb_strtolower(trim((string) $categoryName));
        $icons = [
            '🥬' => ['produce', 'vegetable', 'fruit', 'farm', 'meat', 'seafood', 'rice'],
            '🥖' => ['bake', 'bread', 'cake', 'pastry', 'dessert'],
            '🧺' => ['craft', 'artisan', 'handmade', 'woven', 'souvenir'],
            '☕' => ['beverage', 'drink', 'coffee', 'tea', 'juice'],
            '🥫' => ['pantry', 'sauce', 'condiment', 'preserve', 'spice'],
            '📱' => ['electronic', 'gadget', 'phone', 'computer', 'tech'],
            '👕' => ['cloth', 'apparel', 'fashion', 'wear', 'shoe'],
            '🧴' => ['beauty', 'care', 'cosmetic', 'soap'],
            '🧸' => ['toy', 'game', 'hobby'],
            '🪴' => ['plant', 'garden', 'flower', 'seed'],
            '🐾' => ['pet', 'animal'],
            '📚' => ['book', 'stationery', 'school'],
            '🪑' => ['furniture', 'home', 'decor'],
        ];

        foreach ($icons as $icon => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $icon;
                }
            }
        }

        return '📦';
    }

    public static function orderStatusRank(string $status): int
    {
        return ['cancelled' => 0, 'pending' => 1, 'paid' => 2, 'completed' => 3][$status] ?? 0;
    }
}
