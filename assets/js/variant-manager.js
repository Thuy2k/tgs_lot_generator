/**
 * variant-manager.js — Quản lý biến thể sản phẩm
 *
 * Handles:
 * - Product search (autocomplete)
 * - Auto-fill mẫu khi chọn loại biến thể
 * - Variant CRUD (list/add/edit/delete)
 * - Quick-add value chips
 *
 * @package tgs_lot_generator
 */
(function ($) {
    'use strict';

    const C = window.tgsLotGen || {};
    let searchTimeout = null;
    let selectedProductId = 0;

    /* ── Preset data cho từng loại biến thể ─────────────────────── */
    const PRESETS = {
        size:   { label: 'Size',          chips: ['S', 'M', 'L', 'XL', 'XXL', '28', '30', '32'], skuMap: { S: '-S', M: '-M', L: '-L', XL: '-XL', XXL: '-XXL' } },
        color:  { label: 'Màu sắc',       chips: ['Trắng', 'Đen', 'Xanh dương', 'Xanh lá', 'Đỏ', 'Vàng', 'Hồng', 'Nâu'], skuMap: { 'Trắng': '-WHT', 'Đen': '-BLK', 'Xanh dương': '-BLU', 'Đỏ': '-RED', 'Vàng': '-YLW', 'Hồng': '-PNK' } },
        expiry: { label: 'Hạn sử dụng',   chips: ['3 tháng', '6 tháng', '12 tháng', '18 tháng', '24 tháng', '36 tháng'], skuMap: { '3 tháng': '-3M', '6 tháng': '-6M', '12 tháng': '-12M', '24 tháng': '-24M' } },
        flavor: { label: 'Hương vị',       chips: ['Vani', 'Dâu', 'Socola', 'Trái cây', 'Matcha', 'Cam', 'Việt quất', 'Không đường'], skuMap: { 'Vani': '-VAN', 'Dâu': '-STR', 'Socola': '-CHO', 'Matcha': '-MAT' } },
        weight: { label: 'Trọng lượng',    chips: ['100g', '200g', '400g', '500g', '800g', '1kg', '1.5kg', '2kg'], skuMap: { '100g': '-100G', '200g': '-200G', '400g': '-400G', '500g': '-500G', '1kg': '-1KG' } },
        custom: { label: '',               chips: [], skuMap: {} }
    };

    /* ── Product Search ─────────────────────────────────────────── */

    $('#varProductSearch').on('input', function () {
        const kw = $(this).val().trim();
        if (kw.length < 2) { $('#varProductDropdown').hide(); return; }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            $.post(C.ajaxUrl, {
                action: 'tgs_lot_gen_search_products',
                nonce: C.nonce,
                keyword: kw,
                blog_id: C.blogId
            }, function (res) {
                if (!res.success || !res.data.products.length) {
                    $('#varProductDropdown').html('<div class="var-dd-item var-dd-empty"><i class="bx bx-search-alt me-1"></i>Không tìm thấy sản phẩm nào</div>').show();
                    return;
                }
                let html = '';
                res.data.products.forEach(p => {
                    const price = parseFloat(p.local_product_price_after_tax || 0);
                    const priceFmt = price > 0 ? new Intl.NumberFormat('vi-VN').format(price) + 'đ' : '';
                    html += `<a href="#" class="var-dd-item var-product-item" data-id="${p.local_product_name_id}"
                        data-name="${encodeURIComponent(p.local_product_name)}">
                        <div class="var-dd-name">${p.local_product_name}</div>
                        <div class="var-dd-meta">
                            <span><i class="bx bx-barcode me-1"></i>${p.local_product_barcode_main || '—'}</span>
                            <span><i class="bx bx-purchase-tag me-1"></i>${p.local_product_sku || '—'}</span>
                            ${priceFmt ? '<span class="var-dd-price">' + priceFmt + '</span>' : ''}
                        </div>
                    </a>`;
                });
                $('#varProductDropdown').html(html).show();
            });
        }, 300);
    });

    /* Focus → mở lại nếu đã có kết quả */
    $('#varProductSearch').on('focus', function () {
        if ($('#varProductDropdown').children().length > 0 && !selectedProductId) {
            $('#varProductDropdown').show();
        }
    });

    $(document).on('click', '.var-product-item', function (e) {
        e.preventDefault();
        selectedProductId = $(this).data('id');
        const name = decodeURIComponent($(this).data('name'));
        $('#varProductId').val(selectedProductId);
        $('#varProductSearch').val(name).prop('disabled', true);
        $('#varProductName').text(name);
        $('#varProductInfo').show();
        $('#varProductDropdown').hide();

        // Bật vùng làm việc
        $('#variantWorkArea').css({ opacity: 1, 'pointer-events': 'auto' });
        $('#varProductNameBadge').text(name).show();

        loadVariants();
    });

    $('#varClearProduct').on('click', function () {
        selectedProductId = 0;
        $('#varProductId').val('');
        $('#varProductSearch').val('').prop('disabled', false).focus();
        $('#varProductInfo').hide();
        $('#varProductDropdown').hide();

        // Tắt vùng làm việc
        $('#variantWorkArea').css({ opacity: 0.5, 'pointer-events': 'none' });
        $('#varProductNameBadge').hide();
        $('#varTableBody').html('<tr><td colspan="7" class="text-center py-5"><i class="bx bx-package text-muted" style="font-size:48px;"></i><p class="text-muted mt-2 mb-0">Chọn sản phẩm ở bước 1 để xem & thêm biến thể</p></td></tr>');
        $('#varCount').text('0');
        resetForm();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#varProductSearch, #varProductDropdown, .input-group').length) {
            $('#varProductDropdown').hide();
        }
    });

    /* ── Auto-fill khi đổi loại biến thể ─────────────────────────── */

    $('#varType').on('change', function () {
        const type = $(this).val();
        const preset = PRESETS[type] || PRESETS.custom;
        const isEditing = parseInt($('#varEditId').val()) > 0;

        // Chỉ auto-fill khi đang thêm mới (không phải sửa)
        if (!isEditing) {
            // Auto-fill Label
            if (preset.label) {
                $('#varLabel').val(preset.label);
            } else {
                $('#varLabel').val('');
            }
            // Clear value & SKU (user sẽ chọn từ chips hoặc tự gõ)
            $('#varValue').val('');
            $('#varSkuSuffix').val('');
        }

        // Show hint
        const $opt = $(this).find(':selected');
        const hint = $opt.data('value-hint');
        if (hint) {
            $('#varTypeHint').show();
            $('#varTypeHintText').text('Gợi ý giá trị: ' + hint);
        } else {
            $('#varTypeHint').hide();
        }

        // Render chips
        renderValueChips(type);
    });

    /** Render quick-add chips dưới field "Giá trị" */
    function renderValueChips(type) {
        const preset = PRESETS[type] || PRESETS.custom;
        if (!preset.chips.length) {
            $('#varValueChips').hide().html('');
            return;
        }
        let html = '<span class="text-muted me-1" style="font-size:11px;">Chọn nhanh:</span>';
        preset.chips.forEach(chip => {
            html += `<button type="button" class="btn btn-xs btn-outline-primary var-chip me-1 mb-1" data-val="${escAttr(chip)}" data-type="${type}">${escHtml(chip)}</button>`;
        });
        $('#varValueChips').html(html).show();
    }

    /** Click chip → điền giá trị + tự gợi ý SKU suffix */
    $(document).on('click', '.var-chip', function () {
        const val = $(this).data('val');
        const type = $(this).data('type');
        const preset = PRESETS[type] || {};

        $('#varValue').val(val).trigger('focus');

        // Tự điền SKU suffix nếu có mapping
        if (preset.skuMap && preset.skuMap[val]) {
            $('#varSkuSuffix').val(preset.skuMap[val]);
        }

        // Highlight chip đã chọn
        $('.var-chip').removeClass('active');
        $(this).addClass('active');
    });

    /* ── Load Variants ──────────────────────────────────────────── */

    function loadVariants() {
        if (!selectedProductId) return;

        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_get_variants',
            nonce: C.nonce,
            product_id: selectedProductId,
            blog_id: C.blogId
        }, function (res) {
            if (!res.success || !res.data.variants.length) {
                $('#varTableBody').html(`<tr><td colspan="7" class="text-center py-5">
                    <i class="bx bx-plus-circle text-muted" style="font-size:40px;"></i>
                    <p class="text-muted mt-2 mb-0">Chưa có biến thể nào.<br><small>Dùng form bên trái để thêm biến thể đầu tiên.</small></p>
                </td></tr>`);
                $('#varCount').text('0');
                return;
            }

            const variants = res.data.variants;
            $('#varCount').text(variants.length);

            const typeLabels = { size: '📐 Size', color: '🎨 Màu', expiry: '📅 HSD', flavor: '🍓 Hương vị', weight: '⚖️ KL', custom: '⚙️ Custom' };

            let html = '';
            variants.forEach((v, idx) => {
                const adj = parseFloat(v.variant_price_adjustment || 0);
                const adjStr = adj !== 0 ? (adj > 0 ? '+' : '') + new Intl.NumberFormat('vi-VN').format(adj) + 'đ' : '—';

                html += `<tr>
                    <td>${idx + 1}</td>
                    <td><span class="badge bg-label-info">${typeLabels[v.variant_type] || v.variant_type}</span></td>
                    <td>${escHtml(v.variant_label)}</td>
                    <td><strong>${escHtml(v.variant_value)}</strong></td>
                    <td><code>${escHtml(v.variant_sku_suffix || '—')}</code></td>
                    <td>${adjStr}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-var" data-var="${escAttr(JSON.stringify(v))}" title="Sửa">
                            <i class="bx bx-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-del-var" data-id="${v.variant_id}" title="Xóa">
                            <i class="bx bx-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });

            $('#varTableBody').html(html);
        });
    }

    /* ── Save Variant ───────────────────────────────────────────── */

    $('#variantForm').on('submit', function (e) {
        e.preventDefault();
        if (!selectedProductId) { alert('Vui lòng chọn sản phẩm trước.'); return; }

        const label = $('#varLabel').val().trim();
        const value = $('#varValue').val().trim();
        if (!label || !value) { alert('Nhãn và Giá trị là bắt buộc.'); return; }

        $('#varBtnSave').prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Đang lưu...');

        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_save_variant',
            nonce: C.nonce,
            variant_id: $('#varEditId').val() || 0,
            product_id: selectedProductId,
            blog_id: C.blogId,
            variant_type: $('#varType').val(),
            variant_label: label,
            variant_value: value,
            variant_sku_suffix: $('#varSkuSuffix').val(),
            variant_price_adjustment: $('#varPriceAdj').val() || 0,
            variant_sort_order: $('#varSort').val() || 0,
            is_active: 1
        }, function (res) {
            $('#varBtnSave').prop('disabled', false);
            if (res.success) {
                // Không dùng alert — hiện toast nhẹ hơn
                showToast('✅ ' + res.data.message);
                resetForm();
                loadVariants();
            } else {
                alert('Lỗi: ' + (res.data?.message || 'Không xác định'));
                $('#varBtnSave').html('<i class="bx bx-save me-1"></i>Lưu');
            }
        }).fail(function () {
            $('#varBtnSave').prop('disabled', false).html('<i class="bx bx-save me-1"></i>Lưu');
            alert('Lỗi kết nối server.');
        });
    });

    /* ── Edit Variant ───────────────────────────────────────────── */

    $(document).on('click', '.btn-edit-var', function () {
        let v = $(this).data('var');
        if (typeof v === 'string') v = JSON.parse(v);

        $('#varEditId').val(v.variant_id);
        $('#varType').val(v.variant_type).trigger('change');
        $('#varLabel').val(v.variant_label);
        $('#varValue').val(v.variant_value);
        $('#varSkuSuffix').val(v.variant_sku_suffix || '');
        $('#varPriceAdj').val(v.variant_price_adjustment || 0);
        $('#varSort').val(v.variant_sort_order || 0);

        $('#varFormTitle').html('<i class="bx bx-edit me-1"></i>Sửa biến thể #' + v.variant_id);
        $('#varBtnSave').html('<i class="bx bx-save me-1"></i>Cập nhật');
        $('#varBtnCancel').show();

        // Scroll form vào view
        document.getElementById('variantForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    /* ── Delete Variant ─────────────────────────────────────────── */

    $(document).on('click', '.btn-del-var', function () {
        const id = $(this).data('id');
        if (!confirm('Xóa biến thể này?')) return;

        $.post(C.ajaxUrl, {
            action: 'tgs_lot_gen_delete_variant',
            nonce: C.nonce,
            variant_id: id
        }, function (res) {
            if (res.success) {
                showToast('✅ ' + res.data.message);
                loadVariants();
            } else {
                alert('Lỗi: ' + (res.data?.message || 'Không xác định'));
            }
        });
    });

    /* ── Cancel Edit ─────────────────────────────────────────────── */

    $('#varBtnCancel').on('click', resetForm);

    function resetForm() {
        $('#varEditId').val(0);
        $('#varType').val('custom');
        $('#varLabel, #varValue, #varSkuSuffix').val('');
        $('#varPriceAdj, #varSort').val(0);
        $('#varFormTitle').html('<i class="bx bx-plus me-1"></i>Thêm biến thể');
        $('#varBtnSave').html('<i class="bx bx-save me-1"></i>Lưu');
        $('#varBtnCancel').hide();
        $('#varTypeHint').hide();
        $('#varValueChips').hide();
        $('.var-chip').removeClass('active');
    }

    /* ── Toast nhẹ (thay alert) ──────────────────────────────────── */

    function showToast(msg) {
        const $t = $('<div class="var-toast">' + escHtml(msg) + '</div>');
        $('body').append($t);
        setTimeout(() => $t.addClass('show'), 10);
        setTimeout(() => { $t.removeClass('show'); setTimeout(() => $t.remove(), 300); }, 2500);
    }

    /* ── Helpers ──────────────────────────────────────────────────── */

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /** Escape string cho HTML attribute (xử lý &, ", ', <, >) */
    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

})(jQuery);
