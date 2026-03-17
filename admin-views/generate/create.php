<?php
/**
 * Sinh mã định danh — Trang tạo mới
 *
 * Flow:
 * 1. Tìm sản phẩm (autocomplete)
 * 2. Chọn biến thể (nếu có)
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
        <h4 class="fw-bold mb-0">
            <i class="bx bx-barcode me-2"></i>Sinh mã định danh mới
        </h4>
        <a href="<?php echo function_exists('tgs_url') ? tgs_url('lot-gen-list') : '#'; ?>" class="btn btn-outline-secondary">
            <i class="bx bx-list-ul me-1"></i>DS Phiếu
        </a>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <form id="lotGenForm" autocomplete="off">

                <!-- Tìm sản phẩm -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Sản phẩm <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="text" id="productSearch" class="form-control" placeholder="Nhập tên, barcode hoặc SKU để tìm sản phẩm..." />
                            <input type="hidden" id="productId" name="product_id" value="" />
                            <div id="productDropdown" class="dropdown-menu w-100" style="display:none; max-height:300px; overflow-y:auto;"></div>
                        </div>
                        <div id="productInfo" class="mt-2" style="display:none;">
                            <div class="alert alert-light border py-2 px-3 mb-0">
                                <div class="d-flex justify-content-between">
                                    <span id="productName" class="fw-semibold"></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="clearProduct"><i class="bx bx-x"></i></button>
                                </div>
                                <small class="text-muted">
                                    Barcode: <span id="productBarcode">-</span> |
                                    SKU: <span id="productSku">-</span> |
                                    ĐVT: <span id="productUnit">-</span> |
                                    Giá: <span id="productPrice">-</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tồn kho hiện tại</label>
                        <div id="stockInfo" class="border rounded p-2 text-center text-muted" style="min-height:38px; line-height:38px;">
                            Chưa chọn SP
                        </div>
                    </div>
                </div>

                <!-- Biến thể -->
                <div class="row mb-3" id="variantRow" style="display:none;">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Biến thể (tùy chọn)</label>
                        <select id="variantSelect" class="form-select" name="variant_id">
                            <option value="0">-- Không chọn biến thể --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">&nbsp;</label>
                        <div>
                            <a href="<?php echo function_exists('tgs_url') ? tgs_url('lot-gen-variants') : '#'; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bx bx-plus me-1"></i>Quản lý biến thể
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Số lượng + Lô -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Số lượng <span class="text-danger">*</span></label>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="1" max="5000" value="1" />
                        <small class="text-muted">Tối đa 5000</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Mã lô (Lot Code)</label>
                        <input type="text" id="lotCode" name="lot_code" class="form-control" placeholder="VD: LOT-2026-01" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Ngày sản xuất (MFG)</label>
                        <input type="date" id="mfgDate" name="mfg_date" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Hạn sử dụng (EXP)</label>
                        <input type="date" id="expDate" name="exp_date" class="form-control" />
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
