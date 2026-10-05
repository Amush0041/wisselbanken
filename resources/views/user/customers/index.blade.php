@extends('user.layouts.app')

@section('seo')
<title>Customers | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
@php
use App\Services\Rbac\PermissionService;
$_cOrgId = \App\Support\Rbac\CurrentOrg::sessionOrg((int) auth()->id());
$_cUser  = auth()->user();
$_cAdm   = $_cUser && $_cUser->role === 'admin';
$_cp     = fn(string $g, string $l) => $_cUser && ($_cAdm || ($_cOrgId && app(PermissionService::class)->checkPermission($_cUser->id, (int) $_cOrgId, $g, $l)));
$canAddCustomer    = (bool) $_cp('user_management', 'S');
$canEditCustomer   = (bool) $_cp('user_management', 'O');
$canDeleteCustomer = (bool) $_cp('user_management', 'F');
@endphp
<script>
window.RBAC_CAN = window.RBAC_CAN || {};
window.RBAC_CAN.canAddCustomer    = {{ $canAddCustomer    ? 'true' : 'false' }};
window.RBAC_CAN.canEditCustomer   = {{ $canEditCustomer   ? 'true' : 'false' }};
window.RBAC_CAN.canDeleteCustomer = {{ $canDeleteCustomer ? 'true' : 'false' }};
</script>
<div class="container-fluid flex-grow-1 container-p-y user-page user-customers">
    <div class="customers-workspace">
        <div class="customers-container">
            <!-- Left Panel -->
            <div class="customers-left-panel">
                <div class="customers-left-header">
                    <h2>Customers</h2>
                    <div class="header-actions">
                        <button class="btn-icon" onclick="toggleCustomerSearch(); return false;" title="Search">
                            <i class="ti ti-search"></i>
                        </button>
                    </div>
                </div>

                <div class="customers-left-search" id="customersSearchRow" style="display:none;">
                    <input type="text" id="customerSearch" class="form-control form-control-sm" placeholder="Search customers...">
                </div>

                <div class="customers-left-filters">
                    <div class="filters-row">
                        <div class="filter-chip">
                            <span class="chip-label">Sort by</span>
                            <select id="customerSortBy" class="chip-select">
                                <option value="name" selected>Name</option>
                                <option value="created_at">Created On</option>
                            </select>
                        </div>
                        <div class="filter-chip">
                            <span class="chip-label">Status</span>
                            <select id="customerStatus" class="chip-select">
                                <option value="all" selected>All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="filter-chip">
                            <span class="chip-label">Created On</span>
                            <select id="customerCreatedSort" class="chip-select">
                                <option value="desc" selected>Newest</option>
                                <option value="asc">Oldest</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="customers-left-wrap">
                    <div class="customers-list" id="customersList">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="ti ti-users"></i></div>
                            <p>No customers yet</p>
                        </div>
                    </div>
                    @if ($canAddCustomer)
                    <button type="button" class="customers-new-fab" onclick="newCustomer(); return false;" title="New Customer">+</button>
                    @endif
                </div>

                <div class="customers-footer">
                    <div class="customers-pagination" id="customersPaginationInfo">
                        <span>0-0 of 0 Customers</span>
                    </div>
                </div>
            </div>

            <!-- Right Panel -->
            <div class="customers-right-panel">
                <div class="customers-right-header">
                    <div class="customer-detail-header">
                        <div class="customer-detail-title">
                            <span id="customerHeaderName">New Customer</span>
                        </div>
                        <div class="customer-detail-actions">
                            @if ($canEditCustomer)
                            <button class="btn-action" id="btnCancelCustomer" onclick="cancelCustomerEdit();" style="display:none;">Cancel</button>
                            <button class="btn-action" id="btnEditCustomer" onclick="startCustomerEdit();" style="display:none;">Edit</button>
                            <button class="btn-action primary" id="btnSaveCustomer" onclick="saveCustomer();" style="display:none;">Save</button>
                            @endif
                            @if ($canDeleteCustomer)
                            <button class="btn-action" id="btnDeleteCustomer" onclick="deleteCustomer();" style="display:none;">Delete</button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="customer-form-body">
                    <input type="hidden" id="customer_id" value="">
                    <!-- Empty (default) -->
                    <div class="customer-empty-state" id="customerEmptyState">
                        <div class="empty-icon"><i class="ti ti-user-search"></i></div>
                        <div class="empty-title">Select a customer</div>
                        <div class="empty-sub">Choose a customer from the left, or create a new one.</div>
                        @if ($canAddCustomer)
                        <button class="btn btn-sm btn-primary mt-3" onclick="newCustomer(); return false;">+ New Customer</button>
                        @endif
                    </div>

                    <!-- View mode (tabs) -->
                    <div id="customerViewContainer" style="display:none;">
                        <div class="customer-tabs">
                            <button class="customer-tab active" data-tab="overview" onclick="switchCustomerTab('overview')">Overview</button>
                            <button class="customer-tab" data-tab="details" onclick="switchCustomerTab('details')">Details</button>
                        </div>

                        <!-- Overview -->
                        <div class="customer-tab-panel active" id="tab-overview">
                            <div class="customer-kpis">
                                <div class="customer-kpi">
                                    <div class="kpi-label">Outstanding</div>
                                    <div class="kpi-value" id="kpiOutstanding">$0.00</div>
                                </div>
                                <div class="kpi-divider"></div>
                                <div class="customer-kpi">
                                    <div class="kpi-label">Estimates</div>
                                    <div class="kpi-value" id="kpiEstimates">$0.00</div>
                                </div>
                            </div>

                            <div class="customer-overview-section">
                                <div class="overview-section-header">
                                    <div class="overview-section-title">Sales</div>
                                </div>
                                <div class="overview-empty">No Records</div>
                            </div>

                            <div class="customer-overview-section">
                                <div class="overview-section-header">
                                    <div class="overview-section-title">Recent Activities</div>
                                </div>
                                <div class="activities-list" id="customerActivities">
                                    <div class="overview-empty">No Records</div>
                                </div>
                            </div>
                        </div>

                        <!-- Details (Read-only view) -->
                        <div class="customer-tab-panel" id="tab-details">
                            <div class="customer-details-wrap">
                                <div class="customer-details-layout">
                                    <div class="details-card">
                                        <div class="details-card-title">Contact Information</div>
                                        <div class="details-rows">
                                            <div class="details-row">
                                                <div class="details-label">Company</div>
                                                <div class="details-value" id="detailsCompany">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Contact Person</div>
                                                <div class="details-value" id="detailsContact">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Email</div>
                                                <div class="details-value" id="detailsEmail">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Business Phone</div>
                                                <div class="details-value" id="detailsPhone">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">VAT</div>
                                                <div class="details-value" id="detailsVat">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Fax</div>
                                                <div class="details-value" id="detailsFax">—</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="details-card">
                                        <div class="details-card-title">Billing Address</div>
                                        <div class="details-address-grid">
                                            <div class="details-row">
                                                <div class="details-label">Street 1</div>
                                                <div class="details-value" id="detailsBillingStreet1">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Street 2</div>
                                                <div class="details-value" id="detailsBillingStreet2">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">City</div>
                                                <div class="details-value" id="detailsBillingCity">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">State</div>
                                                <div class="details-value" id="detailsBillingState">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Zip</div>
                                                <div class="details-value" id="detailsBillingZip">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Country</div>
                                                <div class="details-value" id="detailsBillingCountry">—</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="details-card">
                                        <div class="details-card-title">Shipping Address</div>
                                        <div class="details-address-grid" id="detailsShippingGrid">
                                            <div class="details-row">
                                                <div class="details-label">Street 1</div>
                                                <div class="details-value" id="detailsShippingStreet1">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Street 2</div>
                                                <div class="details-value" id="detailsShippingStreet2">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">City</div>
                                                <div class="details-value" id="detailsShippingCity">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">State</div>
                                                <div class="details-value" id="detailsShippingState">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Zip</div>
                                                <div class="details-value" id="detailsShippingZip">—</div>
                                            </div>
                                            <div class="details-row">
                                                <div class="details-label">Country</div>
                                                <div class="details-value" id="detailsShippingCountry">—</div>
                                            </div>
                                        </div>
                                        <div class="details-same-as" id="detailsShippingSame" style="display:none;">Same as billing</div>
                                    </div>

                                    <div class="details-card details-card-wide">
                                        <div class="details-card-title">Notes</div>
                                        <div class="details-notes-box" id="detailsNotes">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit mode (add / edit) -->
                    <div id="customerEditContainer" style="display:none;">
                        <div class="details-card">
                            <div class="details-card-title">Edit Customer</div>
                            <div class="edit-grid">
                                <div class="edit-field">
                                    <label class="edit-label">Company Name</label>
                                    <input type="text" class="form-control" id="company_name">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Contact First Name</label>
                                    <input type="text" class="form-control" id="first_name">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Contact Last Name</label>
                                    <input type="text" class="form-control" id="last_name">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Email</label>
                                    <input type="email" class="form-control" id="email">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Business Phone</label>
                                    <input type="text" class="form-control" id="phone">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">VAT</label>
                                    <input type="text" class="form-control" id="vat">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Fax</label>
                                    <input type="text" class="form-control" id="fax">
                                </div>
                                <div class="edit-field edit-field-wide">
                                    <label class="edit-label">Notes</label>
                                    <textarea class="form-control" id="notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="details-card mt-3">
                            <div class="details-card-title">Billing Address</div>
                            <div class="edit-grid">
                                <div class="edit-field edit-field-wide">
                                    <label class="edit-label">Street 1</label>
                                    <input type="text" class="form-control" id="billing_street_1">
                                </div>
                                <div class="edit-field edit-field-wide">
                                    <label class="edit-label">Street 2</label>
                                    <input type="text" class="form-control" id="billing_street_2">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">City</label>
                                    <input type="text" class="form-control" id="billing_city">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">State</label>
                                    <input type="text" class="form-control" id="billing_state">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Zip</label>
                                    <input type="text" class="form-control" id="billing_zip">
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Country</label>
                                    <input type="text" class="form-control" id="billing_country">
                                </div>
                            </div>
                        </div>

                        <div class="details-card mt-3">
                            <div class="details-card-title d-flex align-items-center justify-content-between">
                                <span>Shipping Address</span>
                                <label class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" id="shipping_same_as_billing" checked>
                                    <span class="form-check-label">Same as Billing</span>
                                </label>
                            </div>
                            <div id="shippingFields">
                                <div class="edit-grid">
                                    <div class="edit-field edit-field-wide">
                                        <label class="edit-label">Street 1</label>
                                        <input type="text" class="form-control" id="shipping_street_1">
                                    </div>
                                    <div class="edit-field edit-field-wide">
                                        <label class="edit-label">Street 2</label>
                                        <input type="text" class="form-control" id="shipping_street_2">
                                    </div>
                                    <div class="edit-field">
                                        <label class="edit-label">City</label>
                                        <input type="text" class="form-control" id="shipping_city">
                                    </div>
                                    <div class="edit-field">
                                        <label class="edit-label">State</label>
                                        <input type="text" class="form-control" id="shipping_state">
                                    </div>
                                    <div class="edit-field">
                                        <label class="edit-label">Zip</label>
                                        <input type="text" class="form-control" id="shipping_zip">
                                    </div>
                                    <div class="edit-field">
                                        <label class="edit-label">Country</label>
                                        <input type="text" class="form-control" id="shipping_country">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="customer-toasts" id="customerToasts" aria-live="polite" aria-atomic="true"></div>

@push('scripts')
<script>
let customersPage = 1;
let selectedCustomerId = null;
let currentSearch = '';
let lastLoadedCustomer = null;
let customerUiMode = 'empty'; // empty | view | edit
let sortBy = 'name';
let sortDir = 'asc';
let statusFilter = 'all';

function escapeHtml(str) {
  if (str == null) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

$(document).ready(function() {
  loadCustomersList();
  setCustomerUiMode('empty');
  enableDragScroll(document.querySelector('.filters-row'));

  let searchTimer = null;
  $('#customerSearch').on('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      currentSearch = $(this).val();
      customersPage = 1;
      loadCustomersList();
    }, 250);
  });

  $('#customerSortBy').on('change', function() {
    sortBy = $(this).val();
    sortDir = (sortBy === 'created_at') ? $('#customerCreatedSort').val() : 'asc';
    customersPage = 1;
    loadCustomersList();
  });
  $('#customerCreatedSort').on('change', function() {
    if ($('#customerSortBy').val() === 'created_at') {
      sortDir = $(this).val();
      customersPage = 1;
      loadCustomersList();
    }
  });
  $('#customerStatus').on('change', function() {
    statusFilter = $(this).val();
    customersPage = 1;
    loadCustomersList();
  });

  $('#shipping_same_as_billing').on('change', function() {
    const same = $(this).is(':checked');
    $('#shippingFields').toggle(!same);
  });
});

function enableDragScroll(el) {
  if (!el) return;
  let isDown = false;
  let startX = 0;
  let scrollLeft = 0;

  el.addEventListener('mousedown', (e) => {
    // allow select dropdown click to work
    if (e.target && (e.target.tagName === 'SELECT' || e.target.closest('select'))) return;
    isDown = true;
    el.classList.add('is-dragging');
    startX = e.pageX - el.offsetLeft;
    scrollLeft = el.scrollLeft;
  });
  el.addEventListener('mouseleave', () => {
    isDown = false;
    el.classList.remove('is-dragging');
  });
  el.addEventListener('mouseup', () => {
    isDown = false;
    el.classList.remove('is-dragging');
  });
  el.addEventListener('mousemove', (e) => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - el.offsetLeft;
    const walk = (x - startX);
    el.scrollLeft = scrollLeft - walk;
  });
}

function toggleCustomerSearch() {
  const $row = $('#customersSearchRow');
  const showing = $row.is(':visible');
  $row.toggle(!showing);
  if (!showing) {
    $('#customerSearch').trigger('focus');
  } else {
    $('#customerSearch').val('');
    currentSearch = '';
    customersPage = 1;
    loadCustomersList();
  }
}

function setCustomerUiMode(mode) {
  customerUiMode = mode;
  $('#customerEmptyState').toggle(mode === 'empty');
  $('#customerViewContainer').toggle(mode === 'view');
  $('#customerEditContainer').toggle(mode === 'edit');

  $('#btnEditCustomer').toggle(mode === 'view' && !!selectedCustomerId);
  $('#btnDeleteCustomer').toggle((mode === 'view' || mode === 'edit') && !!selectedCustomerId);
  $('#btnSaveCustomer').toggle(mode === 'edit');
  $('#btnCancelCustomer').toggle(mode === 'edit');
}

function switchCustomerTab(tab) {
  $('.customer-tab').removeClass('active');
  $(`.customer-tab[data-tab="${tab}"]`).addClass('active');
  $('.customer-tab-panel').removeClass('active');
  $(`#tab-${tab}`).addClass('active');
}

function cancelCustomerEdit() {
  if (lastLoadedCustomer) {
    fillCustomerForm(lastLoadedCustomer);
    setCustomerUiMode('view');
    switchCustomerTab('overview');
    return;
  }
  resetRightPanel();
  setCustomerUiMode('empty');
}

function startCustomerEdit() {
  if (!selectedCustomerId) return;
  setCustomerUiMode('edit');
}

function loadCustomersList() {
  $.ajax({
    url: '{{ route("customers.list") }}',
    method: 'GET',
    data: {
      page: customersPage,
      search: currentSearch,
      sort_by: sortBy,
      sort_dir: sortDir,
      status: statusFilter,
      _t: Date.now()
    },
    success: function(resp) {
      const customers = Array.isArray(resp.customers) ? resp.customers : [];
      renderCustomersList(customers);
      renderCustomersPagination(resp.pagination);
    },
    error: function(xhr) {
      console.error('Failed to load customers', xhr);
      renderCustomersList([]);
      renderCustomersPagination({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
    }
  });
}

function renderCustomersList(customers) {
  const $list = $('#customersList');
  if (!customers.length) {
    $list.html(`
      <div class="empty-state"> 
        <p>No customers found</p>
      </div>
    `);
    return;
  }

  let html = '';
  customers.forEach(c => {
    const active = selectedCustomerId === c.id ? 'active' : '';
    const title = c.company_name || c.contact_name || '(Untitled)';
    html += `
      <div class="customer-item ${active}" onclick="selectCustomer(this)" data-customer-id="${c.id}">
        <div class="customer-title">${escapeHtml(title)}</div>
      </div>
    `;
  });
  $list.html(html);
}

function renderCustomersPagination(p) {
  if (!p) p = { current_page: 1, last_page: 1, per_page: 25, total: 0 };
  const start = ((p.current_page - 1) * p.per_page) + (p.total ? 1 : 0);
  const end = Math.min(start + p.per_page - 1, p.total);
  $('#customersPaginationInfo').html(`<span>${start}-${end} of ${p.total} Customers</span>`);
}

function clearCustomerEditFields() {
  $('#customer_id').val('');
  const fields = [
    'company_name','vat','fax','first_name','last_name','email','phone',
    'billing_street_1','billing_street_2','billing_city','billing_state','billing_zip','billing_country',
    'shipping_street_1','shipping_street_2','shipping_city','shipping_state','shipping_zip','shipping_country',
    'notes'
  ];
  fields.forEach(f => $('#' + f).val(''));
}

function newCustomer() {
  selectedCustomerId = null;
  lastLoadedCustomer = null;
  $('#customerHeaderName').text('New Customer');
  clearCustomerEditFields();
  $('#shipping_same_as_billing').prop('checked', true);
  $('#shippingFields').hide();
  $('.customer-item').removeClass('active');

  $('#kpiOutstanding').text('$0.00');
  $('#kpiEstimates').text('$0.00');
  $('#customerActivities').html('<div class="overview-empty">No Records</div>');

  $('#detailsCompany').text('—');
  $('#detailsContact').text('—');
  $('#detailsEmail').text('—');
  $('#detailsPhone').text('—');
  $('#detailsVat').text('—');
  $('#detailsFax').text('—');
  $('#detailsBillingStreet1').text('—');
  $('#detailsBillingStreet2').text('—');
  $('#detailsBillingCity').text('—');
  $('#detailsBillingState').text('—');
  $('#detailsBillingZip').text('—');
  $('#detailsBillingCountry').text('—');
  $('#detailsShippingStreet1').text('—');
  $('#detailsShippingStreet2').text('—');
  $('#detailsShippingCity').text('—');
  $('#detailsShippingState').text('—');
  $('#detailsShippingZip').text('—');
  $('#detailsShippingCountry').text('—');
  $('#detailsShippingGrid').show();
  $('#detailsShippingSame').hide();
  $('#detailsNotes').text('—');

  setCustomerUiMode('edit');
}

function resetRightPanel() {
  selectedCustomerId = null;
  lastLoadedCustomer = null;
  $('#customerHeaderName').text('Customers');
  $('#customer_id').val('');
  $('.customer-item').removeClass('active');
}

function selectCustomer(el) {
  const id = parseInt(el.getAttribute('data-customer-id'), 10);
  if (!id) return;
  selectedCustomerId = id;
  $('.customer-item').removeClass('active');
  $(el).addClass('active');

  $.ajax({
    url: `{{ url('customers') }}/${id}`,
    method: 'GET',
    success: function(resp) {
      const c = resp.customer;
      lastLoadedCustomer = c;
      fillCustomerForm(c);
      renderCustomerOverview(c, resp.activities || []);
      renderCustomerDetails(c);
      setCustomerUiMode('view');
      switchCustomerTab('overview');
    },
    error: function(xhr) {
      console.error('Failed to load customer', xhr);
      toast('error', xhr.responseJSON?.message || 'Failed to load customer');
    }
  });
}

function fillCustomerForm(c) {
  $('#customerHeaderName').text(c.company_name || (c.first_name ? (c.first_name + ' ' + (c.last_name || '')) : 'Customer'));
  $('#customer_id').val(c.id);

  const fields = [
    'company_name','vat','fax','first_name','last_name','email','phone',
    'billing_street_1','billing_street_2','billing_city','billing_state','billing_zip','billing_country',
    'shipping_street_1','shipping_street_2','shipping_city','shipping_state','shipping_zip','shipping_country',
    'notes'
  ];
  fields.forEach(f => $('#' + f).val(c[f] ?? ''));

  const same = !!c.shipping_same_as_billing;
  $('#shipping_same_as_billing').prop('checked', same);
  $('#shippingFields').toggle(!same);
}

function renderCustomerDetails(c) {
  const company = c.company_name || (c.first_name ? (c.first_name + ' ' + (c.last_name || '')) : '—');
  $('#detailsCompany').text(company || '—');
  $('#detailsPhone').text(c.phone || '—');
  $('#detailsEmail').text(c.email || '—');
  $('#detailsVat').text(c.vat || '—');
  $('#detailsFax').text(c.fax || '—');

  const contact = [c.first_name, c.last_name].filter(Boolean).join(' ').trim();
  $('#detailsContact').text(contact || '—');

  const notes = (c.notes || '').trim();
  $('#detailsNotes').text(notes ? notes : '—');

  $('#detailsBillingStreet1').text(c.billing_street_1 || '—');
  $('#detailsBillingStreet2').text(c.billing_street_2 || '—');
  $('#detailsBillingCity').text(c.billing_city || '—');
  $('#detailsBillingState').text(c.billing_state || '—');
  $('#detailsBillingZip').text(c.billing_zip || '—');
  $('#detailsBillingCountry').text(c.billing_country || '—');

  const shippingSame = !!c.shipping_same_as_billing;
  if (shippingSame) {
    $('#detailsShippingGrid').hide();
    $('#detailsShippingSame').show();
    return;
  }

  $('#detailsShippingGrid').show();
  $('#detailsShippingSame').hide();
  $('#detailsShippingStreet1').text(c.shipping_street_1 || '—');
  $('#detailsShippingStreet2').text(c.shipping_street_2 || '—');
  $('#detailsShippingCity').text(c.shipping_city || '—');
  $('#detailsShippingState').text(c.shipping_state || '—');
  $('#detailsShippingZip').text(c.shipping_zip || '—');
  $('#detailsShippingCountry').text(c.shipping_country || '—');
}

function renderCustomerOverview(c, activitiesFromServer) {
  // Placeholder KPIs for now (hook to invoices/estimates later)
  $('#kpiOutstanding').text('$0.00');
  $('#kpiEstimates').text('$0.00');

  const activities = Array.isArray(activitiesFromServer) ? activitiesFromServer : [];
  if (!activities.length) {
    $('#customerActivities').html('<div class="overview-empty">No Records</div>');
    return;
  }

  const iconForAction = (action) => {
    if (action === 'created') return 'ti ti-plus';
    if (action === 'updated') return 'ti ti-pencil';
    if (action === 'deleted') return 'ti ti-trash';
    return 'ti ti-activity';
  };
  const textForAction = (a) => {
    const who = a.user_name ? `<strong>${escapeHtml(a.user_name)}</strong>` : '<strong>System</strong>';
    if (a.action === 'created') return `Customer created by ${who}.`;
    if (a.action === 'updated') return `Customer updated by ${who}.`;
    if (a.action === 'deleted') return `Customer deleted by ${who}.`;
    return `Activity by ${who}.`;
  };
  const dateText = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    return isNaN(d.getTime()) ? '' : d.toLocaleDateString();
  };

  const html = activities.map(a => `
    <div class="activity-row">
      <div class="activity-icon"><i class="${iconForAction(a.action)}"></i></div>
      <div class="activity-main">
        <div class="activity-text">${textForAction(a)}</div>
        ${a.created_at ? `<div class="activity-sub">${escapeHtml(dateText(a.created_at))}</div>` : ``}
      </div>
    </div>
  `).join('');

  $('#customerActivities').html(html);
}

function toast(type, message) {
  const $wrap = $('#customerToasts');
  const id = 't_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
  const cls = type === 'success' ? 'toast-success' : (type === 'error' ? 'toast-error' : 'toast-info');
  const title = type === 'success' ? 'Success' : (type === 'error' ? 'Error' : 'Info');
  const html = `
    <div class="customer-toast ${cls}" id="${id}">
      <div class="toast-title">${escapeHtml(title)}</div>
      <div class="toast-msg">${escapeHtml(message || '')}</div>
    </div>
  `;
  $wrap.append(html);
  setTimeout(() => $('#' + id).addClass('show'), 10);
  setTimeout(() => {
    const $t = $('#' + id);
    $t.removeClass('show');
    setTimeout(() => $t.remove(), 250);
  }, 2800);
}

function payloadFromForm() {
  const same = $('#shipping_same_as_billing').is(':checked');
  return {
    company_name: $('#company_name').val(),
    vat: $('#vat').val(),
    fax: $('#fax').val(),
    first_name: $('#first_name').val(),
    last_name: $('#last_name').val(),
    email: $('#email').val(),
    phone: $('#phone').val(),

    billing_street_1: $('#billing_street_1').val(),
    billing_street_2: $('#billing_street_2').val(),
    billing_city: $('#billing_city').val(),
    billing_state: $('#billing_state').val(),
    billing_zip: $('#billing_zip').val(),
    billing_country: $('#billing_country').val(),

    shipping_same_as_billing: same ? 1 : 0,
    shipping_street_1: same ? null : $('#shipping_street_1').val(),
    shipping_street_2: same ? null : $('#shipping_street_2').val(),
    shipping_city: same ? null : $('#shipping_city').val(),
    shipping_state: same ? null : $('#shipping_state').val(),
    shipping_zip: same ? null : $('#shipping_zip').val(),
    shipping_country: same ? null : $('#shipping_country').val(),

    notes: $('#notes').val(),
  };
}

function saveCustomer() {
  const id = $('#customer_id').val() || (selectedCustomerId ? String(selectedCustomerId) : '');
  const data = payloadFromForm();

  const ajaxOpts = id
    ? { url: `{{ url('customers') }}/${id}`, method: 'PUT' }
    : { url: `{{ url('customers') }}`, method: 'POST' };

  $.ajax({
    ...ajaxOpts,
    data: data,
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    success: function(resp) {
      if (resp && resp.customer_id) {
        selectedCustomerId = resp.customer_id;
      }
      loadCustomersList();
      toast('success', 'Customer saved');

      if (selectedCustomerId) {
        // Reload selected customer in view mode
        $.ajax({
          url: `{{ url('customers') }}/${selectedCustomerId}`,
          method: 'GET',
          success: function(r2) {
            lastLoadedCustomer = r2.customer;
            fillCustomerForm(r2.customer);
            renderCustomerOverview(r2.customer);
            renderCustomerDetails(r2.customer);
            setCustomerUiMode('view');
            switchCustomerTab('overview');
          }
        });
      } else {
        setCustomerUiMode('empty');
      }
    },
    error: function(xhr) {
      console.error('Failed to save customer', xhr);
      toast('error', xhr.responseJSON?.message || 'Failed to save customer');
    }
  });
}

function deleteCustomer() {
  const id = $('#customer_id').val() || (selectedCustomerId ? String(selectedCustomerId) : '');
  if (!id) return;

  let displayName = 'this customer';
  if (lastLoadedCustomer && String(lastLoadedCustomer.id) === String(id)) {
    const c = lastLoadedCustomer;
    displayName = (c.company_name && String(c.company_name).trim())
      || [c.first_name, c.last_name].filter(Boolean).join(' ').trim()
      || 'Customer #' + id;
  }

  Swal.fire({
    title: 'Delete customer?',
    html: 'Are you sure you want to delete <strong>' + escapeHtml(displayName) + '</strong>? This cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel',
    focusCancel: true,
    customClass: {
      confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
      cancelButton: 'btn btn-label-secondary waves-effect waves-light'
    },
    buttonsStyling: false
  }).then(function(result) {
    if (!result.isConfirmed) return;

    $.ajax({
      url: `{{ url('customers') }}/${id}`,
      method: 'DELETE',
      data: { _token: '{{ csrf_token() }}' },
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      success: function(resp) {
        toast('success', 'Customer deleted');
        resetRightPanel();
        setCustomerUiMode('empty');
        loadCustomersList();
      },
      error: function(xhr) {
        console.error('Failed to delete customer', xhr);
        toast('error', xhr.responseJSON?.message || 'Failed to delete customer');
      }
    });
  });
}
</script>
@endpush
@endsection

