<?php
/**
 * Sinh mã định danh — Trang tạo mới
 *
 * Flow:
 * 1. Tìm sản phẩm có bật tracking (autocomplete) — hoặc thêm nhanh bằng modal
 * 2. Chọn biến thể (nếu có) — hoặc thêm nhanh biến thể bằng modal
 * 3. Nhập số lượng, lô code, HSD
 * 4. Bấm "Sinh mã" → tạo phiếu + lots
 * 5. Chuyển tới trang chi tiết
 *
 * @package tgs_lot_generator
 */

if (!defined('ABSPATH')) exit;
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-barcode me-2"></i>Tạo mã định danh sản phẩm
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Chỉ hiển thị sản phẩm đã bật <strong>theo dõi lô hàng</strong>. Chưa có? Bấm "Thêm nhanh SP".</p>
        </div>
        <a href="<?php echo function_exists('tgs_url') ? tgs_url('lot-gen-list') : '#'; ?>" class="btn btn-outline-secondary">
            <i class="bx bx-list-ul me-1"></i>DS Phiếu
        </a>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <form id="lotGenForm" autocomplete="off">

                <!-- ① Tìm sản phẩm -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary rounded-pill me-2" style="font-size:12px;">1</span>
                            <label class="form-label fw-semibold mb-0">Sản phẩm <span class="text-danger">*</span></label>
                            <span class="badge bg-label-success ms-2" style="font-size:10px;"><i class="bx bx-check me-1"></i>Chỉ SP theo dõi lô</span>
                        </div>
                        <div class="position-relative gen-search-wrap">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bx bx-search text-muted"></i></span>
                                <input type="text" id="productSearch" class="form-control border-start-0" placeholder="Gõ tên sản phẩm, mã barcode hoặc SKU…" />
                            </div>
                            <input type="hidden" id="productId" name="product_id" value="" />
                            <div id="productDropdown" class="gen-product-dropdown" style="display:none;"></div>
                        </div>
                        <!-- Thông tin SP đã chọn -->
                        <div id="productInfo" class="mt-2" style="display:none;">
                            <div class="alert alert-success border-0 py-2 px-3 mb-0">
                                <div class="d-flex align-items-center">
                                    <img id="productThumb" src="" alt="" class="rounded me-2 flex-shrink-0" style="width:40px; height:40px; object-fit:cover; border:1px solid #ddd;" />
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span id="productName" class="fw-semibold"></span>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="clearProduct">
                                                <i class="bx bx-x me-1"></i>Đổi SP
                                            </button>
                                        </div>
                                        <div class="mt-1" style="font-size:12px;">
                                            <span class="badge bg-label-secondary me-1">SKU: <b id="productSku">—</b></span>
                                            <span class="badge bg-label-secondary me-1">Barcode: <b id="productBarcode">—</b></span>
                                            <span class="badge bg-label-secondary me-1">ĐVT: <b id="productUnit">—</b></span>
                                            <span class="badge bg-label-primary">Giá: <b id="productPrice">—</b></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">&nbsp;</label>
                        <div id="stockInfo" class="border rounded p-2 text-center text-muted" style="min-height:38px; line-height:38px;">
                            Chưa chọn SP
                        </div>
                    </div>
                </div>

                <!-- ② Biến thể -->
                <div class="row mb-3" id="variantRow" style="display:none;">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary rounded-pill me-2" style="font-size:12px;">2</span>
                            <label class="form-label fw-semibold mb-0">Biến thể (tùy chọn)</label>
                        </div>
                        <select id="variantSelect" class="form-select" name="variant_id">
                            <option value="0">-- Không chọn biến thể --</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-info" id="btnQuickVariant">
                            <i class="bx bx-plus me-1"></i>Thêm nhanh biến thể
                        </button>
                    </div>
                </div>

                <!-- ③ Số lượng + Lô -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary rounded-pill me-2" style="font-size:12px;">3</span>
                            <label class="form-label fw-semibold mb-0">Số lượng <span class="text-danger">*</span></label>
                        </div>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="1" max="5000" value="1" />
                        <small class="text-muted">Tối đa 5000</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Mã lô (Lot Code)</label>
                        <input type="text" id="lotCode" name="lot_code" class="form-control" placeholder="VD: LOT-2026-01" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Hạn sử dụng (EXP)</label>
                        <div class="input-group">
                            <input type="text" id="expDateDisplay" class="form-control" placeholder="dd/mm/yyyy" maxlength="10" autocomplete="off" />
                            <input type="date" id="expDatePicker" class="form-control" style="max-width:42px; padding-left:0; padding-right:8px; opacity:0.6; cursor:pointer;" tabindex="-1" />
                        </div>
                        <input type="hidden" id="expDate" name="exp_date" value="" />
                    </div>
                </div>

                <!-- Ghi chú -->
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Ghi chú</label>
                        <textarea id="note" name="note" class="form-control" rows="2" placeholder="Ghi chú tùy chọn..."></textarea>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-flex gap-2">
                    <button type="submit" id="btnGenerate" class="btn btn-primary btn-lg">
                        <i class="bx bx-barcode me-1"></i>Sinh mã định danh
                    </button>
                    <button type="reset" class="btn btn-outline-secondary btn-lg" id="btnReset">
                        <i class="bx bx-refresh me-1"></i>Làm mới
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading overlay -->
    <div id="genOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:40px; border-radius:8px; text-align:center;">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <div id="genProgress" class="fw-semibold">Đang sinh mã...</div>
        </div>
    </div>
</div>

<?php
// Include modals
include TGS_LOT_GEN_VIEWS . 'components/modal-quick-product.php';
include TGS_LOT_GEN_VIEWS . 'components/modal-quick-variant.php';
?>
