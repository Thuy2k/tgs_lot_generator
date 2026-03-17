<?php
/**
 * Modal thêm nhanh sản phẩm
 * Include vào create.php và variants/list.php
 *
 * Mặc định: bật theo dõi lô hàng, trạng thái hoạt động, VAT 8%
 * SKU tự sinh, giá bán / thuế / giá trước thuế liên quan nhau
 *
 * @package tgs_lot_generator
 */
if (!defined('ABSPATH')) exit;
?>

<!-- Modal: Thêm nhanh sản phẩm -->
<div class="modal fade" id="modalQuickProduct" tabindex="-1" aria-labelledby="modalQuickProductLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title" id="modalQuickProductLabel">
                    <i class="bx bx-plus-circle me-1"></i>Thêm nhanh sản phẩm
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickProductForm" autocomplete="off">
                <div class="modal-body">
                    <!-- Note nhắc -->
                    <div class="alert alert-info border-0 py-2 mb-3" style="font-size:13px;">
                        <i class="bx bx-info-circle me-1"></i>
                        Sản phẩm sẽ được tạo với <strong>theo dõi lô hàng BẬT sẵn</strong>. Bạn có thể chỉnh sửa chi tiết sau ở trang Quản lý sản phẩm.
                    </div>

                    <div class="row">
                        <!-- Cột trái: Thông tin cơ bản -->
                        <div class="col-md-7">
                            <!-- Ảnh demo + Tên SP -->
                            <div class="d-flex align-items-start mb-3">
                                <div class="qp-thumb-wrap me-3 flex-shrink-0">
                                    <img id="qpThumb" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='8' fill='%23f0f4ff'/%3E%3Ctext x='32' y='36' text-anchor='middle' font-size='24' fill='%23696cff'%3E🧃%3C/text%3E%3C/svg%3E"
                                         alt="Ảnh SP" class="rounded" style="width:64px; height:64px; object-fit:cover; border:2px solid #e8e8e8;" />
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label fw-semibold">Tên sản phẩm <span class="text-danger">*</span></label>
                                    <input type="text" id="qpName" class="form-control" placeholder="VD: Sữa Ensure Gold 850g" required />
                                </div>
                            </div>

                            <!-- SKU (tự sinh) -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mã SKU <span class="badge bg-label-success ms-1">Tự sinh</span></label>
                                <div class="input-group">
                                    <input type="text" id="qpSku" class="form-control bg-light" readonly />
                                    <button type="button" class="btn btn-outline-primary" id="qpRefreshSku" title="Tạo SKU mới">
                                        <i class="bx bx-refresh"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Barcode nhà sản xuất -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Barcode nhà sản xuất (chuỗi)</label>
                                <input type="text" id="qpBarcode" class="form-control" placeholder="VD: 8934680012345" />
                                <div class="form-text">Mã vạch trên hộp sữa gốc từ nhà sản xuất</div>
                            </div>

                            <!-- Đơn vị tính -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Đơn vị tính</label>
                                <select id="qpUnit" class="form-select">
                                    <option value="Lon" selected>Lon</option>
                                    <option value="Hộp">Hộp</option>
                                    <option value="Chai">Chai</option>
                                    <option value="Gói">Gói</option>
                                    <option value="Túi">Túi</option>
                                    <option value="Thùng">Thùng</option>
                                    <option value="Cái">Cái</option>
                                    <option value="Bịch">Bịch</option>
                                    <option value="Lốc">Lốc</option>
                                    <option value="Kg">Kg</option>
                                    <option value="Gram">Gram</option>
                                    <option value="Lít">Lít</option>
                                    <option value="ml">ml</option>
                                </select>
                            </div>
                        </div>

                        <!-- Cột phải: Giá bán -->
                        <div class="col-md-5">
                            <div class="card bg-light border-0">
                                <div class="card-body py-3">
                                    <h6 class="fw-bold mb-3"><i class="bx bx-money me-1"></i>Giá bán</h6>

                                    <!-- Giá bán sau thuế (primary) -->
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Giá bán sau thuế <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" id="qpPriceAfterTax" class="form-control fw-bold" placeholder="0" />
                                            <span class="input-group-text">đ</span>
                                        </div>
                                    </div>

                                    <!-- Thuế VAT -->
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Thuế VAT</label>
                                        <div class="input-group">
                                            <input type="number" id="qpTax" class="form-control" value="8" min="0" max="100" step="0.5" />
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>

                                    <!-- Giá trước thuế (tự tính) -->
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Giá trước thuế <span class="badge bg-label-secondary">Tự tính</span></label>
                                        <div class="input-group">
                                            <input type="text" id="qpPriceBeforeTax" class="form-control bg-white" disabled />
                                            <span class="input-group-text">đ</span>
                                        </div>
                                    </div>

                                    <hr class="my-3" />

                                    <!-- Trạng thái -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-semibold" style="font-size:13px;">Trạng thái</span>
                                        <div class="form-check form-switch mb-0">
                                            <input type="checkbox" class="form-check-input" id="qpStatus" checked />
                                            <label class="form-check-label" id="qpStatusLabel" for="qpStatus">Hoạt động</label>
                                        </div>
                                    </div>

                                    <!-- Theo dõi lô hàng (luôn bật, readonly) -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold" style="font-size:13px;">Theo dõi lô hàng</span>
                                        <span class="badge bg-success"><i class="bx bx-check me-1"></i>Bật</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i>Hủy
                    </button>
                    <button type="submit" class="btn btn-primary" id="qpBtnSave">
                        <i class="bx bx-check me-1"></i>Tạo sản phẩm & chọn ngay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
