<?php

namespace App\Integrations\Contracts;

use Illuminate\Http\Request;

/**
 * Contract for marketplace integrations that expose a mobile-API surface
 * (the operator's mobile app calls /api/marketplace/{platform}/orders to
 * see paginated order lists and order detail).
 *
 * The core MarketplaceOrderController routes by platform slug; each
 * platform's data-shaping (status maps, search columns, JSON transform)
 * lives entirely inside the extension that owns that platform.
 *
 * Marketplaces that don't ship a mobile API (OpenCart, Venta, Pedallion)
 * simply don't implement this and become ineligible for the
 * /api/marketplace/* routes — the controller returns 404 for unknown slugs.
 */
interface MobileMarketplaceProvider
{
    /**
     * The platform slug this provider handles. Must match the {platform}
     * URL segment, e.g. 'lazada', 'shopee', 'tiktok'.
     */
    public function mobilePlatformSlug(): string;

    /**
     * Return the JSON-ready response for GET /api/marketplace/{platform}/orders.
     * Expected keys: data, current_page, last_page, total, tab_counts,
     * pending_sub_counts.
     *
     * @return array<string, mixed>
     */
    public function mobileIndexResponse(Request $request): array;

    /**
     * Return the JSON-ready response for GET /api/marketplace/{platform}/orders/{id},
     * or null when the id doesn't resolve to one of this platform's orders.
     *
     * @return array<string, mixed>|null
     */
    public function mobileShowResponse(int $id): ?array;
}
