@push('css')
<link rel="stylesheet" href="{{ asset('css/quotes-workspace.css') }}">
@endpush

@php
use App\Services\Rbac\PermissionService;
$_rbacOrgId  = session(config('rbac.current_org_session_key'));
$_rbacUser   = auth()->user();
$_rbacAdmin  = $_rbacUser && $_rbacUser->role === 'admin';
$_rbacCheck  = fn(string $g, string $l) => $_rbacUser && ($_rbacAdmin || ($_rbacOrgId && app(PermissionService::class)->checkPermission($_rbacUser->id, (int) $_rbacOrgId, $g, $l)));
$canCreateEstimate = (bool) $_rbacCheck('estimate_management', 'S');
$canEditEstimate   = (bool) $_rbacCheck('estimate_management', 'O');
$canDeleteEstimate = (bool) $_rbacCheck('estimate_management', 'F');
@endphp
<script>
window.RBAC_CAN = {
    canCreateEstimate: {{ $canCreateEstimate ? 'true' : 'false' }},
    canEditEstimate:   {{ $canEditEstimate   ? 'true' : 'false' }},
    canDeleteEstimate: {{ $canDeleteEstimate ? 'true' : 'false' }},
};
</script>

<main class="main">

    <div id="toast-container"></div>
    <section class="mt-0 mb-0">
        <div class="container-fluid px-0">
            <div class="row">
                <div class="col-lg-12">
                <div class="quotes-workspace">
    <div class="quotes-container">
        <!-- Left Panel -->
        <div class="quotes-left-panel">
            <div class="quotes-left-header">
                <h2>Estimate</h2>
                <div class="header-actions">
                    <button class="btn-icon" onclick="loadEstimatesList(); return false;" title="Refresh">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                            <path d="M21 3v5h-5"></path>
                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                            <path d="M3 21v-5h5"></path>
                        </svg>
                    </button>
                    @canDo('estimate_management', 'S')
                    <button class="btn-icon btn-primary" onclick="createNewEstimate()" title="Create Estimate">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                    @endCanDo
                </div>
            </div>

            <div class="estimates-list" id="estimatesList">
                <!-- Estimates will be loaded here via AJAX -->
                <div class="empty-state">
                    <div class="empty-state-icon">📄</div>
                    <p>No estimates found</p>
                </div>
            </div>

            <div class="estimates-footer">
                <div class="estimates-pagination" id="paginationInfo">
                    <span>0-0 of 0 Estimates</span>
                </div>
                <div class="estimates-total">
                    Total: $<span id="totalAmount">0.00</span>
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="quotes-right-panel">
            <div id="estimateDetails">
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <p>Select an estimate to view details</p>
                </div>
            </div>
        </div>
    </div>
</div>
                </div>
            </div>
        </div>
    </section>

</main>



@push('scripts')
<script>
let currentPage = 1;
let selectedQuoteId = null;
let estimateCustomersMap = {};
let createEstimateItems = [];
let createEstimateProductSuggestions = {};
let createEstimateServiceSuggestions = {};
let createProductSortByRecent = false;
let createProductActiveIndex = null;
let createProductDropdownHandlersBound = false;
let createProductDdPositionBound = false;
let createServiceActiveIndex = null;
let createServiceDropdownHandlersBound = false;
let printPdfModalState = {
    quoteId: 0,
    preset: 'standard',
    applyOnly: false
};
let emailComposeState = {
    quoteId: 0
};
let estimateActionsMenuState = {
    initialized: false,
    quoteId: 0
};
let signatureModalState = {
    quoteId: 0,
    drawing: false,
    hasStroke: false,
    ctx: null,
    canvas: null,
    color: '#111111',
    thickness: 3,
    dataUrl: '',
    modalEventsBound: false
};

/** Single CSRF + URL sources (avoid repeating Blade in many AJAX calls; token still server-rendered once). */
const QUOTES_CSRF_TOKEN = '{{ csrf_token() }}';
const QUOTES_BASE_URL = @json(url('quotes'));
const QUOTES_LOGIN_URL = @json(url('login'));
const QUOTES_LIST_ROUTE = @json(route('quotes.list'));
const QUOTES_PROJECT_STORE_URL = @json(route('projects.quotes.store', ['project' => '__PROJECT__']));
const PROJECTS_LIST_ROUTE = @json(route('projects.list'));
const QUOTES_SERVICES_ROUTE = @json(route('quotes.services'));
const QUOTES_STORAGE_URL = @json(rtrim(asset('storage'), '/'));

/** Static right-panel empty state (no user input). */
const ESTIMATE_DETAILS_EMPTY_HTML = `
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <p>Select an estimate to view details</p>
        </div>
    `;

/** Coerce API / DOM values to a finite dollar amount string for safe text insertion (never raw HTML). */
function formatMoneyPlain(value) {
    const n = typeof value === 'number' ? value : parseFloat(String(value == null ? '0' : value).replace(/,/g, ''));
    if (!Number.isFinite(n)) return '0.00';
    return n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function coercePositiveIntId(id) {
    const n = parseInt(String(id), 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
}

/** Legacy placeholder titles stored as real values — treat as empty for editing. */
function isCreateLinePlaceholderTitle(value, kind) {
    const t = String(value || '').trim();
    if (!t) return true;
    if (kind === 'service') return t === 'Service';
    return t === 'Product';
}

/** Split one line item string into title + subtitle (en/em dash or spaced hyphen, else first newline). */
function splitProductTitleSub(full) {
    const s = String(full || '').trim();
    const dashSeps = [' – ', ' — ', ' - '];
    for (let d = 0; d < dashSeps.length; d++) {
        const sep = dashSeps[d];
        const i = s.indexOf(sep);
        if (i >= 0) {
            const name = s.slice(0, i).trim();
            const sub = s.slice(i + sep.length).trim();
            return { name: name, sub: sub };
        }
    }
    const nl = s.indexOf('\n');
    if (nl >= 0) {
        const name = s.slice(0, nl).trim();
        const sub = s.slice(nl + 1).trim();
        return { name: name, sub: sub };
    }
    return { name: s, sub: '' };
}

/** Per-row display parts: explicit product_line_* when set, else derive from description (independent per line). */
function getCreateProductDisplayParts(item) {
    const hasExplicit = Object.prototype.hasOwnProperty.call(item, 'product_line_title')
        || Object.prototype.hasOwnProperty.call(item, 'product_line_subtitle');
    if (hasExplicit) {
        let name = String(item.product_line_title != null ? item.product_line_title : '').trim();
        if (isCreateLinePlaceholderTitle(name, 'product')) name = '';
        return {
            name: name,
            sub: String(item.product_line_subtitle != null ? item.product_line_subtitle : '').trim()
        };
    }
    const derived = splitProductTitleSub(item.description || item.product_text || '');
    if (isCreateLinePlaceholderTitle(derived.name, 'product')) derived.name = '';
    return derived;
}

function syncProductLineFromTitleSub(index) {
    const item = createEstimateItems[index];
    if (!item || item.type !== 'product') return;
    const a = String(item.product_line_title != null ? item.product_line_title : '').trim();
    const b = String(item.product_line_subtitle != null ? item.product_line_subtitle : '').trim();
    item.product_line_title = a;
    item.product_line_subtitle = b;
    item.description = b ? (a + ' – ' + b) : a;
    item.product_text = item.description;
    const $desc = $(`#createItemDesc-${index}`);
    if ($desc.length) $desc.text(item.description || '');
}

function onCreateProductTitleSubInput(index, field, value) {
    if (!createEstimateItems[index]) return;
    if (field === 'title') {
        createEstimateItems[index].product_line_title = value;
    } else {
        createEstimateItems[index].product_line_subtitle = value;
    }
    syncProductLineFromTitleSub(index);
    updateCreateEstimateSummary();
}

function onCreateProductLineTitleFocus(index) {
    const el = document.getElementById('createProductLineTitle-' + index);
    if (!el) return;
    requestAnimationFrame(function() {
        if (typeof el.select === 'function') el.select();
    });
}

function onCreateProductPickedShellClick(event, index) {
    if (event.target && $(event.target).closest('.create-product-picked-fields').length) return;
    openCreateProductPicker(index);
}

/** Service line: explicit service_line_* or split stored description (independent per row). */
function getCreateServiceDisplayParts(item) {
    const hasTitleKey = Object.prototype.hasOwnProperty.call(item, 'service_line_title');
    const hasSubKey = Object.prototype.hasOwnProperty.call(item, 'service_line_subtitle');
    const a = String(item.service_line_title != null ? item.service_line_title : '').trim();
    const b = String(item.service_line_subtitle != null ? item.service_line_subtitle : '').trim();
    if ((hasTitleKey || hasSubKey) && (a || b)) {
        return { name: a || 'Service', sub: b };
    }
    const pl = splitProductTitleSub(item.description || '');
    const nm = pl.name === 'Product' && !pl.sub ? 'Service' : pl.name;
    return { name: nm || 'Service', sub: pl.sub };
}

function syncServiceLineFromTitleSub(index) {
    const item = createEstimateItems[index];
    if (!item || item.type !== 'service') return;
    const a = String(item.service_line_title != null ? item.service_line_title : '').trim();
    const b = String(item.service_line_subtitle != null ? item.service_line_subtitle : '').trim();
    item.service_line_title = a;
    item.service_line_subtitle = b;
    item.description = b ? (a + ' – ' + b) : a;
    item.product_text = '';
}

function onCreateServiceTitleSubInput(index, field, value) {
    if (!createEstimateItems[index]) return;
    if (field === 'title') {
        createEstimateItems[index].service_line_title = value;
        fetchCreateServiceSuggestions(index, value || '');
    } else {
        createEstimateItems[index].service_line_subtitle = value;
    }
    syncServiceLineFromTitleSub(index);
    updateCreateEstimateSummary();
}

function ensureCreateServiceDropdownHandlers() {
    if (createServiceDropdownHandlersBound) return;
    createServiceDropdownHandlersBound = true;
    $(document).on('mousedown.createServiceOuterClose', function(e) {
        if ($(e.target).closest('.item-cell-wrap--service').length) return;
        closeAllCreateServiceDropdowns();
        createServiceActiveIndex = null;
    });
    $(document).on('mousedown.createServicePick', '.create-service-row', function(e) {
        e.preventDefault();
        const index = parseInt($(this).attr('data-index'), 10);
        const row = parseInt($(this).attr('data-row'), 10);
        const items = createEstimateServiceSuggestions[index] || [];
        const it = items[row];
        if (it) applyCreateServiceItem(index, it);
    });
}

function closeAllCreateServiceDropdowns() {
    $('.create-service-dropdown').attr('hidden', true);
}

function renderCreateServiceDropdownUI(index, forceShow) {
    const $dd = $(`#createServiceDropdown-${index}`);
    const $scroll = $(`#createServiceDropdownScroll-${index}`);
    if (!$dd.length || !$scroll.length) return;
    const items = createEstimateServiceSuggestions[index] || [];
    const shouldShow = !!forceShow || createServiceActiveIndex === index;
    if (!items.length) {
        $scroll.html('<div class="create-service-dropdown-empty">No service matches.</div>');
        if (shouldShow) $dd.removeAttr('hidden'); else $dd.attr('hidden', true);
        return;
    }
    const rowsHtml = items.map(function(it, ri) {
        const title = String(it.title || 'Service');
        const sub = String(it.description || '');
        const price = Number(it.rate || 0).toFixed(2);
        return `<button type="button" class="create-service-row" data-index="${index}" data-row="${ri}">` +
            `<span class="create-service-row-title">${escapeHtml(title)}</span>` +
            `<span class="create-service-row-sub">${escapeHtml(sub)}</span>` +
            `<span class="create-service-row-price">$${price}</span>` +
            `</button>`;
    }).join('');
    $scroll.html(rowsHtml);
    if (shouldShow) $dd.removeAttr('hidden'); else $dd.attr('hidden', true);
}

function fetchCreateServiceSuggestions(index, term) {
    $.ajax({
        url: QUOTES_SERVICES_ROUTE,
        method: 'GET',
        data: { q: term || '' },
        success: function(response) {
            const items = Array.isArray(response.items) ? response.items : [];
            createEstimateServiceSuggestions[index] = items;
            renderCreateServiceDropdownUI(index, createServiceActiveIndex === index);
        },
        error: function(xhr) {
            console.error('Error loading service suggestions:', xhr);
        }
    });
}

function onCreateServiceFocus(index) {
    createServiceActiveIndex = index;
    ensureCreateServiceDropdownHandlers();
    const v = $(`#createServiceLineTitle-${index}`).val() || '';
    fetchCreateServiceSuggestions(index, v);
}

function onCreateServiceBlur(index) {
    setTimeout(function() {
        const ae = document.activeElement;
        if (ae && $(ae).closest(`#createServiceDropdown-${index}`).length) return;
        if (createServiceActiveIndex === index) {
            createServiceActiveIndex = null;
        }
        $(`#createServiceDropdown-${index}`).attr('hidden', true);
    }, 180);
}

function applyCreateServiceItem(index, selected) {
    const item = createEstimateItems[index];
    if (!item || item.type !== 'service' || !selected) return;
    const title = String(selected.title || 'Service').trim() || 'Service';
    const sub = String(selected.description || '').trim();
    item.service_line_title = title;
    item.service_line_subtitle = sub;
    item.description = sub ? (title + ' – ' + sub) : title;
    item.rate = Number(selected.rate || 0);
    createServiceActiveIndex = null;
    closeAllCreateServiceDropdowns();
    renderCreateEstimateItemsTable();
}

function positionCreateProductDropdown(index) {
    const $inp = $(`#createProductInput-${index}`);
    const $dd = $(`#createProductDropdown-${index}`);
    if (!$inp.length || !$dd.length || $dd.attr('hidden')) return;
    const el = $inp[0];
    const r = el.getBoundingClientRect();
    const pad = 10;
    const w = Math.min(440, Math.max(260, window.innerWidth - r.left - pad));
    const left = Math.min(Math.max(pad, r.left), Math.max(pad, window.innerWidth - w - pad));
    const top = r.bottom + 4;
    $dd.css({
        position: 'fixed',
        left: left + 'px',
        top: top + 'px',
        width: w + 'px',
        right: 'auto',
        zIndex: 10060
    });
}

function clearCreateProductDropdownStyles($dd) {
    $dd.css({ position: '', left: '', top: '', width: '', right: '', zIndex: '' });
}

function bindCreateProductDropdownPositioning() {
    if (createProductDdPositionBound) return;
    createProductDdPositionBound = true;
    $('#estimateDetails').on('scroll.createProductDd', function() {
        if (createProductActiveIndex !== null && createProductActiveIndex !== undefined) {
            positionCreateProductDropdown(createProductActiveIndex);
        }
    });
    $(window).on('scroll.createProductDd', function() {
        if (createProductActiveIndex !== null && createProductActiveIndex !== undefined) {
            positionCreateProductDropdown(createProductActiveIndex);
        }
    });
    $(window).on('resize.createProductDd', function() {
        if (createProductActiveIndex !== null && createProductActiveIndex !== undefined) {
            positionCreateProductDropdown(createProductActiveIndex);
        }
    });
}
let editingQuoteId = null;
/** @type {File[]} */
let createEstimatePendingFiles = [];

function destroyCreateEstimateDatePicker() {
    const el = document.getElementById('createEstimateDate');
    if (el && el._flatpickr) {
        el._flatpickr.destroy();
    }
}

function initCreateEstimateDatePicker(quote) {
    const el = document.getElementById('createEstimateDate');
    if (!el || typeof flatpickr !== 'function') {
        return;
    }
    if (el._flatpickr) {
        el._flatpickr.destroy();
    }
    const rawDate = quote && quote.estimate_date
        ? String(quote.estimate_date).split('T')[0]
        : new Date().toISOString().split('T')[0];
    flatpickr(el, {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        altInputClass: 'form-control estimate-date-input estimate-date-alt',
        allowInput: false,
        clickOpens: true,
        disableMobile: true,
        defaultDate: rawDate || null,
        monthSelectorType: 'static',
        onReady: function(selectedDates, dateStr, instance) {
            instance.calendarContainer.classList.add('estimate-date-flatpickr');
            if (instance.altInput) {
                instance.altInput.placeholder = 'dd/mm/yyyy';
            }
        }
    });
}

$(document).ready(function() {
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded');
        return;
    }
    loadEstimatesList();
    if (window.RBAC_CAN.canCreateEstimate && coercePositiveIntId(new URLSearchParams(window.location.search).get('project'))) {
        createNewEstimate();
    }
});

function loadEstimatesList() {
    $.ajax({
        url: QUOTES_LIST_ROUTE,
        method: 'GET',
        cache: false,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Cache-Control': 'no-cache'
        },
        data: {
            page: currentPage,
            sort_by: 'created_at',
            sort_dir: 'desc',
            status: 'all',
            customer: 'all',
            date_from: '',
            _t: new Date().getTime()
        },
        success: function(response) {
            let quotesArray = [];
            if (response && response.quotes) {
                if (Array.isArray(response.quotes)) {
                    quotesArray = response.quotes;
                } else if (typeof response.quotes === 'object') {
                    quotesArray = Object.values(response.quotes);
                }
            }
            if (quotesArray.length > 0) {
                renderEstimatesList(quotesArray);
                if (response.pagination) {
                    renderPagination(response.pagination);
                }
                $('#totalAmount').text(formatMoneyPlain(response.total_amount));
            } else {
                renderEstimatesList([]);
                $('#totalAmount').text(formatMoneyPlain(0));
            }
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                alert('Please login to view estimates.');
                window.location.href = QUOTES_LOGIN_URL;
            } else if (xhr.status === 500) {
                alert('Server error. Please try again later.');
            }
            renderEstimatesList([]);
        }
    });
}

function renderEstimatesList(quotes) {
    const listContainer = $('#estimatesList');
    
    if (quotes.length === 0) {
        listContainer.html(`
            <div class="empty-state">
                <div class="empty-state-icon">📄</div>
                <p>No estimates found</p>
            </div>
        `);
        return;
    }

    let html = '';
    quotes.forEach(function(quote) {
        const qid = coercePositiveIntId(quote.id);
        if (!qid) return;
        const isActive = selectedQuoteId === qid ? 'active' : '';
        html += `
            <div class="estimate-item ${isActive}" data-quote-id="${qid}" onclick="selectEstimate(${qid})">
                <div class="estimate-item-header">
                    <div class="estimate-item-title-block">
                        <div class="estimate-customer">${escapeHtml(quote.customer_name || '')}</div>
                    </div>
                    <div class="estimate-item-meta">
                        <div class="estimate-amount">$${formatMoneyPlain(quote.total_amount)}</div>
                        <div class="estimate-date">${escapeHtml(quote.date || '')}</div>
                    </div>
                </div>
                ${window.RBAC_CAN.canDeleteEstimate ? `<button type="button" class="btn-delete-estimate" title="Delete estimate" onclick="event.stopPropagation(); deleteEstimate(${qid});"><i class="ti ti-trash ti-sm"></i></button>` : ''}
            </div>
        `;
    });
    
    listContainer.html(html);
}

function renderPagination(pagination) {
    const cur = parseInt(String(pagination.current_page), 10) || 1;
    const per = parseInt(String(pagination.per_page), 10) || 1;
    const tot = parseInt(String(pagination.total), 10) || 0;
    const start = ((cur - 1) * per) + 1;
    const end = Math.min(start + per - 1, tot);
    $('#paginationInfo').text(start + '-' + end + ' of ' + tot + ' Estimates');
}

function selectEstimate(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    selectedQuoteId = qid;
    $('.estimate-item').removeClass('active');
    $('.estimate-item[data-quote-id="' + qid + '"]').addClass('active');
    editingQuoteId = qid;
    loadEstimateDetails(qid);
}

function loadEstimateDetails(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    $.ajax({
        url: QUOTES_BASE_URL + '/' + qid + '/details',
        method: 'GET',
        success: function(response) {
            renderCreateEstimateForm(response);
        },
        error: function(xhr) {
            console.error('Error loading estimate details:', xhr);
        }
    });
}

function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
function escapeAttr(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/** Normalize quote.attachments from JSON (array or legacy object map) to a plain array. */
function normalizeQuoteAttachmentsRaw(raw) {
    if (raw == null) return [];
    if (Array.isArray(raw)) return raw.filter(Boolean);
    if (typeof raw === 'object') {
        return Object.keys(raw).sort(function(a, b) { return Number(a) - Number(b); })
            .map(function(k) { return raw[k]; })
            .filter(Boolean);
    }
    return [];
}

/** Collapse duplicate slashes (e.g. https://host.com//storage/...) while keeping https:// */
function normalizeAttachmentPublicUrl(href) {
    const s = String(href || '').trim();
    if (!s) return s;
    const idx = s.indexOf('://');
    if (idx === -1) {
        return s.replace(/\/+/g, '/');
    }
    return s.slice(0, idx + 3) + s.slice(idx + 3).replace(/\/+/g, '/');
}

const ESTIMATE_SHIPPING_OPTIONS = [
    { value: '', label: 'Select shipping method' },
    { value: 'Standard Ground', label: 'Standard Ground' },
    { value: 'Priority Shipping', label: 'Priority Shipping' },
    { value: 'International Shipping', label: 'International Shipping' },
    { value: 'PO Box Delivery', label: 'PO Box Delivery' },
    { value: 'Freight Shipping', label: 'Freight Shipping' }
];

function buildShippingMethodOptionsHtml(currentRaw) {
    const selected = String(currentRaw || '').trim();
    const presetValues = new Set(ESTIMATE_SHIPPING_OPTIONS.map(function(o) { return o.value; }));
    let html = '';
    if (selected !== '' && !presetValues.has(selected)) {
        html += '<option value="' + escapeAttr(selected) + '" selected>' + escapeHtml(selected) + '</option>';
    }
    ESTIMATE_SHIPPING_OPTIONS.forEach(function(o) {
        const isSel = o.value === selected ? ' selected' : '';
        html += '<option value="' + escapeAttr(o.value) + '"' + isSel + '>' + escapeHtml(o.label) + '</option>';
    });
    return html;
}

function parseAddressToSplitFields(addr) {
    const lines = String(addr || '').split('\n').map(function(s) { return s.trim(); }).filter(Boolean);
    const street = lines[0] || '';
    let city = '', state = '';
    for (let i = 1; i < lines.length; i++) {
        const m = lines[i].match(/^([^,\d][^,]*),\s*([A-Za-z]{2,}(?:\s+[A-Za-z]{2,})?)\s*$/);
        if (m) { city = m[1].trim(); state = m[2].replace(/\s+\d.*$/, '').trim(); break; }
    }
    return { street: street, city: city, state: state };
}

/** Normalize address strings for comparison (save vs option values). */
function normalizeAddressValue(value) {
    return String(value || '')
        .replace(/\r\n/g, '\n')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n[ \t]+/g, '\n')
        .replace(/\n{2,}/g, '\n')
        .trim();
}

/** Visible label for address <option>: full address; optional type prefix when customer has multiple. */
function formatAddressOptionLabel(addr, addressCount) {
    if (!addr) return '';
    const raw = normalizeAddressValue(addr.value);
    const singleLine = raw.replace(/\n/g, ', ').replace(/\s+,/g, ',').replace(/,\s*,/g, ',').trim();
    if (singleLine !== '') {
        if (addressCount > 1) {
            if (addr.type === 'shipping') return 'Shipping — ' + singleLine;
            if (addr.type === 'billing') return 'Billing — ' + singleLine;
        }
        return singleLine;
    }
    return '';
}

function setCreateEstimateAddressSelection(savedAddress) {
    const $addr = $('#createEstimateAddress');
    if (!$addr.length) return;
    const want = normalizeAddressValue(savedAddress);
    if (!want) {
        $addr.val('');
        return;
    }
    let matched = false;
    $addr.find('option').each(function() {
        const optVal = $(this).attr('value') || '';
        if (normalizeAddressValue(optVal) === want) {
            $addr.val(optVal);
            matched = true;
            return false;
        }
    });
    if (!matched) {
        const label = formatAddressOptionLabel({ value: savedAddress }, 1) || savedAddress;
        $addr.append($('<option></option>').attr('value', savedAddress).text(label));
        $addr.val(savedAddress);
    }
}

function downloadPDF(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    window.open(QUOTES_BASE_URL + '/' + qid + '/pdf', '_blank');
}

function getQuotePdfPreviewUrl(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return '';
    return QUOTES_BASE_URL + '/' + qid + '/pdf-preview?t=' + Date.now();
}

function getOrCreateInPagePrintFrame() {
    let frame = document.getElementById('estimateInPagePrintFrame');
    if (frame) return frame;
    frame = document.createElement('iframe');
    frame.id = 'estimateInPagePrintFrame';
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    frame.style.visibility = 'hidden';
    document.body.appendChild(frame);
    return frame;
}

function duplicateQuote(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    if (!confirm('Are you sure you want to duplicate this quote?')) {
        return;
    }
    $.ajax({
        url: QUOTES_BASE_URL + '/' + qid + '/duplicate',
        method: 'POST',
        data: {
            _token: QUOTES_CSRF_TOKEN
        },
        success: function(response) {
            alert('Quote duplicated successfully');
            loadEstimatesList();
            const newId = response && response.quote_id ? coercePositiveIntId(response.quote_id) : 0;
            if (newId) {
                selectEstimate(newId);
            }
        },
        error: function(xhr) {
            console.error('Error duplicating quote:', xhr);
        }
    });
}

function previewEstimate(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    openPrintPreviewPopup(qid);
}

function printEstimate(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    const previewUrl = getQuotePdfPreviewUrl(qid);
    if (!previewUrl) return;
    const frame = getOrCreateInPagePrintFrame();
    frame.onload = function() {
        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch (e) {}
    };
    frame.src = previewUrl;
}

function getCurrentEstimateTotal() {
    const total = createEstimateItems.reduce(function(sum, item) {
        const qty = Number(item && item.quantity ? item.quantity : 0);
        const rate = Number(item && item.rate ? item.rate : 0);
        return sum + (qty * rate);
    }, 0);
    return Number.isFinite(total) ? total : 0;
}

function openEmailComposeModal(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid || typeof bootstrap === 'undefined') return;
    const modalEl = document.getElementById('estimateEmailModal');
    if (!modalEl) return;
    emailComposeState.quoteId = qid;

    const customerName = getCurrentEstimateCustomerLabel() || 'Customer';
    const estimateLabel = String((document.getElementById('createEstimateLabel') || {}).value || ('Estimate #' + qid)).trim();
    const currency = String((document.getElementById('createEstimateCurrency') || {}).value || 'USD').trim();
    const totalAmount = getCurrentEstimateTotal().toFixed(2);
    const fromEmail = String(((document.getElementById('signatureCustomerName') || {}).value || '')).trim();
    const toInput = document.getElementById('emailComposeTo');
    const subjectInput = document.getElementById('emailComposeSubject');
    const fromInput = document.getElementById('emailComposeFrom');
    const bodyInput = document.getElementById('emailComposeBody');
    const attachmentName = document.getElementById('emailComposeAttachmentName');
    const attachmentLink = document.getElementById('emailComposeAttachmentLink');
    const headerTitle = document.getElementById('emailComposeHeaderTitle');

    if (toInput) toInput.value = '';
    if (subjectInput) subjectInput.value = estimateLabel + ' from ' + customerName;
    if (headerTitle) headerTitle.textContent = estimateLabel + ' from ' + customerName;
    if (fromInput) fromInput.value = fromEmail || 'noreply@wisselbanken.com';
    if (bodyInput) {
        bodyInput.value = 'Dear ' + customerName + '\n\n'
            + 'Estimate #: ' + qid + '\n'
            + 'Estimate Total Amount: ' + currency + ' ' + totalAmount + '\n\n'
            + QUOTES_BASE_URL + '/' + qid + '/pdf-preview';
    }
    if (attachmentName) attachmentName.textContent = estimateLabel + '.pdf';
    if (attachmentLink) attachmentLink.href = QUOTES_BASE_URL + '/' + qid + '/pdf-preview';

    const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
    instance.show();
}

function emailEstimate(quoteId) {
    openEmailComposeModal(quoteId);
}

function closeEmailComposeModal() {
    const modalEl = document.getElementById('estimateEmailModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
}

function sendEstimateEmail() {
    const qid = coercePositiveIntId(emailComposeState.quoteId);
    if (!qid) return;
    const to = encodeURIComponent(String((document.getElementById('emailComposeTo') || {}).value || ''));
    const subject = encodeURIComponent(String((document.getElementById('emailComposeSubject') || {}).value || 'Estimate #' + qid));
    const body = encodeURIComponent(String((document.getElementById('emailComposeBody') || {}).value || ''));
    window.location.href = 'mailto:' + to + '?subject=' + subject + '&body=' + body;
    closeEmailComposeModal();
}

function previewEmailAttachment(event) {
    if (event && typeof event.preventDefault === 'function') event.preventDefault();
    const qid = coercePositiveIntId(emailComposeState.quoteId);
    if (!qid) return;
    openPrintPreviewPopup(qid);
}

function openEstimateSettings(quoteId) {
    openPrintPdfSettingsModal(quoteId);
}

function buildPrintPdfPreviewUrl(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return '';
    const params = new URLSearchParams();
    params.set('t', String(Date.now()));
    params.set('preset', printPdfModalState.preset === 'default' ? 'default' : 'standard');
    if (printPdfModalState.applyOnly) {
        params.set('apply_only', '1');
    }
    return QUOTES_BASE_URL + '/' + qid + '/pdf-preview?' + params.toString();
}

function updatePrintPdfPresetUI() {
    const isDefault = printPdfModalState.preset === 'default';
    const toolbarRight = document.getElementById('printPdfToolbarMode');
    if (toolbarRight) {
        toolbarRight.textContent = isDefault ? 'Default Print' : 'Normal Print';
    }

    const defaultBtn = document.getElementById('printPdfPresetDefaultBtn');
    const standardBtn = document.getElementById('printPdfPresetStandardBtn');
    if (defaultBtn) defaultBtn.classList.toggle('is-active', isDefault);
    if (standardBtn) standardBtn.classList.toggle('is-active', !isDefault);

    const applyCheckbox = document.getElementById('printPdfApplyEstimateOnly');
    if (applyCheckbox && applyCheckbox.checked !== !!printPdfModalState.applyOnly) {
        applyCheckbox.checked = !!printPdfModalState.applyOnly;
    }
}

function refreshPrintPdfPreview() {
    const frame = document.getElementById('printPdfPreviewFrame');
    const qid = coercePositiveIntId(printPdfModalState.quoteId);
    if (!frame || !qid) return;
    frame.src = buildPrintPdfPreviewUrl(qid);
}

function openPrintPdfSettingsModal(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    const modalEl = document.getElementById('printPdfSettingsModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    printPdfModalState.quoteId = qid;
    printPdfModalState.preset = 'standard';
    printPdfModalState.applyOnly = false;
    updatePrintPdfPresetUI();
    refreshPrintPdfPreview();
    const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
    instance.show();
}

function onPrintPdfReset() {
    printPdfModalState.preset = 'standard';
    printPdfModalState.applyOnly = false;
    updatePrintPdfPresetUI();
    refreshPrintPdfPreview();
}

function onPrintPdfPreview() {
    const qid = coercePositiveIntId(printPdfModalState.quoteId);
    if (!qid) return;
    openPrintPreviewPopup(qid);
}

function setPrintPdfPreset(preset) {
    printPdfModalState.preset = preset === 'default' ? 'default' : 'standard';
    updatePrintPdfPresetUI();
    refreshPrintPdfPreview();
}

function onPrintPdfApplyOnlyToggle(checked) {
    printPdfModalState.applyOnly = !!checked;
    refreshPrintPdfPreview();
}

function closePrintPdfSettingsModal() {
    const modalEl = document.getElementById('printPdfSettingsModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
    instance.hide();
}

function onPrintPdfCancel() {
    closePrintPdfSettingsModal();
}

function onPrintPdfSave() {
    closePrintPdfSettingsModal();
}

function togglePrintSidebarSection(sectionId) {
    const contentEl = document.getElementById('printSectionContent-' + sectionId);
    const iconEl = document.getElementById('printSectionIcon-' + sectionId);
    if (!contentEl || !iconEl) return;
    const nextOpen = contentEl.style.display === 'none';
    contentEl.style.display = nextOpen ? 'block' : 'none';
    iconEl.classList.toggle('is-open', nextOpen);
}

function openPrintPreviewPopup(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    const modalEl = document.getElementById('estimatePreviewModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const frame = document.getElementById('estimatePreviewFrame');
    if (frame) {
        frame.src = buildPrintPdfPreviewUrl(qid);
    }
    printPdfModalState.quoteId = qid;
    const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
    instance.show();
}

function onEstimatePreviewDownload() {
    const qid = coercePositiveIntId(printPdfModalState.quoteId);
    if (!qid) return;
    downloadPDF(qid);
}

function onEstimatePreviewPrint() {
    const qid = coercePositiveIntId(printPdfModalState.quoteId);
    if (!qid) return;
    const frame = document.getElementById('estimatePreviewFrame');
    try {
        if (frame && frame.contentWindow && typeof frame.contentWindow.print === 'function') {
            frame.contentWindow.print();
            return;
        }
    } catch (e) {}
    printEstimate(qid);
}

function onEstimatePreviewEmail() {
    const qid = coercePositiveIntId(printPdfModalState.quoteId);
    if (!qid) return;
    emailEstimate(qid);
}

function focusEstimateLabel() {
    const el = document.getElementById('createEstimateLabel');
    if (!el) return;
    el.focus();
    if (typeof el.select === 'function') el.select();
}

function openEstimateEditMode(quoteId) {
    const qid = coercePositiveIntId(quoteId || editingQuoteId || selectedQuoteId);
    if (!qid) {
        focusEstimateLabel();
        return;
    }

    const proceed = window.confirm('Edit this estimate? You can update all fields, products, and services.');
    if (!proceed) return;

    editingQuoteId = qid;
    selectedQuoteId = qid;
    loadEstimateDetails(qid);

    // Wait for re-render, then focus the first editable field.
    setTimeout(function() {
        const firstField = document.getElementById('createEstimateCustomer') || document.getElementById('createEstimateLabel');
        if (firstField && typeof firstField.focus === 'function') {
            firstField.focus();
        }
    }, 120);
}

function getCurrentEstimateCustomerLabel() {
    const customerSelect = document.getElementById('createEstimateCustomer');
    if (customerSelect && customerSelect.selectedOptions && customerSelect.selectedOptions[0]) {
        const txt = String(customerSelect.selectedOptions[0].textContent || '').trim();
        if (txt && txt.toLowerCase() !== 'select customer') {
            return txt;
        }
    }
    const customerTitle = document.querySelector('.estimate-detail-customer');
    if (customerTitle) {
        const txt = String(customerTitle.textContent || '').trim();
        if (txt) return txt;
    }
    return '';
}

function resizeSignatureCanvas() {
    const canvas = signatureModalState.canvas;
    const ctx = signatureModalState.ctx;
    if (!canvas || !ctx) return;

    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    const nextW = Math.max(1, Math.floor(rect.width * dpr));
    const nextH = Math.max(1, Math.floor(rect.height * dpr));
    if (canvas.width === nextW && canvas.height === nextH) return;

    const prevData = signatureModalState.hasStroke ? canvas.toDataURL('image/png') : '';
    canvas.width = nextW;
    canvas.height = nextH;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = signatureModalState.color;
    ctx.lineWidth = signatureModalState.thickness;
    ctx.clearRect(0, 0, rect.width, rect.height);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, rect.width, rect.height);

    if (prevData) {
        const img = new Image();
        img.onload = function() {
            ctx.drawImage(img, 0, 0, rect.width, rect.height);
        };
        img.src = prevData;
    }
}

function getCanvasPointFromEvent(evt) {
    const canvas = signatureModalState.canvas;
    if (!canvas) return null;
    const rect = canvas.getBoundingClientRect();
    return {
        x: evt.clientX - rect.left,
        y: evt.clientY - rect.top
    };
}

function signaturePointerDown(evt) {
    if (!signatureModalState.ctx) return;
    const point = getCanvasPointFromEvent(evt);
    if (!point) return;
    signatureModalState.drawing = true;
    signatureModalState.hasStroke = true;
    signatureModalState.ctx.beginPath();
    signatureModalState.ctx.moveTo(point.x, point.y);
}

function signaturePointerMove(evt) {
    if (!signatureModalState.drawing || !signatureModalState.ctx) return;
    const point = getCanvasPointFromEvent(evt);
    if (!point) return;
    signatureModalState.ctx.lineTo(point.x, point.y);
    signatureModalState.ctx.stroke();
}

function signaturePointerUp() {
    if (!signatureModalState.drawing) return;
    signatureModalState.drawing = false;
    if (signatureModalState.ctx) {
        signatureModalState.ctx.closePath();
    }
}

function initializeSignaturePad() {
    if (signatureModalState.canvas) return;
    const canvas = document.getElementById('estimateSignaturePad');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    signatureModalState.canvas = canvas;
    signatureModalState.ctx = ctx;
    resizeSignatureCanvas();

    canvas.addEventListener('pointerdown', signaturePointerDown);
    canvas.addEventListener('pointermove', signaturePointerMove);
    canvas.addEventListener('pointerup', signaturePointerUp);
    canvas.addEventListener('pointerleave', signaturePointerUp);
    window.addEventListener('resize', resizeSignatureCanvas);
}

function bindSignatureModalEvents() {
    if (signatureModalState.modalEventsBound) return;
    const modalEl = document.getElementById('estimateSignatureModal');
    if (!modalEl) return;
    modalEl.addEventListener('shown.bs.modal', function() {
        // Canvas must be sized after modal becomes visible.
        setTimeout(function() {
            resizeSignatureCanvas();
            if (!signatureModalState.hasStroke) {
                clearSignaturePad();
            }
        }, 40);
    });
    modalEl.addEventListener('hidden.bs.modal', function() {
        signaturePointerUp();
    });
    signatureModalState.modalEventsBound = true;
}

function clearSignaturePad() {
    if (!signatureModalState.ctx || !signatureModalState.canvas) return;
    const canvas = signatureModalState.canvas;
    const ctx = signatureModalState.ctx;
    const rect = canvas.getBoundingClientRect();
    ctx.clearRect(0, 0, rect.width, rect.height);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, rect.width, rect.height);
    signatureModalState.hasStroke = false;
    signatureModalState.dataUrl = '';
}

function onSignatureColorChange(colorValue) {
    const val = String(colorValue || '').trim() || '#111111';
    signatureModalState.color = val;
    if (signatureModalState.ctx) {
        signatureModalState.ctx.strokeStyle = val;
    }
}

function onSignatureThicknessChange(thicknessValue) {
    const n = Math.max(1, Math.min(12, parseInt(String(thicknessValue || '3'), 10) || 3));
    signatureModalState.thickness = n;
    if (signatureModalState.ctx) {
        signatureModalState.ctx.lineWidth = n;
    }
}

function resetEstimateSignatureModalFields() {
    const nameInput = document.getElementById('signatureCustomerName');
    const titleInput = document.getElementById('signatureCustomerTitle');
    const dateInput = document.getElementById('signatureDate');
    const receiverCheckbox = document.getElementById('signatureReceiverToggle');
    const receiverInput = document.getElementById('signatureReceiverName');
    const colorInput = document.getElementById('signatureColorPicker');
    const thicknessInput = document.getElementById('signatureThicknessRange');

    if (nameInput) nameInput.value = getCurrentEstimateCustomerLabel();
    if (titleInput) titleInput.value = '';
    if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);
    if (receiverCheckbox) receiverCheckbox.checked = false;
    if (receiverInput) receiverInput.value = '';
    if (colorInput) colorInput.value = '#111111';
    if (thicknessInput) thicknessInput.value = '3';
    onSignatureColorChange('#111111');
    onSignatureThicknessChange(3);
    clearSignaturePad();
}

function openEstimateSignoff(quoteId) {
    const qid = coercePositiveIntId(quoteId || editingQuoteId || selectedQuoteId);
    if (!qid) return;
    const modalEl = document.getElementById('estimateSignatureModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    signatureModalState.quoteId = qid;
    initializeSignaturePad();
    bindSignatureModalEvents();
    resetEstimateSignatureModalFields();
    const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
    instance.show();
}

function onSignatureReset() {
    resetEstimateSignatureModalFields();
}

function onSignatureCancel() {
    const modalEl = document.getElementById('estimateSignatureModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
    instance.hide();
}

function onSignatureDone() {
    if (signatureModalState.canvas && signatureModalState.hasStroke) {
        signatureModalState.dataUrl = signatureModalState.canvas.toDataURL('image/png');
    }
    const modalEl = document.getElementById('estimateSignatureModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
    instance.hide();
}

function toggleEstimateCompact() {
    const root = document.getElementById('estimateDetails');
    if (!root) return;
    root.classList.toggle('estimate-compact-mode');
}

function hideEstimateActionsMenu() {
    const menu = document.getElementById('estimateActionsMenu');
    if (!menu) return;
    menu.style.display = 'none';
    menu.setAttribute('aria-hidden', 'true');
}

function initializeEstimateActionsMenu() {
    if (estimateActionsMenuState.initialized) return;
    const menu = document.getElementById('estimateActionsMenu');
    if (!menu) return;
    document.addEventListener('click', function(evt) {
        if (!menu.contains(evt.target)) {
            hideEstimateActionsMenu();
        }
    });
    window.addEventListener('resize', hideEstimateActionsMenu);
    window.addEventListener('scroll', hideEstimateActionsMenu, true);
    estimateActionsMenuState.initialized = true;
}

function onEstimateActionDuplicate() {
    const qid = coercePositiveIntId(estimateActionsMenuState.quoteId);
    hideEstimateActionsMenu();
    if (!qid) return;
    duplicateQuote(qid);
}

function onEstimateActionTrash() {
    const qid = coercePositiveIntId(estimateActionsMenuState.quoteId);
    hideEstimateActionsMenu();
    if (!qid) return;
    deleteEstimate(qid);
}

function openEstimateActionsMenu(event, quoteId, triggerEl) {
    if (event && typeof event.preventDefault === 'function') event.preventDefault();
    if (event && typeof event.stopPropagation === 'function') event.stopPropagation();
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    initializeEstimateActionsMenu();
    const menu = document.getElementById('estimateActionsMenu');
    const anchor = triggerEl || (event ? event.currentTarget : null);
    if (!menu || !anchor || typeof anchor.getBoundingClientRect !== 'function') return;

    estimateActionsMenuState.quoteId = qid;
    const rect = anchor.getBoundingClientRect();
    menu.style.display = 'block';
    menu.setAttribute('aria-hidden', 'false');
    menu.style.top = (rect.bottom + window.scrollY + 6) + 'px';
    menu.style.left = (rect.right + window.scrollX - menu.offsetWidth) + 'px';
}

function createNewEstimate() {
    selectedQuoteId = null;
    editingQuoteId = null;
    $('.estimate-item').removeClass('active');
    renderCreateEstimateForm(null);
}

function renderCreateEstimateForm(data) {
    destroyCreateEstimateDatePicker();
    createEstimatePendingFiles = [];
    const quote = data && data.quote ? data.quote : null;
    const itemsFromQuote = (data && Array.isArray(data.items)) ? data.items : [];
    createEstimateItems = itemsFromQuote.map(function(it) {
        const pid = it.product_variation_color_id || null;
        const desc = it.description || '';
        const pl = splitProductTitleSub(desc);
        const hasDescText = String(desc).trim() !== '';
        return {
            type: it.type === 'service' ? 'service' : 'product',
            product_variation_color_id: pid,
            product_text: desc,
            description: desc,
            product_line_title: pl.name,
            product_line_subtitle: pl.sub,
            quantity: Number(it.quantity || 1),
            rate: (function(r) {
                if (typeof r === 'number' && !Number.isNaN(r)) return r;
                return parseFloat(String(r || '0').replace(/,/g, '')) || 0;
            })(it.rate),
            tax: 0,
            discount: 0,
            notes: it.item_notes || '',
            productPickerOpen: !pid && !hasDescText
        };
    });
    const hasEstimateLabelKey = quote && Object.prototype.hasOwnProperty.call(quote, 'estimate_label');
    const estimateLabelVal = quote
        ? String(hasEstimateLabelKey ? (quote.estimate_label || '') : (quote.project_name || ''))
        : '';
    const subtitleVal = quote
        ? String(hasEstimateLabelKey ? (quote.project_name || '') : '')
        : '';
    const projectAddressVal = quote ? String(quote.project_address || '') : '';
    const _addrParsed = quote && quote.customer_address ? parseAddressToSplitFields(String(quote.customer_address)) : { street: '', city: '', state: '' };
    const streetVal = _addrParsed.street;
    const cityVal = _addrParsed.city;
    const stateVal = _addrParsed.state;
    const shippingOptsHtml = buildShippingMethodOptionsHtml(quote && quote.shipping_method ? quote.shipping_method : '');
    const termsVal = quote && quote.terms_and_conditions ? String(quote.terms_and_conditions) : '';
    const staffNotesVal = quote && quote.staff_notes ? String(quote.staff_notes) : '';
    const discountRawVal = quote && quote.order_discount_raw != null ? String(quote.order_discount_raw) : '';
    const shipCostNum = quote && quote.shipping_cost != null && quote.shipping_cost !== '' ? Number(quote.shipping_cost) : 0;
    const shipCostDisp = Number.isFinite(shipCostNum) && shipCostNum > 0 ? shipCostNum.toFixed(2) : '';
    const _canCreate = window.RBAC_CAN.canCreateEstimate;
    const _canEdit   = window.RBAC_CAN.canEditEstimate;
    // Save a new estimate needs S; save an existing needs O.
    const _canSave   = quote ? _canEdit : _canCreate;
    const _rbacSaveBtn = _canSave
        ? '<button type="button" class="btn-action primary" onclick="submitCreateEstimateForm()">Save</button>'
        : '';
    const _rbacEditBtn = (_canEdit && quote)
        ? '<button type="button" class="btn-action btn-action-icon" onclick="openEstimateEditMode(' + (quote ? quote.id : 0) + ')" title="Edit"><i class="ti ti-pencil"></i></button>'
        : '';
    const _rbacAddItemsBar = _canSave
        ? '<div class="create-items-toolbar"><button type="button" class="btn-add-product" onclick="addCreateEstimateItemRow(\'product\')"><i class="ti ti-circle-plus"></i><span>Add Product</span></button><button type="button" class="btn-add-product" onclick="addCreateEstimateItemRow(\'service\')"><i class="ti ti-circle-plus"></i><span>Add Service</span></button></div>'
        : '';
    const formHtml = `
        <div class="quotes-right-header estimate-create-header">
            <div class="estimate-detail-header">
                <div class="estimate-detail-customer">${quote ? escapeHtml(quote.customer_name || quote.estimate_label || 'Estimate') : 'New Estimate'}</div>
                <div class="estimate-detail-project"></div>
            </div>
            <div class="estimate-detail-actions">
                ${_rbacSaveBtn}
                ${quote ? `
                ${_rbacEditBtn}
                <button type="button" class="btn-action btn-action-icon" onclick="openEstimateSignoff(${quote.id})" title="Sign"><i class="ti ti-writing-sign"></i></button>
                <button type="button" class="btn-action btn-action-icon" onclick="openPrintPreviewPopup(${quote.id})" title="PDF Preview &amp; Download"><i class="ti ti-file-type-pdf"></i></button>
                <button type="button" class="btn-action btn-action-icon" onclick="printEstimate(${quote.id})" title="Print"><i class="ti ti-printer"></i></button>
                <button type="button" class="btn-action btn-action-icon" onclick="emailEstimate(${quote.id})" title="Email"><i class="ti ti-mail"></i></button>
                <button type="button" class="btn-action btn-action-icon" onclick="openEstimateActionsMenu(event, ${quote.id}, this)" title="More"><i class="ti ti-dots-vertical"></i></button>
                ` : `
                <button type="button" class="btn-action btn-action-icon" onclick="focusEstimateLabel()" title="Edit fields"><i class="ti ti-pencil"></i></button>
                `}
            </div>
        </div>
        <div class="estimate-create-form estimate-create-form-themed">
            ${quote ? '' : `
            <div class="wb-notch mb-3">
                <span class="wb-notch__label">Project*</span>
                <div class="wb-notch__control">
                    <select id="createEstimateProject" class="form-control" required>
                        <option value="">Select project</option>
                    </select>
                </div>
                <div class="form-text" id="createEstimateProjectHint"></div>
            </div>`}
            <div class="estimate-form-grid estimate-form-grid--primary">
                <div class="wb-notch wb-notch--field-customer">
                    <span class="wb-notch__label">Customer*</span>
                    <div class="wb-notch__control">
                        <select id="createEstimateCustomer" class="form-control" onchange="onCreateEstimateCustomerChange()">
                            <option value="">Select customer</option>
                        </select>
                    </div>
                </div>
                <div class="wb-notch wb-notch--field-street">
                    <span class="wb-notch__label">Address</span>
                    <div class="wb-notch__control">
                        <input type="text" id="createEstimateStreet" class="form-control" placeholder="Street address" value="${escapeAttr(streetVal)}" autocomplete="off">
                    </div>
                </div>
                <div class="wb-notch wb-notch--field-city">
                    <span class="wb-notch__label">City</span>
                    <div class="wb-notch__control">
                        <input type="text" id="createEstimateCity" class="form-control" placeholder="City" value="${escapeAttr(cityVal)}" autocomplete="off">
                    </div>
                </div>
                <div class="wb-notch wb-notch--field-state">
                    <span class="wb-notch__label">State</span>
                    <div class="wb-notch__control">
                        <input type="text" id="createEstimateState" class="form-control" placeholder="State" value="${escapeAttr(stateVal)}" autocomplete="off">
                    </div>
                </div>
                <div class="wb-notch wb-notch--field-estimate-no">
                    <span class="wb-notch__label">Project Name or Number</span>
                    <div class="wb-notch__control">
                        <input id="createEstimateLabel" type="text" class="form-control" placeholder="Project name or number" size="18" maxlength="64" value="${escapeAttr(estimateLabelVal)}" autocomplete="off" />
                    </div>
                </div>
                <div class="wb-notch wb-notch--field-currency">
                    <span class="wb-notch__label">Currency</span>
                    <div class="wb-notch__control">
                        <select id="createEstimateCurrency" class="form-control wb-notch-native">
                            <option value="" ${!quote ? 'selected' : ''}>Select</option>
                            <option value="USD" ${quote && (quote.currency || 'USD') === 'USD' ? 'selected' : ''}>USD</option>
                            <option value="CAD" ${quote && quote.currency === 'CAD' ? 'selected' : ''}>CAD</option>
                            <option value="EUR" ${quote && quote.currency === 'EUR' ? 'selected' : ''}>EUR</option>
                            <option value="PKR" ${quote && quote.currency === 'PKR' ? 'selected' : ''}>PKR</option>
                        </select>
                    </div>
                </div>
                <div class="wb-notch wb-notch--field-date">
                    <span class="wb-notch__label">Estimate date *</span>
                    <div class="wb-notch__control wb-notch__control--date">
                        <input type="text" id="createEstimateDate" class="form-control estimate-date-input" placeholder="dd/mm/yyyy" size="10" value="${escapeAttr((quote && quote.estimate_date) ? String(quote.estimate_date).split('T')[0] : new Date().toISOString().split('T')[0])}" autocomplete="off" readonly />
                        <span class="wb-notch__suffix" aria-hidden="true"><i class="ti ti-calendar"></i></span>
                    </div>
                </div>
            </div>
            <div class="estimate-form-grid estimate-form-grid--secondary">
                <div class="wb-notch wb-notch--field-subtitle">
                    <span class="wb-notch__label">Sub Title</span>
                    <div class="wb-notch__control">
                        <input id="createEstimateSubtitle" type="text" class="form-control" placeholder="Sub Title" size="32" maxlength="120" value="${escapeAttr(subtitleVal)}" autocomplete="off" />
                    </div>
                </div>
                <div class="wb-notch wb-notch--field-shipping">
                    <span class="wb-notch__label">Shipping Method</span>
                    <div class="wb-notch__control">
                        <select id="createEstimateShippingMethod" class="form-control wb-notch-native">${shippingOptsHtml}</select>
                    </div>
                </div>
                <label class="create-discount-inline m-0"><input type="checkbox" id="createDiscountBeforeTax"> Discount before tax</label>
            </div>
            <div class="estimate-form-address-row">
                <div class="wb-notch wb-notch--field-project-address">
                    <span class="wb-notch__label">Job Site Address</span>
                    <div class="wb-notch__control" style="padding-top:6px;padding-bottom:4px;">
                        <textarea id="createEstimateProjectAddress" class="form-control" rows="2" placeholder="Job site / delivery address" style="resize:vertical;min-height:44px;">${escapeHtml(projectAddressVal)}</textarea>
                    </div>
                </div>
            </div>
            <div class="estimate-create-items-wrap">
                <div class="estimate-create-items-scroll">
                <table class="items-table create-items-table">
                    <colgroup>
                        <col class="cci-col-sr" />
                        <col class="cci-col-items" />
                        <col class="cci-col-notes" />
                        <col class="cci-col-qty" />
                        <col class="cci-col-rate" />
                        <col class="cci-col-amt" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Sr. No.</th>
                            <th scope="col" class="th-line-items">Items</th>
                            <th scope="col" class="th-line-notes">Notes</th>
                            <th>Quantity</th>
                            <th>Rate</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="createEstimateItemsBody"></tbody>
                </table>
                </div>
                ${_rbacAddItemsBar}
            </div>
            <div class="create-estimate-bottom">
                <div class="create-estimate-bottom-left">
                    <div class="estimate-bottom-box">
                        <div class="estimate-bottom-title">Terms & Conditions</div>
                        <textarea id="createEstimateTerms" class="estimate-bottom-input" rows="2" placeholder="Terms for this estimate">${escapeHtml(termsVal)}</textarea>
                    </div>
                    <div class="estimate-bottom-box">
                        <div class="estimate-bottom-title">Notes</div>
                        <textarea id="createEstimateNotes" class="estimate-bottom-input" rows="2" placeholder="Notes for the customer">${escapeHtml((quote && quote.notes) ? quote.notes : '')}</textarea>
                    </div>
                    <div class="estimate-bottom-box">
                        <div class="estimate-bottom-title">Internal Notes</div>
                        <textarea id="createEstimateStaffNotes" class="estimate-bottom-input" rows="2" placeholder="Internal notes (not shown on customer PDF)">${escapeHtml(staffNotesVal)}</textarea>
                    </div>
                    <div class="estimate-bottom-box estimate-bottom-attachments">
                        <div class="estimate-bottom-title">Attachment</div>
                        <input type="file" id="createAttachmentComputer" class="visually-hidden-estimate-file" multiple accept="*/*" autocomplete="off" />
                        <input type="file" id="createAttachmentDocument" class="visually-hidden-estimate-file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,image/*" autocomplete="off" />
                        <div class="estimate-attachment-actions">
                            <button type="button" class="estimate-attachment-action" onclick="document.getElementById('createAttachmentComputer').click()">
                                <span class="estimate-attachment-action-icon" aria-hidden="true"><i class="ti ti-plus"></i></span>
                                <span>Upload from Computer</span>
                            </button>
                            <button type="button" class="estimate-attachment-action" onclick="document.getElementById('createAttachmentDocument').click()">
                                <span class="estimate-attachment-action-icon" aria-hidden="true"><i class="ti ti-file-upload"></i></span>
                                <span>Upload from Document</span>
                            </button>
                        </div>
                        <div class="estimate-attachment-lists">
                            <div id="createEstimateAttachmentSaved"></div>
                            <div id="createEstimateAttachmentPending" class="estimate-attachment-pending"></div>
                        </div>
                    </div>
                </div>
                <div class="estimate-summary-box">
                    <div class="summary-row"><span>Sub Total</span><strong id="createSummarySubtotal">$0.00</strong></div>
                    <div class="summary-row">
                        <span>Discount</span>
                        <input type="text" id="createQuoteDiscountInput" class="summary-row-input" placeholder="30 or 30%" value="${escapeAttr(discountRawVal)}" autocomplete="off" />
                    </div>
                    <div class="summary-row">
                        <span>Shipping Cost</span>
                        <input type="text" id="createQuoteShippingInput" class="summary-row-input" placeholder="Shipping cost" value="${escapeAttr(shipCostDisp)}" autocomplete="off" />
                    </div>
                    <div class="summary-row total"><span>Total</span><strong id="createSummaryTotal">$0.00</strong></div>
                </div>
            </div>
        </div>
    `;
    $('#estimateDetails').html(formHtml);
    loadCustomersForEstimateForm(quote);
    if (!quote) loadProjectsForEstimateForm();
    renderCreateEstimateItemsTable();
    initCreateEstimateDatePicker(quote);
    bindCreateEstimateBottomEvents();
    renderSavedEstimateAttachments(quote);
    renderPendingEstimateAttachments();
}

function addCreateEstimateItemRow(type) {
    const t = type || 'product';
    if (t === 'service') {
        createEstimateItems.push({
            type: 'service',
            product_variation_color_id: null,
            product_text: '',
            description: 'Service',
            service_line_title: 'Service',
            service_line_subtitle: '',
            quantity: 1,
            rate: 0,
            tax: 0,
            discount: 0,
            notes: '',
            productPickerOpen: false
        });
    } else {
        createEstimateItems.push({
            type: 'product',
            product_variation_color_id: null,
            product_text: '',
            description: '',
            quantity: 1,
            rate: 0,
            tax: 0,
            discount: 0,
            notes: '',
            productPickerOpen: true
        });
    }
    renderCreateEstimateItemsTable();
}

function removeCreateEstimateItemRow(index) {
    createEstimateItems.splice(index, 1);
    renderCreateEstimateItemsTable();
}

function formatCreateEstimateQtyValue(q) {
    const n = Math.max(1, parseInt(String(q == null ? '1' : q), 10) || 1);
    return String(n);
}

function formatCreateEstimateMoneyField(n) {
    const x = Number(n);
    if (Number.isNaN(x)) return '0.00';
    return x.toFixed(2);
}

function refreshCreateEstimateRowAmount(index) {
    const item = createEstimateItems[index];
    if (!item) return;
    const qty = Number(item.quantity || 0);
    const rate = Number(item.rate || 0);
    const tax = Number(item.tax || 0);
    const discount = Number(item.discount || 0);
    const base = qty * rate;
    const amount = Math.max(0, base + tax - discount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const $cell = $(`#createItemAmount-${index}`);
    if ($cell.length) $cell.text('$' + amount);
}

function onCreateQtyTextInput(el, index) {
    let v = String(el.value || '').replace(/\D/g, '');
    if (v === '') {
        el.value = '1';
        updateCreateEstimateItem(index, 'quantity', '1');
        return;
    }
    const n = Math.max(1, parseInt(v, 10) || 1);
    el.value = String(n);
    updateCreateEstimateItem(index, 'quantity', el.value);
}

function onCreateQtyTextBlur(el, index) {
    const n = Math.max(1, parseInt(String(el.value || '1'), 10) || 1);
    el.value = String(n);
    createEstimateItems[index].quantity = n;
    refreshCreateEstimateRowAmount(index);
    updateCreateEstimateSummary();
}

function onCreateDecimalTextInput(el, index, field) {
    let raw = String(el.value || '').replace(/[^\d.]/g, '');
    const dot = raw.indexOf('.');
    if (dot !== -1) {
        raw = raw.slice(0, dot + 1) + raw.slice(dot + 1).replace(/\./g, '');
    }
    const parts = raw.split('.');
    if (parts[1] && parts[1].length > 2) {
        raw = parts[0] + '.' + parts[1].slice(0, 2);
    }
    el.value = raw;
    const num = raw === '' || raw === '.' ? 0 : parseFloat(raw);
    updateCreateEstimateItem(index, field, Number.isNaN(num) ? 0 : num);
}

function onCreateDecimalTextBlur(el, index, field) {
    const n = Math.max(0, parseFloat(String(el.value || '0')) || 0);
    createEstimateItems[index][field] = n;
    el.value = formatCreateEstimateMoneyField(n);
    refreshCreateEstimateRowAmount(index);
    updateCreateEstimateSummary();
}

function updateCreateEstimateItem(index, field, value) {
    if (!createEstimateItems[index]) return;
    if (field === 'quantity') {
        createEstimateItems[index][field] = Math.max(1, parseInt(String(value || '1'), 10) || 1);
    } else if (field === 'rate' || field === 'tax' || field === 'discount') {
        createEstimateItems[index][field] = Math.max(0, parseFloat(String(value == null ? '0' : value)) || 0);
    } else {
        createEstimateItems[index][field] = value ?? '';
    }
    if (field === 'description' || field === 'notes') {
        return;
    }
    refreshCreateEstimateRowAmount(index);
    updateCreateEstimateSummary();
}

function resizeCreateEstimateTextarea(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

function resizeCreateEstimateTextareas() {
    $('.create-item-notes-input, .create-product-sub-input, .create-service-sub-input').each(function() {
        resizeCreateEstimateTextarea(this);
    });
}

function onCreateItemNotesInput(el, index) {
    resizeCreateEstimateTextarea(el);
    updateCreateEstimateItem(index, 'notes', el ? el.value : '');
}

function onCreateProductSubInput(el, index) {
    resizeCreateEstimateTextarea(el);
    onCreateProductTitleSubInput(index, 'sub', el ? el.value : '');
}

function onCreateServiceSubInput(el, index) {
    resizeCreateEstimateTextarea(el);
    onCreateServiceTitleSubInput(index, 'sub', el ? el.value : '');
}

function ensureCreateProductDropdownHandlers() {
    if (createProductDropdownHandlersBound) return;
    createProductDropdownHandlersBound = true;
    bindCreateProductDropdownPositioning();
    $(document).on('mousedown.createProductOuterClose', function(e) {
        if ($(e.target).closest('.item-cell-wrap--product').length) return;
        closeAllCreateProductDropdowns();
        createProductActiveIndex = null;
    });
    $(document).on('mousedown.createProductPick', '.create-product-row', function(e) {
        e.preventDefault();
        const index = parseInt($(this).attr('data-index'), 10);
        const row = parseInt($(this).attr('data-row'), 10);
        const items = getSortedProductSuggestions(index);
        const it = items[row];
        if (it) applyCreateProductItem(index, it);
    });
    $(document).on('mousedown.createProductFooter', '.create-product-dropdown-footer', function(e) {
        e.stopPropagation();
    });
}

function closeAllCreateProductDropdowns() {
    $('.item-cell-wrap--product').removeClass('is-product-dd-open');
    $('.create-product-dropdown').each(function() {
        const $dd = $(this);
        $dd.attr('hidden', true);
        clearCreateProductDropdownStyles($dd);
    });
}

function getSortedProductSuggestions(index) {
    const raw = createEstimateProductSuggestions[index];
    const items = Array.isArray(raw) ? raw.slice() : [];
    if (createProductSortByRecent) {
        items.sort(function(a, b) {
            return (Number(b.id) || 0) - (Number(a.id) || 0);
        });
    }
    return items;
}

function toggleCreateProductSortRecent(index, checked) {
    createProductSortByRecent = !!checked;
    $('.create-product-sort-recent').prop('checked', createProductSortByRecent);
    renderCreateProductDropdownUI(index, true);
}

function renderCreateProductDropdownUI(index, forceShow) {
    const $dd = $(`#createProductDropdown-${index}`);
    const $scroll = $(`#createProductDropdownScroll-${index}`);
    if (!$dd.length || !$scroll.length) return;
    $('.item-cell-wrap--product').removeClass('is-product-dd-open');
    $dd.find('.create-product-sort-recent').prop('checked', createProductSortByRecent);
    const items = getSortedProductSuggestions(index);
    const shouldShow = !!forceShow || createProductActiveIndex === index;
    if (!items.length) {
        $scroll.html('<div class="create-product-dropdown-empty">No matches — keep typing to use a custom line item.</div>');
        if (shouldShow) {
            $dd.removeAttr('hidden');
            $dd.closest('.item-cell-wrap--product').addClass('is-product-dd-open');
            requestAnimationFrame(function() { positionCreateProductDropdown(index); });
        } else {
            $dd.attr('hidden', true);
            clearCreateProductDropdownStyles($dd);
        }
        return;
    }
    const rowsHtml = items.map(function(it, ri) {
        const title = String(it.text || '').substring(0, 220);
        const price = Number(it.rate || 0).toFixed(2);
        return `<button type="button" class="create-product-row" data-index="${index}" data-row="${ri}">` +
            `<span class="create-product-row-title">${escapeHtml(title)}</span>` +
            `<span class="create-product-row-price">$${price}</span>` +
            `</button>`;
    }).join('');
    $scroll.html(rowsHtml);
    if (shouldShow) {
        $dd.removeAttr('hidden');
        $dd.closest('.item-cell-wrap--product').addClass('is-product-dd-open');
        requestAnimationFrame(function() { positionCreateProductDropdown(index); });
    } else {
        $dd.attr('hidden', true);
        clearCreateProductDropdownStyles($dd);
    }
}

function onCreateProductFocus(index) {
    createProductActiveIndex = index;
    ensureCreateProductDropdownHandlers();
    const el = document.getElementById('createProductInput-' + index);
    if (el && isCreateLinePlaceholderTitle(el.value, 'product')) {
        el.value = '';
        onCreateProductInput(index, '');
    }
    const v = el ? (el.value || '') : '';
    requestAnimationFrame(function() {
        if (el && typeof el.select === 'function') el.select();
    });
    fetchCreateProductSuggestions(index, v);
}

function onCreateProductBlur(index) {
    setTimeout(function() {
        const ae = document.activeElement;
        if (ae && $(ae).closest(`#createProductDropdown-${index}`).length) return;
        createProductActiveIndex = null;
        closeAllCreateProductDropdowns();
        const $inp = $(`#createProductInput-${index}`);
        if ($inp.length) applyCreateProductSelection(index, $inp.val());
    }, 180);
}

function openCreateProductPicker(index) {
    if (!createEstimateItems[index] || createEstimateItems[index].type !== 'product') return;
    createEstimateItems[index].productPickerOpen = true;
    createProductActiveIndex = null;
    closeAllCreateProductDropdowns();
    renderCreateEstimateItemsTable();
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            const el = document.getElementById('createProductInput-' + index);
            if (el) {
                el.focus();
                if (typeof el.select === 'function') el.select();
            }
            onCreateProductFocus(index);
        });
    });
}

function applyCreateProductItem(index, selected) {
    if (!createEstimateItems[index] || !selected) return;
    const desc = selected.description || selected.text || '';
    const pl = splitProductTitleSub(desc);
    createEstimateItems[index].product_variation_color_id = selected.id || null;
    createEstimateItems[index].product_text = selected.text || '';
    createEstimateItems[index].description = desc;
    createEstimateItems[index].product_line_title = pl.name;
    createEstimateItems[index].product_line_subtitle = pl.sub;
    createEstimateItems[index].rate = Number(selected.rate || 0);
    createEstimateItems[index].productPickerOpen = false;
    createProductActiveIndex = null;
    closeAllCreateProductDropdowns();
    renderCreateEstimateItemsTable();
}

function onCreateProductInput(index, value) {
    if (!createEstimateItems[index]) return;
    const text = String(value || '');
    createEstimateItems[index].product_text = text;
    createEstimateItems[index].description = text;
    createEstimateItems[index].product_variation_color_id = null;
    updateCreateEstimateSummary();
    $(`#createItemDesc-${index}`).text(text || '');
    fetchCreateProductSuggestions(index, text);
}

function fetchCreateProductSuggestions(index, term) {
    $.ajax({
        url: @json(route('quotes.product-variations')),
        method: 'GET',
        data: { q: term || '' },
        success: function(response) {
            const items = Array.isArray(response.items) ? response.items : [];
            createEstimateProductSuggestions[index] = items;
            renderCreateProductDropdownUI(index, createProductActiveIndex === index);
        },
        error: function(xhr) {
            console.error('Error loading product suggestions:', xhr);
        }
    });
}

function applyCreateProductSelection(index, value) {
    if (!createEstimateItems[index]) return;
    const item = createEstimateItems[index];
    const options = createEstimateProductSuggestions[index] || [];
    const selected = options.find(function(it) {
        return String(it.text || '') === String(value || '');
    });
    if (!selected) {
        const v = String(value || '').trim();
        const pt = String(item.product_text || '').trim();
        const desc = String(item.description || '').trim();
        if (item.product_variation_color_id && (v === pt || v === desc)) {
            const pl = splitProductTitleSub(item.description || '');
            item.product_line_title = pl.name;
            item.product_line_subtitle = pl.sub;
            item.productPickerOpen = false;
            renderCreateEstimateItemsTable();
            refreshCreateEstimateRowAmount(index);
            updateCreateEstimateSummary();
            return;
        }
        item.product_variation_color_id = null;
        item.product_text = value || '';
        item.description = value || '';
        const pl = splitProductTitleSub(item.description || '');
        item.product_line_title = isCreateLinePlaceholderTitle(pl.name, 'product') ? '' : pl.name;
        item.product_line_subtitle = pl.sub;
        item.productPickerOpen = false;
        const $desc = $(`#createItemDesc-${index}`);
        if ($desc.length) $desc.text(value || '');
        renderCreateEstimateItemsTable();
        refreshCreateEstimateRowAmount(index);
        updateCreateEstimateSummary();
        return;
    }
    applyCreateProductItem(index, selected);
}

function renderCreateEstimateItemsTable() {
    const $body = $('#createEstimateItemsBody');
    if (!$body.length) return;
    if (!createEstimateItems.length) {
        $body.html(`
            <tr>
                <td colspan="6" class="text-center text-muted">No items yet. Click "+ Add Product" or "+ Add Service".</td>
            </tr>
        `);
        updateCreateEstimateSummary();
        return;
    }

    const rows = createEstimateItems.map(function(item, idx) {
        const qty = Number(item.quantity || 0);
        const rate = Number(item.rate || 0);
        const tax = Number(item.tax || 0);
        const discount = Number(item.discount || 0);
        const base = qty * rate;
        const amount = Math.max(0, base + tax - discount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const qtyDisp = formatCreateEstimateQtyValue(item.quantity);
        const rateDisp = formatCreateEstimateMoneyField(item.rate);
        const showProductPicked = item.type === 'product' && item.productPickerOpen !== true;
        const dispParts = item.type === 'product' ? getCreateProductDisplayParts(item) : { name: '', sub: '' };
        const serviceDisp = item.type === 'service' ? getCreateServiceDisplayParts(item) : null;
        let serviceBlockHtml = '';
        if (item.type === 'service' && serviceDisp) {
            serviceBlockHtml = `
                <div class="item-cell-wrap item-cell-wrap--service" data-service-row="${idx}">
                    <div class="create-service-line-fields">
                        <input type="text" class="create-service-title-input" id="createServiceLineTitle-${idx}" autocomplete="off" placeholder="Service name" value="${escapeAttr(serviceDisp.name)}" onfocus="onCreateServiceFocus(${idx})" onblur="onCreateServiceBlur(${idx})" oninput="onCreateServiceTitleSubInput(${idx}, 'title', this.value)" />
                        <div class="create-service-dropdown" id="createServiceDropdown-${idx}" hidden>
                            <div class="create-service-dropdown-scroll" id="createServiceDropdownScroll-${idx}"></div>
                        </div>
                        <textarea class="create-service-sub-input" id="createServiceLineSub-${idx}" rows="1" autocomplete="off" placeholder="Service description" oninput="onCreateServiceSubInput(this, ${idx})">${escapeHtml(serviceDisp.sub)}</textarea>
                    </div>
                </div>`;
        }
        const pickedBlock = item.type === 'product'
            ? `<div class="create-product-picked${showProductPicked ? '' : ' is-hidden'}" id="createProductPicked-${idx}" onclick="onCreateProductPickedShellClick(event, ${idx})">
                    <div class="create-product-picked-fields" onclick="event.stopPropagation();">
                        <input type="text" class="create-product-picked-name create-product-title-input" id="createProductLineTitle-${idx}" autocomplete="off" placeholder="Product name" value="${escapeAttr(dispParts.name)}" onfocus="onCreateProductLineTitleFocus(${idx})" oninput="onCreateProductTitleSubInput(${idx}, 'title', this.value)" />
                        <textarea class="create-product-picked-sub create-product-sub-input" id="createProductLineSub-${idx}" rows="1" autocomplete="off" oninput="onCreateProductSubInput(this, ${idx})">${escapeHtml(dispParts.sub)}</textarea>
                    </div>
                </div>`
            : '';
        const editorHiddenClass = showProductPicked ? ' is-hidden' : '';
        const productSelectHtml = item.type === 'product'
            ? `
                <div class="item-cell-wrap item-cell-wrap--product" data-product-row="${idx}">
                    ${pickedBlock}
                    <div class="create-product-editor${editorHiddenClass}">
                    <input class="editable-input create-product-input" type="text" id="createProductInput-${idx}" data-product-index="${idx}" value="${escapeAttr(item.product_text || item.description || '')}" autocomplete="off" placeholder="Search or type product" onfocus="onCreateProductFocus(${idx})" oninput="onCreateProductInput(${idx}, this.value)" onblur="onCreateProductBlur(${idx})" />
                    <div class="create-product-dropdown" id="createProductDropdown-${idx}" hidden>
                        <div class="create-product-dropdown-scroll" id="createProductDropdownScroll-${idx}"></div>
                        <label class="create-product-dropdown-footer" for="createProductSortRecent-${idx}">
                            <input type="checkbox" class="create-product-sort-recent" id="createProductSortRecent-${idx}" ${createProductSortByRecent ? 'checked' : ''} onchange="toggleCreateProductSortRecent(${idx}, this.checked)" />
                            <span>Sort by Recent Used</span>
                        </label>
                    </div>
                    <div class="item-inline-desc" id="createItemDesc-${idx}" data-placeholder="Description">${escapeHtml(item.description || '')}</div>
                    </div>
                </div>
            `
            : '';
        const notesEscaped = escapeHtml(item.notes || '');
        const itemsCellInner = item.type === 'product' ? productSelectHtml : serviceBlockHtml;
        return `
            <tr class="create-estimate-item-row">
                <td>${idx + 1}</td>
                <td class="td-items"><div class="td-line-cell-fill">${itemsCellInner}</div></td>
                <td class="td-notes"><div class="td-line-cell-fill"><textarea class="create-item-notes-input" rows="1" placeholder="Notes" autocomplete="off" oninput="onCreateItemNotesInput(this, ${idx})">${notesEscaped}</textarea></div></td>
                <td class="td-num"><input type="text" inputmode="numeric" class="editable-input editable-number" value="${escapeAttr(qtyDisp)}" oninput="onCreateQtyTextInput(this, ${idx})" onblur="onCreateQtyTextBlur(this, ${idx})" autocomplete="off"></td>
                <td class="td-num"><input type="text" inputmode="decimal" class="editable-input editable-number" id="createItemRate-${idx}" value="${escapeAttr(rateDisp)}" oninput="onCreateDecimalTextInput(this, ${idx}, 'rate')" onblur="onCreateDecimalTextBlur(this, ${idx}, 'rate')" autocomplete="off"></td>
                <td class="td-num td-amount td-amount-with-action">
                    <div class="create-item-amount-wrap">
                        <span id="createItemAmount-${idx}" class="td-amount-value">$${amount}</span>
                        <button type="button" class="create-item-row-delete" title="Remove line" aria-label="Remove line" onclick="removeCreateEstimateItemRow(${idx})"><i class="ti ti-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    $body.html(rows);
    ensureCreateProductDropdownHandlers();
    resizeCreateEstimateTextareas();
    updateCreateEstimateSummary();
}

function getEstimateCurrencyPrefix() {
    const c = ($('#createEstimateCurrency').val() || 'USD').trim();
    if (c === 'EUR') return '€';
    if (c === 'PKR') return 'Rs';
    return '$';
}

function formatEstimateMoney(amount) {
    const n = Number(amount);
    const x = Number.isFinite(n) ? n : 0;
    const p = getEstimateCurrencyPrefix();
    return p + x.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

/** Parse order-level discount: plain number or trailing % of line net subtotal. */
function parseOrderDiscountAmount(raw, lineNetSubtotal) {
    const base = Math.max(0, Number(lineNetSubtotal) || 0);
    const s = String(raw || '').trim();
    if (!s) return 0;
    if (s.endsWith('%')) {
        const p = parseFloat(s.slice(0, -1).trim());
        if (!Number.isFinite(p) || p < 0) return 0;
        return Math.min(base, base * (p / 100));
    }
    const n = parseFloat(s.replace(/[^0-9.]/g, ''));
    if (!Number.isFinite(n) || n < 0) return 0;
    return Math.min(base, n);
}

function parseShippingCostInput(raw) {
    const n = parseFloat(String(raw || '').replace(/[^0-9.]/g, ''));
    return Number.isFinite(n) && n > 0 ? n : 0;
}

function updateCreateEstimateSummary() {
    let lineNet = 0;
    createEstimateItems.forEach(function(item) {
        const qty = Number(item.quantity || 0);
        const rate = Number(item.rate || 0);
        const tax = Number(item.tax || 0);
        const discount = Number(item.discount || 0);
        const base = (qty * rate) + tax;
        lineNet += Math.max(0, base - discount);
    });
    const orderDisc = parseOrderDiscountAmount($('#createQuoteDiscountInput').val(), lineNet);
    const shipping = parseShippingCostInput($('#createQuoteShippingInput').val());
    const total = Math.max(0, lineNet - orderDisc + shipping);

    $('#createSummarySubtotal').text(formatEstimateMoney(lineNet));
    $('#createSummaryTotal').text(formatEstimateMoney(total));
}

function renderSavedEstimateAttachments(quote) {
    const $box = $('#createEstimateAttachmentSaved');
    if (!$box.length) return;
    const rows = normalizeQuoteAttachmentsRaw(quote && quote.attachments ? quote.attachments : []);
    if (!rows.length) {
        $box.html('');
        return;
    }
    $box.html('<div class="text-muted small mb-1">Saved files</div>' + rows.map(function(a) {
        const name = escapeHtml(a.name || 'File');
        const path = String(a.path || '').replace(/^\/+/, '').replace(/\\/g, '/');
        if (!path) return '';
        const hrefRaw = (a.url && String(a.url).trim()) ? String(a.url).trim()
            : (QUOTES_STORAGE_URL + '/' + path.split('/').map(encodeURIComponent).join('/'));
        const href = normalizeAttachmentPublicUrl(hrefRaw);
        return `<div class="mb-1"><a href="${escapeAttr(href)}" target="_blank" rel="noopener noreferrer">${name}</a></div>`;
    }).filter(Boolean).join(''));
}

function renderPendingEstimateAttachments() {
    const $box = $('#createEstimateAttachmentPending');
    if (!$box.length) return;
    if (!createEstimatePendingFiles.length) {
        $box.html('');
        return;
    }
    const fileLines = createEstimatePendingFiles.map(function(f) {
        return `<div class="estimate-attachment-pending-row"><span>${escapeHtml(f.name)}</span></div>`;
    });
    $box.html('<div class="text-muted small mb-1">These files upload when you click <strong>Save</strong> in the header.</div>' + fileLines.join(''));
}

function appendCreateEstimateFilesFromInput(input) {
    if (!input || !input.files || !input.files.length) return;
    for (let i = 0; i < input.files.length; i += 1) {
        createEstimatePendingFiles.push(input.files[i]);
    }
    input.value = '';
    renderPendingEstimateAttachments();
}

function bindCreateEstimateBottomEvents() {
    const $root = $('#estimateDetails');
    $root.off('.estBottom');
    $root.on('input.estBottom', '#createQuoteDiscountInput, #createQuoteShippingInput', function() {
        updateCreateEstimateSummary();
    });
    $root.on('change.estBottom', '#createEstimateCurrency', function() {
        updateCreateEstimateSummary();
    });
    $root.on('change.estBottom', '#createAttachmentComputer, #createAttachmentDocument', function() {
        appendCreateEstimateFilesFromInput(this);
    });
}

function loadProjectsForEstimateForm() {
    $.ajax({
        url: PROJECTS_LIST_ROUTE,
        method: 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        success: function(response) {
            const projects = Array.isArray(response.projects) ? response.projects : [];
            const $select = $('#createEstimateProject');
            if (!$select.length) return;
            let options = '<option value="">Select project</option>';
            projects.forEach(function(p) {
                options += `<option value="${escapeAttr(String(p.id))}">${escapeHtml(p.name || ('Project #' + p.id))}</option>`;
            });
            $select.html(options);
            const wanted = coercePositiveIntId(new URLSearchParams(window.location.search).get('project'));
            if (wanted && projects.some(function(p) { return p.id === wanted; })) {
                $select.val(String(wanted));
            } else if (projects.length === 1) {
                $select.val(String(projects[0].id));
            }
            if (!projects.length) {
                $('#createEstimateProjectHint').text('No projects yet. Create a project first.');
            }
        },
        error: function(xhr) {
            console.error('Error loading projects for estimate form:', xhr);
        }
    });
}

function loadCustomersForEstimateForm(quote) {
    $.ajax({
        url: @json(route('quotes.customers')),
        method: 'GET',
        success: function(response) {
            const customers = Array.isArray(response.customers) ? response.customers : [];
            const $select = $('#createEstimateCustomer');
            if (!$select.length) return;

            if ($select.data('select2')) {
                $select.select2('destroy');
            }

            estimateCustomersMap = {};
            let options = '<option value="">Select customer</option>';
            customers.forEach(function(c) {
                const safeName = escapeHtml(c.name || 'Customer');
                estimateCustomersMap[String(c.id)] = c;
                options += `<option value="${escapeAttr(String(c.id))}">${safeName}</option>`;
            });
            $select.html(options);

            if ($.fn && $.fn.select2) {
                $select.select2({
                    width: '100%',
                    placeholder: 'Select customer',
                    allowClear: false,
                    minimumResultsForSearch: 0,
                    dropdownParent: $(document.body),
                    dropdownCssClass: 'quotes-estimate-select2-dropdown'
                });
            }

            if (quote && quote.customer_id) {
                $select.val(String(quote.customer_id)).trigger('change');
                onCreateEstimateCustomerChange(quote.customer_address || '');
            }
        },
        error: function(xhr) {
            console.error('Error loading customers for estimate form:', xhr);
        }
    });
}

function onCreateEstimateCustomerChange(preferredAddress) {
    const customerId = String($('#createEstimateCustomer').val() || '');
    const customer = estimateCustomersMap[customerId];

    if (!customer) {
        $('#createEstimateStreet').val('');
        $('#createEstimateCity').val('');
        $('#createEstimateState').val('');
        return;
    }

    // Auto-populate from customer billing fields
    $('#createEstimateStreet').val(customer.billing_street || '');
    $('#createEstimateCity').val(customer.billing_city || '');
    $('#createEstimateState').val(customer.billing_state || '');

    // If a saved address exists, parse and override (preserves manually-edited addresses)
    if (preferredAddress !== undefined && preferredAddress !== null) {
        const addr = String(preferredAddress).trim();
        if (addr) {
            const parsed = parseAddressToSplitFields(addr);
            if (parsed.street) $('#createEstimateStreet').val(parsed.street);
            if (parsed.city) $('#createEstimateCity').val(parsed.city);
            if (parsed.state) $('#createEstimateState').val(parsed.state);
        }
    }
}

function submitCreateEstimateForm() {
    const customerId = $('#createEstimateCustomer').val();
    if (!customerId) {
        alert('Please select customer');
        return;
    }

    const currency = ($('#createEstimateCurrency').val() || '').trim();
    const editId = coercePositiveIntId(editingQuoteId);
    const isEditing = !!editId;
    const projectId = coercePositiveIntId($('#createEstimateProject').val());
    if (!isEditing && !projectId) {
        alert('Please select project');
        return;
    }
    const requestUrl = isEditing
        ? (QUOTES_BASE_URL + '/' + editId + '/editor')
        : QUOTES_PROJECT_STORE_URL.replace('__PROJECT__', String(projectId));

    const payloadItems = createEstimateItems.map(function(it) {
        return {
            type: it.type === 'service' ? 'service' : 'product',
            product_variation_color_id: it.product_variation_color_id || null,
            description: it.description || it.product_text || '',
            quantity: it.quantity || 1,
            rate: it.rate || 0,
            notes: it.notes || ''
        };
    });

    const fd = new FormData();
    fd.append('customer_id', String(customerId));
    fd.append('estimate_label', String($('#createEstimateLabel').val() || ''));
    fd.append('project_name', String($('#createEstimateSubtitle').val() || ''));
    fd.append('project_address', String($('#createEstimateProjectAddress').val() || ''));
    const _street = ($('#createEstimateStreet').val() || '').trim();
    const _city = ($('#createEstimateCity').val() || '').trim();
    const _state = ($('#createEstimateState').val() || '').trim();
    const _cityState = [_city, _state].filter(Boolean).join(', ');
    fd.append('customer_address', [_street, _cityState].filter(Boolean).join('\n'));
    fd.append('shipping_method', String($('#createEstimateShippingMethod').val() || ''));
    fd.append('currency', currency);
    fd.append('estimate_date', String($('#createEstimateDate').val() || ''));
    fd.append('notes', String($('#createEstimateNotes').val() || ''));
    fd.append('terms_and_conditions', String($('#createEstimateTerms').val() || ''));
    fd.append('staff_notes', String($('#createEstimateStaffNotes').val() || ''));
    fd.append('shipping_cost', String(parseShippingCostInput($('#createQuoteShippingInput').val())));
    fd.append('order_discount_raw', String($('#createQuoteDiscountInput').val() || ''));

    payloadItems.forEach(function(it, i) {
        fd.append('items[' + i + '][type]', String(it.type || 'product'));
        fd.append('items[' + i + '][product_variation_color_id]', it.product_variation_color_id ? String(it.product_variation_color_id) : '');
        fd.append('items[' + i + '][description]', String(it.description || ''));
        fd.append('items[' + i + '][quantity]', String(it.quantity || 1));
        fd.append('items[' + i + '][rate]', String(it.rate != null ? it.rate : 0));
        fd.append('items[' + i + '][notes]', String(it.notes || ''));
    });

    createEstimatePendingFiles.forEach(function(file) {
        fd.append('attachments[]', file, file.name);
    });

    if (isEditing) {
        fd.append('_method', 'PUT');
    }

    const ajaxOpts = {
        url: requestUrl,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': QUOTES_CSRF_TOKEN,
            'X-Requested-With': 'XMLHttpRequest'
        },
        data: fd,
        processData: false,
        contentType: false,
        success: function(response) {
            createEstimatePendingFiles = [];
            loadEstimatesList();
            const rid = response && response.quote_id ? coercePositiveIntId(response.quote_id) : 0;
            if (rid) {
                selectEstimate(rid);
            }
        },
        error: function(xhr) {
            console.error('Error saving estimate:', xhr);
            alert(xhr.responseJSON?.message || 'Failed to save estimate');
        }
    };

    $.ajax(ajaxOpts);
}

function cancelCreateEstimate() {
    if (selectedQuoteId) {
        loadEstimateDetails(selectedQuoteId);
        return;
    }
    destroyCreateEstimateDatePicker();
    $('#estimateDetails').html(ESTIMATE_DETAILS_EMPTY_HTML);
}

function deleteEstimate(quoteId) {
    const qid = coercePositiveIntId(quoteId);
    if (!qid) return;
    if (!confirm('Are you sure you want to delete this estimate?')) {
        return;
    }

    $.ajax({
        url: QUOTES_BASE_URL + '/' + qid,
        method: 'DELETE',
        data: {
            _token: QUOTES_CSRF_TOKEN
        },
        success: function(response) {
            if (response && response.success) {
                if (selectedQuoteId === qid) {
                    selectedQuoteId = null;
                    destroyCreateEstimateDatePicker();
                    $('#estimateDetails').html(ESTIMATE_DETAILS_EMPTY_HTML);
                }

                loadEstimatesList();
            } else {
                console.error('Delete estimate failed:', response);
                alert(response?.message || 'Failed to delete estimate');
            }
        },
        error: function(xhr) {
            console.error('Error deleting estimate:', xhr);
            alert(xhr.responseJSON?.message || 'Failed to delete estimate');
        }
    });
}
</script>
@endpush

<div class="modal fade" id="printPdfSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-centered">
        <div class="modal-content print-pdf-modal">
            <div class="modal-header print-pdf-modal-header">
                <h5 class="modal-title">PDF &amp; Print Settings</h5>
                <div class="print-pdf-top-actions">
                    <button type="button" class="btn-action btn-action-icon" title="Search"><i class="ti ti-search"></i></button>
                    <button type="button" class="print-pdf-header-btn" onclick="onPrintPdfCancel()">Cancel</button>
                    <button type="button" class="print-pdf-header-btn primary" onclick="onPrintPdfSave()">Save</button>
                </div>
            </div>
            <div class="print-pdf-toolbar">
                <div class="print-pdf-toolbar-left">Estimate <i class="ti ti-chevron-down"></i></div>
                <div class="print-pdf-toolbar-right"><span id="printPdfToolbarMode">Normal Print</span> <i class="ti ti-chevron-down"></i></div>
            </div>
            <div class="print-pdf-modal-body">
                <div class="print-pdf-preview-pane">
                    <iframe id="printPdfPreviewFrame" title="PDF Preview" class="print-pdf-preview-frame" src="about:blank"></iframe>
                </div>
            </div>
            <div class="print-pdf-footer">
                <button type="button" class="print-pdf-footer-btn with-icon" onclick="onPrintPdfReset()"><i class="ti ti-refresh"></i><span>Reset</span></button>
                <button type="button" class="print-pdf-footer-btn with-icon" onclick="onPrintPdfPreview()"><i class="ti ti-eye"></i><span>Preview</span></button>
                <button type="button" id="printPdfPresetDefaultBtn" class="print-pdf-footer-btn with-icon" onclick="setPrintPdfPreset('default')"><i class="ti ti-tool"></i><span>Default</span></button>
                <button type="button" id="printPdfPresetStandardBtn" class="print-pdf-footer-btn with-icon is-active" onclick="setPrintPdfPreset('standard')"><i class="ti ti-file-text"></i><span>Standard</span></button>
                <span class="print-pdf-footer-divider" aria-hidden="true"></span>
                <label class="print-pdf-footer-check"><input type="checkbox" id="printPdfApplyEstimateOnly" onchange="onPrintPdfApplyOnlyToggle(this.checked)"> Apply to this Estimate only</label>
            </div>
        </div>
    </div>
</div>

<div id="estimateActionsMenu" class="estimate-actions-menu" style="display:none;" aria-hidden="true">
    @canDo('estimate_management', 'O')
    <button type="button" class="estimate-actions-menu-item" onclick="onEstimateActionDuplicate()">Duplicate Estimate</button>
    @endCanDo
    @canDo('estimate_management', 'F')
    <button type="button" class="estimate-actions-menu-item danger" onclick="onEstimateActionTrash()">Trash</button>
    @endCanDo
</div>

<div class="modal fade" id="estimateEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog email-compose-dialog modal-dialog-centered">
        <div class="modal-content email-compose-modal">
            <div class="email-compose-header">
                <div class="email-compose-title" id="emailComposeHeaderTitle">Estimate Email</div>
                <div class="email-compose-actions">
                    <button type="button" class="email-compose-text-btn" onclick="closeEmailComposeModal()">Cancel</button>
                    <button type="button" class="email-compose-text-btn primary" onclick="sendEstimateEmail()">Send</button>
                </div>
            </div>
            <div class="email-compose-body">
                <div class="email-compose-row"><label>To:</label><input id="emailComposeTo" type="text" placeholder=""></div>
                <div class="email-compose-row"><label>Subject:</label><input id="emailComposeSubject" type="text"></div>
                <div class="email-compose-row"><label>From:</label><input id="emailComposeFrom" type="text"></div>
                <div class="email-compose-toolbar">
                    <span>Size</span><span>Font</span><i class="ti ti-bold"></i><i class="ti ti-italic"></i><i class="ti ti-underline"></i>
                    <i class="ti ti-align-left"></i><i class="ti ti-align-center"></i><i class="ti ti-align-right"></i>
                    <i class="ti ti-photo"></i><i class="ti ti-link"></i>
                </div>
                <textarea id="emailComposeBody" class="email-compose-editor"></textarea>
                <a id="emailComposeAttachmentLink" class="email-compose-attachment" href="#" onclick="previewEmailAttachment(event)">
                    <i class="ti ti-file-type-pdf"></i>
                    <span id="emailComposeAttachmentName">Estimate</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="estimatePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog estimate-preview-dialog modal-dialog-centered">
        <div class="modal-content estimate-preview-modal">
            <div class="estimate-preview-header">
                <div class="estimate-preview-title">Preview</div>
                <div class="estimate-preview-actions">
                    <button type="button" class="estimate-preview-icon-btn" onclick="onEstimatePreviewDownload()" title="Download PDF"><i class="ti ti-download"></i></button>
                    <button type="button" class="estimate-preview-icon-btn" onclick="onEstimatePreviewPrint()" title="Print"><i class="ti ti-printer"></i></button>
                    <button type="button" class="estimate-preview-icon-btn" onclick="onEstimatePreviewEmail()" title="Email"><i class="ti ti-mail"></i></button>
                    <button type="button" class="estimate-preview-icon-btn" data-bs-dismiss="modal" title="Close"><i class="ti ti-x"></i></button>
                </div>
            </div>
            <div class="estimate-preview-body">
                <iframe id="estimatePreviewFrame" title="Estimate Preview" class="estimate-preview-frame" src="about:blank"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="estimateSignatureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog signature-modal-dialog modal-dialog-centered">
        <div class="modal-content signature-modal-content">
            <div class="signature-modal-header">
                <h5 class="signature-modal-title">Customer Signature</h5>
                <div class="signature-modal-actions">
                    <button type="button" class="signature-header-icon-btn" onclick="onSignatureReset()" title="Reset"><i class="ti ti-refresh"></i></button>
                    <button type="button" class="signature-header-text-btn" onclick="onSignatureCancel()">Cancel</button>
                    <button type="button" class="signature-header-text-btn primary" onclick="onSignatureDone()">Done</button>
                </div>
            </div>
            <div class="signature-modal-body">
                <div class="signature-row single">
                    <label class="signature-field-label">Name</label>
                    <input id="signatureCustomerName" type="text" class="signature-input" placeholder="Name">
                </div>
                <div class="signature-row two-cols">
                    <div>
                        <label class="signature-field-label">Title</label>
                        <input id="signatureCustomerTitle" type="text" class="signature-input" placeholder="Title">
                    </div>
                    <div>
                        <label class="signature-field-label">Date*</label>
                        <input id="signatureDate" type="date" class="signature-input">
                    </div>
                </div>
                <div class="signature-row receiver-row">
                    <label class="signature-checkbox-wrap"><input id="signatureReceiverToggle" type="checkbox"></label>
                    <input id="signatureReceiverName" type="text" class="signature-input" placeholder="Receiver's Signature">
                </div>
                <div class="signature-pad-wrap">
                    <canvas id="estimateSignaturePad" class="signature-pad-canvas"></canvas>
                </div>
                <div class="signature-controls-row">
                    <input id="signatureColorPicker" type="color" class="signature-color-input" value="#111111" onchange="onSignatureColorChange(this.value)">
                    <div class="signature-thickness-wrap">
                        <span>Thickness</span>
                        <input id="signatureThicknessRange" type="range" min="1" max="12" value="3" oninput="onSignatureThicknessChange(this.value)">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

