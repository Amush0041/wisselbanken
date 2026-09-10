@extends('user.layouts.app')

@section('seo')
<title>My Products | {{ config('app.name', 'Wisselbanken') }}</title>
@endsection

@php
use App\Services\Rbac\PermissionService;
$_pOrgId = session(config('rbac.current_org_session_key'));
$_pUser  = auth()->user();
$_pAdm   = $_pUser && $_pUser->role === 'admin';
$_pc     = fn(string $l) => $_pUser && ($_pAdm || ($_pOrgId && app(PermissionService::class)->checkPermission($_pUser->id, (int) $_pOrgId, 'product_management', $l)));
$canCreateProduct = (bool) $_pc('S');
$canEditProduct   = (bool) $_pc('O');
$canDeleteProduct = (bool) $_pc('F');
@endphp
<script>
window.RBAC_PRODUCTS = {
    canCreate: {{ $canCreateProduct ? 'true' : 'false' }},
    canEdit:   {{ $canEditProduct   ? 'true' : 'false' }},
    canDelete: {{ $canDeleteProduct ? 'true' : 'false' }},
};
</script>

@push('css')
<style>
/* Fill layout-page content slot only — do not use 100vh here or the block overflows and the theme footer overlaps the list. */
.user-products-page.container-fluid {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
.user-products-screen {
    background: #ffffff;
    color: #384551;
    border: 1px solid #dfe3ea;
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.ups-grid { display: grid; grid-template-columns: 300px minmax(0, 1fr); flex: 1 1 auto; min-height: 0; }
.ups-left { border-right: 1px solid #dfe3ea; display: flex; flex-direction: column; min-width: 0; background: #ffffff; min-height: 0; }
.ups-right { display: flex; flex-direction: column; min-width: 0; background: #ffffff; min-height: 0; overflow: hidden; }
.ups-hd {
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 0 12px;
    border-bottom: 1px solid #dfe3ea;
    background: #f7f8fa;
    flex-shrink: 0;
    min-width: 0;
    overflow: hidden;
}
.ups-hd h5 {
    margin: 0;
    color: #384551;
    font-size: 16px;
    font-weight: 600;
    min-width: 0;
    flex: 1 1 auto;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.2;
}
.ups-tools { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
/* Editor toolbar: no title — actions only */
.ups-right .ups-hd { justify-content: flex-end; }
.ups-icon-btn { border: 1px solid #d4dae3; background: #ffffff; color: #5a6673; width: 30px; height: 30px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; }
.ups-search { padding: 8px 10px; border-bottom: 1px solid #dfe3ea; }
.ups-search input { background: #ffffff; border: 1px solid #cfd6e2; color: #384551; border-radius: 4px; height: 34px; font-size: 13px; }
.ups-list { flex: 1; overflow-y: auto; padding-bottom: 68px; }
.ups-item { padding: 10px 12px; border-bottom: 1px solid #edf0f5; cursor: pointer; }
.ups-item.active { background: #eef2f7; }
.ups-item-name { color: #394657; font-size: 14px; font-weight: 600; }
.ups-item-sub { color: #7e8a97; font-size: 12px; }
.ups-item-price { color: #4a5968; font-size: 12px; text-align: right; }
.ups-foot { height: 38px; border-top: 1px solid #dfe3ea; display: flex; align-items: center; justify-content: center; color: #7e8a97; font-size: 13px; background: #f7f8fa; }
.ups-body { padding: 0; flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; }
.ups-sec { border-bottom: 1px solid #e4e8ef; }
.ups-sec-h { background: #e8eef6; color: #2f3d4d; padding: 3px 8px; font-size: 11px; font-weight: 700; line-height: 1.25; letter-spacing: 0.02em; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between; }
.ups-sec-h.sub { font-size: 12px; }
.ups-grid-2 { display: grid; grid-template-columns: 1fr 1fr; }
.ups-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
.ups-grid-3 .ups-cell { border-right: 1px solid #e4e8ef; border-bottom: 1px solid #e4e8ef; }
.ups-grid-3 .ups-cell:nth-child(3n) { border-right: none; }
.ups-grid-3 > .ups-cell:last-child { border-right: none; }
.ups-span-2 { grid-column: span 2; }
.ups-split { display: grid; grid-template-columns: minmax(0, 1fr) 90px; align-items: stretch; }
.ups-split-main { min-width: 0; display: flex; flex-direction: column; }
.ups-split-side { border-left: 1px solid #e4e8ef; padding: 5px 6px; display: flex; flex-direction: column; gap: 3px; min-width: 0; background: #fbfcfe; }
.ups-split-side .ups-label { margin-bottom: 0; }
.ups-cell { padding: 5px 8px; border-right: 1px solid #e4e8ef; border-bottom: 1px solid #e4e8ef; min-height: 0; }
.ups-grid-2 .ups-cell:nth-child(2n) { border-right: none; }
.ups-label { color: #7f8a98; font-size: 11px; margin-bottom: 2px; }
.ups-value { color: #394657; font-size: 16px; font-weight: 600; }
.ups-inp, .ups-txt { width: 100%; background: #ffffff; border: 1px solid #cfd6e2; color: #384551; border-radius: 4px; padding: 4px 8px; font-size: 13px; line-height: 1.35; }
.ups-inp { min-height: 30px; }
.ups-txt { min-height: 40px; resize: vertical; }
.ups-switch { display: flex; align-items: center; gap: 8px; color: #5f6b78; font-size: 13px; }
.ups-actions { display: flex; gap: 10px; align-items: center; flex-shrink: 0; }
.ups-save { background: #4A171E; color: #fff; border: none; border-radius: 4px; height: 30px; padding: 0 12px; font-size: 13px; font-weight: 600; }
.ups-save:hover { background: #5a1f28; }
.ups-delete { background: #e14d4d; color: #fff; border: none; border-radius: 4px; height: 30px; padding: 0 12px; font-size: 13px; font-weight: 600; }
.ups-menu-wrap { position: relative; }
.ups-menu-btn { border: 1px solid #d4dae3; background: #fff; color: #5a6673; width: 34px; height: 34px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; }
.ups-menu { position: absolute; top: 38px; right: 0; min-width: 180px; background: #fff; border: 1px solid #dfe3ea; border-radius: 6px; box-shadow: 0 8px 20px rgba(0,0,0,.12); z-index: 10; display: none; }
.ups-menu.show { display: block; }
.ups-menu-item { width: 100%; border: none; background: transparent; text-align: left; padding: 10px 12px; color: #334155; font-size: 14px; }
.ups-menu-item:hover { background: #f5f7fb; }
.ups-menu-item.archive { color: #128a42; }
.ups-menu-item.delete { color: #c62828; }
.ups-modal-label { font-size: 13px; color: #51606f; margin-bottom: 6px; }
.ups-new-fab { position: sticky; align-self: flex-end; right: 16px; bottom: 64px; margin-right: 16px; margin-top: auto; margin-bottom: 12px; width: 42px; height: 42px; border-radius: 50%; border: none; background: #ff9800; color: #fff; font-size: 22px; font-weight: 700; box-shadow: 0 8px 16px rgba(0,0,0,.35); z-index: 3; }
.ups-left-wrap { position: relative; display: flex; flex: 1; min-height: 0; flex-direction: column; }
.ups-image-box { border: 1px dashed #c2ccd9; border-radius: 4px; min-height: 40px; max-height: 52px; display: flex; align-items: center; justify-content: center; color: #8a98ac; font-size: 11px; background: #fff; overflow: hidden; }
.ups-upload-row { margin-top: 4px; display: flex; flex-direction: column; gap: 4px; align-items: stretch; }
.ups-upload-btn { border: 1px solid #cfd6e2; background: #fff; color: #4f5d6c; border-radius: 4px; height: 26px; padding: 0 8px; font-size: 11px; }
.ups-upload-name { color: #7f8a98; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ups-hidden-file { display: none; }
.ups-error { margin-top: 4px; color: #d93025; font-size: 12px; line-height: 1.3; min-height: 16px; }
.ups-inp.is-invalid, .ups-txt.is-invalid { border-color: #d93025; }
@media (max-width: 1200px) {
    .ups-grid { grid-template-columns: 1fr; }
    .ups-left { min-height: 220px; max-height: 40vh; }
    .ups-split { grid-template-columns: 1fr; }
    .ups-split-side { border-left: none; border-top: 1px solid #e4e8ef; }
}
.ups-split-main > .ups-cell.ups-cell--wide { border-right: none; }
.ups-inv-row { padding: 4px 10px 6px; border-bottom: 1px solid #e4e8ef; background: #fff; }
/* List item redesign */
.ups-item-row { display: flex; gap: 8px; align-items: center; }
.ups-item-thumb { width: 34px; height: 34px; flex-shrink: 0; border-radius: 4px; overflow: hidden; background: #edf1f7; display: flex; align-items: center; justify-content: center; }
.ups-item-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ups-item-thumb--empty { color: #b2bcca; font-size: 15px; }
.ups-item-body { flex: 1 1 auto; min-width: 0; }
.ups-item-badges { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 3px; }
.ups-badge { display: inline-block; padding: 1px 6px; border-radius: 10px; background: #edf1f7; color: #57607a; font-size: 10px; font-weight: 600; letter-spacing: 0.01em; }
.ups-item-price-wrap { flex-shrink: 0; text-align: right; }
.ups-item-notes { font-size: 11px; color: #96a0ae; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-left: 42px; font-style: italic; }
/* Welcome / empty states */
.ups-welcome { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 32px; text-align: center; }
.ups-welcome-icon { font-size: 46px; color: #c8d1de; margin-bottom: 14px; line-height: 1; }
.ups-welcome-title { font-size: 17px; font-weight: 600; color: #5a6778; margin-bottom: 8px; }
.ups-welcome-desc { font-size: 13px; color: #8e9aaa; max-width: 310px; line-height: 1.55; }
.ups-empty-list { padding: 32px 16px; text-align: center; }
.ups-empty-list-icon { font-size: 30px; color: #c8d1de; margin-bottom: 8px; }
.ups-empty-list-text { font-size: 13px; font-weight: 600; color: #7e8a97; margin-bottom: 3px; }
.ups-empty-list-hint { font-size: 11px; color: #aab3bf; }
/* Right header title */
.ups-right .ups-hd { justify-content: space-between; }
.ups-hd-title { font-size: 13px; font-weight: 600; color: #4a5968; min-width: 0; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 8px; }
</style>
@endpush

@section('content')
<div class="container-fluid flex-grow-1 user-page user-products-page">
    <div class="user-products-screen">
        <div class="ups-grid">
            <div class="ups-left">
                <div class="ups-hd">
                    <h5>Product Catalog</h5>
                    <div class="ups-tools">
                        <button type="button" class="ups-icon-btn" onclick="loadProductList(1)" title="Refresh"><i class="ti ti-refresh"></i></button>
                    </div>
                </div>
                <div class="ups-left-wrap">
                    <div class="ups-search"><input type="text" id="productSearch" class="ups-inp" placeholder="Search products"></div>
                    <div class="ups-list" id="productList"></div>
                    @if ($canCreateProduct)
                    <button type="button" class="ups-new-fab" onclick="startNewProduct()" title="New Product">+</button>
                    @endif
                </div>
                <div class="ups-foot" id="productPagination">0 Product</div>
            </div>
            <div class="ups-right">
                <div class="ups-hd">
                    <span class="ups-hd-title" id="editorTitle"></span>
                    <div class="ups-actions">
                        @if ($canEditProduct || $canCreateProduct)
                        <button type="button" class="ups-save" onclick="saveProduct()">Save</button>
                        @endif
                        @if ($canDeleteProduct)
                        <button type="button" class="ups-delete" id="deleteBtn" onclick="deleteProduct()" style="display:none;">Delete</button>
                        @endif
                        @if ($canEditProduct)
                        <div class="ups-menu-wrap" id="productActionsWrap" style="display:none;">
                            <button type="button" class="ups-menu-btn" id="productActionsBtn" title="More actions"><i class="ti ti-dots-vertical"></i></button>
                            <div class="ups-menu" id="productActionsMenu">
                                <button type="button" class="ups-menu-item" onclick="openVariationModal()">Add Variation</button>
                                <button type="button" class="ups-menu-item" onclick="duplicateProduct()">Duplicate Product</button>
                                <button type="button" class="ups-menu-item archive" onclick="archiveProduct()">Archive Product</button>
                                @if ($canDeleteProduct)
                                <button type="button" class="ups-menu-item delete" onclick="deleteProduct()">Delete</button>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="ups-body" id="productEditor"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="variationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Variation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <div class="ups-modal-label">Variant Size *</div>
                    <input type="text" id="variation_size" class="ups-inp" placeholder="e.g. Medium / 4x8">
                    <div class="ups-error" id="variation_size_error"></div>
                </div>
                <div class="mb-2">
                    <div class="ups-modal-label">SKU</div>
                    <input type="text" id="variation_sku" class="ups-inp">
                    <div class="ups-error" id="variation_sku_error"></div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="ups-modal-label">Quantity</div>
                        <input type="text" id="variation_quantity" class="ups-inp" value="1">
                        <div class="ups-error" id="variation_quantity_error"></div>
                    </div>
                    <div class="col-6">
                        <div class="ups-modal-label">Unit Type</div>
                        <input type="text" id="variation_unit_type" class="ups-inp" value="box">
                        <div class="ups-error" id="variation_unit_type_error"></div>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <div class="ups-modal-label">Sell Price</div>
                        <input type="text" id="variation_sell_price" class="ups-inp" value="0">
                        <div class="ups-error" id="variation_sell_price_error"></div>
                    </div>
                    <div class="col-6">
                        <div class="ups-modal-label">Currency</div>
                        <input type="text" id="variation_currency" class="ups-inp" placeholder="PKR / USD">
                        <div class="ups-error" id="variation_currency_error"></div>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="ups-switch"><input type="checkbox" id="variation_inventory_enabled" checked> Inventory enabled</label>
                    <div class="ups-error" id="variation_inventory_enabled_error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                @if ($canEditProduct)
                <button type="button" class="ups-save" onclick="submitVariation()">Add Variation</button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const LIST_URL = @json(route('user-products.list'));
    const STORE_URL = @json(route('user-products.store'));
    const BASE_URL = @json(url('user-products'));
    const DUPLICATE_URL_BASE = @json(url('user-products')) + '/';
    const ARCHIVE_SUFFIX = '/archive';
    const DUPLICATE_SUFFIX = '/duplicate';
    const VARIATION_SUFFIX = '/variation';
    const CSRF = @json(csrf_token());
    let selectedId = null;
    let currentPage = 1;
    let selectedImageFile = null;
    let searchDebounceTimer = null;
    let variationModalInstance = null;
    let currentParentProductId = null;
    const FIELD_ID_BY_KEY = {
        name: 'f_name',
        sku: 'f_sku',
        variant_size: 'f_variant_size',
        category: 'f_category',
        quantity: 'f_quantity',
        unit_type: 'f_unit_type',
        buy_price: 'f_buy_price',
        buy_price_tax: 'f_buy_price_tax',
        sell_price: 'f_sell_price',
        sell_price_tax: 'f_sell_price_tax',
        currency: 'f_currency',
        stock: 'f_stock',
        inventory_enabled: 'f_inventory_enabled',
        on_hand_stock: 'f_on_hand_stock',
        committed_stock: 'f_committed_stock',
        available_for_sale: 'f_available_for_sale',
        to_be_invoiced: 'f_to_be_invoiced',
        to_be_billed: 'f_to_be_billed',
        image_url: 'f_image_url',
        image_file: 'f_image_file',
        description: 'f_description',
        item_notes: 'f_notes'
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    const CURRENCY_SYMBOLS = { USD: '$', EUR: '€', GBP: '£', PKR: 'Rs ', CAD: 'CA$', AUD: 'A$', AED: 'AED ', SAR: 'SAR ', INR: '₹', CNY: '¥', JPY: '¥', CHF: 'CHF ' };

    function formatPrice(price, currency) {
        const n = parseFloat(price) || 0;
        const code = (currency || '').toUpperCase().trim();
        const sym = CURRENCY_SYMBOLS[code] || (code ? code + ' ' : '');
        return sym + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function numberValue(id) {
        const raw = (document.getElementById(id)?.value || '').trim();
        if (raw === '') return 0;
        const n = parseFloat(raw);
        return Number.isFinite(n) ? n : 0;
    }

    function boolValue(id) {
        return !!document.getElementById(id)?.checked;
    }

    function normalizeCurrencyValue(raw) {
        const s = String(raw || '').trim();
        if (!s) return '';
        const firstToken = s.split(/[\s-]+/)[0] || '';
        const code = firstToken.replace(/[^A-Za-z]/g, '').toUpperCase();
        if (code) return code.slice(0, 10);
        return s.toUpperCase().slice(0, 10);
    }

    function buildPayload() {
        const payload = {
            name: (document.getElementById('f_name')?.value || '').trim(),
            sku: (document.getElementById('f_sku')?.value || '').trim(),
            variant_size: (document.getElementById('f_variant_size')?.value || '').trim(),
            category: (document.getElementById('f_category')?.value || '').trim(),
            quantity: numberValue('f_quantity'),
            unit_type: (document.getElementById('f_unit_type')?.value || '').trim(),
            buy_price: numberValue('f_buy_price'),
            buy_price_tax: numberValue('f_buy_price_tax'),
            sell_price: numberValue('f_sell_price'),
            sell_price_tax: numberValue('f_sell_price_tax'),
            currency: normalizeCurrencyValue(document.getElementById('f_currency')?.value || ''),
            stock: numberValue('f_stock'),
            inventory_enabled: boolValue('f_inventory_enabled') ? 1 : 0,
            on_hand_stock: numberValue('f_on_hand_stock'),
            committed_stock: numberValue('f_committed_stock'),
            available_for_sale: numberValue('f_available_for_sale'),
            to_be_invoiced: numberValue('f_to_be_invoiced'),
            to_be_billed: numberValue('f_to_be_billed'),
            image_url: (document.getElementById('f_image_url')?.value || '').trim(),
            description: (document.getElementById('f_description')?.value || '').trim(),
            item_notes: (document.getElementById('f_notes')?.value || '').trim()
        };
        if (currentParentProductId) {
            payload.parent_product_id = currentParentProductId;
        }
        return payload;
    }

    function setImageFile(file) {
        selectedImageFile = file || null;
        const nameEl = document.getElementById('f_image_file_name');
        if (nameEl) {
            nameEl.textContent = selectedImageFile ? selectedImageFile.name : 'No file selected';
        }
        const box = document.getElementById('f_image_preview');
        if (!box) return;
        if (!selectedImageFile) return;
        const previewUrl = URL.createObjectURL(selectedImageFile);
        box.innerHTML = '<img src="' + esc(previewUrl) + '" style="max-height:40px;max-width:100%;object-fit:contain;" alt="Product image preview"/>';
    }

    function clearFieldErrors() {
        document.querySelectorAll('.ups-error').forEach(function(el) { el.textContent = ''; });
        document.querySelectorAll('.ups-inp.is-invalid, .ups-txt.is-invalid').forEach(function(el) {
            el.classList.remove('is-invalid');
        });
    }

    function clearVariationErrors() {
        ['variation_size','variation_sku','variation_quantity','variation_unit_type','variation_sell_price','variation_currency','variation_inventory_enabled'].forEach(function(id) {
            const err = document.getElementById(id + '_error');
            if (err) err.textContent = '';
            const el = document.getElementById(id);
            if (el && el.classList.contains('ups-inp')) el.classList.remove('is-invalid');
        });
    }

    function applyVariationErrors(errors) {
        clearVariationErrors();
        const map = {
            variant_size: 'variation_size',
            sku: 'variation_sku',
            quantity: 'variation_quantity',
            unit_type: 'variation_unit_type',
            sell_price: 'variation_sell_price',
            currency: 'variation_currency',
            inventory_enabled: 'variation_inventory_enabled'
        };
        Object.keys(errors || {}).forEach(function(key) {
            const target = map[key];
            if (!target) return;
            const msg = Array.isArray(errors[key]) && errors[key].length ? String(errors[key][0]) : '';
            const err = document.getElementById(target + '_error');
            if (err) err.textContent = msg;
            const el = document.getElementById(target);
            if (el && el.classList.contains('ups-inp') && msg) el.classList.add('is-invalid');
        });
    }

    function showFieldError(fieldKey, message) {
        const fieldId = FIELD_ID_BY_KEY[fieldKey];
        if (!fieldId) return;
        const input = document.getElementById(fieldId);
        if (input && (input.classList.contains('ups-inp') || input.classList.contains('ups-txt'))) {
            input.classList.add('is-invalid');
        }
        const errEl = document.getElementById(fieldId + '_error');
        if (errEl) {
            errEl.textContent = message;
        }
    }

    function applyValidationErrors(errors) {
        clearFieldErrors();
        const errs = errors && typeof errors === 'object' ? errors : {};
        Object.keys(errs).forEach(function(fieldKey) {
            const messages = Array.isArray(errs[fieldKey]) ? errs[fieldKey] : [];
            if (messages.length > 0) {
                showFieldError(fieldKey, String(messages[0]));
            }
        });
    }

    function renderWelcomeState() {
        document.getElementById('deleteBtn').style.display = 'none';
        document.getElementById('productActionsWrap').style.display = 'none';
        const titleEl = document.getElementById('editorTitle');
        if (titleEl) titleEl.textContent = '';
        document.getElementById('productEditor').innerHTML = `
            <div class="ups-welcome">
                <div class="ups-welcome-icon"><i class="ti ti-package"></i></div>
                <div class="ups-welcome-title">Your Product Catalog</div>
                <div class="ups-welcome-desc">Build a library of products and services to quickly add to any estimate. Select an item on the left to edit it, or tap <strong>+</strong> to add a new one.</div>
            </div>
        `;
    }

    function renderEditor(p) {
        const x = p || {};
        document.getElementById('deleteBtn').style.display = x.id ? 'inline-block' : 'none';
        document.getElementById('productActionsWrap').style.display = x.id ? 'block' : 'none';
        const titleEl = document.getElementById('editorTitle');
        if (titleEl) titleEl.textContent = x.id ? (x.name || 'Product') : 'New Product';
        currentParentProductId = x.parent_product_id ? Number(x.parent_product_id) : null;
        selectedImageFile = null;
        const imgBox = x.image_url ? '<img src="' + esc(x.image_url) + '" style="max-height:40px;max-width:100%;object-fit:contain;" alt="Product image"/>' : '<i class="ti ti-photo" style="font-size:22px;"></i>';
        document.getElementById('productEditor').innerHTML = `
            <div class="ups-sec">
                <div class="ups-sec-h sub">General</div>
                <div class="ups-split">
                    <div class="ups-split-main">
                        <div class="ups-cell ups-cell--wide"><div class="ups-label">Product Name *</div><input id="f_name" class="ups-inp" value="${esc(x.name || '')}"><div class="ups-error" id="f_name_error"></div></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 0.65fr 0.9fr;">
                            <div class="ups-cell"><div class="ups-label">SKU</div><input id="f_sku" class="ups-inp" value="${esc(x.sku || '')}"><div class="ups-error" id="f_sku_error"></div></div>
                            <div class="ups-cell"><div class="ups-label">Category</div><input id="f_category" class="ups-inp" value="${esc(x.category || '')}"><div class="ups-error" id="f_category_error"></div></div>
                            <div class="ups-cell"><div class="ups-label">Variant Size</div><input id="f_variant_size" class="ups-inp" value="${esc(x.variant_size || '')}"><div class="ups-error" id="f_variant_size_error"></div></div>
                            <div class="ups-cell"><div class="ups-label">Qty</div><input id="f_quantity" class="ups-inp" value="${esc(x.quantity || '1')}"><div class="ups-error" id="f_quantity_error"></div></div>
                            <div class="ups-cell" style="border-right:none;"><div class="ups-label">Unit</div><input id="f_unit_type" class="ups-inp" value="${esc(x.unit_type || 'box')}"><div class="ups-error" id="f_unit_type_error"></div></div>
                        </div>
                    </div>
                    <aside class="ups-split-side">
                        <div class="ups-label">Image</div>
                        <div class="ups-image-box" id="f_image_preview">${imgBox}</div>
                        <div class="ups-upload-row">
                            <button type="button" class="ups-upload-btn" onclick="document.getElementById('f_image_file').click()">Upload</button>
                            <span class="ups-upload-name" id="f_image_file_name">No file selected</span>
                            <input type="file" id="f_image_file" class="ups-hidden-file" accept="image/*">
                        </div>
                        <div class="ups-error" id="f_image_file_error"></div>
                    </aside>
                </div>
            </div>
            <div class="ups-sec">
                <div class="ups-sec-h sub">Pricing & Stock</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 0.8fr 0.8fr 1.1fr;">
                    <div class="ups-cell"><div class="ups-label">Buy Price</div><input id="f_buy_price" class="ups-inp" value="${esc(x.buy_price || '0')}"><div class="ups-error" id="f_buy_price_error"></div></div>
                    <div class="ups-cell"><div class="ups-label">Buy Tax</div><input id="f_buy_price_tax" class="ups-inp" value="${esc(x.buy_price_tax || '0')}"><div class="ups-error" id="f_buy_price_tax_error"></div></div>
                    <div class="ups-cell"><div class="ups-label">Sell Price</div><input id="f_sell_price" class="ups-inp" value="${esc(x.sell_price || x.default_unit_price || '0')}"><div class="ups-error" id="f_sell_price_error"></div></div>
                    <div class="ups-cell"><div class="ups-label">Sell Tax</div><input id="f_sell_price_tax" class="ups-inp" value="${esc(x.sell_price_tax || '0')}"><div class="ups-error" id="f_sell_price_tax_error"></div></div>
                    <div class="ups-cell">
                        <div class="ups-label">Currency</div>
                        <input id="f_currency" class="ups-inp" list="currencySuggestions" placeholder="PKR" value="${esc(x.currency || '')}">
                        <datalist id="currencySuggestions"><option value="PKR"></option><option value="USD"></option><option value="EUR"></option><option value="GBP"></option><option value="CAD"></option><option value="AUD"></option><option value="AED"></option><option value="SAR"></option><option value="INR"></option><option value="CNY"></option><option value="JPY"></option><option value="CHF"></option><option value="SGD"></option><option value="HKD"></option><option value="ZAR"></option></datalist>
                        <div class="ups-error" id="f_currency_error"></div>
                    </div>
                    <div class="ups-cell"><div class="ups-label">Stock</div><input id="f_stock" class="ups-inp" value="${esc(x.stock || '0')}"><div class="ups-error" id="f_stock_error"></div></div>
                    <div class="ups-cell" style="border-right:none;display:flex;flex-direction:column;justify-content:center;">
                        <label class="ups-switch"><input type="checkbox" id="f_inventory_enabled" ${x.inventory_enabled === false ? '' : 'checked'}> Inventory</label>
                        <div class="ups-error" id="f_inventory_enabled_error"></div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;">
                    <div class="ups-cell"><div class="ups-label">On Hand</div><input id="f_on_hand_stock" class="ups-inp" value="${esc(x.on_hand_stock || '0')}"><div class="ups-error" id="f_on_hand_stock_error"></div></div>
                    <div class="ups-cell"><div class="ups-label">Committed</div><input id="f_committed_stock" class="ups-inp" value="${esc(x.committed_stock || '0')}"><div class="ups-error" id="f_committed_stock_error"></div></div>
                    <div class="ups-cell"><div class="ups-label">Available</div><input id="f_available_for_sale" class="ups-inp" value="${esc(x.available_for_sale || '0')}"><div class="ups-error" id="f_available_for_sale_error"></div></div>
                    <div class="ups-cell"><div class="ups-label">To Invoice</div><input id="f_to_be_invoiced" class="ups-inp" value="${esc(x.to_be_invoiced || '0')}"><div class="ups-error" id="f_to_be_invoiced_error"></div></div>
                    <div class="ups-cell" style="border-right:none;"><div class="ups-label">To Bill</div><input id="f_to_be_billed" class="ups-inp" value="${esc(x.to_be_billed || '0')}"><div class="ups-error" id="f_to_be_billed_error"></div></div>
                </div>
            </div>
            <div class="ups-sec" style="border-bottom:none;">
                <div style="display:grid;grid-template-columns:1fr 1.5fr 1.5fr;">
                    <div class="ups-cell"><div class="ups-label">Image URL</div><input id="f_image_url" class="ups-inp" value="${esc(x.image_url || '')}"><div class="ups-error" id="f_image_url_error"></div></div>
                    <div class="ups-cell"><div class="ups-label">Description</div><textarea id="f_description" class="ups-txt" rows="2">${esc(x.description || '')}</textarea><div class="ups-error" id="f_description_error"></div></div>
                    <div class="ups-cell" style="border-right:none;"><div class="ups-label">Notes</div><textarea id="f_notes" class="ups-txt" rows="2">${esc(x.item_notes || '')}</textarea><div class="ups-error" id="f_notes_error"></div></div>
                </div>
            </div>
        `;
        const fileInput = document.getElementById('f_image_file');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                setImageFile(file);
                showFieldError('image_file', '');
            });
        }
    }

    function loadProductList(page) {
        currentPage = page || 1;
        const q = document.getElementById('productSearch').value.trim();
        const params = new URLSearchParams({ page: currentPage, search: q });
        fetch(LIST_URL + '?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                const rows = data.products || [];
                const list = document.getElementById('productList');
                if (!rows.length) {
                    const isSearching = document.getElementById('productSearch').value.trim() !== '';
                    list.innerHTML = '<div class="ups-empty-list">' +
                        '<div class="ups-empty-list-icon"><i class="ti ti-package"></i></div>' +
                        '<div class="ups-empty-list-text">' + (isSearching ? 'No matches found' : 'No products yet') + '</div>' +
                        '<div class="ups-empty-list-hint">' + (isSearching ? 'Try a different search term' : 'Tap + to add your first product') + '</div>' +
                        '</div>';
                } else {
                    list.innerHTML = rows.map(function(p) {
                        const active = selectedId === p.id ? ' active' : '';
                        const price = formatPrice(p.sell_price || '0', p.currency);
                        const notes = (p.item_notes || '').trim();
                        const thumb = p.image_url
                            ? '<div class="ups-item-thumb"><img src="' + esc(p.image_url) + '" alt="" loading="lazy"></div>'
                            : '<div class="ups-item-thumb ups-item-thumb--empty"><i class="ti ti-package"></i></div>';
                        const varBadge = p.variant_size ? '<span class="ups-badge">' + esc(p.variant_size) + '</span>' : '';
                        const catBadge = p.category ? '<span class="ups-badge">' + esc(p.category) + '</span>' : '';
                        const hintBadge = !p.variant_size && !p.category && p.catalog_hint ? '<span class="ups-badge">' + esc(p.catalog_hint) + '</span>' : '';
                        const badges = varBadge + catBadge + hintBadge;
                        const notesHtml = notes ? '<div class="ups-item-notes">' + esc(notes.length > 55 ? notes.slice(0, 55) + '…' : notes) + '</div>' : '';
                        return '<div class="ups-item' + active + '" data-id="' + p.id + '">' +
                            '<div class="ups-item-row">' +
                            thumb +
                            '<div class="ups-item-body">' +
                            '<div class="ups-item-name">' + esc(p.name || 'Product') + '</div>' +
                            (badges ? '<div class="ups-item-badges">' + badges + '</div>' : '') +
                            '</div>' +
                            '<div class="ups-item-price-wrap"><div class="ups-item-price">' + esc(price) + '</div></div>' +
                            '</div>' +
                            notesHtml +
                            '</div>';
                    }).join('');
                    list.querySelectorAll('.ups-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            selectedId = parseInt(el.getAttribute('data-id'), 10);
                            list.querySelectorAll('.ups-item').forEach(function(x) { x.classList.remove('active'); });
                            el.classList.add('active');
                            loadProductDetail(selectedId);
                        });
                    });
                }
                const pg = data.pagination || {};
                const total = pg.total || 0;
                document.getElementById('productPagination').textContent = total + ' ' + (total === 1 ? 'Product' : 'Products');
            })
            .catch(function() {
                document.getElementById('productList').innerHTML = '<div class="ups-item"><div class="ups-item-sub text-danger">Could not load list</div></div>';
            });
    }

    function loadProductDetail(id) {
        fetch(BASE_URL + '/' + id, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renderEditor(data.product || {});
            })
            .catch(function() {
                renderEditor({});
            });
    }

    window.startNewProduct = function() {
        selectedId = null;
        currentParentProductId = null;
        document.querySelectorAll('.ups-item').forEach(function(x) { x.classList.remove('active'); });
        renderEditor({});
    };

    window.saveProduct = function() {
        clearFieldErrors();
        const payload = buildPayload();
        if (!payload.name) {
            showFieldError('name', 'Product Name is required.');
            return;
        }
        const isEdit = !!selectedId;
        const url = isEdit ? (BASE_URL + '/' + selectedId) : STORE_URL;
        const formData = new FormData();
        Object.keys(payload).forEach(function(key) {
            formData.append(key, payload[key] == null ? '' : String(payload[key]));
        });
        if (selectedImageFile) {
            formData.append('image_file', selectedImageFile, selectedImageFile.name);
        }
        // PHP does not parse multipart bodies on PUT; use POST + method spoof for edits.
        let fetchMethod = 'POST';
        if (isEdit) {
            formData.append('_method', 'PUT');
        }
        fetch(url, {
            method: fetchMethod,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        }).then(function(r) {
            return r.json().then(function(data) {
                return { ok: r.ok, status: r.status, data: data };
            });
        }).then(function(res) {
            if (!res.ok) {
                if (res.status === 422) {
                    applyValidationErrors(res.data.errors || {});
                    return;
                }
                alert(res.data && res.data.message ? res.data.message : 'Failed to save product.');
                return;
            }
            if (!isEdit && res.data && res.data.product_id) {
                selectedId = parseInt(res.data.product_id, 10);
            }
            loadProductList(currentPage);
            if (selectedId) {
                loadProductDetail(selectedId);
            }
        }).catch(function() {
            alert('Failed to save product.');
        });
    };

    window.deleteProduct = function() {
        if (!selectedId) return;
        if (!confirm('Remove this product?')) return;
        fetch(BASE_URL + '/' + selectedId, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); }).then(function() {
            selectedId = null;
            currentParentProductId = null;
            loadProductList(1);
            renderEditor({});
        });
    };

    window.duplicateProduct = function() {
        if (!selectedId) return;
        fetch(DUPLICATE_URL_BASE + selectedId + DUPLICATE_SUFFIX, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data && data.product_id) {
                selectedId = Number(data.product_id);
            }
            loadProductList(1);
            if (selectedId) loadProductDetail(selectedId);
        });
    };

    window.archiveProduct = function() {
        if (!selectedId) return;
        if (!confirm('Archive this product?')) return;
        fetch(DUPLICATE_URL_BASE + selectedId + ARCHIVE_SUFFIX, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); }).then(function() {
            selectedId = null;
            currentParentProductId = null;
            loadProductList(1);
            renderEditor({});
        });
    };

    window.openVariationModal = function() {
        if (!selectedId) return;
        clearVariationErrors();
        const nameInput = document.getElementById('f_name');
        const skuInput = document.getElementById('f_sku');
        const qtyInput = document.getElementById('f_quantity');
        const unitInput = document.getElementById('f_unit_type');
        const priceInput = document.getElementById('f_sell_price');
        const currInput = document.getElementById('f_currency');
        document.getElementById('variation_size').value = '';
        document.getElementById('variation_sku').value = skuInput ? (skuInput.value || '') : '';
        document.getElementById('variation_quantity').value = qtyInput ? (qtyInput.value || '1') : '1';
        document.getElementById('variation_unit_type').value = unitInput ? (unitInput.value || 'box') : 'box';
        document.getElementById('variation_sell_price').value = priceInput ? (priceInput.value || '0') : '0';
        document.getElementById('variation_currency').value = currInput ? (currInput.value || '') : '';
        document.getElementById('variation_inventory_enabled').checked = document.getElementById('f_inventory_enabled') ? !!document.getElementById('f_inventory_enabled').checked : true;
        if (!variationModalInstance) {
            variationModalInstance = new bootstrap.Modal(document.getElementById('variationModal'));
        }
        variationModalInstance.show();
    };

    window.submitVariation = function() {
        if (!selectedId) return;
        clearVariationErrors();
        const payload = {
            variant_size: (document.getElementById('variation_size').value || '').trim(),
            sku: (document.getElementById('variation_sku').value || '').trim(),
            quantity: (document.getElementById('variation_quantity').value || '').trim(),
            unit_type: (document.getElementById('variation_unit_type').value || '').trim(),
            sell_price: (document.getElementById('variation_sell_price').value || '').trim(),
            currency: normalizeCurrencyValue(document.getElementById('variation_currency').value || ''),
            inventory_enabled: document.getElementById('variation_inventory_enabled').checked ? 1 : 0
        };
        fetch(DUPLICATE_URL_BASE + selectedId + VARIATION_SUFFIX, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function(r) {
            return r.json().then(function(data) {
                return { ok: r.ok, status: r.status, data: data };
            });
        }).then(function(res) {
            if (!res.ok) {
                if (res.status === 422) {
                    applyVariationErrors(res.data.errors || {});
                    return;
                }
                alert(res.data && res.data.message ? res.data.message : 'Failed to add variation.');
                return;
            }
            const data = res.data || {};
            if (data && data.product_id) {
                selectedId = Number(data.product_id);
                loadProductList(1);
                loadProductDetail(selectedId);
            }
            if (variationModalInstance) variationModalInstance.hide();
        });
    };

    document.getElementById('productSearch').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); loadProductList(1); }
    });

    document.getElementById('productSearch').addEventListener('input', function() {
        if (searchDebounceTimer) {
            window.clearTimeout(searchDebounceTimer);
        }
        searchDebounceTimer = window.setTimeout(function() {
            loadProductList(1);
        }, 250);
    });

    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('productActionsWrap');
        const menu = document.getElementById('productActionsMenu');
        const btn = document.getElementById('productActionsBtn');
        if (!wrap || !menu || !btn) return;
        if (btn.contains(e.target)) {
            menu.classList.toggle('show');
            return;
        }
        if (!wrap.contains(e.target)) {
            menu.classList.remove('show');
        }
    });

    document.addEventListener('DOMContentLoaded', function() { renderWelcomeState(); loadProductList(1); });
})();
</script>
@endpush
