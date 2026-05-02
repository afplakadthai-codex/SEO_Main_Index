<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

// Listing reviews — safe load
$_bvReviewLib = __DIR__ . '/includes/listing_reviews.php';
if (is_file($_bvReviewLib)) {
    require_once $_bvReviewLib;
}
unset($_bvReviewLib);

/**
 * Return the review stars HTML for a listing card.
 * Results are cached per listing ID so multiple card loops pay one query each.
 * Returns empty string if the table is unavailable or there are no reviews.
 */
if (!function_exists('bv_home_card_review_html')) {
    function bv_home_card_review_html(int $listingId): string
    {
        static $cache = [];
        if ($listingId <= 0) {
            return '';
        }
        if (array_key_exists($listingId, $cache)) {
            return $cache[$listingId];
        }
        if (!function_exists('bv_listing_review_summary_for_listing')
            || !function_exists('bv_listing_review_table_exists')
            || !bv_listing_review_table_exists('listing_reviews')) {
            return $cache[$listingId] = '';
        }
        try {
            $s     = bv_listing_review_summary_for_listing($listingId);
            $count = (int) ($s['total_reviews'] ?? 0);
            $avg   = (float) ($s['avg_rating']   ?? 0);
        } catch (Throwable $e) {
            return $cache[$listingId] = '';
        }
        if ($count <= 0) {
            return $cache[$listingId] = '';
        }
        $filled  = max(0, min(5, (int) round($avg)));
        $stars   = str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);
        $avgFmt  = htmlspecialchars(number_format($avg, 1), ENT_QUOTES, 'UTF-8');
        $label   = htmlspecialchars($count . ' review' . ($count === 1 ? '' : 's'), ENT_QUOTES, 'UTF-8');
        $html    = '<div class="listing-card-rating">'
                 . '<span class="listing-card-stars" aria-hidden="true">' . $stars . '</span>'
                 . '<span class="listing-card-avg">' . $avgFmt . '</span>'
                 . '<span class="listing-card-count">(' . $label . ')</span>'
                 . '</div>';
        return $cache[$listingId] = $html;
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pageTitle = 'Betta Fish for Sale Worldwide | Premium Thai Betta Marketplace | Bettavaro';
$metaDescription = 'Buy premium betta fish from trusted Thai breeders on Bettavaro. Explore rare Halfmoon, Koi, Giant, Fancy, wild type, auction, fixed-price, and best-offer bettas ready for collectors worldwide.';
$canonicalUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') . '/' : 'https://www.bettavaro.com/';
$metaRobots = 'index,follow,max-image-preview:large';
$ogImage = 'https://www.bettavaro.com/assets/img/og-home.jpg';
$homepageJsonLd = json_encode([
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Bettavaro',
        'url' => $canonicalUrl,
        'description' => $metaDescription,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => 'https://www.bettavaro.com/listings.php?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Bettavaro',
        'url' => 'https://www.bettavaro.com/',
        'logo' => 'https://www.bettavaro.com/assets/img/logo.png',
        'sameAs' => [],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$bodyClass = 'home-page';

if (!function_exists('bv_e')) {
    function bv_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bv_table_columns')) {
    function bv_table_columns(PDO $pdo, string $table): array
    {
        static $cache = [];

        $key = strtolower($table);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
            $cols = [];
            if ($stmt) {
                foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                    $field = (string) ($row['Field'] ?? '');
                    if ($field !== '') {
                        $cols[$field] = true;
                    }
                }
            }
            $cache[$key] = $cols;
            return $cols;
        } catch (Throwable $e) {
            $cache[$key] = [];
            return [];
        }
    }
}

if (!function_exists('bv_table_exists')) {
    function bv_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];

        $key = strtolower(trim($table));
        if ($key === '') {
            return false;
        }

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            $cache[$key] = (bool) $stmt->fetchColumn();
            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;
            return false;
        }
    }
}

if (!function_exists('bv_public_listing_url')) {
    function bv_public_listing_url(array $row): string
    {
        $slug = trim((string) ($row['slug'] ?? ''));
        $id = (int) ($row['id'] ?? 0);

        $saleFormat = strtolower(trim((string) ($row['sale_format'] ?? 'fixed')));
        $isAuction = ($saleFormat === 'auction');

        if ($isAuction) {
            if ($slug !== '') {
                return '/auction.php?slug=' . rawurlencode($slug);
            }
            return $id > 0 ? '/auction.php?id=' . $id : '/auctions.php';
        }

        if ($slug !== '') {
            return '/listing.php?slug=' . rawurlencode($slug);
        }

        return $id > 0 ? '/listing.php?id=' . $id : '/listings.php';
    }
}

if (!function_exists('bv_listing_detail_url')) {
    function bv_listing_detail_url(array $row): string
    {
        $slug = trim((string) ($row['slug'] ?? ''));
        $id = (int) ($row['id'] ?? 0);

        if ($slug !== '') {
            return '/listing.php?slug=' . rawurlencode($slug);
        }

        return $id > 0 ? '/listing.php?id=' . $id : '/listings.php';
    }
}

if (!function_exists('bv_resolve_image_url')) {
    function bv_resolve_image_url(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        if (strpos($path, 'data:image/') === 0) {
            return $path;
        }

        if ($path[0] === '/') {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('bv_effective_sale_status')) {
    function bv_effective_sale_status(?string $saleStatus, ?string $status): string
    {
        $saleStatus = strtolower(trim((string) $saleStatus));
        $status = strtolower(trim((string) $status));

        if (in_array($saleStatus, ['available', 'reserved', 'sold'], true)) {
            return $saleStatus;
        }
        if (in_array($status, ['reserved', 'sold'], true)) {
            return $status;
        }
        if (in_array($status, ['active', 'available', 'published'], true)) {
            return 'available';
        }

        return $status !== '' ? $status : 'unknown';
    }
}

if (!function_exists('bv_status_badge')) {
    function bv_status_badge(string $status): array
    {
        $status = strtolower(trim($status));

        switch ($status) {
            case 'reserved':
                return ['Reserved', '#c76f30', 'rgba(199,111,48,0.12)'];
            case 'sold':
                return ['Sold', '#7fb4ff', 'rgba(54,108,214,0.18)'];
            case 'available':
            case 'active':
            case 'published':
                return ['Available', '#7fe4a6', 'rgba(18,112,66,0.20)'];
            default:
                return [ucfirst($status !== '' ? $status : 'Unknown'), '#d3d7d4', 'rgba(255,255,255,0.08)'];
        }
    }
}

if (!function_exists('bv_sale_format_badge')) {
    function bv_sale_format_badge(array $row): array
    {
        $saleFormat = strtolower(trim((string) ($row['sale_format'] ?? 'fixed')));
        $auctionStatus = strtolower(trim((string) ($row['auction_status'] ?? '')));
        $auctionEndsAt = trim((string) ($row['auction_ends_at'] ?? ''));

        $isAuction = ($saleFormat === 'auction');

        if ($isAuction) {
            if ($auctionStatus === 'scheduled') {
                return ['Scheduled Auction', '#ffd37a', 'rgba(255,211,122,0.14)'];
            }
            if (in_array($auctionStatus, ['ended', 'awaiting_payment', 'paid', 'closed', 'cancelled', 'expired'], true)) {
                return [ucwords(str_replace('_', ' ', $auctionStatus)), '#c8d2db', 'rgba(200,210,219,0.14)'];
            }
            if ($auctionEndsAt !== '' && strtotime($auctionEndsAt) !== false && strtotime($auctionEndsAt) < time()) {
                return ['Auction Ended', '#ffcf7a', 'rgba(255,187,0,0.14)'];
            }
            return ['Live Auction', '#ffb86b', 'rgba(255,140,0,0.16)'];
        }

        if ($saleFormat === 'offer') {
            return ['Best Offer', '#93c5fd', 'rgba(59,130,246,0.16)'];
        }

        return ['Fixed Price', '#8de7b1', 'rgba(18,112,66,0.20)'];
    }
}

if (!function_exists('bv_is_live_auction')) {
    function bv_is_live_auction(array $row): bool
    {
        $saleFormat = strtolower(trim((string) ($row['sale_format'] ?? 'fixed')));
        if ($saleFormat !== 'auction') {
            return false;
        }

        $auctionStatus = strtolower(trim((string) ($row['auction_status'] ?? '')));
        if (in_array($auctionStatus, ['scheduled', 'ended', 'awaiting_payment', 'paid', 'closed', 'cancelled', 'expired'], true)) {
            return $auctionStatus === 'live';
        }

        $startsAt = trim((string) ($row['auction_starts_at'] ?? ''));
        $endsAt = trim((string) ($row['auction_ends_at'] ?? ''));
        $now = time();

        if ($startsAt !== '' && strtotime($startsAt) !== false && $now < strtotime($startsAt)) {
            return false;
        }
        if ($endsAt !== '' && strtotime($endsAt) !== false && $now > strtotime($endsAt)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('bv_home_current_user')) {
    function bv_home_current_user(): ?array
    {
        if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
            return $_SESSION['user'];
        }

        $legacyId = 0;
        if (!empty($_SESSION['user_id'])) {
            $legacyId = (int) $_SESSION['user_id'];
        } elseif (!empty($_SESSION['member_id'])) {
            $legacyId = (int) $_SESSION['member_id'];
        }

        if ($legacyId <= 0) {
            return null;
        }

        return [
            'id' => $legacyId,
            'first_name' => (string) ($_SESSION['user_first_name'] ?? ''),
            'last_name' => (string) ($_SESSION['user_last_name'] ?? ''),
            'email' => (string) ($_SESSION['user_email'] ?? $_SESSION['member_email'] ?? ''),
            'role' => (string) ($_SESSION['user_role'] ?? $_SESSION['member_role'] ?? 'user'),
            'seller_application_status' => (string) ($_SESSION['seller_application_status'] ?? ''),
        ];
    }
}

if (!function_exists('bv_home_user_name')) {
    function bv_home_user_name(?array $user): string
    {
        if (!$user) {
            return '';
        }

        $full = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        if ($full !== '') {
            return $full;
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return 'Member';
    }
}

if (!function_exists('bv_home_load_active_seller_discounts')) {
    function bv_home_load_active_seller_discounts(PDO $pdo, array $sellerIds): array
    {
        $sellerIds = array_values(array_unique(array_filter(array_map('intval', $sellerIds), static function ($id) {
            return $id > 0;
        })));

        if (!$sellerIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));

        $sql = "
            SELECT
                id,
                seller_id,
                discount_percent,
                is_active,
                start_at,
                end_at,
                NOW() AS db_now
            FROM seller_discounts
            WHERE seller_id IN ($placeholders)
              AND is_active = 1
            ORDER BY discount_percent DESC, id DESC
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($sellerIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $map = [];
            $nowTs = time();

            foreach ($rows as $row) {
                $sellerId = (int)($row['seller_id'] ?? 0);
                if ($sellerId <= 0 || isset($map[$sellerId])) {
                    continue;
                }

                $percent = (float)($row['discount_percent'] ?? 0);
                if ($percent <= 0) {
                    continue;
                }

                $startAt = trim((string)($row['start_at'] ?? ''));
                $endAt   = trim((string)($row['end_at'] ?? ''));

                if ($startAt !== '' && strtotime($startAt) !== false && strtotime($startAt) > $nowTs) {
                    continue;
                }

                if ($endAt !== '' && strtotime($endAt) !== false && strtotime($endAt) < $nowTs) {
                    continue;
                }

                if ($percent > 100) {
                    $percent = 100.0;
                }

                $map[$sellerId] = [
                    'discount_percent' => $percent,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'is_active' => (int)($row['is_active'] ?? 0),
                    'id' => (int)($row['id'] ?? 0),
                    'db_now' => (string)($row['db_now'] ?? ''),
                ];
            }

            return $map;
        } catch (Throwable $e) {
            return [
                '__error__' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ];
        }
    }
}

if (!function_exists('bv_home_discounted_price')) {
    function bv_home_discounted_price(float $price, float $discountPercent): float
    {
        if ($price < 0) {
            $price = 0.0;
        }
        if ($discountPercent < 0) {
            $discountPercent = 0.0;
        }
        if ($discountPercent > 100) {
            $discountPercent = 100.0;
        }

        $final = $price - (($price * $discountPercent) / 100);
        if ($final < 0) {
            $final = 0.0;
        }

        return round($final, 2);
    }
}

if (!function_exists('bv_home_format_money')) {
    function bv_home_format_money($amount, string $currency = 'USD'): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            return '';
        }

        if (function_exists('money')) {
            return money((float) $amount, $currency);
        }

        return number_format((float) $amount, 2) . ' ' . $currency;
    }
}

if (!function_exists('bv_home_attach_discount_to_listing')) {
    function bv_home_attach_discount_to_listing(array $item, array $discountMap): array
    {
        $sellerId = (int) ($item['seller_id'] ?? 0);
        $saleFormat = strtolower(trim((string) ($item['sale_format'] ?? 'fixed')));
        $isAuction = ($saleFormat === 'auction');

        $price = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null;
        $currency = (string) ($item['currency'] ?? 'USD');

        $item['_discount_percent'] = 0.0;
        $item['_discounted_price'] = $price;
        $item['_has_discount'] = false;
        $item['_original_price_text'] = $price !== null ? bv_home_format_money($price, $currency) : '';
        $item['_discounted_price_text'] = $price !== null ? bv_home_format_money($price, $currency) : '';
        $item['_discount_badge_text'] = '';

        if ($isAuction || $saleFormat === 'offer') {
            return $item;
        }

        if ($sellerId <= 0 || $price === null || $price <= 0) {
            return $item;
        }

        if (empty($discountMap[$sellerId])) {
            return $item;
        }

        $discountPercent = (float) ($discountMap[$sellerId]['discount_percent'] ?? 0);
        if ($discountPercent <= 0) {
            return $item;
        }

        $discountedPrice = bv_home_discounted_price($price, $discountPercent);

        $item['_discount_percent'] = $discountPercent;
        $item['_discounted_price'] = $discountedPrice;
        $item['_has_discount'] = $discountedPrice < $price;
        $item['_original_price_text'] = bv_home_format_money($price, $currency);
        $item['_discounted_price_text'] = bv_home_format_money($discountedPrice, $currency);
        $item['_discount_badge_text'] = '-' . rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.') . '%';

        return $item;
    }
}

if (!function_exists('bv_home_sort_fixed_listings')) {
    function bv_home_sort_fixed_listings(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            // Primary: marketplace ranking score (higher = better).
            // ranking_score is populated by the LEFT JOIN to listing_ranking_scores
            // in the main homepage query; defaults to 0 when no score exists.
            $aRank = (float) ($a['ranking_score'] ?? 0);
            $bRank = (float) ($b['ranking_score'] ?? 0);
            if ($aRank !== $bRank) {
                return $bRank <=> $aRank;
            }

            // Tiebreaker 1: listings with an active seller discount surface first.
            $aHasDiscount = !empty($a['_has_discount']) ? 1 : 0;
            $bHasDiscount = !empty($b['_has_discount']) ? 1 : 0;
            if ($aHasDiscount !== $bHasDiscount) {
                return $bHasDiscount <=> $aHasDiscount;
            }

            // Tiebreaker 2: featured flag.
            $aFeatured = (int) ($a['featured'] ?? 0);
            $bFeatured = (int) ($b['featured'] ?? 0);
            if ($aFeatured !== $bFeatured) {
                return $bFeatured <=> $aFeatured;
            }

            // Tiebreaker 3: higher discount percentage first.
            $aDiscount = (float) ($a['_discount_percent'] ?? 0);
            $bDiscount = (float) ($b['_discount_percent'] ?? 0);
            if ($aDiscount !== $bDiscount) {
                return $bDiscount <=> $aDiscount;
            }

            // Tiebreaker 4: newest listing first.
            $aCreated = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $bCreated = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
            if ($aCreated !== $bCreated) {
                return $bCreated <=> $aCreated;
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        return $items;
    }
}

function bv_home_load_seller_profiles(PDO $pdo, array $sellerIds): array
{
    $profileCols = bv_table_columns($pdo, 'seller_applications');
    if (!$profileCols) {
        return [];
    }

    $sellerIds = array_values(array_unique(array_filter(array_map('intval', $sellerIds), static function ($id) {
        return $id > 0;
    })));

    if (!$sellerIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));

    $sql = "
        SELECT
            id,
            user_id,
            farm_name,
            farm_logo_path,
            application_status,
            updated_at,
            created_at
        FROM seller_applications
        WHERE user_id IN ($placeholders)
        ORDER BY
            CASE WHEN application_status = 'approved' THEN 0 ELSE 1 END,
            CASE
                WHEN COALESCE(NULLIF(TRIM(farm_name), ''), NULLIF(TRIM(farm_logo_path), '')) IS NOT NULL THEN 0
                ELSE 1
            END,
            updated_at DESC,
            created_at DESC,
            id DESC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sellerIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];

        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $farmName = trim((string) ($row['farm_name'] ?? ''));
            $farmLogoPath = trim((string) ($row['farm_logo_path'] ?? ''));

            $profile = [
                'farm_name' => $farmName,
                'farm_logo_path' => $farmLogoPath,
                'application_status' => strtolower(trim((string) ($row['application_status'] ?? ''))),
            ];

            $hasUsefulData = ($farmName !== '' || $farmLogoPath !== '');

            if ($userId > 0) {
                if (
                    !isset($map[$userId]) ||
                    (
                        $hasUsefulData &&
                        (($map[$userId]['farm_name'] ?? '') === '' && ($map[$userId]['farm_logo_path'] ?? '') === '')
                    )
                ) {
                    $map[$userId] = $profile;
                }
            }
        }

        return $map;
    } catch (Throwable $e) {
        return [
            '__error__' => [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ];
    }
}

function bv_home_attach_seller_profile_to_listing(array $item, array $sellerProfileMap): array
{
    $sellerId = (int) ($item['seller_id'] ?? 0);
    $profile = ($sellerId > 0 && isset($sellerProfileMap[$sellerId]) && is_array($sellerProfileMap[$sellerId]))
        ? $sellerProfileMap[$sellerId]
        : [];

    $item['_seller_farm_name'] = trim((string) ($profile['farm_name'] ?? ''));
    $item['_seller_farm_logo_path'] = trim((string) ($profile['farm_logo_path'] ?? ''));
    $item['_seller_farm_logo_url'] = bv_resolve_image_url($item['_seller_farm_logo_path']);

    return $item;
}

$currentUser = bv_home_current_user();
$isLoggedIn = is_array($currentUser) && !empty($currentUser['id']);
$currentUserName = bv_home_user_name($currentUser);
$currentUserRole = strtolower(trim((string) ($currentUser['role'] ?? 'user')));
$currentSellerStatus = strtolower(trim((string) ($currentUser['seller_application_status'] ?? '')));
$isSellerApproved = ($currentUserRole === 'seller' && $currentSellerStatus === 'approved');


$fallbackImage = 'data:image/svg+xml;utf8,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 900">
        <defs>
            <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#0f1711"/>
                <stop offset="100%" stop-color="#162119"/>
            </linearGradient>
        </defs>
        <rect width="1200" height="900" fill="url(#bg)"/>
        <circle cx="920" cy="210" r="180" fill="rgba(201,164,93,0.10)"/>
        <circle cx="260" cy="700" r="220" fill="rgba(201,164,93,0.06)"/>
        <rect x="170" y="250" width="860" height="400" rx="28" fill="none" stroke="#2e4534" stroke-width="4"/>
        <text x="600" y="420" text-anchor="middle" fill="#e5c98a" font-size="44" font-family="Arial, Helvetica, sans-serif" font-weight="700">Bettavaro Featured Fish</text>
        <text x="600" y="485" text-anchor="middle" fill="#b7c7ba" font-size="28" font-family="Arial, Helvetica, sans-serif">Image coming soon</text>
    </svg>'
);

$featuredListings = [];
$fixedListings = [];
$auctionListings = [];
$offerListings = [];
$discountHighlightListings = [];
$sellerDiscountMap = [];
$sellerProfileMap = [];
$sellerIds = [];


try {
    $listingCols = bv_table_columns($pdo, 'listings');

    $selectParts = [
        'id',
        'title',
        'slug',
        'short_description',
        'price',
        'currency',
        'country',
        'city',
        'cover_image',
        'featured',
        'status',
        'sale_status',
        'created_at',
    ];

    $optionalCols = [
        'seller_id',
        'sale_format',
        'auction_enabled',
        'auction_start_price',
        'auction_min_increment',
        'auction_reserve_price',
        'auction_starts_at',
        'auction_ends_at',
        'auction_status',
        'auction_current_bid',
        'auction_bid_count',
        'auction_last_bid_at',
    ];

 foreach ($optionalCols as $col) {
        if (isset($listingCols[$col])) {
            $selectParts[] = $col;
        }
    }

    $selectSqlParts = array_map(static function (string $col): string {
        return 'l.`' . str_replace('`', '``', $col) . '`';
    }, $selectParts);
    $selectSqlParts[] = 'COALESCE(r.final_score, 0) AS ranking_score';

    $featuredSql = "
        SELECT " . implode(",\n            ", $selectSqlParts) . "
        FROM listings l
        LEFT JOIN listing_ranking_scores r ON r.listing_id = l.id
        WHERE l.status IN ('active', 'sold')
        ORDER BY COALESCE(r.final_score, 0) DESC, l.created_at DESC
        LIMIT 12
    "; 

    $featuredStmt = $pdo->query($featuredSql);
    $featuredListings = $featuredStmt ? ($featuredStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    foreach ($featuredListings as $row) {
        $sellerId = (int) ($row['seller_id'] ?? 0);
        if ($sellerId > 0) {
            $sellerIds[] = $sellerId;
        }
    }

    $sellerDiscountMap = bv_home_load_active_seller_discounts($pdo, $sellerIds);
    $sellerProfileMap = bv_home_load_seller_profiles($pdo, $sellerIds);

    foreach ($featuredListings as $row) {
$row = bv_home_attach_discount_to_listing($row, $sellerDiscountMap);
$row = bv_home_attach_seller_profile_to_listing($row, $sellerProfileMap);

        $saleFormat = strtolower(trim((string) ($row['sale_format'] ?? 'fixed')));
        $isAuction = ($saleFormat === 'auction');

        if ($isAuction) {
            $auctionListings[] = $row;
        } elseif ($saleFormat === 'offer') {
            $offerListings[] = $row;
        } else {
            $fixedListings[] = $row;
        }
    }

    $fixedListings = bv_home_sort_fixed_listings($fixedListings);

    $discountHighlightListings = array_values(array_filter(
        $fixedListings,
        static function (array $item): bool {
            return !empty($item['_has_discount']);
        }
    ));

    if (count($discountHighlightListings) > 6) {
        $discountHighlightListings = array_slice($discountHighlightListings, 0, 6);
    }

    if (count($fixedListings) > 12) {
        $fixedListings = array_slice($fixedListings, 0, 12);
    }

    if (count($auctionListings) > 12) {
        $auctionListings = array_slice($auctionListings, 0, 12);
    }

    if (count($offerListings) > 12) {
        $offerListings = array_slice($offerListings, 0, 12);
    }

} catch (Throwable $e) {

    $featuredListings = [];
    $fixedListings = [];
    $auctionListings = [];
    $offerListings = [];
    $discountHighlightListings = [];
    $sellerDiscountMap = [];
    $sellerProfileMap = [];
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/menu.php';
?>
<?php if (!empty($homepageJsonLd)): ?>
<script type="application/ld+json">
<?= $homepageJsonLd . "
" ?>
</script>
<?php endif; ?>



<style>
.home-format-note{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:700;
  background:rgba(255,255,255,0.05);
  border:1px solid rgba(255,255,255,0.08);
  color:#d9e3db;
}
.home-mini-meta{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-top:10px;
}
.home-mini-pill{
  display:inline-flex;
  align-items:center;
  padding:6px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:700;
  background:rgba(255,255,255,0.05);
  border:1px solid rgba(255,255,255,0.08);
  color:#d9e3db;
}
.section-split-accent{
  height:1px;
  background:linear-gradient(90deg, rgba(229,201,138,0), rgba(229,201,138,0.32), rgba(229,201,138,0));
  margin:8px 0 0;
}
.home-empty-card{
  padding:24px;
}
.home-empty-card h3{
  margin-bottom:10px;
}
.home-empty-card p{
  margin-bottom:0;
}
.auction-card-body{
  display:flex;
  flex-direction:column;
  height:100%;
}
.auction-card-bottom{
  margin-top:auto;
  padding-top:12px;
}
.auction-card-actions{
  margin-top:12px;
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
}
.auction-detail-btn{
  min-height:38px;
  padding:0 14px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  text-decoration:none;
  border:none;
  cursor:pointer;
  border-radius:999px;
  background:rgba(255,255,255,0.06);
  border:1px solid rgba(229,201,138,0.22);
  color:#e7d39f;
  font-size:13px;
  font-weight:700;
}
.auction-detail-btn:hover{
  background:rgba(255,255,255,0.10);
}
.listing-card{
  height:100%;
}
.listing-card .listing-link{
  display:flex;
  flex-direction:column;
  height:100%;
}
.listing-card .listing-body{
  display:flex;
  flex-direction:column;
  flex:1;
}
.listing-card .listing-body{
  display:flex;
  flex-direction:column;
  flex:1;
  padding-bottom:18px;
}
.listing-card-rating{
  display:flex;
  align-items:center;
  gap:5px;
  margin-top:5px;
  font-size:13px;
  line-height:1;
}
.listing-card-stars{
  color:#f59e0b;
  letter-spacing:1px;
  font-size:14px;
}
.listing-card-avg{
  color:#e5c98a;
  font-weight:700;
}
.listing-card-count{
  color:rgba(229,201,138,0.65);
  font-size:12px;
}
.home-discount-badge{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:6px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:800;
  color:#182118;
  background:linear-gradient(135deg, #f5d77b, #d7a93d);
  box-shadow:0 10px 24px rgba(0,0,0,0.18);
  white-space:nowrap;
}
.home-price-stack{
  display:flex;
  flex-direction:column;
  gap:4px;
}
.home-old-price{
  font-size:13px;
  color:#9ca8a0;
  text-decoration:line-through;
  line-height:1.2;
}
.home-new-price{
  font-size:22px;
  font-weight:800;
  color:#f0d998;
  line-height:1.15;
}
.home-discount-note{
  margin-top:6px;
  font-size:12px;
  font-weight:700;
  color:#cfe7d5;
}
</style>

<section class="section" style="padding-top: 42px;">
  <div class="container">
    <div class="hero-shell">
      <div class="hero-grid">
        <div class="hero-copy">
          <p class="eyebrow">Premium Betta Fish from Thailand</p>
          <h1>Carefully Selected Betta Fish for Collectors and Enthusiasts</h1>
          <p>
            Bettavaro presents premium betta fish from Thailand with a cleaner, calmer, and more trustworthy
            browsing experience. Explore fixed-price listings, live auctions, view fish details clearly,
            and reserve with confidence.
          </p>

          <div class="hero-actions">
            <a href="/listings.php" class="btn">View All Listings</a>

            <?php if ($isLoggedIn): ?>
              <a href="/member/index.php" class="btn-outline">My Account</a>
              <a href="/logout.php" class="btn-soft">Logout</a>
            <?php else: ?>
              <a href="/login.php?redirect=%2Fmember%2Findex.php" class="btn-outline">Login</a>
            <?php endif; ?>

            <a href="/contact.php" class="btn-soft js-track-contact" data-contact-type="hero_contact">Contact Us</a>
          </div>

          <div class="home-mini-meta">
            <span class="home-format-note">Fixed Price = buy now clarity</span>
            <span class="home-format-note">Live Auction = bidding flow</span>
            <span class="home-format-note">Best Offer = negotiate</span>
          </div>
        </div>

        <aside class="hero-side">
          <h3>Built for premium presentation</h3>
          <p>
            Bettavaro is shaped to make each listing feel cleaner, more trustworthy, and easier to understand at a glance.
          </p>

          <div class="info-stack">
            <div class="info-card">
              <strong>Collector-first clarity</strong>
              <span>Fish details, pricing, and sale format are presented clearly, without making buyers guess.</span>
            </div>
            <div class="info-card">
              <strong>Fixed + Auction separated</strong>
              <span>Direct-buy listings and auction listings are split clearly on the homepage for faster browsing.</span>
            </div>
            <div class="info-card">
              <strong>Thailand-origin quality</strong>
              <span>Presentation built around premium Thai betta fish and serious collector expectations.</span>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </div>
</section>

<?php if ($isLoggedIn): ?>
<section class="section" style="padding-top: 0; padding-bottom: 10px;">
  <div class="container">
    <div class="card session-card" style="border-color: rgba(229, 201, 138, 0.28); background: linear-gradient(135deg, rgba(12, 31, 23, 0.98), rgba(10, 25, 19, 0.98));">
      <div class="session-grid">
        <div style="max-width: 760px;">
          <p class="eyebrow" style="margin-bottom: 10px;">Account Session Active</p>
          <h2>Welcome back, <?php echo bv_e($currentUserName); ?></h2>
          <p class="muted" style="margin: 0;">
            You are already signed in as <strong><?php echo bv_e($isSellerApproved ? 'seller' : $currentUserRole); ?></strong>.
            Your session is active on the homepage, so you can head straight into your dashboard instead of doing the login dance again.
          </p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <a href="/member/index.php" class="btn"><?php echo $isSellerApproved ? 'Open Seller Dashboard' : 'Open Member Dashboard'; ?></a>
          <a href="/member/change-password.php" class="btn-outline">Change Password</a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top: 12px;">
  <div class="container">
    <div class="section-header">
      <div style="max-width: 860px;">
        <p class="eyebrow">Why Bettavaro</p>
        <h2>Presentation, trust, and cleaner buyer flow</h2>
        <p class="muted" style="margin: 0; max-width: 760px;">
          Our goal is to present betta fish with clarity and consistency. Each listing is designed to help buyers
          understand what they are viewing, how it is being sold, and what action they can take next.
        </p>
      </div>
    </div>

    <div class="feature-grid">
      <div class="card feature-card">
        <h3>Curated Listings</h3>
        <p>Fish with character and strong visual identity should be presented cleanly, not buried under visual clutter.</p>
      </div>
      <div class="card feature-card">
        <h3>Clear Sale Format</h3>
        <p>Fixed price and auction listings are shown separately so buyers do not have to guess whether they can buy now or bid.</p>
      </div>
      <div class="card feature-card">
        <h3>Thailand-Origin Quality</h3>
        <p>A premium collector-minded presentation shaped around Thai betta fish and the expectations of serious buyers.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($discountHighlightListings): ?>
<section class="section" id="featured-discounts" style="padding-top: 8px;">
  <div class="container">
    <div class="section-header">
      <div>
        <p class="eyebrow">Seller Discounts</p>
        <h2>Special prices available now</h2>
        <p class="muted" style="margin: 0;">ปลาที่มีส่วนลดจากผู้ขายในช่วงเวลานี้ ดันขึ้นมาโชว์ก่อนเลย</p>
      </div>
      <a href="/listings.php?sale_format=fixed" class="btn-outline">Browse Fixed Listings</a>
    </div>

    <div class="listing-grid">
      <?php foreach ($discountHighlightListings as $item): ?>
        <?php
          $title = (string) ($item['title'] ?? 'Untitled Listing');
          $slug = (string) ($item['slug'] ?? '');
          $excerpt = trim((string) ($item['short_description'] ?? ''));
          $listingUrl = bv_public_listing_url($item);
          $rawImage = bv_resolve_image_url($item['cover_image'] ?? '');
          $image = $rawImage !== '' ? $rawImage : $fallbackImage;
          $location = trim((string) (($item['city'] ?? '') . ', ' . ($item['country'] ?? '')), ', ');
          $location = $location !== '' ? $location : 'International';

          $originalPriceText = (string) ($item['_original_price_text'] ?? '');
          $discountedPriceText = (string) ($item['_discounted_price_text'] ?? '');
          $discountBadgeText = (string) ($item['_discount_badge_text'] ?? '');
		  $farmName = trim((string) ($item['_seller_farm_name'] ?? ''));
			$farmLogo = trim((string) ($item['_seller_farm_logo_url'] ?? ''));
        ?>
        <article class="card listing-card">
          <a
            href="<?= bv_e($listingUrl) ?>"
            class="listing-link js-track-featured"
            data-listing-slug="<?= bv_e($slug) ?>"
            data-source-section="home_discount_highlight"
          >
            <div class="listing-media">
              <img
                src="<?= bv_e($image) ?>"
                alt="<?= bv_e($title) ?>"
                loading="lazy"
                onerror="this.onerror=null;this.src='<?= bv_e($fallbackImage) ?>';"
              >
            </div>

            <div class="listing-body auction-card-body">
              <div class="listing-topline">
              <?php if ($farmName !== ''): ?>
  <span style="display:inline-flex;align-items:center;gap:8px;">
    <?php if ($farmLogo !== ''): ?>
      <img
        src="<?= bv_e($farmLogo) ?>"
        alt="<?= bv_e($farmName) ?>"
        style="width:22px;height:22px;border-radius:999px;object-fit:cover;border:1px solid rgba(229,201,138,0.30);background:rgba(255,255,255,0.08);"
        loading="lazy"
      >
    <?php else: ?>
      <span style="width:22px;height:22px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(229,201,138,0.30);background:rgba(255,255,255,0.08);color:#e5c98a;font-size:11px;font-weight:800;">
        <?= bv_e(function_exists('mb_substr') ? mb_strtoupper(mb_substr($farmName, 0, 1)) : strtoupper(substr($farmName, 0, 1))) ?>
      </span>
    <?php endif; ?>
    <span><?= bv_e($farmName) ?></span>
  </span>
<?php endif; ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <?php if ($discountBadgeText !== ''): ?>
                    <span class="home-discount-badge"><?= bv_e($discountBadgeText) ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <h3 class="listing-title"><?= bv_e($title) ?></h3>
              <?= bv_home_card_review_html((int)($item['id'] ?? 0)) ?>

              <?php if ($excerpt !== ''): ?>
                <p class="listing-excerpt"><?= bv_e($excerpt) ?></p>
              <?php endif; ?>

              <div class="listing-bottom">
                <div class="home-price-stack">
                  <div class="home-old-price"><?= bv_e($originalPriceText) ?></div>
                  <div class="home-new-price"><?= bv_e($discountedPriceText) ?></div>
                  <div class="home-discount-note">Seller discount applied</div>
                </div>
                <span class="listing-view">View Details →</span>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="featured-fixed" style="padding-top: 8px;">
  <div class="container">
    <div class="section-header">
      <div>
        <p class="eyebrow">Fixed Price Listings</p>
        <h2>Buy now listings ready for direct purchase</h2>
        <p class="muted" style="margin: 0;">ปลาที่ตั้งราคาขายชัดเจน ซื้อแบบตรงไปตรงมา ไม่ต้องเดา ไม่ต้องลุ้นค้อนตก</p>
      </div>
      <a href="/listings.php?sale_format=fixed" class="btn-outline">View Fixed Listings</a>
    </div>

    <?php if (!$fixedListings): ?>
      <div class="card home-empty-card">
        <h3>No fixed-price listings yet</h3>
        <p class="muted">ยังไม่มีรายการขายแบบ Fixed ในตอนนี้ แต่โซนนี้พร้อมรอปลาแล้วครับ</p>
      </div>
    <?php else: ?>
      <div class="listing-grid">
        <?php foreach ($fixedListings as $item): ?>
          <?php
            $title = (string) ($item['title'] ?? 'Untitled Listing');
            $slug = (string) ($item['slug'] ?? '');
            $excerpt = trim((string) ($item['short_description'] ?? ''));
            $listingUrl = bv_public_listing_url($item);
            $detailUrl = bv_listing_detail_url($item);
            $rawImage = bv_resolve_image_url($item['cover_image'] ?? '');
            $image = $rawImage !== '' ? $rawImage : $fallbackImage;
            $location = trim((string) (($item['city'] ?? '') . ', ' . ($item['country'] ?? '')), ', ');
            $location = $location !== '' ? $location : 'International';
            $displayStatus = bv_effective_sale_status($item['sale_status'] ?? '', $item['status'] ?? '');
            [$statusLabel, $statusColor, $statusBg] = bv_status_badge($displayStatus);
            [$formatLabel, $formatColor, $formatBg] = bv_sale_format_badge($item);

            $hasDiscount = !empty($item['_has_discount']);
            $originalPriceText = (string) ($item['_original_price_text'] ?? '');
            $discountedPriceText = (string) ($item['_discounted_price_text'] ?? '');
            $discountBadgeText = (string) ($item['_discount_badge_text'] ?? '');

$farmName = trim((string) ($item['_seller_farm_name'] ?? ''));
$farmLogo = trim((string) ($item['_seller_farm_logo_url'] ?? ''));

$priceText = '';
if ($hasDiscount) {
    $priceText = $discountedPriceText;
} elseif (isset($item['price']) && $item['price'] !== '' && is_numeric($item['price'])) {
    $priceText = bv_home_format_money((float) $item['price'], (string) ($item['currency'] ?? 'USD'));
}
         ?>
          <article class="card listing-card">
            <a
              href="<?= bv_e($listingUrl) ?>"
              class="listing-link js-track-featured"
              data-listing-slug="<?= bv_e($slug) ?>"
              data-source-section="home_fixed"
            >
              <div class="listing-media">
                <img
                  src="<?= bv_e($image) ?>"
                  alt="<?= bv_e($title) ?>"
                  loading="lazy"
                  onerror="this.onerror=null;this.src='<?= bv_e($fallbackImage) ?>';"
                >
              </div>

              <div class="listing-body auction-card-body">
                <div class="listing-topline">
                  <?php if ($farmName !== ''): ?>
  <span style="display:inline-flex;align-items:center;gap:8px;">
    <?php if ($farmLogo !== ''): ?>
      <img
        src="<?= bv_e($farmLogo) ?>"
        alt="<?= bv_e($farmName) ?>"
        style="width:22px;height:22px;border-radius:999px;object-fit:cover;border:1px solid rgba(229,201,138,0.30);background:rgba(255,255,255,0.08);"
        loading="lazy"
      >
    <?php else: ?>
      <span style="width:22px;height:22px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(229,201,138,0.30);background:rgba(255,255,255,0.08);color:#e5c98a;font-size:11px;font-weight:800;">
        <?= bv_e(function_exists('mb_substr') ? mb_strtoupper(mb_substr($farmName, 0, 1)) : strtoupper(substr($farmName, 0, 1))) ?>
      </span>
    <?php endif; ?>
    <span><?= bv_e($farmName) ?></span>
  </span>
<?php endif; ?>
                  <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if ($hasDiscount && $discountBadgeText !== ''): ?>
                      <span class="home-discount-badge"><?= bv_e($discountBadgeText) ?></span>
                    <?php endif; ?>
                    <span class="status-pill" style="color: <?= bv_e($formatColor) ?>; background: <?= bv_e($formatBg) ?>;">
                      <?= bv_e($formatLabel) ?>
                    </span>
                    <span class="status-pill" style="color: <?= bv_e($statusColor) ?>; background: <?= bv_e($statusBg) ?>;">
                      <?= bv_e($statusLabel) ?>
                    </span>
                  </div>
                </div>

                <h3 class="listing-title"><?= bv_e($title) ?></h3>
                <?= bv_home_card_review_html((int)($item['id'] ?? 0)) ?>

                <?php if ($excerpt !== ''): ?>
                  <p class="listing-excerpt"><?= bv_e($excerpt) ?></p>
                <?php endif; ?>

                <div class="listing-bottom">
                  <div class="home-price-stack">
                    <?php if ($hasDiscount): ?>
                      <div class="home-old-price"><?= bv_e($originalPriceText) ?></div>
                      <div class="home-new-price"><?= bv_e($discountedPriceText) ?></div>
                      <div class="home-discount-note">Seller discount applied</div>
                    <?php elseif ($priceText !== ''): ?>
                      <div class="listing-price"><?= bv_e($priceText) ?></div>
                    <?php endif; ?>
                  </div>
                  <span class="listing-view">View Details →</span>
                </div>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section" style="padding-top: 6px; padding-bottom: 0;">
  <div class="container">
    <div class="section-split-accent"></div>
  </div>
</section>

<section class="section" id="featured-auction" style="padding-top: 14px;">
  <div class="container">
    <div class="section-header">
      <div>
        <p class="eyebrow">Live Auction Listings</p>
        <h2>Fish currently open for bidding</h2>
      </div>
      <a href="/listings.php?sale_format=auction" class="btn-outline">View Auctions</a>
    </div>

    <?php if (!$auctionListings): ?>
      <div class="card home-empty-card">
        <h3>No auction listings yet</h3>
      </div>
    <?php else: ?>
      <div class="listing-grid">
        <?php foreach ($auctionListings as $item): ?>
          <?php
            $title = (string) ($item['title'] ?? 'Untitled Listing');
            $slug = (string) ($item['slug'] ?? '');
            $excerpt = trim((string) ($item['short_description'] ?? ''));
            $listingUrl = bv_public_listing_url($item);
            $detailUrl = bv_listing_detail_url($item);
            $rawImage = bv_resolve_image_url($item['cover_image'] ?? '');
            $image = $rawImage !== '' ? $rawImage : $fallbackImage;
            $location = trim((string) (($item['city'] ?? '') . ', ' . ($item['country'] ?? '')), ', ');
            $location = $location !== '' ? $location : 'International';
            $displayStatus = bv_effective_sale_status($item['sale_status'] ?? '', $item['status'] ?? '');
            [$statusLabel, $statusColor, $statusBg] = bv_status_badge($displayStatus);
            [$formatLabel, $formatColor, $formatBg] = bv_sale_format_badge($item);

            $currency = (string) ($item['currency'] ?? 'USD');
            $auctionDisplay = '';
            if (isset($item['auction_current_bid']) && $item['auction_current_bid'] !== null && $item['auction_current_bid'] !== '' && is_numeric($item['auction_current_bid'])) {
                $auctionDisplay = bv_home_format_money((float) $item['auction_current_bid'], $currency);
            } elseif (isset($item['auction_start_price']) && $item['auction_start_price'] !== null && $item['auction_start_price'] !== '' && is_numeric($item['auction_start_price'])) {
                $auctionDisplay = bv_home_format_money((float) $item['auction_start_price'], $currency);
            } elseif (isset($item['price']) && $item['price'] !== '' && is_numeric($item['price'])) {
                $auctionDisplay = bv_home_format_money((float) $item['price'], $currency);
            }
			$farmName = trim((string) ($item['_seller_farm_name'] ?? ''));
			$farmLogo = trim((string) ($item['_seller_farm_logo_url'] ?? ''));

            $auctionBidCount = (int) ($item['auction_bid_count'] ?? 0);
            $auctionEndsAt = trim((string) ($item['auction_ends_at'] ?? ''));
            $isLiveAuction = bv_is_live_auction($item);
          ?>
          <article class="card listing-card">
            <a
              href="<?= bv_e($listingUrl) ?>"
              class="listing-link js-track-featured"
              data-listing-slug="<?= bv_e($slug) ?>"
              data-source-section="home_auction"
            >
              <div class="listing-media">
                <img
                  src="<?= bv_e($image) ?>"
                  alt="<?= bv_e($title) ?>"
                  loading="lazy"
                  onerror="this.onerror=null;this.src='<?= bv_e($fallbackImage) ?>';"
                >
              </div>

              <div class="listing-body">
                <div class="listing-topline">
<?php if ($farmName !== ''): ?>
  <span style="display:inline-flex;align-items:center;gap:8px;">
    <?php if ($farmLogo !== ''): ?>
      <img
        src="<?= bv_e($farmLogo) ?>"
        alt="<?= bv_e($farmName) ?>"
        style="width:22px;height:22px;border-radius:999px;object-fit:cover;border:1px solid rgba(229,201,138,0.30);background:rgba(255,255,255,0.08);"
        loading="lazy"
      >
    <?php else: ?>
      <span style="width:22px;height:22px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(229,201,138,0.30);background:rgba(255,255,255,0.08);color:#e5c98a;font-size:11px;font-weight:800;">
        <?= bv_e(function_exists('mb_substr') ? mb_strtoupper(mb_substr($farmName, 0, 1)) : strtoupper(substr($farmName, 0, 1))) ?>
      </span>
    <?php endif; ?>
    <span><?= bv_e($farmName) ?></span>
  </span>
<?php endif; ?>
                  <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="status-pill" style="color: <?= bv_e($formatColor) ?>; background: <?= bv_e($formatBg) ?>;">
                      <?= bv_e($formatLabel) ?>
                    </span>
                    <span class="status-pill" style="color: <?= bv_e($statusColor) ?>; background: <?= bv_e($statusBg) ?>;">
                      <?= bv_e($statusLabel) ?>
                    </span>
                  </div>
                </div>

                <h3 class="listing-title"><?= bv_e($title) ?></h3>
                <?= bv_home_card_review_html((int)($item['id'] ?? 0)) ?>

                <?php if ($excerpt !== ''): ?>
                  <p class="listing-excerpt"><?= bv_e($excerpt) ?></p>
                <?php endif; ?>

                <div class="listing-bottom auction-card-bottom">
                  <?php if ($auctionDisplay !== ''): ?>
                    <div class="listing-price"><?= $auctionBidCount > 0 ? 'Current Bid: ' : 'Start Bid: ' ?><?= bv_e($auctionDisplay) ?></div>
                  <?php endif; ?>

                  <div class="home-mini-meta">
                    <span class="home-mini-pill">Bids: <?= $auctionBidCount ?></span>

                    <?php if ($auctionEndsAt !== '' && strtotime($auctionEndsAt) !== false): ?>
                      <span class="home-mini-pill">Ends: <?= bv_e(date('Y-m-d H:i', strtotime($auctionEndsAt))) ?></span>
                    <?php endif; ?>

                    <span class="home-mini-pill"><?= $isLiveAuction ? 'Open Now' : 'Auction Status Tracked' ?></span>
                  </div>

                  <div class="auction-card-actions">
                    <button
                      type="button"
                      class="auction-detail-btn"
                      onclick="event.preventDefault(); event.stopPropagation(); window.location.href='<?= bv_e($detailUrl) ?>';"
                    >
                      View Detail
                    </button>

                    <span class="listing-view">View Auction →</span>
                  </div>
                </div>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($offerListings): ?>
<section class="section" id="featured-offer" style="padding-top: 14px;">
  <div class="container">
    <div class="section-header">
      <div>
        <p class="eyebrow">Best Offer Listings</p>
        <h2>Listings open to negotiation</h2>
        <p class="muted" style="margin: 0;">ถ้ามีระบบต่อรองราคาแล้ว โซนนี้จะช่วยกันไม่ให้ปลาไปปนกับ fixed และ auction</p>
      </div>
      <a href="/listings.php?sale_format=offer" class="btn-outline">View Best Offer</a>
    </div>

    <div class="listing-grid">
      <?php foreach ($offerListings as $item): ?>
        <?php
          $title = (string) ($item['title'] ?? 'Untitled Listing');
          $slug = (string) ($item['slug'] ?? '');
          $excerpt = trim((string) ($item['short_description'] ?? ''));
          $listingUrl = bv_public_listing_url($item);
          $rawImage = bv_resolve_image_url($item['cover_image'] ?? '');
          $image = $rawImage !== '' ? $rawImage : $fallbackImage;
          $location = trim((string) (($item['city'] ?? '') . ', ' . ($item['country'] ?? '')), ', ');
          $location = $location !== '' ? $location : 'International';
          $displayStatus = bv_effective_sale_status($item['sale_status'] ?? '', $item['status'] ?? '');
          [$statusLabel, $statusColor, $statusBg] = bv_status_badge($displayStatus);
		  $farmName = trim((string) ($item['_seller_farm_name'] ?? ''));
		  $farmLogo = trim((string) ($item['_seller_farm_logo_url'] ?? ''));
          [$formatLabel, $formatColor, $formatBg] = bv_sale_format_badge($item);

          $priceText = '';
          if (isset($item['price']) && $item['price'] !== '' && is_numeric($item['price'])) {
              $priceText = bv_home_format_money((float) $item['price'], (string) ($item['currency'] ?? 'USD'));
          }
        ?>
        <article class="card listing-card">
          <a
            href="<?= bv_e($listingUrl) ?>"
            class="listing-link js-track-featured"
            data-listing-slug="<?= bv_e($slug) ?>"
            data-source-section="home_offer"
          >
            <div class="listing-media">
              <img
                src="<?= bv_e($image) ?>"
                alt="<?= bv_e($title) ?>"
                loading="lazy"
                onerror="this.onerror=null;this.src='<?= bv_e($fallbackImage) ?>';"
              >
            </div>

            <div class="listing-body">
              <div class="listing-topline">
<?php if ($farmName !== ''): ?>
  <span style="display:inline-flex;align-items:center;gap:8px;">
    <?php if ($farmLogo !== ''): ?>
      <img
        src="<?= bv_e($farmLogo) ?>"
        alt="<?= bv_e($farmName) ?>"
        style="width:22px;height:22px;border-radius:999px;object-fit:cover;border:1px solid rgba(229,201,138,0.30);background:rgba(255,255,255,0.08);"
        loading="lazy"
      >
    <?php else: ?>
      <span style="width:22px;height:22px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(229,201,138,0.30);background:rgba(255,255,255,0.08);color:#e5c98a;font-size:11px;font-weight:800;">
        <?= bv_e(function_exists('mb_substr') ? mb_strtoupper(mb_substr($farmName, 0, 1)) : strtoupper(substr($farmName, 0, 1))) ?>
      </span>
    <?php endif; ?>
    <span><?= bv_e($farmName) ?></span>
  </span>
<?php endif; ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <span class="status-pill" style="color: <?= bv_e($formatColor) ?>; background: <?= bv_e($formatBg) ?>;">
                    <?= bv_e($formatLabel) ?>
                  </span>
                  <span class="status-pill" style="color: <?= bv_e($statusColor) ?>; background: <?= bv_e($statusBg) ?>;">
                    <?= bv_e($statusLabel) ?>
                  </span>
                </div>
              </div>

              <h3 class="listing-title"><?= bv_e($title) ?></h3>
              <?= bv_home_card_review_html((int)($item['id'] ?? 0)) ?>

              <?php if ($excerpt !== ''): ?>
                <p class="listing-excerpt"><?= bv_e($excerpt) ?></p>
              <?php endif; ?>

              <div class="listing-bottom">
                <div>
                  <?php if ($priceText !== ''): ?>
                    <div class="listing-price"><?= bv_e($priceText) ?></div>
                  <?php endif; ?>
                </div>
                <span class="listing-view">View Details →</span>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top: 16px;">
  <div class="container">
    <div class="card cta-card">
      <p class="eyebrow">Start Here</p>
      <h2>Start exploring premium betta fish online</h2>
      <p>
        Browse our public listings to discover fish presented with a clearer and more trustworthy structure.
        Fixed-price listings and live auctions are now separated more clearly, helping buyers understand the path immediately.
      </p>
      <p>
        You can begin with our <a href="/listings.php">latest listings</a>, learn more <a href="/about.php">about Bettavaro</a>,
        or read our <a href="/shipping.php">shipping information</a> and <a href="/doa-policy.php">DOA policy</a>.
      </p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
