<?php

declare(strict_types=1);

namespace PoradnikPro;

final class MonetizationService
{
    public static function resolveOfferCta(array $offer, string $leadFallbackUrl = ''): array
    {
        $rawUrl = trim((string) ($offer['affiliate_url'] ?? ''));
        $isDirect = $rawUrl !== ''
            && $rawUrl !== '#'
            && preg_match('#^(https?://|/)#i', $rawUrl) === 1;

        if ($isDirect) {
            return [
                'url' => $rawUrl,
                'is_fallback' => false,
            ];
        }

        if ($leadFallbackUrl === '') {
            $leadFallbackUrl = function_exists('home_url') ? (string) home_url('/uslugi/') : '/uslugi/';
        }

        return [
            'url' => $leadFallbackUrl,
            'is_fallback' => true,
        ];
    }

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
            $offers[$index]['_original_index'] = $index;
            $rating = (float) ($offer['rating'] ?? 0);
            $epc = (float) ($offer['epc'] ?? 0);
            $premiumBoost = ! empty($offer['premium']) ? 1.15 : 1.0;
            $positionBoost = max(0.8, 1.0 - ($index * 0.03));
            $offers[$index]['score'] = round((($rating * 0.7) + ($epc * 0.3)) * $premiumBoost * $positionBoost, 3);
        }

        usort($offers, static function (array $a, array $b): int {
            $scoreCmp = (($b['score'] ?? 0) <=> ($a['score'] ?? 0));
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }

            $premiumCmp = ((int) ! empty($b['premium'])) <=> ((int) ! empty($a['premium']));
            if ($premiumCmp !== 0) {
                return $premiumCmp;
            }

            $ratingCmp = ((float) ($b['rating'] ?? 0)) <=> ((float) ($a['rating'] ?? 0));
            if ($ratingCmp !== 0) {
                return $ratingCmp;
            }

            $epcCmp = ((float) ($b['epc'] ?? 0)) <=> ((float) ($a['epc'] ?? 0));
            if ($epcCmp !== 0) {
                return $epcCmp;
            }

            return ((int) ($a['_original_index'] ?? 0)) <=> ((int) ($b['_original_index'] ?? 0));
        });

        foreach ($offers as $index => $offer) {
            $offers[$index]['rank'] = $index + 1;
            $offers[$index]['badge'] = $index < 3 ? 'PREMIUM+' : 'PREMIUM';
            unset($offers[$index]['_original_index']);
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