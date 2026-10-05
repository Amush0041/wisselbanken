@extends('user.layouts.app')

@section('seo')
<title>My Services | {{ config('app.name', 'Wisselbanken') }}</title>
@endsection

@php
use App\Services\Rbac\PermissionService;
$_svcOrgId = \App\Support\Rbac\CurrentOrg::sessionOrg((int) auth()->id());
$_svcUser  = auth()->user();
$_svcAdm   = $_svcUser && $_svcUser->role === 'admin';
$_svc      = fn(string $l) => $_svcUser && ($_svcAdm || ($_svcOrgId && app(PermissionService::class)->checkPermission($_svcUser->id, (int) $_svcOrgId, 'product_management', $l)));
$canCreateService = (bool) $_svc('S');
$canEditService   = (bool) $_svc('O');
$canDeleteService = (bool) $_svc('F');
@endphp

@push('css')
<style>
.uss-screen { background: #ffffff; color: #384551; border: 1px solid #dfe3ea; min-height: calc(100vh - 180px); }
.uss-grid { display: grid; grid-template-columns: 340px minmax(0, 1fr); min-height: calc(100vh - 182px); }
.uss-left { border-right: 1px solid #dfe3ea; display: flex; flex-direction: column; min-width: 0; background: #ffffff; }
.uss-right { display: flex; flex-direction: column; min-width: 0; background: #ffffff; }
.uss-hd { height: 48px; display: flex; align-items: center; justify-content: space-between; padding: 0 14px; border-bottom: 1px solid #dfe3ea; background: #f7f8fa; }
.uss-hd h5 { margin: 0; color: #384551; font-size: 18px; font-weight: 600; }
.uss-tools { display: flex; gap: 8px; align-items: center; }
.uss-icon-btn { border: 1px solid #d4dae3; background: #ffffff; color: #5a6673; width: 30px; height: 30px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; }
.uss-search { padding: 8px 10px; border-bottom: 1px solid #dfe3ea; }
.uss-search input { background: #ffffff; border: 1px solid #cfd6e2; color: #384551; border-radius: 4px; height: 34px; font-size: 13px; }
.uss-list { flex: 1; overflow-y: auto; padding-bottom: 68px; }
.uss-item { padding: 10px 12px; border-bottom: 1px solid #edf0f5; cursor: pointer; }
.uss-item.active { background: #eef2f7; }
.uss-item-name { color: #394657; font-size: 14px; font-weight: 600; }
.uss-item-sub { color: #7e8a97; font-size: 12px; }
.uss-item-price { color: #4a5968; font-size: 12px; text-align: right; }
.uss-foot { height: 38px; border-top: 1px solid #dfe3ea; display: flex; align-items: center; justify-content: center; color: #7e8a97; font-size: 13px; background: #f7f8fa; }
.uss-body { padding: 0; overflow-y: auto; }
.uss-sec { border-bottom: 1px solid #e4e8ef; }
.uss-sec-h { background: #eef1f5; color: #394657; padding: 8px 12px; font-size: 32px; font-weight: 600; line-height: 1.2; display: flex; align-items: center; justify-content: space-between; }
.uss-sec-h.sub { font-size: 20px; }
.uss-grid-2 { display: grid; grid-template-columns: 1fr 1fr; }
.uss-cell { padding: 10px 12px; border-right: 1px solid #e4e8ef; border-bottom: 1px solid #e4e8ef; min-height: 58px; }
.uss-grid-2 .uss-cell:nth-child(2n) { border-right: none; }
.uss-label { color: #7f8a98; font-size: 12px; margin-bottom: 3px; }
.uss-inp, .uss-txt { width: 100%; background: #ffffff; border: 1px solid #cfd6e2; color: #384551; border-radius: 4px; padding: 6px 8px; font-size: 13px; }
.uss-txt { min-height: 120px; resize: vertical; }
.uss-actions { display: flex; gap: 10px; align-items: center; }
.uss-save { background: #4A171E; color: #fff; border: none; border-radius: 4px; height: 34px; padding: 0 14px; font-weight: 600; }
.uss-save:hover { background: #5a1f28; }
.uss-cancel { background: #fff; color: #4A171E; border: 1px solid #cbd5e1; border-radius: 4px; height: 34px; padding: 0 14px; font-weight: 600; }
.uss-delete { background: #e14d4d; color: #fff; border: none; border-radius: 4px; height: 34px; padding: 0 14px; font-weight: 600; }
.uss-new-fab { position: sticky; align-self: flex-end; right: 16px; bottom: 64px; margin-right: 16px; margin-top: auto; margin-bottom: 12px; width: 42px; height: 42px; border-radius: 50%; border: none; background: #ff9800; color: #fff; font-size: 22px; font-weight: 700; box-shadow: 0 8px 16px rgba(0,0,0,.35); z-index: 3; }
.uss-left-wrap { position: relative; display: flex; flex: 1; min-height: 0; flex-direction: column; }
.uss-error { margin-top: 4px; color: #d93025; font-size: 12px; line-height: 1.3; min-height: 16px; }
.uss-inp.is-invalid, .uss-txt.is-invalid { border-color: #d93025; }
@media (max-width: 1200px) { .uss-grid { grid-template-columns: 1fr; } .uss-left { min-height: 340px; } }
</style>
@endpush

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page">
    <div class="uss-screen">
        <div class="uss-grid">
            <div class="uss-left">
                <div class="uss-hd">
                    <h5>Services</h5>
                    <div class="uss-tools">
                        <button type="button" class="uss-icon-btn" onclick="loadServiceList(1)" title="Refresh"><i class="ti ti-refresh"></i></button>
                    </div>
                </div>
                <div class="uss-left-wrap">
                    <div class="uss-search"><input type="text" id="serviceSearch" class="uss-inp" placeholder="Search services"></div>
                    <div class="uss-list" id="serviceList"></div>
                    @if ($canCreateService)
                    <button type="button" class="uss-new-fab" onclick="startNewService()" title="New Service">+</button>
                    @endif
                </div>
                <div class="uss-foot" id="servicePagination">0 Service</div>
            </div>
            <div class="uss-right">
                <div class="uss-hd">
                    <h5 id="serviceEditorTitle">Create Service</h5>
                    <div class="uss-actions">
                        @if ($canCreateService)
                        <button type="button" class="uss-cancel" onclick="cancelServiceEdit()">Cancel</button>
                        @endif
                        @if ($canEditService || $canCreateService)
                        <button type="button" class="uss-save" onclick="saveService()">Save</button>
                        @endif
                        @if ($canDeleteService)
                        <button type="button" class="uss-delete" id="serviceDeleteBtn" onclick="deleteService()" style="display:none;">Delete</button>
                        @endif
                    </div>
                </div>
                <div class="uss-body" id="serviceEditor"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const LIST_URL = @json(route('user-services.list'));
    const STORE_URL = @json(route('user-services.store'));
    const UPDATE_BASE = @json(url('user-services'));
    const CSRF = @json(csrf_token());
    const CAN_CREATE = @json($canCreateService);
    let selectedId = null;
    let currentPage = 1;
    let searchDebounceTimer = null;
    const FIELD_MAP = {
        service_name: 'svc_name',
        sku: 'svc_sku',
        quantity: 'svc_quantity',
        unit_type: 'svc_unit_type',
        default_unit_price: 'svc_rate',
        tax_label: 'svc_tax_label',
        description: 'svc_description',
        item_notes: 'svc_notes'
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function clearErrors() {
        document.querySelectorAll('.uss-error').forEach(function(el) { el.textContent = ''; });
        document.querySelectorAll('.uss-inp.is-invalid, .uss-txt.is-invalid').forEach(function(el) { el.classList.remove('is-invalid'); });
    }

    function showError(field, msg) {
        const id = FIELD_MAP[field];
        if (!id) return;
        const input = document.getElementById(id);
        if (input && (input.classList.contains('uss-inp') || input.classList.contains('uss-txt'))) {
            input.classList.add('is-invalid');
        }
        const err = document.getElementById(id + '_error');
        if (err) err.textContent = msg;
    }

    function applyErrors(errors) {
        clearErrors();
        Object.keys(errors || {}).forEach(function(k) {
            const arr = Array.isArray(errors[k]) ? errors[k] : [];
            if (arr.length) showError(k, String(arr[0]));
        });
    }

    function buildPayload() {
        return {
            service_name: (document.getElementById('svc_name')?.value || '').trim(),
            sku: (document.getElementById('svc_sku')?.value || '').trim(),
            quantity: (document.getElementById('svc_quantity')?.value || '').trim(),
            unit_type: (document.getElementById('svc_unit_type')?.value || '').trim(),
            default_unit_price: (document.getElementById('svc_rate')?.value || '').trim(),
            tax_label: (document.getElementById('svc_tax_label')?.value || '').trim(),
            description: (document.getElementById('svc_description')?.value || '').trim(),
            item_notes: (document.getElementById('svc_notes')?.value || '').trim()
        };
    }

    function renderServiceEditor(service) {
        const s = service || {};
        if (!CAN_CREATE && !s.id) {
            document.getElementById('serviceEditorTitle').textContent = 'Service Details';
            document.getElementById('serviceEditor').innerHTML = '<div class="uss-sec"><div class="uss-item-sub">Select a service to view its details.</div></div>';
            return;
        }
        const dis = CAN_CREATE ? '' : ' disabled';
        document.getElementById('serviceEditorTitle').textContent = s.id ? (s.service_name || s.title || 'Service') : 'Create Service';
        const delBtn = document.getElementById('serviceDeleteBtn');
        if (delBtn) delBtn.style.display = s.id ? 'inline-block' : 'none';
        document.getElementById('serviceEditor').innerHTML = `
            <div class="uss-sec"><div class="uss-sec-h sub">Details</div>
                <div class="uss-grid-2">
                    <div class="uss-cell"><div class="uss-label">Service Name *</div><input id="svc_name" class="uss-inp"${dis} value="${esc(s.service_name || s.title || '')}"><div class="uss-error" id="svc_name_error"></div></div>
                    <div class="uss-cell"><div class="uss-label">SKU</div><input id="svc_sku" class="uss-inp"${dis} value="${esc(s.sku || 'SAC')}"><div class="uss-error" id="svc_sku_error"></div></div>
                </div>
            </div>
            <div class="uss-sec"><div class="uss-sec-h sub">Quantity</div>
                <div class="uss-grid-2">
                    <div class="uss-cell"><div class="uss-label">Quantity</div><input id="svc_quantity" class="uss-inp"${dis} value="${esc(s.quantity || '1')}"><div class="uss-error" id="svc_quantity_error"></div></div>
                    <div class="uss-cell"><div class="uss-label">Unit Type</div><input id="svc_unit_type" class="uss-inp"${dis} value="${esc(s.unit_type || '')}"><div class="uss-error" id="svc_unit_type_error"></div></div>
                </div>
            </div>
            <div class="uss-sec"><div class="uss-sec-h sub">Pricing & Tax</div>
                <div class="uss-grid-2">
                    <div class="uss-cell"><div class="uss-label">Rate</div><input id="svc_rate" class="uss-inp"${dis} value="${esc(s.default_unit_price || '0')}"><div class="uss-error" id="svc_rate_error"></div></div>
                    <div class="uss-cell"><div class="uss-label">Default Taxes (Services)</div><input id="svc_tax_label" class="uss-inp"${dis} value="${esc(s.tax_label || '')}"><div class="uss-error" id="svc_tax_label_error"></div></div>
                </div>
            </div>
            <div class="uss-sec"><div class="uss-sec-h sub">Description</div>
                <div class="uss-cell" style="border-right:none;"><div class="uss-label">Notes</div><textarea id="svc_notes" class="uss-txt"${dis}>${esc(s.item_notes || '')}</textarea><div class="uss-error" id="svc_notes_error"></div></div>
            </div>
        `;
    }

    function loadServiceList(page) {
        currentPage = page || 1;
        const q = document.getElementById('serviceSearch').value.trim();
        const params = new URLSearchParams({ page: currentPage, search: q });
        fetch(LIST_URL + '?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                const rows = data.services || [];
                const list = document.getElementById('serviceList');
                if (!rows.length) {
                    list.innerHTML = '<div class="uss-item"><div class="uss-item-sub">No services found</div></div>';
                } else {
                    list.innerHTML = rows.map(function(s) {
                        const active = selectedId === s.id ? ' active' : '';
                        return '<div class="uss-item' + active + '" data-id="' + s.id + '">' +
                            '<div class="d-flex justify-content-between gap-2"><div class="uss-item-name">' + esc(s.title || 'Service') + '</div><div class="uss-item-price">Rs.' + esc(s.default_unit_price || '0.00') + '</div></div>' +
                            '</div>';
                    }).join('');
                    list.querySelectorAll('.uss-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            selectedId = parseInt(el.getAttribute('data-id'), 10);
                            list.querySelectorAll('.uss-item').forEach(function(x) { x.classList.remove('active'); });
                            el.classList.add('active');
                            loadServiceDetail(selectedId);
                        });
                    });
                }
                const pg = data.pagination || {};
                document.getElementById('servicePagination').textContent = String(pg.total || 0) + ' Service';
            })
            .catch(function() {
                document.getElementById('serviceList').innerHTML = '<div class="uss-item"><div class="uss-item-sub text-danger">Could not load list.</div></div>';
            });
    }

    function loadServiceDetail(id) {
        fetch(UPDATE_BASE + '/' + id, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renderServiceEditor(data.service || {});
            })
            .catch(function() {
                renderServiceEditor({});
            });
    }

    window.startNewService = function() {
        selectedId = null;
        document.querySelectorAll('.uss-item').forEach(function(x) { x.classList.remove('active'); });
        renderServiceEditor({});
    };

    window.cancelServiceEdit = function() {
        if (selectedId) {
            loadServiceDetail(selectedId);
            return;
        }
        renderServiceEditor({});
    };

    window.saveService = function() {
        clearErrors();
        const payload = buildPayload();
        if (!payload.service_name) {
            showError('service_name', 'Service name is required.');
            return;
        }
        const isEdit = !!selectedId;
        const url = isEdit ? (UPDATE_BASE + '/' + selectedId) : STORE_URL;
        const method = isEdit ? 'PUT' : 'POST';
        fetch(url, {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function(r) {
            return r.json().then(function(data) {
                return { ok: r.ok, status: r.status, data: data };
            });
        }).then(function(res) {
            if (!res.ok) {
                if (res.status === 422) {
                    applyErrors(res.data.errors || {});
                    return;
                }
                alert(res.data && res.data.message ? res.data.message : 'Failed to save service.');
                return;
            }
            if (!isEdit && res.data && res.data.service_id) {
                selectedId = Number(res.data.service_id);
            }
            loadServiceList(1);
            if (selectedId) loadServiceDetail(selectedId);
        }).catch(function() {
            alert('Failed to save service.');
        });
    };

    window.deleteService = function() {
        if (!selectedId) return;
        if (!confirm('Remove this service from your list?')) return;
        fetch(UPDATE_BASE + '/' + selectedId, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); }).then(function() {
            selectedId = null;
            loadServiceList(1);
            renderServiceEditor({});
        });
    };

    document.getElementById('serviceSearch').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); loadServiceList(1); }
    });

    document.getElementById('serviceSearch').addEventListener('input', function() {
        if (searchDebounceTimer) {
            window.clearTimeout(searchDebounceTimer);
        }
        searchDebounceTimer = window.setTimeout(function() {
            loadServiceList(1);
        }, 250);
    });

    document.addEventListener('DOMContentLoaded', function() { renderServiceEditor({}); loadServiceList(1); });
})();
</script>
@endpush
