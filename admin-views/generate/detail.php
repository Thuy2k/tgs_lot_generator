<?php
/**
 * Chi tiết phiếu sinh mã
 *
 * URL: ?page=tgs-shop-management&view=lot-gen-detail&ledger_id=XXX
 *
 * Hiển thị:
 * - Thông tin phiếu
 * - Danh sách lots (barcode, trạng thái, biến thể)
 * - Nút: Kích hoạt / Hủy kích hoạt / Xóa / In
 *
 * @package tgs_lot_generator
 */

if (!defined('ABSPATH')) exit;

$ledger_id = intval($_GET['ledger_id'] ?? 0);
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bx bx-detail me-2"></i>Chi tiết phiếu sinh mã
        </h4>
        <div>
            <a href="<?php echo function_exists('tgs_url') ? tgs_url('lot-gen-list') : '#'; ?>" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i>Quay lại
            </a>
            <a href="<?php echo function_exists('tgs_url') ? tgs_url('lot-gen-create') : '#'; ?>" class="btn btn-primary ms-1">
                <i class="bx bx-plus me-1"></i>Sinh mã mới
            </a>
        </div>
    </div>

    <input type="hidden" id="detailLedgerId" value="<?php echo $ledger_id; ?>" />

    <!-- Thông tin phiếu -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bx bx-file me-1"></i>Thông tin phiếu</h5>
            <span class="badge bg-label-primary" id="ticketCode">-</span>
        </div>
        <div class="card-body">
            <div class="row" id="ledgerInfo">
                <div class="col-md-3"><strong>Tiêu đề:</strong> <span id="infoTitle">-</span></div>
                <div class="col-md-3"><strong>Người tạo:</strong> <span id="infoUser">-</span></div>
                <div class="col-md-3"><strong>Ngày tạo:</strong> <span id="infoDate">-</span></div>
                <div class="col-md-3"><strong>Ghi chú:</strong> <span id="infoNote">-</span></div>
            </div>
            <div class="row mt-2" id="statsRow">
                <div class="col-md-3"><span class="badge bg-label-secondary">Tổng: <b id="statTotal">0</b></span></div>
                <div class="col-md-3"><span class="badge bg-label-warning">Đã sinh: <b id="statGenerated">0</b></span></div>
                <div class="col-md-3"><span class="badge bg-label-success">Đã kích hoạt: <b id="statActive">0</b></span></div>
                <div class="col-md-3"><span class="badge bg-label-danger">Đã xóa: <b id="statDeleted">0</b></span></div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnSelectAll">
                    <i class="bx bx-check-square me-1"></i>Chọn tất cả
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDeselectAll">
                    <i class="bx bx-square me-1"></i>Bỏ chọn
                </button>
                <span class="badge bg-label-primary" id="selectedCountBadge" style="display:none;">Đã chọn: <b id="selectedCount">0</b></span>
                <div class="vr"></div>
                <button type="button" class="btn btn-sm btn-success" id="btnActivate" disabled>
                    <i class="bx bx-check-circle me-1"></i>Kích hoạt (<span id="activateCount">0</span>)
                </button>
                <button type="button" class="btn btn-sm btn-warning" id="btnDeactivate" disabled>
                    <i class="bx bx-minus-circle me-1"></i>Hủy kích hoạt (<span id="deactivateCount">0</span>)
                </button>
                <button type="button" class="btn btn-sm btn-danger" id="btnDelete" disabled>
                    <i class="bx bx-trash me-1"></i>Xóa (<span id="deleteCount">0</span>)
                </button>
                <div class="vr"></div>
                <button type="button" class="btn btn-sm btn-info" id="btnPrint" disabled>
                    <i class="bx bx-printer me-1"></i>In mã định danh (<span id="printCount">0</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- Cấu hình in -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="text-muted fw-semibold" style="font-size:13px;"><i class="bx bx-cog me-1"></i>Hiển thị trên nhãn in:</span>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optShowPrice" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Giá bán
                </button>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optShowVariant" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Biến thể
                </button>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optShowLot" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Lô / HSD
                </button>
                <span class="text-muted ms-2" style="font-size:11px;">Bấm để bật/tắt • Chỉ áp dụng khi in mã định danh</span>
            </div>
        </div>
    </div>

    <!-- Bảng lots -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;"><input type="checkbox" class="form-check-input" id="checkAll" /></th>
                        <th>#</th>
                        <th>Mã định danh</th>
                        <th>Sản phẩm</th>
                        <th>Biến thể</th>
                        <th>Lô</th>
                        <th>HSD</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="lotsTableBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
