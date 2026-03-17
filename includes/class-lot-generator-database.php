<?php
/**
 * TGS Lot Generator — Database
 *
 * - Tạo bảng wp_global_product_variants (trong plugin riêng, KHÔNG đụng class-tgs-database.php)
 * - Migration thêm cột variant_id vào wp_global_product_lots
 *
 * @package tgs_lot_generator
 */

if (!defined('ABSPATH')) exit;

class TGS_Lot_Generator_Database
{
    /** Option key lưu version DB hiện tại */
    const DB_VERSION_KEY = 'tgs_lot_gen_db_version';
    const DB_VERSION     = '1.0.0';

    /**
     * Kiểm tra + tạo/migrate bảng nếu cần
     */
    public static function maybe_create_tables()
    {
        $installed_ver = get_option(self::DB_VERSION_KEY, '0');
        if (version_compare($installed_ver, self::DB_VERSION, '>=')) {
            return;
        }

        self::create_tables();
        update_option(self::DB_VERSION_KEY, self::DB_VERSION);
    }

    /**
     * Tạo bảng + chạy migration
     */
    private static function create_tables()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        // 1. Bảng wp_global_product_variants
        $t = TGS_TABLE_GLOBAL_PRODUCT_VARIANTS;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) {
            dbDelta(self::sql_global_product_variants($charset));
        }

        // 2. Migration: thêm cột variant_id vào wp_global_product_lots
        self::migration_add_variant_id_to_lots();
    }

    /* =========================================================================
     * SQL tạo bảng
     * ========================================================================= */

    /**
     * Bảng GLOBAL wp_global_product_variants
     * Quản lý biến thể sản phẩm (size, color, expiry, custom)
     *
     * Bảng này KHÔNG có prefix của site (không phải wp_X_...)
     * Chỉ có DUY NHẤT 1 bảng trong toàn bộ hệ thống multisite
     */
    private static function sql_global_product_variants($charset_collate)
    {
        $table = TGS_TABLE_GLOBAL_PRODUCT_VARIANTS;
        return "CREATE TABLE {$table} (
            variant_id BIGINT NOT NULL AUTO_INCREMENT,
            local_product_name_id BIGINT NOT NULL,
            source_blog_id BIGINT NOT NULL,
            variant_type VARCHAR(50) NOT NULL DEFAULT 'custom',
            variant_label VARCHAR(255) NOT NULL DEFAULT '',
            variant_value VARCHAR(255) NOT NULL DEFAULT '',
            variant_sku_suffix VARCHAR(100) DEFAULT NULL,
            variant_barcode_main VARCHAR(500) DEFAULT NULL,
            variant_price_adjustment DECIMAL(15,3) DEFAULT 0,
            variant_sort_order INT DEFAULT 0,
            variant_meta JSON DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (variant_id),
            KEY idx_product_blog (local_product_name_id, source_blog_id),
            KEY idx_variant_type (variant_type),
            KEY idx_variant_sku_suffix (variant_sku_suffix),
            KEY idx_variant_barcode (variant_barcode_main),
            KEY idx_is_active (is_active),
            KEY idx_is_deleted (is_deleted)
        ) {$charset_collate};";
    }

    /* =========================================================================
     * Migrations
     * ========================================================================= */

    /**
     * Thêm cột variant_id vào wp_global_product_lots (idempotent)
     */
    private static function migration_add_variant_id_to_lots()
    {
        global $wpdb;
        $table = TGS_TABLE_GLOBAL_PRODUCT_LOTS;

        $col = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'variant_id'");
        if (!empty($col)) return; // Đã có rồi

        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `variant_id` BIGINT DEFAULT NULL AFTER `batch_id`");
        $wpdb->query("ALTER TABLE `{$table}` ADD KEY `idx_variant_id` (`variant_id`)");
    }
}
