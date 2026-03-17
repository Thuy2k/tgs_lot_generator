<?php
/**
 * Modal thêm nhanh biến thể
 * Include vào create.php — cho phép thêm biến thể ngay tại trang sinh mã
 *
 * @package tgs_lot_generator
 */
if (!defined('ABSPATH')) exit;
?>

<!-- Modal: Thêm nhanh biến thể -->
<div class="modal fade" id="modalQuickVariant" tabindex="-1" aria-labelledby="modalQuickVariantLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-3">
                <h5 class="modal-title" id="modalQuickVariantLabel">
                    <i class="bx bx-palette me-1"></i>Thêm nhanh biến thể
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickVariantForm" autocomplete="off">
                <div class="modal-body">
                    <!-- SP đang chọn -->
                    <div class="alert alert-light border py-2 mb-3">
                        <small class="text-muted">Sản phẩm:</small>
                        <strong id="qvProductName">—</strong>
                    </div>

                    <!-- Loại biến thể -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Loại biến thể <span class="text-danger">*</span></label>
                        <select id="qvType" class="form-select">
                            <option value="size">📐 Size / Kích cỡ</option>
                            <option value="color">🎨 Màu sắc</option>
                            <option value="expiry">📅 Hạn sử dụng</option>
                            <option value="flavor">🍓 Hương vị</option>
                            <option value="weight">⚖️ Trọng lượng</option>
                            <option value="custom" selected>⚙️ Tùy chọn</option>
                        </select>
                    </div>

                    <!-- Nhãn -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nhãn (Label) <span class="text-danger">*</span></label>
                        <input type="text" id="qvLabel" class="form-control" placeholder="VD: Size, Màu, HSD..." />
                    </div>

                    <!-- Giá trị + chips -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Giá trị (Value) <span class="text-danger">*</span></label>
                        <input type="text" id="qvValue" class="form-control" placeholder="VD: S, M, L, Xanh, Đỏ..." />
                        <div id="qvValueChips" class="mt-1"></div>
                    </div>

                    <!-- SKU Suffix -->
                    <div class="mb-3">
                        <label class="form-label">SKU Suffix</label>
                        <input type="text" id="qvSkuSuffix" class="form-control" placeholder="VD: -S, -M" />
                    </div>

                    <!-- Điều chỉnh giá -->
                    <div class="mb-3">
                        <label class="form-label">Điều chỉnh giá (±)</label>
                        <div class="input-group">
                            <input type="number" id="qvPriceAdj" class="form-control" value="0" step="1000" />
                            <span class="input-group-text">đ</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i>Hủy
                    </button>
                    <button type="submit" class="btn btn-info text-white" id="qvBtnSave">
                        <i class="bx bx-check me-1"></i>Tạo biến thể & chọn ngay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
