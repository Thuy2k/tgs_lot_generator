/**
 * lot-generate.js — Trang sinh mã định danh (all-in-one workflow)
 *
 * Handles:
 * - Product search autocomplete (chỉ SP tracking=1)
 * - Hiển thị thumbnail, SKU, barcode sau khi chọn SP
 * - Modal thêm nhanh sản phẩm (giá, thuế, SKU tự sinh)
 * - Modal thêm nhanh biến thể (type→label auto-fill, chips)
 * - Variant dropdown & auto-select sau create
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

    /* ── Format helpers ──────────────────────────────────────────── */

    const vnd = (n) => n > 0 ? new Intl.NumberFormat('vi-VN').format(n) + 'đ' : '—';
    const escHtml = (s) => { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    const escAttr = (s) => s ? s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
    const defaultThumb = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Crect width='40' height='40' rx='6' fill='%23f0f4ff'/%3E%3Ctext x='20' y='25' text-anchor='middle' font-size='16' fill='%23696cff'%3E🧃%3C/text%3E%3C/svg%3E";

    /* ── Preset data (chia sẻ với variant modal) ─────────────────── */

    const PRESETS = {
        size:   { label: 'Size',        chips: ['S','M','L','XL','XXL','28','30','32'], skuMap: {S:'-S',M:'-M',L:'-L',XL:'-XL',XXL:'-XXL'} },
        color:  { label: 'Màu sắc',     chips: ['Trắng','Đen','Xanh dương','Xanh lá','Đỏ','Vàng','Hồng','Nâu'], skuMap: {'Trắng':'-WHT','Đen':'-BLK','Xanh dương':'-BLU','Đỏ':'-RED','Vàng':'-YLW','Hồng':'-PNK'} },
        expiry: { label: 'Hạn sử dụng', chips: ['3 tháng','6 tháng','12 tháng','18 tháng','24 tháng','36 tháng'], skuMap: {'3 tháng':'-3M','6 tháng':'-6M','12 tháng':'-12M','24 tháng':'-24M'} },
        flavor: { label: 'Hương vị',     chips: ['Vani','Dâu','Socola','Trái cây','Matcha','Cam','Việt quất','Không đường'], skuMap: {'Vani':'-VAN','Dâu':'-STR','Socola':'-CHO','Matcha':'-MAT'} },
        weight: { label: 'Trọng lượng',  chips: ['100g','200g','400g','500g','800g','1kg','1.5kg','2kg'], skuMap: {'100g':'-100G','200g':'-200G','400g':'-400G','500g':'-500G','1kg':'-1KG'} },
        custom: { label: '', chips: [], skuMap: {} }
    };

    /* ── Toast (lightweight notification) ─────────────────────────── */

    function showToast(msg, type) {
        type = type || 'dark';
        const bgMap = { success: '#16a34a', danger: '#dc2626', dark: '#1e293b', info: '#696cff' };
        const bg = bgMap[type] || bgMap.dark;
        const $t = $('<div class="gen-toast">' + escHtml(msg) + '</div>').css('background', bg);
        $('body').append($t);
        setTimeout(() => $t.addClass('show'), 10);
        setTimeout(() => { $t.removeClass('show'); setTimeout(() => $t.remove(), 300); }, 3000);
    }

    /* ====================================================================
     * ① PRODUCT SEARCH — Autocomplete + New product button
     * ==================================================================== */

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
                renderDropdown(res, kw);
            });
        }, 300);
    });

    /** Render dropdown kết quả tìm kiếm SP */
    function renderDropdown(res, kw) {
        const $dd = $('#productDropdown');
        let html = '';

        if (res.success && res.data.products.length) {
            res.data.products.forEach(p => {
                const price = parseFloat(p.local_product_price_after_tax || 0);
                const thumb = p.local_product_thumbnail || defaultThumb;
                html += `<a href="#" class="gen-dd-item gen-product-item"
                    data-id="${p.local_product_name_id}"
                    data-name="${encodeURIComponent(p.local_product_name)}"
                    data-barcode="${escAttr(p.local_product_barcode_main || '')}"
                    data-sku="${escAttr(p.local_product_sku || '')}"
                    data-unit="${escAttr(p.local_product_unit || '')}"
                    data-price="${price}"
                    data-thumb="${escAttr(thumb)}">
                    <div class="d-flex align-items-center">
                        <img src="${escAttr(thumb)}" class="rounded me-2 flex-shrink-0" style="width:32px;height:32px;object-fit:cover;border:1px solid #eee;" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="gen-dd-name">${escHtml(p.local_product_name)}</div>
                            <div class="gen-dd-meta">
                                <span><i class="bx bx-barcode me-1"></i>${escHtml(p.local_product_barcode_main || '—')}</span>
                                <span><i class="bx bx-purchase-tag me-1"></i>${escHtml(p.local_product_sku || '—')}</span>
                                ${price > 0 ? '<span class="gen-dd-price">' + vnd(price) + '</span>' : ''}
                            </div>
                        </div>
                    </div>
                </a>`;
            });
        } else {
            // Không tìm thấy
            html += `<div class="gen-dd-item gen-dd-empty">
                <i class="bx bx-search-alt me-1"></i>Không tìm thấy SP nào có theo dõi lô với "<b>${escHtml(kw)}</b>"
            </div>`;
        }

        // Luôn hiện nút "Thêm nhanh SP"
        html += `<a href="#" class="gen-dd-item gen-dd-add-new" id="ddAddProduct">
            <i class="bx bx-plus-circle me-1 text-primary"></i>
            <strong class="text-primary">Thêm nhanh sản phẩm mới</strong>
            <small class="text-muted ms-1">— tự bật theo dõi lô</small>
        </a>`;

        $dd.html(html).show();
    }

    /* Click chọn SP từ dropdown */
    $(document).on('click', '.gen-product-item', function (e) {
        e.preventDefault();
        const $el = $(this);
        selectProduct({
            id:       $el.data('id'),
            name:     decodeURIComponent($el.data('name')),
            barcode:  $el.data('barcode'),
            sku:      $el.data('sku'),
            unit:     $el.data('unit'),
            price:    $el.data('price'),
            thumb:    $el.data('thumb') || ''
        });
    });

    /** Chọn SP & hiển thị info */
    function selectProduct(p) {
        selectedProduct = p;
        $('#productId').val(p.id);
        $('#productSearch').val(p.name).prop('disabled', true);
        $('#productDropdown').hide();

        // Hiện thông tin SP
        const thumb = p.thumb || defaultThumb;
        $('#productThumb').attr('src', thumb);
        $('#productName').text(p.name);
        $('#productSku').text(p.sku || '—');
        $('#productBarcode').text(p.barcode || '—');
        $('#productUnit').text(p.unit || '—');
        $('#productPrice').text(vnd(parseFloat(p.price || 0)));
        $('#productInfo').slideDown(200);

        // Load stock + variants
        loadStockInfo(p.id);
        loadVariants(p.id);
    }

    /** Clear chọn SP */
    $('#clearProduct').on('click', function () {
        selectedProduct = null;
        $('#productId').val('');
        $('#productSearch').val('').prop('disabled', false).focus();
        $('#productInfo').slideUp(200);
        $('#stockInfo').html('<span class="text-muted">Chưa chọn SP</span>');
        $('#variantRow').slideUp(200);
        $('#variantSelect').html('<option value="0">-- Không chọn biến thể --</option>');
    });

    /* Click "Thêm nhanh SP" từ dropdown → mở modal */
    $(document).on('click', '#ddAddProduct', function (e) {
        e.preventDefault();
        $('#productDropdown').hide();
        openQuickProductModal();
    });

    // Hide dropdown on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.gen-search-wrap').length) {
            $('#productDropdown').hide();
        }
    });

    /* ====================================================================
     * ② STOCK INFO
     * ==================================================================== */

    function loadStockInfo(productId) {
        $('#stockInfo').html('<i class="bx bx-loader-alt bx-spin me-1"></i>Đang tải...');
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

    /* ====================================================================
     * ③ VARIANTS — Load dropdown + "Thêm nhanh biến thể"
     * ==================================================================== */

    function loadVariants(productId, autoSelectId) {
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
                    const sel = autoSelectId && parseInt(v.variant_id) === parseInt(autoSelectId) ? ' selected' : '';
                    html += `<option value="${v.variant_id}"${sel}>${escHtml(v.variant_label)}: ${escHtml(v.variant_value)}${adjStr}</option>`;
                });
            }
            $('#variantSelect').html(html);
            // Luôn hiện variant row (vì có nút "thêm nhanh")
            $('#variantRow').slideDown(200);
        });
    }

    /* Mở modal thêm nhanh biến thể */
    $('#btnQuickVariant').on('click', function () {
        if (!selectedProduct) { showToast('Vui lòng chọn sản phẩm trước', 'danger'); return; }
        openQuickVariantModal();
    });

    /* ====================================================================
     * ④ MODAL: Thêm nhanh sản phẩm
     * ==================================================================== */

    function openQuickProductModal() {
        // Reset form
        $('#quickProductForm')[0].reset();
        $('#qpPriceBeforeTax').val('');
        $('#qpStatus').prop('checked', true);
        $('#qpStatusLabel').text('Hoạt động');
        $('#qpTax').val(8);
        // Pre-fill name from search box
        const kw = $('#productSearch').val().trim();
        if (kw.length >= 2) $('#qpName').val(kw);
        // Generate SKU
        fetchNewSku();
        // Open
        const modal = new bootstrap.Modal(document.getElementById('modalQuickProduct'));
        modal.show();
    }

    /** Fetch SKU from server */
    function fetchNewSku() {
        $('#qpSku').val('Đang tạo...').addClass('text-muted');
        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_generate_sku',
            nonce: C.nonce
        }, function (res) {
            if (res.success) {
                $('#qpSku').val(res.data.sku).removeClass('text-muted');
            } else {
                $('#qpSku').val('').removeClass('text-muted');
                showToast('Không tạo được SKU, hãy thử lại.', 'danger');
            }
        }).fail(function () {
            $('#qpSku').val('').removeClass('text-muted');
        });
    }

    /* SKU refresh button */
    $('#qpRefreshSku').on('click', fetchNewSku);

    /* Price ↔ Tax calculation */
    function calcPriceBeforeTax() {
        const afterTax = parseFloat($('#qpPriceAfterTax').val().replace(/[^\d.]/g, '')) || 0;
        const tax = parseFloat($('#qpTax').val()) || 0;
        if (afterTax > 0 && tax >= 0) {
            const before = Math.round(afterTax / (1 + tax / 100));
            $('#qpPriceBeforeTax').val(new Intl.NumberFormat('vi-VN').format(before));
        } else {
            $('#qpPriceBeforeTax').val('');
        }
    }
    $('#qpPriceAfterTax, #qpTax').on('input change', calcPriceBeforeTax);

    /* Status toggle label */
    $('#qpStatus').on('change', function () {
        $('#qpStatusLabel').text(this.checked ? 'Hoạt động' : 'Ngưng');
    });

    /* Submit: Create product */
    $('#quickProductForm').on('submit', function (e) {
        e.preventDefault();

        const name = $('#qpName').val().trim();
        const sku  = $('#qpSku').val().trim();
        if (!name) { showToast('Vui lòng nhập tên sản phẩm', 'danger'); return; }
        if (!sku)  { showToast('Chưa có mã SKU, bấm nút refresh', 'danger'); return; }

        const priceAfterTax = parseFloat($('#qpPriceAfterTax').val().replace(/[^\d.]/g, '')) || 0;
        const tax  = parseFloat($('#qpTax').val()) || 0;
        const priceBefore = priceAfterTax > 0 ? Math.round(priceAfterTax / (1 + tax / 100)) : 0;

        $('#qpBtnSave').prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Đang tạo...');

        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_quick_create_product',
            nonce: C.nonce,
            product_name: name,
            product_sku: sku,
            product_barcode: $('#qpBarcode').val().trim(),
            product_price_after_tax: priceAfterTax,
            product_tax: tax,
            product_price: priceBefore,
            product_unit: $('#qpUnit').val(),
            product_status: $('#qpStatus').is(':checked') ? 1 : 0
        }, function (res) {
            $('#qpBtnSave').prop('disabled', false).html('<i class="bx bx-check me-1"></i>Tạo sản phẩm & chọn ngay');

            if (!res.success) {
                showToast('Lỗi: ' + (res.data?.message || 'Không xác định'), 'danger');
                return;
            }

            // Đóng modal
            bootstrap.Modal.getInstance(document.getElementById('modalQuickProduct'))?.hide();

            // Auto-select product
            const p = res.data.product;
            selectProduct({
                id:       p.local_product_name_id,
                name:     p.local_product_name,
                barcode:  p.local_product_barcode_main,
                sku:      p.local_product_sku,
                unit:     p.local_product_unit,
                price:    p.local_product_price_after_tax,
                thumb:    p.local_product_thumbnail || ''
            });

            showToast('✅ ' + (res.data.message || 'Đã tạo sản phẩm!'), 'success');
        }).fail(function () {
            $('#qpBtnSave').prop('disabled', false).html('<i class="bx bx-check me-1"></i>Tạo sản phẩm & chọn ngay');
            showToast('Lỗi kết nối server', 'danger');
        });
    });

    /* ====================================================================
     * ⑤ MODAL: Thêm nhanh biến thể
     * ==================================================================== */

    function openQuickVariantModal() {
        // Reset form
        $('#quickVariantForm')[0].reset();
        $('#qvType').val('custom');
        $('#qvLabel, #qvValue, #qvSkuSuffix').val('');
        $('#qvPriceAdj').val(0);
        $('#qvValueChips').html('');
        // Hiện tên SP đang chọn
        $('#qvProductName').text(selectedProduct ? selectedProduct.name : '—');
        // Open
        const modal = new bootstrap.Modal(document.getElementById('modalQuickVariant'));
        modal.show();
    }

    /* Auto-fill khi đổi loại biến thể (trong modal) */
    $('#qvType').on('change', function () {
        const type = $(this).val();
        const preset = PRESETS[type] || PRESETS.custom;
        if (preset.label) {
            $('#qvLabel').val(preset.label);
        } else {
            $('#qvLabel').val('');
        }
        $('#qvValue').val('');
        $('#qvSkuSuffix').val('');
        renderQvChips(type);
    });

    function renderQvChips(type) {
        const preset = PRESETS[type] || PRESETS.custom;
        if (!preset.chips.length) { $('#qvValueChips').html(''); return; }
        let html = '<span class="text-muted me-1" style="font-size:11px;">Chọn nhanh:</span>';
        preset.chips.forEach(chip => {
            html += `<button type="button" class="btn btn-xs btn-outline-primary var-chip qv-chip me-1 mb-1" data-val="${escAttr(chip)}" data-type="${type}">${escHtml(chip)}</button>`;
        });
        $('#qvValueChips').html(html);
    }

    /* Click chip trong modal biến thể */
    $(document).on('click', '.qv-chip', function () {
        const val = $(this).data('val');
        const type = $(this).data('type');
        const preset = PRESETS[type] || {};
        $('#qvValue').val(val);
        if (preset.skuMap && preset.skuMap[val]) {
            $('#qvSkuSuffix').val(preset.skuMap[val]);
        }
        $('.qv-chip').removeClass('active');
        $(this).addClass('active');
    });

    /* Submit: Create variant */
    $('#quickVariantForm').on('submit', function (e) {
        e.preventDefault();
        if (!selectedProduct) { showToast('Chưa chọn sản phẩm', 'danger'); return; }

        const label = $('#qvLabel').val().trim();
        const value = $('#qvValue').val().trim();
        if (!label || !value) { showToast('Nhãn và Giá trị là bắt buộc', 'danger'); return; }

        $('#qvBtnSave').prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Đang tạo...');

        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_save_variant',
            nonce: C.nonce,
            variant_id: 0,
            product_id: selectedProduct.id,
            blog_id: C.blogId,
            variant_type: $('#qvType').val(),
            variant_label: label,
            variant_value: value,
            variant_sku_suffix: $('#qvSkuSuffix').val(),
            variant_price_adjustment: $('#qvPriceAdj').val() || 0,
            variant_sort_order: 0,
            is_active: 1
        }, function (res) {
            $('#qvBtnSave').prop('disabled', false).html('<i class="bx bx-check me-1"></i>Tạo biến thể & chọn ngay');

            if (!res.success) {
                showToast('Lỗi: ' + (res.data?.message || 'Không xác định'), 'danger');
                return;
            }

            // Đóng modal
            bootstrap.Modal.getInstance(document.getElementById('modalQuickVariant'))?.hide();

            // Reload variants & auto-select mới tạo
            const newId = res.data.variant_id;
            loadVariants(selectedProduct.id, newId);

            showToast('✅ ' + (res.data.message || 'Đã tạo biến thể!'), 'success');
        }).fail(function () {
            $('#qvBtnSave').prop('disabled', false).html('<i class="bx bx-check me-1"></i>Tạo biến thể & chọn ngay');
            showToast('Lỗi kết nối server', 'danger');
        });
    });

    /* ====================================================================
     * ⑥ FORM SUBMIT — Sinh mã định danh
     * ==================================================================== */

    $('#lotGenForm').on('submit', function (e) {
        e.preventDefault();

        const productId = $('#productId').val();
        if (!productId) { showToast('Vui lòng chọn sản phẩm.', 'danger'); return; }

        const qty = parseInt($('#quantity').val());
        if (!qty || qty < 1 || qty > 5000) { showToast('Số lượng phải từ 1 đến 5000.', 'danger'); return; }

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
                showToast('Lỗi: ' + (res.data?.message || 'Không xác định'), 'danger');
                return;
            }

            const d = res.data;
            showToast(`✅ ${d.message} — Mã phiếu: ${d.ticket_code}, Số mã: ${d.lots_created}`, 'success');

            // Redirect to detail after short delay
            setTimeout(function () {
                const u = new URL(window.location.href);
                u.searchParams.set('view', 'lot-gen-detail');
                u.searchParams.set('ledger_id', d.ledger_id);
                for (const key of [...u.searchParams.keys()]) {
                    if (!['page', 'view', 'ledger_id'].includes(key)) u.searchParams.delete(key);
                }
                window.location.href = u.toString();
            }, 800);
        }).fail(function () {
            $('#genOverlay').hide();
            $('#btnGenerate').prop('disabled', false);
            showToast('Lỗi kết nối server.', 'danger');
        });
    });

    /* ====================================================================
     * ⑦ RESET
     * ==================================================================== */

    $('#btnReset').on('click', function () {
        $('#clearProduct').trigger('click');
        $('#quantity').val(1);
        $('#lotCode, #mfgDate, #expDate, #note').val('');
    });

})(jQuery);
