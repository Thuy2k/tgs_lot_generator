<?php
/**
 * Danh sách định danh sản phẩm (type=16)
 *
 * @package tgs_lot_generator
 */

if (!defined('ABSPATH')) exit;
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bx bx-list-ul me-2"></i>Danh sách định danh sản phẩm
        </h4>
        <a href="<?php echo function_exists('tgs_url') ? tgs_url('lot-gen-create') : '#'; ?>" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i>Tạo mã định danh
        </a>
    </div>

    <!-- Bộ lọc -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <input type="text" id="searchInput" class="form-control" placeholder="Tìm mã phiếu, tiêu đề..." />
                </div>
                <div class="col-md-3">
                    <button type="button" id="btnSearch" class="btn btn-primary">
                        <i class="bx bx-search me-1"></i>Tìm
                    </button>
                    <button type="button" id="btnRefresh" class="btn btn-outline-secondary ms-1">
                        <i class="bx bx-refresh"></i>
                    </button>
                </div>
                <div class="col-md-3 text-end">
                    <span class="text-muted" id="totalInfo">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Mã phiếu</th>
                        <th>Sản phẩm</th>
                        <th>Số mã</th>
                        <th>Người tạo</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="ledgerTableBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Phân trang -->
    <nav class="mt-3">
        <ul class="pagination justify-content-center" id="pagination"></ul>
    </nav>
</div>
