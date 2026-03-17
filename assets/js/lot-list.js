/**
 * lot-list.js — Danh sách phiếu sinh mã
 *
 * @package tgs_lot_generator
 */
(function ($) {
    'use strict';

    const C = window.tgsLotGen || {};
    let currentPage = 1;

    /* ── Load ledgers ──────────────────────────────────────────── */

    function loadLedgers(page) {
        page = page || 1;
        currentPage = page;

        const search = $('#searchInput').val().trim();
        $('#ledgerTableBody').html('<tr><td colspan="7" class="text-center py-4 text-muted">Đang tải...</td></tr>');

        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_get_ledgers',
            nonce: C.nonce,
            page: page,
            per_page: 20,
            search: search
        }, function (res) {
            if (!res.success || !res.data.ledgers.length) {
                $('#ledgerTableBody').html('<tr><td colspan="7" class="text-center py-4 text-muted">Không có phiếu nào.</td></tr>');
                $('#totalInfo').text('0 phiếu');
                $('#pagination').html('');
                return;
            }

            const d = res.data;
            let html = '';
            d.ledgers.forEach((l, idx) => {
                const offset = (d.page - 1) * d.per_page + idx + 1;
                const meta = l.meta || {};
                const productName = meta.product_name || (l.local_ledger_title || '-');
                const variantInfo = meta.variant_info ? ` <small class="text-primary">[${meta.variant_info}]</small>` : '';
                const date = l.created_at ? new Date(l.created_at).toLocaleString('vi-VN') : '-';

                html += `<tr style="cursor:pointer;" class="ledger-row" data-id="${l.local_ledger_id}">
                    <td>${offset}</td>
                    <td><span class="badge bg-label-primary">${escHtml(l.local_ledger_code)}</span></td>
                    <td>${escHtml(productName)}${variantInfo}</td>
                    <td><span class="badge bg-label-info">${l.lots_count}</span></td>
                    <td>${escHtml(l.user_name || '-')}</td>
                    <td>${date}</td>
                    <td>
                        <a href="${detailUrl(l.local_ledger_id)}" class="btn btn-sm btn-outline-primary">
                            <i class="bx bx-show"></i> Xem
                        </a>
                    </td>
                </tr>`;
            });

            $('#ledgerTableBody').html(html);
            $('#totalInfo').text(`${d.total} phiếu`);
            renderPagination(d.page, d.pages);
        });
    }

    /* ── Pagination ─────────────────────────────────────────────── */

    function renderPagination(page, pages) {
        if (pages <= 1) { $('#pagination').html(''); return; }
        let html = '';
        // Prev
        html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${page - 1}">&laquo;</a></li>`;
        // Pages
        const start = Math.max(1, page - 2);
        const end = Math.min(pages, page + 2);
        for (let i = start; i <= end; i++) {
            html += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }
        // Next
        html += `<li class="page-item ${page >= pages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${page + 1}">&raquo;</a></li>`;
        $('#pagination').html(html);
    }

    $(document).on('click', '#pagination .page-link', function (e) {
        e.preventDefault();
        const p = parseInt($(this).data('page'));
        if (p > 0) loadLedgers(p);
    });

    /* ── Row click ──────────────────────────────────────────────── */

    $(document).on('click', '.ledger-row td:not(:last-child)', function () {
        const id = $(this).closest('tr').data('id');
        window.location.href = detailUrl(id);
    });

    /* ── Helpers ─────────────────────────────────────────────────── */

    function detailUrl(ledgerId) {
        const u = new URL(window.location.href);
        u.searchParams.set('view', 'lot-gen-detail');
        u.searchParams.set('ledger_id', ledgerId);
        return u.toString();
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /* ── Events ──────────────────────────────────────────────────── */

    $('#btnSearch').on('click', () => loadLedgers(1));
    $('#btnRefresh').on('click', () => { $('#searchInput').val(''); loadLedgers(1); });
    $('#searchInput').on('keydown', function (e) { if (e.key === 'Enter') loadLedgers(1); });

    /* ── Init ────────────────────────────────────────────────────── */

    loadLedgers(1);

})(jQuery);
