<?php

declare(strict_types=1);

namespace PoradnikPro;

final class MonetizationService
{
    public static function rankedOffers(int $postId = 0): array
    {
        $postId = $postId > 0 ? $postId : (int) get_the_ID();
        $raw = (string) get_post_meta($postId, 'pp_offers_json', true);

        $offers = self::fallbackOffers();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $offers = array_values(array_filter($decoded, static fn ($item): bool => is_array($item)));
            }
        }

        foreach ($offers as $index => $offer) {
            $rating = (float) ($offer['rating'] ?? 0);
            $epc = (float) ($offer['epc'] ?? 0);
            $premiumBoost = ! empty($offer['premium']) ? 1.15 : 1.0;
            $positionBoost = max(0.8, 1.0 - ($index * 0.03));
            $offers[$index]['score'] = round((($rating * 0.7) + ($epc * 0.3)) * $premiumBoost * $positionBoost, 3);
        }

        usort($offers, static fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        foreach ($offers as $index => $offer) {
            $offers[$index]['rank'] = $index + 1;
            $offers[$index]['badge'] = $index < 3 ? 'PREMIUM+' : 'PREMIUM';
        }

        return $offers;
    }

    public static function fallbackOffers(): array
    {
        return [
            [
                'name' => 'Oferta A',
                'rating' => 4.8,
                'epc' => 3.4,
                'price' => 'od 199 PLN',
                'affiliate_url' => '#',
                'premium' => true,
            ],
            [
                'name' => 'Oferta B',
                'rating' => 4.6,
                'epc' => 2.9,
                'price' => 'od 179 PLN',
                'affiliate_url' => '#',
                'premium' => true,
            ],
            [
                'name' => 'Oferta C',
                'rating' => 4.4,
                'epc' => 2.5,
                'price' => 'od 149 PLN',
                'affiliate_url' => '#',
                'premium' => false,
            ],
            [
                'name' => 'Oferta D',
                'rating' => 4.2,
                'epc' => 2.1,
                'price' => 'od 129 PLN',
                'affiliate_url' => '#',
                'premium' => false,
            ],
        ];
    }
}