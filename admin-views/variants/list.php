<?php
/**
 * Quản lý biến thể sản phẩm
 *
 * CRUD biến thể (size, color, expiry, flavor, weight, custom)
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
                <i class="bx bx-palette me-2"></i>Quản lý biến thể sản phẩm
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Thêm các biến thể (Size, Màu, HSD, Hương vị…) cho sản phẩm trước khi sinh mã định danh.</p>
        </div>
        <a href="<?php echo function_exists('tgs_url') ? tgs_url('lot-gen-create') : '#'; ?>" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i>Sinh mã
        </a>
    </div>

    <!-- Bước 1: Chọn sản phẩm -->
    <div class="card mb-4 var-search-card">
        <div class="card-body pb-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-primary rounded-pill me-2" style="font-size:12px;">1</span>
                <label class="form-label fw-semibold mb-0">Chọn sản phẩm</label>
            </div>
            <div class="position-relative var-search-wrap">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bx bx-search text-muted"></i></span>
                    <input type="text" id="varProductSearch" class="form-control border-start-0" placeholder="Gõ tên sản phẩm, mã barcode hoặc SKU để tìm kiếm…" />
                </div>
                <input type="hidden" id="varProductId" value="" />
                <div id="varProductDropdown" class="var-product-dropdown" style="display:none;"></div>
            </div>
            <div id="varProductInfo" class="mt-2" style="display:none;">
                <div class="alert alert-success border-0 py-2 px-3 mb-0 d-flex align-items-center">
                    <i class="bx bx-check-circle me-2 fs-5"></i>
                    <span id="varProductName" class="fw-semibold"></span>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="varClearProduct">
                        <i class="bx bx-x me-1"></i>Đổi SP
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bước 2: Thêm & Xem biến thể -->
    <div id="variantWorkArea" style="opacity:0.5; pointer-events:none; transition: opacity 0.3s;">
        <div class="d-flex align-items-center mb-3">
            <span class="badge bg-primary rounded-pill me-2" style="font-size:12px;">2</span>
            <span class="fw-semibold">Thêm & quản lý biến thể</span>
            <span id="varProductNameBadge" class="badge bg-label-primary ms-2" style="display:none;"></span>
        </div>

        <div class="row">
            <!-- Form thêm/sửa -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header py-3">
                        <h5 class="mb-0" id="varFormTitle"><i class="bx bx-plus me-1"></i>Thêm biến thể</h5>
                    </div>
                    <div class="card-body">
                        <form id="variantForm" autocomplete="off">
                            <input type="hidden" id="varEditId" value="0" />

                            <!-- Loại biến thể -->
                            <div class="mb-3">
                                <label class="form-label">Loại biến thể <span class="text-danger">*</span></label>
                                <select id="varType" class="form-select" name="variant_type">
                                    <option value="size" data-icon="bx-ruler" data-label="Size" data-value-hint="S, M, L, XL, XXL" data-sku-hint="-S, -M, -L">📐 Size / Kích cỡ</option>
                                    <option value="color" data-icon="bx-palette" data-label="Màu sắc" data-value-hint="Trắng, Đen, Xanh, Đỏ" data-sku-hint="-WHT, -BLK, -BLU">🎨 Màu sắc</option>
                                    <option value="expiry" data-icon="bx-calendar" data-label="Hạn sử dụng" data-value-hint="6 tháng, 12 tháng, 24 tháng" data-sku-hint="-6M, -12M, -24M">📅 Hạn sử dụng</option>
                                    <option value="flavor" data-icon="bx-cookie" data-label="Hương vị" data-value-hint="Vani, Dâu, Socola, Trái cây" data-sku-hint="-VAN, -STR, -CHO">🍓 Hương vị</option>
                                    <option value="weight" data-icon="bx-dumbbell" data-label="Trọng lượng" data-value-hint="200g, 400g, 800g, 1kg" data-sku-hint="-200G, -400G, -1KG">⚖️ Trọng lượng</option>
                                    <option value="custom" data-icon="bx-customize" data-label="" data-value-hint="" data-sku-hint="">⚙️ Tùy chọn (Custom)</option>
                                </select>
                                <div id="varTypeHint" class="form-text text-info mt-1" style="display:none;">
                                    <i class="bx bx-info-circle me-1"></i><span id="varTypeHintText"></span>
                                </div>
                            </div>

                            <!-- Nhãn -->
                            <div class="mb-3">
                                <label class="form-label">Nhãn (Label) <span class="text-danger">*</span></label>
                                <input type="text" id="varLabel" class="form-control" name="variant_label" placeholder="VD: Size, Màu, HSD..." />
                                <div class="form-text">Tên thuộc tính hiển thị trên tem barcode</div>
                            </div>

                            <!-- Giá trị -->
                            <div class="mb-3">
                                <label class="form-label">Giá trị (Value) <span class="text-danger">*</span></label>
                                <input type="text" id="varValue" class="form-control" name="variant_value" placeholder="VD: S, M, L, Xanh, Đỏ..." />
                                <div id="varValueChips" class="mt-1" style="display:none;"></div>
                                <div class="form-text">Giá trị cụ thể. Click gợi ý phía trên để điền nhanh.</div>
                            </div>

                            <!-- SKU Suffix -->
                            <div class="mb-3">
                                <label class="form-label">SKU Suffix</label>
                                <input type="text" id="varSkuSuffix" class="form-control" name="variant_sku_suffix" placeholder="VD: -S, -M, -L" />
                                <div class="form-text">Đuôi SKU thêm vào mã SP gốc. VD: SP001<strong>-L</strong></div>
                            </div>

                            <!-- Điều chỉnh giá -->
                            <div class="mb-3">
                                <label class="form-label">Điều chỉnh giá (±)</label>
                                <div class="input-group">
                                    <input type="number" id="varPriceAdj" class="form-control" name="variant_price_adjustment" value="0" step="1000" />
                                    <span class="input-group-text">đ</span>
                                </div>
                                <div class="form-text">Cộng/trừ so với giá gốc. VD: +5000 = đắt hơn, -2000 = rẻ hơn</div>
                            </div>

                            <!-- Thứ tự -->
                            <div class="mb-3">
                                <label class="form-label">Thứ tự hiển thị</label>
                                <input type="number" id="varSort" class="form-control" name="variant_sort_order" value="0" min="0" />
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" id="varBtnSave">
                                    <i class="bx bx-save me-1"></i>Lưu
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="varBtnCancel" style="display:none;">
                                    <i class="bx bx-x me-1"></i>Hủy sửa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Danh sách -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0"><i class="bx bx-list-ul me-1"></i>Danh sách biến thể</h5>
                        <span class="badge bg-label-primary" id="varCount">0</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Loại</th>
                                    <th>Nhãn</th>
                                    <th>Giá trị</th>
                                    <th>SKU Suffix</th>
                                    <th>± Giá</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="varTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="bx bx-package text-muted" style="font-size:48px;"></i>
                                        <p class="text-muted mt-2 mb-0">Chọn sản phẩm ở bước 1 để xem & thêm biến thể</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include modal thêm nhanh sản phẩm (cho trường hợp SP chưa tồn tại)
include TGS_LOT_GEN_VIEWS . 'components/modal-quick-product.php';
?>
