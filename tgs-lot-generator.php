<?php
/**
 * Plugin Name: TGS Lot Generator
 * Description: Sinh mã định danh tự động + Quản lý biến thể sản phẩm
 * Version: 1.0.0
 * Author: TGS Dev Team
 * Requires Plugins: tgs_shop_management
 */

if (!defined('ABSPATH')) exit;

// ── Constants ────────────────────────────────────────────────────────────────
define('TGS_LOT_GEN_VERSION', '1.0.0');
define('TGS_LOT_GEN_DIR', plugin_dir_path(__FILE__));
define('TGS_LOT_GEN_URL', plugin_dir_url(__FILE__));
define('TGS_LOT_GEN_VIEWS', TGS_LOT_GEN_DIR . 'admin-views/');

// Ledger type mới
if (!defined('TGS_LEDGER_TYPE_LOT_GENERATE')) {
    define('TGS_LEDGER_TYPE_LOT_GENERATE', 16);
}

// Bảng biến thể
if (!defined('TGS_TABLE_GLOBAL_PRODUCT_VARIANTS')) {
    define('TGS_TABLE_GLOBAL_PRODUCT_VARIANTS', 'wp_global_product_variants');
}

// ── Init ─────────────────────────────────────────────────────────────────────
function tgs_lot_gen_init()
{
    // Dependency check
    if (!class_exists('TGS_Shop_Management') && !defined('TGS_SHOP_PLUGIN_DIR')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>TGS Lot Generator</strong> cần plugin <strong>TGS Shop Management</strong> được kích hoạt.</p></div>';
        });
        return;
    }

    // Load includes
    require_once TGS_LOT_GEN_DIR . 'includes/class-lot-generator-database.php';
    require_once TGS_LOT_GEN_DIR . 'includes/class-lot-generator-ajax.php';

    // Create / migrate tables
    TGS_Lot_Generator_Database::maybe_create_tables();

    // Register AJAX
    TGS_Lot_Generator_Ajax::register();
}
add_action('plugins_loaded', 'tgs_lot_gen_init', 25);

// ── Routes ───────────────────────────────────────────────────────────────────
add_filter('tgs_shop_dashboard_routes', function ($routes) {
    $dir = TGS_LOT_GEN_VIEWS;
    $routes['lot-gen-create']  = ['Sinh mã định danh', $dir . 'generate/create.php'];
    $routes['lot-gen-list']    = ['DS phiếu sinh mã',  $dir . 'generate/list.php'];
    $routes['lot-gen-detail']  = ['Chi tiết phiếu',     $dir . 'generate/detail.php'];
    $routes['lot-gen-variants'] = ['Quản lý biến thể',  $dir . 'variants/list.php'];
    return $routes;
});

// ── Sidebar Menu ─────────────────────────────────────────────────────────────
add_action('tgs_shop_sidebar_menu', function ($current_view) {
    $views = ['lot-gen-create', 'lot-gen-list', 'lot-gen-detail', 'lot-gen-variants'];
    $is_active = in_array($current_view, $views);
    $open = $is_active ? ' active open' : '';
    $href = function_exists('tgs_url') ? function ($v) { return tgs_url($v); } : function ($v) {
        return admin_url('admin.php?page=tgs-shop-management&view=' . $v);
    };
    ?>
    <li class="menu-item<?php echo $open; ?>">
        <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-barcode"></i>
            <div>Sinh mã định danh</div>
        </a>
        <ul class="menu-sub">
            <li class="menu-item<?php echo $current_view === 'lot-gen-create' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url($href('lot-gen-create')); ?>" class="menu-link">
                    <div>Sinh mã mới</div>
                </a>
            </li>
            <li class="menu-item<?php echo $current_view === 'lot-gen-list' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url($href('lot-gen-list')); ?>" class="menu-link">
                    <div>DS phiếu sinh mã</div>
                </a>
            </li>
            <li class="menu-item<?php echo $current_view === 'lot-gen-variants' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url($href('lot-gen-variants')); ?>" class="menu-link">
                    <div>Quản lý biến thể</div>
                </a>
            </li>
        </ul>
    </li>
    <?php
});

// ── Enqueue Assets ───────────────────────────────────────────────────────────
add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'tgs-shop-management') === false) return;

    $view = sanitize_text_field($_GET['view'] ?? '');
    if (strpos($view, 'lot-gen') !== 0) return;

    wp_enqueue_style('tgs-lot-gen-css', TGS_LOT_GEN_URL . 'assets/css/lot-generator.css', [], TGS_LOT_GEN_VERSION);

    if ($view === 'lot-gen-variants') {
        wp_enqueue_script('tgs-lot-gen-variant', TGS_LOT_GEN_URL . 'assets/js/variant-manager.js', ['jquery'], TGS_LOT_GEN_VERSION, true);
        wp_localize_script('tgs-lot-gen-variant', 'tgsLotGen', tgs_lot_gen_localize_data());
    }
    if ($view === 'lot-gen-create') {
        wp_enqueue_script('tgs-lot-gen-create', TGS_LOT_GEN_URL . 'assets/js/lot-generate.js', ['jquery'], TGS_LOT_GEN_VERSION, true);
        wp_localize_script('tgs-lot-gen-create', 'tgsLotGen', tgs_lot_gen_localize_data());
    }
    if ($view === 'lot-gen-detail') {
        wp_enqueue_script('tgs-lot-gen-detail', TGS_LOT_GEN_URL . 'assets/js/lot-detail.js', ['jquery'], TGS_LOT_GEN_VERSION, true);
        wp_localize_script('tgs-lot-gen-detail', 'tgsLotGen', tgs_lot_gen_localize_data());
    }
    if ($view === 'lot-gen-list') {
        wp_enqueue_script('tgs-lot-gen-list', TGS_LOT_GEN_URL . 'assets/js/lot-list.js', ['jquery'], TGS_LOT_GEN_VERSION, true);
        wp_localize_script('tgs-lot-gen-list', 'tgsLotGen', tgs_lot_gen_localize_data());
    }
});

function tgs_lot_gen_localize_data()
{
    return [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('tgs_lot_gen_nonce'),
        'blogId'  => get_current_blog_id(),
    ];
}
