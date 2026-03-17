/**
 * lot-detail.js — Chi tiết phiếu định danh sản phẩm
 *
 * Handles:
 * - Load detail + lots
 * - Select / deselect lots (with row highlighting)
 * - Activate / Deactivate / Delete lots
 * - Print barcodes (with toggle config)
 *
 * @package tgs_lot_generator
 */
(function ($) {
    'use strict';

    const C = window.tgsLotGen || {};
    const ledgerId = parseInt($('#detailLedgerId').val()) || 0;
    let allLots = [];

    if (!ledgerId) {
        $('#lotsTableBody').html('<tr><td colspan="9" class="text-center text-danger py-4">Không có ledger_id. Vui lòng chọn phiếu từ danh sách.</td></tr>');
    }

    /* ── Toast (lightweight notification) ─────────────────────────── */

    function showToast(msg, type) {
        type = type || 'dark';
        const bgMap = { success: '#16a34a', danger: '#dc2626', dark: '#1e293b', info: '#696cff', warning: '#f59e0b' };
        const bg = bgMap[type] || bgMap.dark;
        const duration = type === 'danger' ? 5000 : 3000;
        const $t = $('<div class="gen-toast">' + msg + '</div>').css('background', bg);
        $('body').append($t);
        setTimeout(() => $t.addClass('show'), 10);
        setTimeout(() => { $t.removeClass('show'); setTimeout(() => $t.remove(), 300); }, duration);
    }

    /* ── Load Detail ─────────────────────────────────────────────── */

    function loadDetail() {
        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_get_detail',
            nonce: C.nonce,
            ledger_id: ledgerId
        }, function (res) {
            if (!res.success) {
                showToast('❌ ' + (res.data?.message || 'Không xác định'), 'danger');
                return;
            }

            const d = res.data;
            const l = d.ledger;

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
            $('#lotsTableBody').html('<tr><td colspan="9" class="text-center py-4 text-muted">Không có mã nào.</td></tr>');
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

            const boxCode = lot.box_code ? `<code>${escHtml(lot.box_code)}</code>` : '';

            const boxId = parseInt(lot.global_box_manager_id) || 0;

            html += `<tr class="lot-row ${rowClass}" data-lot-id="${lot.global_product_lot_id}" data-status="${status}" data-deleted="${lot.is_deleted}" data-box-id="${boxId}">
                <td class="lot-check-cell">${canCheck ? `<input type="checkbox" class="form-check-input lot-check" value="${lot.global_product_lot_id}" />` : ''}</td>
                <td>${idx + 1}</td>
                <td><code class="lot-barcode">${escHtml(lot.global_product_lot_barcode)}</code></td>
                <td>${escHtml(lot.local_product_name || '-')}</td>
                <td>${escHtml(variantStr)}</td>
                <td>${escHtml(lot.lot_code || '-')}</td>
                <td>${expStr}</td>
                <td>${boxCode}</td>
                <td>${statusBadge}</td>
            </tr>`;
        });

        $('#lotsTableBody').html(html);
        updateToolbar();
    }

    /* ── Selection — with row highlighting ────────────────────────── */

    /* Click anywhere on the row (except links) → toggle checkbox */
    $(document).on('click', '.lot-row', function (e) {
        // Nếu click vào checkbox thì để nó tự xử lý
        if ($(e.target).is('.lot-check')) return;
        const $cb = $(this).find('.lot-check');
        if (!$cb.length) return;
        $cb.prop('checked', !$cb.prop('checked')).trigger('change');
    });

    /* Checkbox change → highlight row */
    $(document).on('change', '.lot-check', function () {
        const $tr = $(this).closest('tr');
        if (this.checked) {
            $tr.addClass('lot-row-selected');
        } else {
            $tr.removeClass('lot-row-selected');
        }
        syncCheckAll();
        updateToolbar();
    });

    /* Header checkAll */
    $(document).on('change', '#checkAll', function () {
        const checked = this.checked;
        $('.lot-check').each(function () {
            $(this).prop('checked', checked).closest('tr').toggleClass('lot-row-selected', checked);
        });
        updateToolbar();
    });

    /** Sync #checkAll state with individual checkboxes */
    function syncCheckAll() {
        const total = $('.lot-check').length;
        const checked = $('.lot-check:checked').length;
        $('#checkAll').prop('checked', total > 0 && total === checked);
    }

    /* Toolbar buttons */
    $('#btnSelectAll').on('click', function () {
        $('.lot-check').prop('checked', true).closest('tr').addClass('lot-row-selected');
        $('#checkAll').prop('checked', true);
        updateToolbar();
        showToast('✅ Đã chọn tất cả ' + $('.lot-check').length + ' mã', 'info');
    });

    $('#btnDeselectAll').on('click', function () {
        $('.lot-check').prop('checked', false).closest('tr').removeClass('lot-row-selected');
        $('#checkAll').prop('checked', false);
        updateToolbar();
    });

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
        const canAssign = getSelectedIds($tr => $tr.data('deleted') == 0 && !$tr.data('box-id'));

        // Selection count
        if (allChecked.length > 0) {
            $('#selectedCountBadge').show();
            $('#selectedCount').text(allChecked.length);
        } else {
            $('#selectedCountBadge').hide();
        }

        $('#activateCount').text(canActivate.length);
        $('#deactivateCount').text(canDeactivate.length);
        $('#deleteCount').text(canDelete.length);
        $('#printCount').text(canPrint.length);
        $('#assignBoxCount').text(canAssign.length);

        $('#btnActivate').prop('disabled', !canActivate.length);
        $('#btnDeactivate').prop('disabled', !canDeactivate.length);
        $('#btnDelete').prop('disabled', !canDelete.length);
        $('#btnPrint').prop('disabled', !canPrint.length);
        $('#btnAssignBox').prop('disabled', !canAssign.length);
    }

    /* ── Actions ─────────────────────────────────────────────────── */

    function doAction(action, label, filterFn) {
        const ids = getSelectedIds(filterFn);
        if (!ids.length) { showToast('⚠️ Chưa chọn mã phù hợp', 'warning'); return; }
        if (!confirm(`${label} ${ids.length} mã?`)) return;

        $.post(C.ajaxUrl, {
            action: action,
            nonce: C.nonce,
            lot_ids: JSON.stringify(ids)
        }, function (res) {
            if (res.success) {
                showToast('✅ ' + res.data.message, 'success');
                loadDetail();
            } else {
                showToast('❌ ' + (res.data?.message || 'Không xác định'), 'danger');
            }
        });
    }

    $('#btnActivate').on('click', () => doAction('tgs_lot_gen_activate_lots', 'Kích hoạt', $tr => $tr.data('status') == 100 && $tr.data('deleted') == 0));
    $('#btnDeactivate').on('click', () => doAction('tgs_lot_gen_deactivate_lots', 'Hủy kích hoạt', $tr => $tr.data('status') == 1 && $tr.data('deleted') == 0));
    $('#btnDelete').on('click', () => doAction('tgs_lot_gen_delete_lots', 'Xóa', $tr => $tr.data('status') == 100 && $tr.data('deleted') == 0));

    /* ── Print Config Toggle Buttons ──────────────────────────────── */

    $(document).on('click', '.print-opt-toggle', function () {
        const isActive = $(this).data('active') === 1;
        $(this).data('active', isActive ? 0 : 1);
        $(this).toggleClass('active', !isActive);

        // Cập nhật icon
        const $icon = $(this).find('.toggle-icon');
        if (!isActive) {
            $icon.removeClass('bx-x').addClass('bx-check');
        } else {
            $icon.removeClass('bx-check').addClass('bx-x');
        }
    });

    /* ── Print ───────────────────────────────────────────────────── */

    $('#btnPrint').on('click', function () {
        const ids = getSelectedIds($tr => $tr.data('deleted') == 0);
        if (!ids.length) { showToast('⚠️ Chưa chọn mã nào', 'warning'); return; }

        // Get barcodes from selected lots
        const barcodes = [];
        ids.forEach(id => {
            const lot = allLots.find(l => parseInt(l.global_product_lot_id) === id);
            if (lot) barcodes.push(lot.global_product_lot_barcode);
        });

        if (!barcodes.length) return;

        const showPrice   = $('#optShowPrice').data('active') ? 1 : 0;
        const showVariant = $('#optShowVariant').data('active') ? 1 : 0;
        const showLot     = $('#optShowLot').data('active') ? 1 : 0;

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

    /* ── Assign to Box (Gắn vào thùng) ─────────────────────────── */

    let selectedBox = null;
    let boxSearchTimer = null;

    $('#btnAssignBox').on('click', function () {
        const allSelected = getSelectedIds($tr => $tr.data('deleted') == 0);
        const alreadyInBox = getSelectedIds($tr => $tr.data('deleted') == 0 && $tr.data('box-id') > 0);
        const ids = getSelectedIds($tr => $tr.data('deleted') == 0 && !$tr.data('box-id'));
        if (!ids.length && alreadyInBox.length) {
            showToast('⚠️ Tất cả mã đã chọn đều đã gắn thùng rồi. Vào trang chi tiết thùng để sửa nhé!', 'warning');
            return;
        }
        if (!ids.length) { showToast('⚠️ Chưa chọn mã nào', 'warning'); return; }
        if (alreadyInBox.length) {
            showToast(`ℹ️ ${alreadyInBox.length} mã đã gắn thùng sẽ được bỏ qua. Chỉ gắn ${ids.length} mã chưa có thùng.`, 'info');
        }
        // Reset modal state
        selectedBox = null;
        $('#searchBoxInput').val('');
        $('#boxSearchResults').hide().empty();
        $('#selectedBoxCard').hide();
        $('#btnConfirmAssign').prop('disabled', true);
        $('#modalLotCount').text(ids.length);
        // Open modal
        const modal = new bootstrap.Modal($('#assignBoxModal')[0]);
        modal.show();
        setTimeout(() => $('#searchBoxInput').focus(), 300);
    });

    /* Search boxes as user types */
    $('#searchBoxInput').on('input', function () {
        const kw = $.trim($(this).val());
        clearTimeout(boxSearchTimer);
        if (kw.length < 1) {
            $('#boxSearchResults').hide().empty();
            return;
        }
        boxSearchTimer = setTimeout(() => searchBoxes(kw), 300);
    });

    /* Also search on focus if there's text */
    $('#searchBoxInput').on('focus', function () {
        const kw = $.trim($(this).val());
        if (kw.length >= 1) searchBoxes(kw);
    });

    function searchBoxes(keyword) {
        $.post(C.ajaxUrl, {
            action: 'tgs_box_search_boxes',
            nonce: C.boxNonce,
            keyword: keyword
        }, function (res) {
            const $list = $('#boxSearchResults');
            $list.empty();
            if (!res.success || !res.data.boxes.length) {
                $list.html('<div class="list-group-item text-muted text-center py-2" style="font-size:13px;">Không tìm thấy thùng nào</div>').show();
                return;
            }
            res.data.boxes.forEach(box => {
                const capText = box.remaining === -1
                    ? '<span class="text-success">∞ Không giới hạn</span>'
                    : `Còn trống: <b>${box.remaining}</b> / ${box.capacity}`;
                $list.append(
                    `<a href="#" class="list-group-item list-group-item-action box-search-item py-2" data-box='${JSON.stringify(box)}'>
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold" style="font-size:13px;">${escHtml(box.box_title || box.box_code)}</div>
                                <small class="text-muted"><code>${escHtml(box.box_code)}</code> · ${escHtml(box.box_type)}</small>
                            </div>
                            <span class="badge bg-label-info" style="font-size:11px;">${capText}</span>
                        </div>
                    </a>`
                );
            });
            $list.show();
        }).fail(function () {
            showToast('❌ Lỗi tìm thùng', 'danger');
        });
    }

    /* Select a box from search results */
    $(document).on('click', '.box-search-item', function (e) {
        e.preventDefault();
        const box = $(this).data('box');
        selectedBox = box;
        $('#selBoxTitle').text(box.box_title || box.box_code);
        $('#selBoxCode').text(box.box_code);
        $('#selBoxType').text(box.box_type);
        const capLabel = box.remaining === -1
            ? '∞ Không giới hạn'
            : `Đang chứa: ${box.actual_qty} / ${box.capacity} — Còn trống: ${box.remaining}`;
        $('#selBoxCapacity').text(capLabel);
        $('#selectedBoxCard').show();
        $('#boxSearchResults').hide().empty();
        $('#searchBoxInput').val('');
        $('#btnConfirmAssign').prop('disabled', false);
    });

    /* Clear selected box */
    $('#btnClearBox').on('click', function () {
        selectedBox = null;
        $('#selectedBoxCard').hide();
        $('#btnConfirmAssign').prop('disabled', true);
        $('#searchBoxInput').focus();
    });

    /* Close dropdown when clicking outside */
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#searchBoxInput, #boxSearchResults').length) {
            $('#boxSearchResults').hide();
        }
    });

    /* Confirm assign */
    $('#btnConfirmAssign').on('click', function () {
        if (!selectedBox) return;
        const ids = getSelectedIds($tr => $tr.data('deleted') == 0 && !$tr.data('box-id'));
        if (!ids.length) { showToast('⚠️ Không có mã nào chưa gắn thùng để gắn.', 'warning'); return; }

        // Check capacity (client-side pre-check)
        if (selectedBox.remaining !== -1 && ids.length > selectedBox.remaining) {
            showToast(`⚠️ Thùng chỉ còn ${selectedBox.remaining} chỗ trống, bạn đang chọn ${ids.length} mã!`, 'warning');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Đang gắn...');

        $.post(C.ajaxUrl, {
            action: 'tgs_box_add_items',
            nonce: C.boxNonce,
            box_id: selectedBox.box_id,
            lot_ids: ids
        }, function (res) {
            if (res.success) {
                showToast(`✅ ${res.data.message}`, 'success');
                // Close modal
                bootstrap.Modal.getInstance($('#assignBoxModal')[0])?.hide();
                // Reload detail to refresh data
                loadDetail();
            } else {
                showToast('❌ ' + (res.data?.message || 'Không xác định'), 'danger');
            }
        }).fail(function () {
            showToast('❌ Lỗi kết nối khi gắn vào thùng', 'danger');
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="bx bx-check me-1"></i>Gắn vào thùng');
        });
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
