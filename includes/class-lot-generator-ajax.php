<?php
/**
 * TGS Lot Generator — AJAX Handler
 *
 * Xử lý tất cả AJAX actions cho plugin:
 *  - Biến thể (CRUD)
 *  - Sinh mã (generate lots)
 *  - Danh sách phiếu
 *  - Chi tiết / kích hoạt / xóa / in
 *
 * @package tgs_lot_generator
 */

if (!defined('ABSPATH')) exit;

class TGS_Lot_Generator_Ajax
{
    /**
     * Đăng ký tất cả AJAX actions
     */
    public static function register()
    {
        $actions = [
            // ── Biến thể ──
            'tgs_lot_gen_get_variants',
            'tgs_lot_gen_save_variant',
            'tgs_lot_gen_delete_variant',

            // ── Sinh mã ──
            'tgs_lot_gen_search_products',
            'tgs_lot_gen_get_product_lots_count',
            'tgs_lot_gen_generate',

            // ── Danh sách phiếu ──
            'tgs_lot_gen_get_ledgers',

            // ── Chi tiết phiếu ──
            'tgs_lot_gen_get_detail',
            'tgs_lot_gen_activate_lots',
            'tgs_lot_gen_deactivate_lots',
            'tgs_lot_gen_delete_lots',

            // ── In barcode ──
            'tgs_lot_gen_print_barcodes',

            // ── Quick-create (modal) ──
            'tgs_lot_gen_quick_create_product',
            'tgs_lot_gen_generate_sku',
        ];

        foreach ($actions as $action) {
            add_action("wp_ajax_{$action}", [__CLASS__, $action]);
        }
    }

    /* =========================================================================
     * Helpers
     * ========================================================================= */

    private static function verify()
    {
        if (!check_ajax_referer('tgs_lot_gen_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce không hợp lệ.'], 403);
        }
    }

    private static function json_ok($data = [], $msg = 'OK')
    {
        wp_send_json_success(array_merge(['message' => $msg], $data));
    }

    private static function json_err($msg = 'Lỗi', $code = 400)
    {
        wp_send_json_error(['message' => $msg], $code);
    }

    /* =========================================================================
     * A. BIẾN THỂ (Variants)
     * ========================================================================= */

    /**
     * Lấy danh sách biến thể theo sản phẩm
     */
    public static function tgs_lot_gen_get_variants()
    {
        self::verify();
        global $wpdb;

        $product_id = intval($_POST['product_id'] ?? 0);
        $blog_id    = intval($_POST['blog_id'] ?? get_current_blog_id());

        $table = TGS_TABLE_GLOBAL_PRODUCT_VARIANTS;

        $where = "is_deleted = 0";
        $params = [];

        if ($product_id > 0) {
            $where .= " AND local_product_name_id = %d AND source_blog_id = %d";
            $params[] = $product_id;
            $params[] = $blog_id;
        } else {
            $where .= " AND source_blog_id = %d";
            $params[] = $blog_id;
        }

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY variant_sort_order ASC, variant_id DESC";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        self::json_ok(['variants' => $rows ?: []]);
    }

    /**
     * Tạo / Cập nhật biến thể
     */
    public static function tgs_lot_gen_save_variant()
    {
        self::verify();
        global $wpdb;

        $table = TGS_TABLE_GLOBAL_PRODUCT_VARIANTS;
        $now   = current_time('mysql');

        $variant_id  = intval($_POST['variant_id'] ?? 0);
        $product_id  = intval($_POST['product_id'] ?? 0);
        $blog_id     = intval($_POST['blog_id'] ?? get_current_blog_id());

        if ($product_id <= 0) {
            self::json_err('Chưa chọn sản phẩm.');
            return; // wp_send_json_error đã die, nhưng thêm return cho rõ ràng
        }

        $data = [
            'local_product_name_id'  => $product_id,
            'source_blog_id'         => $blog_id,
            'variant_type'           => sanitize_text_field($_POST['variant_type'] ?? 'custom'),
            'variant_label'          => sanitize_text_field($_POST['variant_label'] ?? ''),
            'variant_value'          => sanitize_text_field($_POST['variant_value'] ?? ''),
            'variant_sku_suffix'     => sanitize_text_field($_POST['variant_sku_suffix'] ?? ''),
            'variant_barcode_main'   => sanitize_text_field($_POST['variant_barcode_main'] ?? ''),
            'variant_price_adjustment' => floatval($_POST['variant_price_adjustment'] ?? 0),
            'variant_sort_order'     => intval($_POST['variant_sort_order'] ?? 0),
            'is_active'              => intval($_POST['is_active'] ?? 1),
            'updated_at'             => $now,
        ];

        // Meta JSON (tùy chọn)
        if (!empty($_POST['variant_meta'])) {
            $data['variant_meta'] = wp_json_encode(json_decode(stripslashes($_POST['variant_meta']), true));
        }

        if ($variant_id > 0) {
            // Update
            $wpdb->update($table, $data, ['variant_id' => $variant_id]);
            self::json_ok(['variant_id' => $variant_id], 'Đã cập nhật biến thể.');
        } else {
            // Insert
            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
            $new_id = $wpdb->insert_id;
            if (!$new_id) {
                self::json_err('Không thể tạo biến thể. DB error: ' . $wpdb->last_error);
                return;
            }
            self::json_ok(['variant_id' => $new_id], 'Đã thêm biến thể.');
        }
    }

    /**
     * Xóa mềm biến thể
     */
    public static function tgs_lot_gen_delete_variant()
    {
        self::verify();
        global $wpdb;

        $variant_id = intval($_POST['variant_id'] ?? 0);
        if ($variant_id <= 0) { self::json_err('variant_id không hợp lệ.'); return; }

        $wpdb->update(TGS_TABLE_GLOBAL_PRODUCT_VARIANTS, [
            'is_deleted' => 1,
            'deleted_at' => current_time('mysql'),
        ], ['variant_id' => $variant_id]);

        self::json_ok([], 'Đã xóa biến thể.');
    }

    /* =========================================================================
     * B. TÌM SẢN PHẨM (Search products)
     * ========================================================================= */

    /**
     * Tìm sản phẩm local (autocomplete)
     */
    public static function tgs_lot_gen_search_products()
    {
        self::verify();
        global $wpdb;

        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $blog_id = intval($_POST['blog_id'] ?? get_current_blog_id());
        $table   = defined('TGS_TABLE_LOCAL_PRODUCT_NAME') ? TGS_TABLE_LOCAL_PRODUCT_NAME : $wpdb->prefix . 'local_product_name';

        $sql = $wpdb->prepare(
            "SELECT local_product_name_id, local_product_name, local_product_barcode_main,
                    local_product_sku, local_product_unit, local_product_price_after_tax,
                    local_product_thumbnail, local_product_is_tracking
             FROM {$table}
             WHERE is_deleted = 0
               AND local_product_is_tracking = 1
               AND (local_product_name LIKE %s OR local_product_barcode_main LIKE %s OR local_product_sku LIKE %s)
             ORDER BY local_product_name ASC
             LIMIT 30",
            "%{$keyword}%", "%{$keyword}%", "%{$keyword}%"
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        // Đếm tổng kết quả (bao gồm cả SP ko tracking) để hiện gợi ý "Thêm SP mới"
        $total_all = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE is_deleted = 0
               AND (local_product_name LIKE %s OR local_product_barcode_main LIKE %s OR local_product_sku LIKE %s)",
            "%{$keyword}%", "%{$keyword}%", "%{$keyword}%"
        ));

        self::json_ok(['products' => $rows ?: [], 'total_all' => intval($total_all), 'keyword' => $keyword]);
    }

    /**
     * Lấy số lượng lot hiện có của 1 sản phẩm (theo blog + trạng thái)
     */
    public static function tgs_lot_gen_get_product_lots_count()
    {
        self::verify();
        global $wpdb;

        $product_id = intval($_POST['product_id'] ?? 0);
        $blog_id    = intval($_POST['blog_id'] ?? get_current_blog_id());

        if ($product_id <= 0) { self::json_err('product_id không hợp lệ.'); return; }

        $table = TGS_TABLE_GLOBAL_PRODUCT_LOTS;

        // Đếm theo trạng thái
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT local_product_lot_is_active AS status, COUNT(*) AS cnt
             FROM {$table}
             WHERE local_product_name_id = %d AND to_blog_id = %d AND is_deleted = 0
             GROUP BY local_product_lot_is_active",
            $product_id, $blog_id
        ), ARRAY_A);

        $result = ['total' => 0, 'in_stock' => 0, 'pending' => 0, 'generated' => 0];
        foreach ($counts as $row) {
            $s = intval($row['status']);
            $c = intval($row['cnt']);
            $result['total'] += $c;
            if ($s === 1) $result['in_stock'] = $c;
            if ($s === 2) $result['pending']  = $c;
            if ($s === 100) $result['generated'] = $c; // 100 = đã sinh, chưa kích hoạt
        }

        self::json_ok(['counts' => $result]);
    }

    /* =========================================================================
     * C. SINH MÃ (Generate Lots)
     * ========================================================================= */

    /**
     * Sinh mã hàng loạt
     * Input: product_id, blog_id, quantity, variant_id (optional), lot_code, exp_date, mfg_date
     *
     * Flow:
     * 1. Tạo phiếu sổ cái type=16
     * 2. Sinh N lot mới (is_active=100 = đã sinh, chưa kích hoạt)
     * 3. Trả về ledger_id để chuyển tới trang chi tiết
     */
    public static function tgs_lot_gen_generate()
    {
        self::verify();
        global $wpdb;

        $product_id = intval($_POST['product_id'] ?? 0);
        $blog_id    = intval($_POST['blog_id'] ?? get_current_blog_id());
        $quantity   = intval($_POST['quantity'] ?? 0);
        $variant_id = intval($_POST['variant_id'] ?? 0);
        $lot_code   = sanitize_text_field($_POST['lot_code'] ?? '');
        $exp_date   = sanitize_text_field($_POST['exp_date'] ?? '');
        $mfg_date   = sanitize_text_field($_POST['mfg_date'] ?? '');
        $note       = sanitize_text_field($_POST['note'] ?? '');

        if ($product_id <= 0) { self::json_err('Chưa chọn sản phẩm.'); return; }
        if ($quantity <= 0 || $quantity > 5000) { self::json_err('Số lượng phải từ 1 đến 5000.'); return; }

        // Lấy thông tin sản phẩm
        $product_table = defined('TGS_TABLE_LOCAL_PRODUCT_NAME') ? TGS_TABLE_LOCAL_PRODUCT_NAME : $wpdb->prefix . 'local_product_name';
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT local_product_name_id, local_product_name, local_product_barcode_main, local_product_sku, local_product_unit, local_product_price_after_tax
             FROM {$product_table} WHERE local_product_name_id = %d AND is_deleted = 0",
            $product_id
        ), ARRAY_A);

        if (!$product) { self::json_err('Sản phẩm không tồn tại.'); return; }

        // Lấy thông tin biến thể (nếu có)
        $variant = null;
        if ($variant_id > 0) {
            $variant = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . TGS_TABLE_GLOBAL_PRODUCT_VARIANTS . " WHERE variant_id = %d AND is_deleted = 0",
                $variant_id
            ), ARRAY_A);
        }

        $now = current_time('mysql');
        $user_id = get_current_user_id();

        // ── Bước 1: Tạo phiếu sổ cái type=16 ──
        $ledger_table = defined('TGS_TABLE_LOCAL_LEDGER') ? TGS_TABLE_LOCAL_LEDGER : $wpdb->prefix . 'local_ledger';
        $ticket_code  = function_exists('tgs_shop_generate_ticket_code') ? tgs_shop_generate_ticket_code('SMD') : 'SMD-' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

        $meta = [
            'plugin'     => 'tgs_lot_generator',
            'product_id' => $product_id,
            'product_name' => $product['local_product_name'],
            'quantity'   => $quantity,
            'variant_id' => $variant_id ?: null,
            'variant_info' => $variant ? ($variant['variant_label'] . ': ' . $variant['variant_value']) : null,
        ];

        $wpdb->insert($ledger_table, [
            'local_ledger_code'   => $ticket_code,
            'local_ledger_title'  => 'Sinh mã định danh - ' . $product['local_product_name'] . ($variant ? ' [' . $variant['variant_label'] . ': ' . $variant['variant_value'] . ']' : ''),
            'local_ledger_type'   => TGS_LEDGER_TYPE_LOT_GENERATE, // 16
            'local_ledger_note'   => $note,
            'local_ledger_status' => 1, // Đã duyệt ngay
            'local_ledger_meta_id' => null,
            'user_id'             => $user_id,
            'is_deleted'          => 0,
            'created_at'          => $now,
            'updated_at'          => $now,
            'local_ledger_person_meta' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);

        $ledger_id = $wpdb->insert_id;
        if (!$ledger_id) {
            self::json_err('Không thể tạo phiếu sổ cái. DB: ' . $wpdb->last_error);
            return;
        }

        // ── Bước 2: Sinh N lot mới ──
        $lots_table = TGS_TABLE_GLOBAL_PRODUCT_LOTS;
        $created_lot_ids = [];
        $created_barcodes = [];

        for ($i = 0; $i < $quantity; $i++) {
            // Sinh barcode EAN-13 unique
            $barcode_data = function_exists('tgs_shop_generate_lot_barcode_data')
                ? tgs_shop_generate_lot_barcode_data()
                : ['barcode' => self::fallback_barcode(), 'barcode_url' => '', 'qr_code_url' => ''];

            $insert = [
                'global_product_lot_barcode' => $barcode_data['barcode'],
                'local_product_name_id'      => $product_id,
                'global_product_lot_price'   => floatval($product['local_product_price_after_tax'] ?? 0),
                'source_blog_id'             => $blog_id,
                'to_blog_id'                 => $blog_id,
                'global_product_lot_is_active' => 100, // 100 = đã sinh, chưa kích hoạt
                'local_product_lot_is_active'  => 100,
                'lot_code'                   => $lot_code,
                'exp_date'                   => $exp_date ?: null,
                'mfg_date'                   => $mfg_date ?: null,
                'barcode_url'                => $barcode_data['barcode_url'] ?? '',
                'qr_code_url'                => $barcode_data['qr_code_url'] ?? '',
                'local_product_barcode_main' => $product['local_product_barcode_main'] ?? '',
                'local_product_sku'          => $product['local_product_sku'] ?? '',
                'batch_id'                   => null,
                'variant_id'                 => $variant_id ?: null,
                'user_id'                    => $user_id,
                'is_deleted'                 => 0,
                'created_at'                 => $now,
                'updated_at'                 => $now,
                'product_lot_meta'           => wp_json_encode([
                    'ledger_id'   => $ledger_id,
                    'ledger_code' => $ticket_code,
                    'generated_by' => 'tgs_lot_generator',
                    'variant_id'  => $variant_id ?: null,
                    'variant_info' => $variant ? ($variant['variant_label'] . ': ' . $variant['variant_value']) : null,
                ], JSON_UNESCAPED_UNICODE),
            ];

            $wpdb->insert($lots_table, $insert);
            $lot_id = $wpdb->insert_id;
            if ($lot_id) {
                $created_lot_ids[] = $lot_id;
                $created_barcodes[] = $barcode_data['barcode'];
            }
        }

        // ── Bước 3: Cập nhật phiếu với danh sách lot_ids ──
        $wpdb->update($ledger_table, [
            'local_ledger_item_id' => wp_json_encode($created_lot_ids),
        ], ['local_ledger_id' => $ledger_id]);

        self::json_ok([
            'ledger_id'    => $ledger_id,
            'ticket_code'  => $ticket_code,
            'lots_created' => count($created_lot_ids),
            'lot_ids'      => $created_lot_ids,
        ], 'Đã sinh ' . count($created_lot_ids) . ' mã định danh.');
    }

    /**
     * Fallback barcode khi helper chưa load
     */
    private static function fallback_barcode()
    {
        $first = mt_rand(1, 9);
        $remaining = str_pad(mt_rand(0, 99999999999), 11, '0', STR_PAD_LEFT);
        $code12 = $first . $remaining;
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$code12[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;
        return $code12 . $check;
    }

    /* =========================================================================
     * D. DANH SÁCH PHIẾU (Ledger list)
     * ========================================================================= */

    /**
     * Lấy danh sách phiếu sinh mã (type=16)
     */
    public static function tgs_lot_gen_get_ledgers()
    {
        self::verify();
        global $wpdb;

        $page     = max(1, intval($_POST['page'] ?? 1));
        $per_page = min(100, max(10, intval($_POST['per_page'] ?? 20)));
        $offset   = ($page - 1) * $per_page;
        $search   = sanitize_text_field($_POST['search'] ?? '');

        $ledger_table = defined('TGS_TABLE_LOCAL_LEDGER') ? TGS_TABLE_LOCAL_LEDGER : $wpdb->prefix . 'local_ledger';

        $where = "l.local_ledger_type = %d AND l.is_deleted = 0";
        $params = [TGS_LEDGER_TYPE_LOT_GENERATE];

        if ($search !== '') {
            $where .= " AND (l.local_ledger_code LIKE %s OR l.local_ledger_title LIKE %s)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        // Tổng
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$ledger_table} l WHERE {$where}",
            ...$params
        ));

        // Danh sách
        $params_q = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.local_ledger_id, l.local_ledger_code, l.local_ledger_title, l.local_ledger_note,
                    l.local_ledger_status, l.local_ledger_item_id, l.local_ledger_person_meta,
                    l.user_id, l.created_at, l.updated_at,
                    u.display_name as user_name
             FROM {$ledger_table} l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE {$where}
             ORDER BY l.created_at DESC
             LIMIT %d OFFSET %d",
            ...$params_q
        ), ARRAY_A);

        // Enrich: đếm lots cho mỗi phiếu
        foreach ($rows as &$row) {
            $lot_ids = json_decode($row['local_ledger_item_id'] ?? '[]', true);
            $row['lots_count'] = is_array($lot_ids) ? count($lot_ids) : 0;
            $row['meta'] = json_decode($row['local_ledger_person_meta'] ?? '{}', true);
        }
        unset($row);

        self::json_ok([
            'ledgers'  => $rows ?: [],
            'total'    => intval($total),
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => ceil($total / $per_page),
        ]);
    }

    /* =========================================================================
     * E. CHI TIẾT PHIẾU
     * ========================================================================= */

    /**
     * Lấy chi tiết 1 phiếu + danh sách lots
     */
    public static function tgs_lot_gen_get_detail()
    {
        self::verify();
        global $wpdb;

        $ledger_id = intval($_POST['ledger_id'] ?? 0);
        if ($ledger_id <= 0) { self::json_err('ledger_id không hợp lệ.'); return; }

        $ledger_table = defined('TGS_TABLE_LOCAL_LEDGER') ? TGS_TABLE_LOCAL_LEDGER : $wpdb->prefix . 'local_ledger';

        $ledger = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, u.display_name as user_name
             FROM {$ledger_table} l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE l.local_ledger_id = %d AND l.local_ledger_type = %d AND l.is_deleted = 0",
            $ledger_id, TGS_LEDGER_TYPE_LOT_GENERATE
        ), ARRAY_A);

        if (!$ledger) { self::json_err('Không tìm thấy phiếu.'); return; }

        // Lấy danh sách lots
        $lot_ids = json_decode($ledger['local_ledger_item_id'] ?? '[]', true);
        $lots = [];

        if (!empty($lot_ids)) {
            $placeholders = implode(',', array_fill(0, count($lot_ids), '%d'));
            $product_table = defined('TGS_TABLE_LOCAL_PRODUCT_NAME') ? TGS_TABLE_LOCAL_PRODUCT_NAME : $wpdb->prefix . 'local_product_name';

            $lots = $wpdb->get_results($wpdb->prepare(
                "SELECT l.global_product_lot_id, l.global_product_lot_barcode, l.local_product_name_id,
                        l.global_product_lot_price, l.lot_code, l.exp_date, l.mfg_date,
                        l.global_product_lot_is_active, l.local_product_lot_is_active,
                        l.local_product_barcode_main, l.local_product_sku,
                        l.variant_id, l.product_lot_meta, l.is_deleted, l.created_at,
                        p.local_product_name
                 FROM " . TGS_TABLE_GLOBAL_PRODUCT_LOTS . " l
                 LEFT JOIN {$product_table} p ON l.local_product_name_id = p.local_product_name_id
                 WHERE l.global_product_lot_id IN ({$placeholders})
                 ORDER BY l.global_product_lot_id ASC",
                ...$lot_ids
            ), ARRAY_A);
        }

        // Lấy thông tin biến thể nếu có
        $variant_ids = array_unique(array_filter(array_column($lots, 'variant_id')));
        $variants_map = [];
        if (!empty($variant_ids)) {
            $vph = implode(',', array_fill(0, count($variant_ids), '%d'));
            $v_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . TGS_TABLE_GLOBAL_PRODUCT_VARIANTS . " WHERE variant_id IN ({$vph})",
                ...$variant_ids
            ), ARRAY_A);
            foreach ($v_rows as $v) {
                $variants_map[$v['variant_id']] = $v;
            }
        }

        // Enrich lots
        foreach ($lots as &$lot) {
            $lot['variant'] = $variants_map[$lot['variant_id'] ?? 0] ?? null;
            $lot['lot_meta'] = json_decode($lot['product_lot_meta'] ?? '{}', true);
        }
        unset($lot);

        // Stats
        $stats = ['total' => count($lots), 'active' => 0, 'generated' => 0, 'deleted' => 0];
        foreach ($lots as $l) {
            if (intval($l['is_deleted']) === 1) { $stats['deleted']++; continue; }
            $s = intval($l['local_product_lot_is_active']);
            if ($s === 1) $stats['active']++;
            elseif ($s === 100) $stats['generated']++;
        }

        self::json_ok([
            'ledger' => $ledger,
            'lots'   => $lots,
            'stats'  => $stats,
            'meta'   => json_decode($ledger['local_ledger_person_meta'] ?? '{}', true),
        ]);
    }

    /**
     * Kích hoạt lots (100 → 1)
     */
    public static function tgs_lot_gen_activate_lots()
    {
        self::verify();
        global $wpdb;

        $lot_ids = json_decode(stripslashes($_POST['lot_ids'] ?? '[]'), true);
        if (empty($lot_ids)) { self::json_err('Chưa chọn mã nào.'); return; }

        $now = current_time('mysql');
        $activated = 0;

        foreach ($lot_ids as $id) {
            $id = intval($id);
            $result = $wpdb->update(TGS_TABLE_GLOBAL_PRODUCT_LOTS, [
                'global_product_lot_is_active' => 1, // in_stock
                'local_product_lot_is_active'  => 1,
                'updated_at'                   => $now,
            ], [
                'global_product_lot_id' => $id,
                'local_product_lot_is_active' => 100, // Chỉ kích hoạt lot đang ở trạng thái 100 (generated)
                'is_deleted' => 0,
            ]);
            if ($result) $activated++;
        }

        self::json_ok(['activated' => $activated], "Đã kích hoạt {$activated} mã.");
    }

    /**
     * Hủy kích hoạt lots (1 → 100)
     */
    public static function tgs_lot_gen_deactivate_lots()
    {
        self::verify();
        global $wpdb;

        $lot_ids = json_decode(stripslashes($_POST['lot_ids'] ?? '[]'), true);
        if (empty($lot_ids)) { self::json_err('Chưa chọn mã nào.'); return; }

        $now = current_time('mysql');
        $deactivated = 0;

        foreach ($lot_ids as $id) {
            $id = intval($id);
            $result = $wpdb->update(TGS_TABLE_GLOBAL_PRODUCT_LOTS, [
                'global_product_lot_is_active' => 100,
                'local_product_lot_is_active'  => 100,
                'updated_at'                   => $now,
            ], [
                'global_product_lot_id' => $id,
                'local_product_lot_is_active' => 1,
                'is_deleted' => 0,
            ]);
            if ($result) $deactivated++;
        }

        self::json_ok(['deactivated' => $deactivated], "Đã hủy kích hoạt {$deactivated} mã.");
    }

    /**
     * Xóa mềm lots thừa
     */
    public static function tgs_lot_gen_delete_lots()
    {
        self::verify();
        global $wpdb;

        $lot_ids = json_decode(stripslashes($_POST['lot_ids'] ?? '[]'), true);
        if (empty($lot_ids)) { self::json_err('Chưa chọn mã nào.'); return; }

        $now = current_time('mysql');
        $deleted = 0;

        foreach ($lot_ids as $id) {
            $id = intval($id);
            // Chỉ cho xóa lot đang ở trạng thái 100 (chưa kích hoạt) hoặc là_deleted đã = 0
            $result = $wpdb->update(TGS_TABLE_GLOBAL_PRODUCT_LOTS, [
                'is_deleted'  => 1,
                'deleted_at'  => $now,
                'updated_at'  => $now,
            ], [
                'global_product_lot_id' => $id,
                'is_deleted' => 0,
                'local_product_lot_is_active' => 100, // Chỉ xóa lot chưa kích hoạt
            ]);
            if ($result) $deleted++;
        }

        self::json_ok(['deleted' => $deleted], "Đã xóa {$deleted} mã.");
    }

    /* =========================================================================
     * F. IN BARCODE
     * ========================================================================= */

    /**
     * In barcode labels
     * Mở trang HTML print riêng (standalone, không WordPress header/footer)
     */
    public static function tgs_lot_gen_print_barcodes()
    {
        // Check nonce qua GET or POST
        if (!wp_verify_nonce($_GET['nonce'] ?? $_POST['nonce'] ?? '', 'tgs_lot_gen_nonce')) {
            wp_die('Nonce không hợp lệ.');
        }

        global $wpdb;

        $barcodes_str = sanitize_text_field($_GET['barcodes'] ?? $_POST['barcodes'] ?? '');
        $show_price   = !empty($_GET['show_price'] ?? $_POST['show_price'] ?? 0);
        $show_variant = ($_GET['show_variant'] ?? $_POST['show_variant'] ?? '1') !== '0';
        $show_lot_info = ($_GET['show_lot_info'] ?? $_POST['show_lot_info'] ?? '1') !== '0';

        if (empty($barcodes_str)) wp_die('Không có barcode để in.');

        $barcodes = array_filter(array_map('trim', explode(',', $barcodes_str)));
        if (empty($barcodes)) wp_die('Danh sách barcode không hợp lệ.');

        $product_table = defined('TGS_TABLE_LOCAL_PRODUCT_NAME') ? TGS_TABLE_LOCAL_PRODUCT_NAME : $wpdb->prefix . 'local_product_name';

        // Batch query thay vì query từng barcode (tránh N+1)
        $placeholders = implode(',', array_fill(0, count($barcodes), '%s'));
        $lots_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, p.local_product_name AS product_name, p.local_product_price_after_tax AS price
             FROM " . TGS_TABLE_GLOBAL_PRODUCT_LOTS . " l
             LEFT JOIN {$product_table} p ON l.local_product_name_id = p.local_product_name_id
             WHERE l.global_product_lot_barcode IN ({$placeholders})",
            ...$barcodes
        ), ARRAY_A);

        // Index lots theo barcode để giữ đúng thứ tự ban đầu
        $lots_by_barcode = [];
        foreach ($lots_rows as $lot) {
            $lots_by_barcode[$lot['global_product_lot_barcode']] = $lot;
        }

        // Batch lấy biến thể (1 query duy nhất)
        $variant_ids = array_unique(array_filter(array_column($lots_rows, 'variant_id')));
        $variants_map = [];
        if (!empty($variant_ids)) {
            $vph = implode(',', array_fill(0, count($variant_ids), '%d'));
            $v_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . TGS_TABLE_GLOBAL_PRODUCT_VARIANTS . " WHERE variant_id IN ({$vph})",
                ...$variant_ids
            ), ARRAY_A);
            foreach ($v_rows as $v) {
                $variants_map[$v['variant_id']] = $v;
            }
        }

        // Gộp kết quả, giữ thứ tự barcode gốc
        $lots_data = [];
        foreach ($barcodes as $barcode) {
            if (isset($lots_by_barcode[$barcode])) {
                $lot = $lots_by_barcode[$barcode];
                if (!empty($lot['variant_id']) && isset($variants_map[$lot['variant_id']])) {
                    $lot['variant'] = $variants_map[$lot['variant_id']];
                }
                $lots_data[] = $lot;
            }
        }

        self::output_print_html($lots_data, [
            'show_price'    => $show_price,
            'show_variant'  => $show_variant,
            'show_lot_info' => $show_lot_info,
        ]);
        exit;
    }

    /**
     * Output HTML trang in barcode (35×22mm, 2 tem/hàng, giấy 70×22mm)
     */
    private static function output_print_html($lots, $options = [])
    {
        $show_price    = !empty($options['show_price']);
        $show_variant  = $options['show_variant'] ?? true;
        $show_lot_info = $options['show_lot_info'] ?? true;
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>In Mã Định Danh (Lot Generator)</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
            <style>
                @page { size: 70mm 22mm; margin: 0; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                html, body { width: 70mm; height: auto; margin: 0; padding: 0; font-family: Arial, sans-serif; }
                .barcode-container { display: flex; flex-wrap: wrap; width: 70mm; }
                .barcode-item {
                    width: 35mm; height: 22mm;
                    padding: 0.2mm 1mm 0.5mm 1mm;
                    text-align: center;
                    display: flex; flex-direction: column; justify-content: flex-end; align-items: center;
                    overflow: hidden; background: #fff; border: 1px dashed #ccc;
                }
                .barcode-item svg { width: 32mm !important; height: 12mm !important; max-width: 32mm; max-height: 12mm; flex-shrink: 0; }
                .product-name { font-size: 6pt; font-weight: bold; line-height: 1.0; max-width: 33mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: -0.5mm; margin-bottom: 0.2mm; }
                .product-price { font-size: 5.5pt; font-weight: bold; line-height: 1.0; margin-bottom: 0.1mm; }
                .variant-info { font-size: 5pt; color: #0066cc; font-weight: bold; line-height: 1.0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 33mm; margin-bottom: 0.1mm; }
                .lot-info { font-size: 5pt; color: #333; line-height: 1.0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 33mm; }
                .print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; border-radius: 4px; z-index: 100; font-size: 14px; }
                .print-btn:hover { background: #45a049; }
                @media print {
                    html, body { width: 70mm; margin: 0; padding: 0; }
                    .print-btn { display: none; }
                    .barcode-container { width: 70mm; }
                    .barcode-item { border: none; page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <button class="print-btn" onclick="window.print()">🖨️ In Mã Định Danh</button>
            <div class="barcode-container">
                <?php foreach ($lots as $index => $lot): ?>
                    <?php
                        $price = floatval($lot['price'] ?? 0);
                        $price_fmt = $price > 0 ? number_format($price, 0, ',', '.') . 'đ' : '';
                        $variant = $lot['variant'] ?? null;
                        $variant_label = '';
                        if ($variant && $show_variant) {
                            $variant_label = esc_html($variant['variant_label'] . ': ' . $variant['variant_value']);
                        }
                    ?>
                    <div class="barcode-item">
                        <svg id="barcode-<?php echo $index; ?>"></svg>
                        <div class="product-name"><?php echo esc_html($lot['product_name'] ?? 'N/A'); ?></div>
                        <?php if ($show_price && $price_fmt): ?>
                            <div class="product-price"><?php echo $price_fmt; ?></div>
                        <?php endif; ?>
                        <?php if ($variant_label): ?>
                            <div class="variant-info"><?php echo $variant_label; ?></div>
                        <?php endif; ?>
                        <?php if ($show_lot_info): ?>
                            <div class="lot-info">
                                <?php if (!empty($lot['lot_code'])): ?>Lô: <?php echo esc_html($lot['lot_code']); ?><?php endif; ?>
                                <?php if (!empty($lot['exp_date']) && $lot['exp_date'] !== '0000-00-00'): ?> | HSD: <?php echo date('d/m/Y', strtotime($lot['exp_date'])); ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
                <?php foreach ($lots as $index => $lot):
                    $bc = $lot['global_product_lot_barcode'];
                    $is_legacy = strpos($bc, 'tgs_') === 0;
                    $fmt = $is_legacy ? 'CODE128' : 'EAN13';
                ?>
                JsBarcode("#barcode-<?php echo $index; ?>", "<?php echo esc_js($bc); ?>", {
                    format: "<?php echo $fmt; ?>",
                    width: <?php echo $is_legacy ? '1.2' : '2'; ?>,
                    height: 42,
                    displayValue: true,
                    fontSize: 13,
                    font: "Arial",
                    margin: 0, marginTop: 0, marginBottom: 0,
                    textMargin: 1
                });
                <?php endforeach; ?>
            </script>
        </body>
        </html>
        <?php
    }

    /* =========================================================================
     * G. QUICK-CREATE (Modal thêm nhanh SP / Biến thể)
     * ========================================================================= */

    /**
     * Sinh SKU ngẫu nhiên (giống tgs_shop_product_generate_sku)
     */
    public static function tgs_lot_gen_generate_sku()
    {
        self::verify();
        global $wpdb;

        $table = defined('TGS_TABLE_LOCAL_PRODUCT_NAME') ? TGS_TABLE_LOCAL_PRODUCT_NAME : $wpdb->prefix . 'local_product_name';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sku = '1' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE local_product_sku = %s AND (is_deleted IS NULL OR is_deleted = 0)",
                $sku
            ));
            if ($exists == 0) {
                self::json_ok(['sku' => $sku]);
                return;
            }
        }
        self::json_err('Không thể tạo mã SKU. Thử lại.');
        return;
    }

    /**
     * Thêm nhanh sản phẩm (từ modal, không cần ảnh/gallery)
     * Mặc định bật theo dõi lô hàng (is_tracking=1), trạng thái hoạt động
     */
    public static function tgs_lot_gen_quick_create_product()
    {
        self::verify();
        global $wpdb;

        $name    = sanitize_text_field($_POST['product_name'] ?? '');
        $sku     = sanitize_text_field($_POST['product_sku'] ?? '');
        $barcode = sanitize_text_field($_POST['product_barcode'] ?? '');
        $price_after_tax = floatval($_POST['product_price_after_tax'] ?? 0);
        $tax     = floatval($_POST['product_tax'] ?? 8);
        $price   = floatval($_POST['product_price'] ?? 0);
        $unit    = sanitize_text_field($_POST['product_unit'] ?? 'Lon');
        $status  = intval($_POST['product_status'] ?? 1);

        if (empty($name)) { self::json_err('Tên sản phẩm không được trống.'); return; }
        if (empty($sku))  { self::json_err('Mã SKU không được trống.'); return; }

        $table = defined('TGS_TABLE_LOCAL_PRODUCT_NAME') ? TGS_TABLE_LOCAL_PRODUCT_NAME : $wpdb->prefix . 'local_product_name';

        // Check SKU unique
        $sku_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE local_product_sku = %s AND (is_deleted IS NULL OR is_deleted = 0)",
            $sku
        ));
        if ($sku_exists > 0) { self::json_err('Mã SKU đã tồn tại.'); return; }

        $now = current_time('mysql');
        $meta = wp_json_encode([
            'sku'     => $sku,
            'weight'  => 0,
            'unit'    => $unit,
            'brand'   => '',
            'origin'  => '',
            'gallery' => [],
            'created_via' => 'tgs_lot_generator_quick_create',
        ], JSON_UNESCAPED_UNICODE);

        $wpdb->insert($table, [
            'local_product_name'            => $name,
            'local_product_sku'             => $sku,
            'local_product_barcode_main'    => $barcode,
            'local_product_price'           => $price,
            'local_product_tax'             => $tax,
            'local_product_price_after_tax' => $price_after_tax,
            'local_product_unit'            => $unit,
            'local_product_status'          => $status ? 'active' : 'inactive',
            'local_product_is_tracking'     => 1, // Luôn bật tracking
            'local_product_meta'            => $meta,
            'local_product_thumbnail'       => '',
            'user_id'                       => get_current_user_id(),
            'is_deleted'                    => 0,
            'created_at'                    => $now,
            'updated_at'                    => $now,
        ]);

        $new_id = $wpdb->insert_id;
        if (!$new_id) {
            self::json_err('Không thể tạo sản phẩm. DB: ' . $wpdb->last_error);
            return;
        }

        // Trả về product data đầy đủ để JS tự chọn ngay
        self::json_ok([
            'product' => [
                'local_product_name_id'         => $new_id,
                'local_product_name'            => $name,
                'local_product_sku'             => $sku,
                'local_product_barcode_main'    => $barcode,
                'local_product_price_after_tax' => $price_after_tax,
                'local_product_unit'            => $unit,
                'local_product_thumbnail'       => '',
                'local_product_is_tracking'     => 1,
            ]
        ], 'Đã thêm sản phẩm "' . $name . '" thành công!');
    }
}
