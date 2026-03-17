/**
 * lot-generate.js — Trang sinh mã định danh
 *
 * Handles:
 * - Product search autocomplete
 * - Variant dropdown
 * - Stock info display
 * - Generate lots submission
 *
 * @package tgs_lot_generator
 */
(function ($) {
    'use strict';

    const C = window.tgsLotGen || {};
    let searchTimeout = null;
    let selectedProduct = null;

    /* ── Product Search ─────────────────────────────────────────── */

    $('#productSearch').on('input', function () {
        const kw = $(this).val().trim();
        if (kw.length < 2) { $('#productDropdown').hide(); return; }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            $.post(C.ajaxUrl, {
                action: 'tgs_lot_gen_search_products',
                nonce: C.nonce,
                keyword: kw,
                blog_id: C.blogId
            }, function (res) {
                if (!res.success || !res.data.products.length) {
                    $('#productDropdown').html('<div class="dropdown-item text-muted">Không tìm thấy</div>').show();
                    return;
                }
                let html = '';
                res.data.products.forEach(p => {
                    const price = parseFloat(p.local_product_price_after_tax || 0);
                    const priceFmt = price > 0 ? new Intl.NumberFormat('vi-VN').format(price) + 'đ' : '';
                    html += `<a href="#" class="dropdown-item product-item" data-id="${p.local_product_name_id}"
                        data-name="${encodeURIComponent(p.local_product_name)}"
                        data-barcode="${p.local_product_barcode_main || ''}"
                        data-sku="${p.local_product_sku || ''}"
                        data-unit="${p.local_product_unit || ''}"
                        data-price="${price}">
                        <strong>${p.local_product_name}</strong>
                        <br><small class="text-muted">${p.local_product_barcode_main || '-'} | ${p.local_product_sku || '-'} | ${priceFmt}</small>
                    </a>`;
                });
                $('#productDropdown').html(html).show();
            });
        }, 300);
    });

    $(document).on('click', '.product-item', function (e) {
        e.preventDefault();
        const $el = $(this);
        selectedProduct = {
            id: $el.data('id'),
            name: decodeURIComponent($el.data('name')),
            barcode: $el.data('barcode'),
            sku: $el.data('sku'),
            unit: $el.data('unit'),
            price: $el.data('price')
        };

        $('#productId').val(selectedProduct.id);
        $('#productSearch').val(selectedProduct.name);
        $('#productDropdown').hide();

        // Show info
        $('#productName').text(selectedProduct.name);
        $('#productBarcode').text(selectedProduct.barcode || '-');
        $('#productSku').text(selectedProduct.sku || '-');
        $('#productUnit').text(selectedProduct.unit || '-');
        const pFmt = selectedProduct.price > 0 ? new Intl.NumberFormat('vi-VN').format(selectedProduct.price) + 'đ' : '-';
        $('#productPrice').text(pFmt);
        $('#productInfo').show();

        // Load stock info
        loadStockInfo(selectedProduct.id);
        // Load variants
        loadVariants(selectedProduct.id);
    });

    $('#clearProduct').on('click', function () {
        selectedProduct = null;
        $('#productId').val('');
        $('#productSearch').val('');
        $('#productInfo').hide();
        $('#stockInfo').html('<span class="text-muted">Chưa chọn SP</span>');
        $('#variantRow').hide();
        $('#variantSelect').html('<option value="0">-- Không chọn biến thể --</option>');
    });

    // Hide dropdown on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#productSearch, #productDropdown').length) {
            $('#productDropdown').hide();
        }
    });

    /* ── Stock Info ──────────────────────────────────────────────── */

    function loadStockInfo(productId) {
        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_get_product_lots_count',
            nonce: C.nonce,
            product_id: productId,
            blog_id: C.blogId
        }, function (res) {
            if (!res.success) {
                $('#stockInfo').html('<span class="text-danger">Lỗi</span>');
                return;
            }
            const c = res.data.counts;
            $('#stockInfo').html(`
                <span class="badge bg-label-primary me-1">Tổng: ${c.total}</span>
                <span class="badge bg-label-success me-1">Kho: ${c.in_stock}</span>
                <span class="badge bg-label-warning me-1">Chờ: ${c.pending}</span>
                <span class="badge bg-label-info">Đã sinh: ${c.generated}</span>
            `);
        });
    }

    /* ── Variants ────────────────────────────────────────────────── */

    function loadVariants(productId) {
        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_get_variants',
            nonce: C.nonce,
            product_id: productId,
            blog_id: C.blogId
        }, function (res) {
            let html = '<option value="0">-- Không chọn biến thể --</option>';
            if (res.success && res.data.variants.length) {
                res.data.variants.forEach(v => {
                    const adj = parseFloat(v.variant_price_adjustment || 0);
                    const adjStr = adj !== 0 ? ` (${adj > 0 ? '+' : ''}${new Intl.NumberFormat('vi-VN').format(adj)}đ)` : '';
                    html += `<option value="${v.variant_id}">${v.variant_label}: ${v.variant_value}${adjStr}</option>`;
                });
                $('#variantRow').show();
            } else {
                $('#variantRow').hide();
            }
            $('#variantSelect').html(html);
        });
    }

    /* ── Form Submit ─────────────────────────────────────────────── */

    $('#lotGenForm').on('submit', function (e) {
        e.preventDefault();

        const productId = $('#productId').val();
        if (!productId) { alert('Vui lòng chọn sản phẩm.'); return; }

        const qty = parseInt($('#quantity').val());
        if (!qty || qty < 1 || qty > 5000) { alert('Số lượng phải từ 1 đến 5000.'); return; }

        if (!confirm(`Bạn sắp sinh ${qty} mã định danh. Tiếp tục?`)) return;

        // Show overlay
        $('#genOverlay').show();
        $('#btnGenerate').prop('disabled', true);

        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_generate',
            nonce: C.nonce,
            product_id: productId,
            blog_id: C.blogId,
            quantity: qty,
            variant_id: $('#variantSelect').val() || 0,
            lot_code: $('#lotCode').val(),
            mfg_date: $('#mfgDate').val(),
            exp_date: $('#expDate').val(),
            note: $('#note').val()
        }, function (res) {
            $('#genOverlay').hide();
            $('#btnGenerate').prop('disabled', false);

            if (!res.success) {
                alert('Lỗi: ' + (res.data?.message || 'Không xác định'));
                return;
            }

            const d = res.data;
            alert(`✅ ${d.message}\nMã phiếu: ${d.ticket_code}\nSố mã: ${d.lots_created}`);

            // Redirect to detail
            const u = new URL(window.location.href);
            u.searchParams.set('view', 'lot-gen-detail');
            u.searchParams.set('ledger_id', d.ledger_id);
            // Xóa params thừa
            for (const key of [...u.searchParams.keys()]) {
                if (!['page', 'view', 'ledger_id'].includes(key)) u.searchParams.delete(key);
            }
            window.location.href = u.toString();
        }).fail(function () {
            $('#genOverlay').hide();
            $('#btnGenerate').prop('disabled', false);
            alert('Lỗi kết nối server.');
        });
    });

    /* ── Reset ───────────────────────────────────────────────────── */

    $('#btnReset').on('click', function () {
        $('#clearProduct').trigger('click');
        $('#quantity').val(1);
        $('#lotCode, #mfgDate, #expDate, #note').val('');
    });

})(jQuery);
