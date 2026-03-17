/**
 * lot-detail.js — Chi tiết phiếu sinh mã
 *
 * Handles:
 * - Load detail + lots
 * - Select / deselect lots
 * - Activate / Deactivate / Delete lots
 * - Print barcodes
 *
 * @package tgs_lot_generator
 */
(function ($) {
    'use strict';

    const C = window.tgsLotGen || {};
    const ledgerId = parseInt($('#detailLedgerId').val()) || 0;
    let allLots = [];

    if (!ledgerId) {
        $('#lotsTableBody').html('<tr><td colspan="8" class="text-center text-danger py-4">Không có ledger_id. Vui lòng chọn phiếu từ danh sách.</td></tr>');
    }

    /* ── Load Detail ─────────────────────────────────────────────── */

    function loadDetail() {
        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_get_detail',
            nonce: C.nonce,
            ledger_id: ledgerId
        }, function (res) {
            if (!res.success) {
                alert('Lỗi: ' + (res.data?.message || 'Không xác định'));
                return;
            }

            const d = res.data;
            const l = d.ledger;
            const meta = d.meta || {};

            // Header
            $('#ticketCode').text(l.local_ledger_code);
            $('#infoTitle').text(l.local_ledger_title || '-');
            $('#infoUser').text(l.user_name || '-');
            $('#infoDate').text(l.created_at ? new Date(l.created_at).toLocaleString('vi-VN') : '-');
            $('#infoNote').text(l.local_ledger_note || '-');

            // Stats
            const s = d.stats;
            $('#statTotal').text(s.total);
            $('#statGenerated').text(s.generated);
            $('#statActive').text(s.active);
            $('#statDeleted').text(s.deleted);

            // Lots table
            allLots = d.lots || [];
            renderLots();
        });
    }

    /* ── Render Lots ─────────────────────────────────────────────── */

    function renderLots() {
        if (!allLots.length) {
            $('#lotsTableBody').html('<tr><td colspan="8" class="text-center py-4 text-muted">Không có mã nào.</td></tr>');
            return;
        }

        let html = '';
        allLots.forEach((lot, idx) => {
            const isDeleted = parseInt(lot.is_deleted) === 1;
            const status = parseInt(lot.local_product_lot_is_active);
            let statusBadge = '';
            if (isDeleted) statusBadge = '<span class="badge bg-label-danger">Đã xóa</span>';
            else if (status === 100) statusBadge = '<span class="badge bg-label-warning">Đã sinh</span>';
            else if (status === 1) statusBadge = '<span class="badge bg-label-success">Kho</span>';
            else if (status === 2) statusBadge = '<span class="badge bg-label-secondary">Chờ duyệt</span>';
            else if (status === 0) statusBadge = '<span class="badge bg-label-dark">Đã bán</span>';
            else statusBadge = `<span class="badge bg-label-secondary">${status}</span>`;

            const variant = lot.variant;
            const variantStr = variant ? `${variant.variant_label}: ${variant.variant_value}` : '-';
            const expStr = lot.exp_date && lot.exp_date !== '0000-00-00' ? new Date(lot.exp_date).toLocaleDateString('vi-VN') : '-';
            const rowClass = isDeleted ? 'table-danger text-decoration-line-through' : '';
            const canCheck = !isDeleted;

            html += `<tr class="${rowClass}" data-lot-id="${lot.global_product_lot_id}" data-status="${status}" data-deleted="${lot.is_deleted}">
                <td>${canCheck ? `<input type="checkbox" class="lot-check" value="${lot.global_product_lot_id}" />` : ''}</td>
                <td>${idx + 1}</td>
                <td><code>${escHtml(lot.global_product_lot_barcode)}</code></td>
                <td>${escHtml(lot.local_product_name || '-')}</td>
                <td>${escHtml(variantStr)}</td>
                <td>${escHtml(lot.lot_code || '-')}</td>
                <td>${expStr}</td>
                <td>${statusBadge}</td>
            </tr>`;
        });

        $('#lotsTableBody').html(html);
        updateToolbar();
    }

    /* ── Selection ───────────────────────────────────────────────── */

    $(document).on('change', '.lot-check, #checkAll', function () {
        if (this.id === 'checkAll') {
            $('.lot-check').prop('checked', this.checked);
        }
        updateToolbar();
    });

    $('#btnSelectAll').on('click', () => { $('.lot-check').prop('checked', true); $('#checkAll').prop('checked', true); updateToolbar(); });
    $('#btnDeselectAll').on('click', () => { $('.lot-check').prop('checked', false); $('#checkAll').prop('checked', false); updateToolbar(); });

    function getSelectedIds(filterFn) {
        const ids = [];
        $('.lot-check:checked').each(function () {
            const $tr = $(this).closest('tr');
            const lotId = parseInt($(this).val());
            if (!filterFn || filterFn($tr)) ids.push(lotId);
        });
        return ids;
    }

    function updateToolbar() {
        const allChecked = getSelectedIds();
        const canActivate = getSelectedIds($tr => $tr.data('status') == 100 && $tr.data('deleted') == 0);
        const canDeactivate = getSelectedIds($tr => $tr.data('status') == 1 && $tr.data('deleted') == 0);
        const canDelete = getSelectedIds($tr => $tr.data('status') == 100 && $tr.data('deleted') == 0);
        const canPrint = getSelectedIds($tr => $tr.data('deleted') == 0);

        $('#activateCount').text(canActivate.length);
        $('#deactivateCount').text(canDeactivate.length);
        $('#deleteCount').text(canDelete.length);
        $('#printCount').text(canPrint.length);

        $('#btnActivate').prop('disabled', !canActivate.length);
        $('#btnDeactivate').prop('disabled', !canDeactivate.length);
        $('#btnDelete').prop('disabled', !canDelete.length);
        $('#btnPrint').prop('disabled', !canPrint.length);
    }

    /* ── Actions ─────────────────────────────────────────────────── */

    function doAction(action, label, filterFn) {
        const ids = getSelectedIds(filterFn);
        if (!ids.length) { alert('Chưa chọn mã phù hợp.'); return; }
        if (!confirm(`${label} ${ids.length} mã?`)) return;

        $.post(C.ajaxUrl, {
            action: action,
            nonce: C.nonce,
            lot_ids: JSON.stringify(ids)
        }, function (res) {
            if (res.success) {
                alert('✅ ' + res.data.message);
                loadDetail();
            } else {
                alert('Lỗi: ' + (res.data?.message || 'Không xác định'));
            }
        });
    }

    $('#btnActivate').on('click', () => doAction('tgs_lot_gen_activate_lots', 'Kích hoạt', $tr => $tr.data('status') == 100 && $tr.data('deleted') == 0));
    $('#btnDeactivate').on('click', () => doAction('tgs_lot_gen_deactivate_lots', 'Hủy kích hoạt', $tr => $tr.data('status') == 1 && $tr.data('deleted') == 0));
    $('#btnDelete').on('click', () => doAction('tgs_lot_gen_delete_lots', 'Xóa', $tr => $tr.data('status') == 100 && $tr.data('deleted') == 0));

    /* ── Print ───────────────────────────────────────────────────── */

    $('#btnPrint').on('click', function () {
        const ids = getSelectedIds($tr => $tr.data('deleted') == 0);
        if (!ids.length) { alert('Chưa chọn mã nào.'); return; }

        // Get barcodes from selected lots
        const barcodes = [];
        ids.forEach(id => {
            const lot = allLots.find(l => parseInt(l.global_product_lot_id) === id);
            if (lot) barcodes.push(lot.global_product_lot_barcode);
        });

        if (!barcodes.length) return;

        const showPrice = $('#optShowPrice').is(':checked') ? 1 : 0;
        const showVariant = $('#optShowVariant').is(':checked') ? 1 : 0;
        const showLot = $('#optShowLot').is(':checked') ? 1 : 0;

        const url = C.ajaxUrl + '?' + $.param({
            action: 'tgs_lot_gen_print_barcodes',
            nonce: C.nonce,
            barcodes: barcodes.join(','),
            show_price: showPrice,
            show_variant: showVariant,
            show_lot_info: showLot
        });

        window.open(url, '_blank', 'width=800,height=600');
    });

    /* ── Helpers ──────────────────────────────────────────────────── */

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /* ── Init ────────────────────────────────────────────────────── */

    if (ledgerId) loadDetail();

})(jQuery);
