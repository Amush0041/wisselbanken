<?php

use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\ColorEffectController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\FinishController;
use App\Http\Controllers\Admin\ManufacturerController;
use App\Http\Controllers\Admin\PaintTypeController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductFileController;
use App\Http\Controllers\Admin\ProductPricingController;
use App\Http\Controllers\Admin\SizeController;
use App\Http\Controllers\Admin\SpecificationController;
use App\Http\Controllers\Admin\ThicknessController;
use App\Http\Controllers\Frontend\BlogController as FrontendBlogController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\PalletProductController;
use App\Http\Controllers\Frontend\FrontendController;
use App\Http\Controllers\Frontend\ListController;
use App\Http\Controllers\Frontend\OrderController;
use App\Http\Controllers\Frontend\OrderApprovalController;
use App\Http\Controllers\Frontend\PlanCrosswalkController;
use App\Http\Controllers\Frontend\RfqController;
use App\Http\Controllers\Frontend\RfqSellerController;
use App\Http\Controllers\Frontend\QuoteController;
use App\Http\Controllers\Frontend\CustomerController;
use App\Http\Controllers\Frontend\UserProductController;
use App\Http\Controllers\Frontend\UserSavedServiceController;
use App\Http\Controllers\Frontend\UserWorkspaceController;
use App\Http\Controllers\Frontend\ProductController as FrontendProductController;
use App\Http\Controllers\Frontend\ProductDetailController;
use App\Http\Controllers\Frontend\ProductFilterDataController;
use App\Http\Controllers\Frontend\ProfileController;
use App\Http\Controllers\Frontend\ServiceController; 
use App\Models\ProductPricing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// use Illuminate\Support\Facades\Artisan;

// Route::get('/run-storage-link', function () {
//     try {
//         Artisan::call('storage:link');
//         return 'Storage link created successfully!';
//     } catch (\Exception $e) {
//         return 'Failed to create storage link: ' . $e->getMessage();
//     }
// });

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
/** Main Page **/
Route::get('/', [FrontendController::class, 'mainPage']);
/** Contact page **/
Route::get('contacts', [FrontendController::class, 'contacts']);
/* Division Product */
Route::get('product-division/{slug}', [FrontendController::class, 'productDivision'])->name('product-division');
/* Manufacturer Product */
Route::get('product-manufacturer/{slug}', [FrontendController::class, 'productManufacturer']);
/* Product */
Route::get('product-manufacturer', [FrontendProductController::class, 'productManufacturer']);
Route::get('product-filter', [FrontendProductController::class, 'productFilter'])->name('product-filter');
Route::get('get-filter-data',[ProductFilterDataController::class,'getFilterData'])->name('get-filter-data');
Route::post('get-products', [FrontendProductController::class, 'getProducts']);
Route::get('product-detail/{slug}', [FrontendProductController::class, 'productDetail']); 
// Add to pallet

Route::post('add-to-pallet', [PalletProductController::class, 'addToPallet'])->name('add-to-pallet'); 
Route::post('mini-pallet', [PalletProductController::class, 'miniPallet'])->name('mini-pallet');
Route::post('update-product-detail-sidebar-pallet', [PalletProductController::class, 'updateProductDetailSidebarPallet'])->name('update-product-detail-sidebar-pallet'); 
Route::post('get-product-variations', [FrontendProductController::class, 'getProductVariations']);  
/** Services urls **/ 
Route::get('service/take-off-estimating-services', [ServiceController::class, 'estimating_service'])->name('estimating-service');
Route::get('service/material-quote-service', [ServiceController::class, 'material_quote'])->name('material-quote-service');
Route::get('service/shop-drawing-service', [ServiceController::class, 'shop_drawing'])->name('shop-drawing-service');
Route::get('service/turnkey-construction-service', [ServiceController::class, 'turnkey_construction'])->name('turnkey-construction-service');
Route::get('service/submital-builder-service', [ServiceController::class, 'submital_builder'])->name('submital-builder-service');
/* Blog Routes */
Route::get('blogs', [FrontendBlogController::class, 'index'])->name('blogs.index');
Route::get('blogs/{slug}', [FrontendBlogController::class, 'show'])->name('blogs.show');
Auth::routes(['verify' => true]);

/* Post-registration onboarding confirmation (auth but not verified required) */
Route::get('register-complete', [\App\Http\Controllers\Auth\RegisterController::class, 'complete'])
    ->name('register.complete')->middleware('auth');

/* Invite-based registration — no email required, token carries org + role */
Route::get('register/invite/{token}', [\App\Http\Controllers\Auth\InviteController::class, 'show'])
    ->name('invite.show')->middleware('guest');
Route::post('register/invite/{token}', [\App\Http\Controllers\Auth\InviteController::class, 'register'])
    ->name('invite.register')->middleware('guest');



Route::get('add-list-pallet/{listid}', [PalletProductController::class, 'addListToPallet']);
Route::prefix('pallet')->group(function() {
Route::get('/', [PalletProductController::class, 'viewPallet'])->name('pallet.view');
Route::post('/add-list/{list}', [PalletProductController::class, 'addListToPallet'])->name('pallet.add-list');
Route::post('/update-item', [PalletProductController::class, 'updatePalletItem'])->name('pallet.update-item');
Route::post('pallet-item-remove', [PalletProductController::class, 'palletItemRemove'])->name('pallet-item-remove');
Route::post('/clear', [PalletProductController::class, 'clearPallet'])->name('pallet.clear');
});
// Add multiple items to cart
Route::post('add-multiple-to-pallet', [PalletProductController::class, 'addMultipleToPallet'])->name('add-multiple-to-pallet');

// Get states for address forms
Route::get('get-states', function() {
    return response()->json(\App\Models\StateTax::orderBy('state')->select('id', 'state')->get());
})->name('get-states');

// Check pallet address (available for both guests and logged-in users)
Route::get('check-pallet-address', [PalletProductController::class, 'checkPalletAddress'])->name('check-pallet-address');

Route::group(['middleware' => ['auth','verified','checkRole:user']], function() {
    Route::get('user-dashboard', [UserWorkspaceController::class, 'index'])->name('user.dashboard');
    Route::view('workspace', 'frontend.workspace')->name('workspace');

    /* Org Switcher — changes the current_org session key */
    Route::post('org/switch', [\App\Http\Controllers\Frontend\OrgSwitchController::class, 'switch'])->name('org.switch');

    /* RBAC org-admin — Overview, Team, Roles, Audit Log, Settings, Delegations, API Tokens */
    Route::get('org-admin', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'index'])->name('org-admin.index');
    Route::get('org-admin/overview', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'overview'])->name('org-admin.overview');
    Route::get('org-admin/my-roles', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'myRoles'])->name('org-admin.my-roles');
    Route::get('org-admin/audit-log', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'auditLog'])->name('org-admin.audit-log');
    Route::post('org-admin/invite', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'generateInvite'])->name('org-admin.invite');
    Route::post('org-admin/roles', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'assignRole'])->name('org-admin.roles.assign');
    Route::post('org-admin/peel-off', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'peelOff'])->name('org-admin.peel-off');
    Route::delete('org-admin/roles/{userOrgRole}', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'removeRole'])->name('org-admin.roles.remove');
    Route::get('org-admin/roles-list', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'rolesList'])->name('org-admin.roles.list');
    Route::put('org-admin/roles-list/permissions', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'updateRolePermission'])->name('org-admin.roles.permissions.update');
    Route::post('org-admin/roles-list/create', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'storeRole'])->name('org-admin.roles.store');
    /* Org Connections (org-to-org relationships — plan §4.5) */
    Route::get('org-admin/connections', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'connections'])->name('org-admin.connections.index');
    Route::post('org-admin/connections', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'storeConnection'])->name('org-admin.connections.store');
    Route::delete('org-admin/connections/{orgRelationship}', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'destroyConnection'])->name('org-admin.connections.destroy');
    /* Project Members (quote-scoped access — plan §3.5) */
    Route::get('org-admin/projects', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'projects'])->name('org-admin.projects.index');
    Route::post('org-admin/projects/members', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'addProjectMember'])->name('org-admin.projects.members.store');
    Route::delete('org-admin/projects/members/{projectMember}', [\App\Http\Controllers\Frontend\OrgAdminController::class, 'removeProjectMember'])->name('org-admin.projects.members.destroy');
    /* Projects (entity CRUD, members, page — doc §6.5) */
    Route::get('projects', [\App\Http\Controllers\Frontend\ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/list', [\App\Http\Controllers\Frontend\ProjectController::class, 'list'])->name('projects.list');
    Route::post('projects', [\App\Http\Controllers\Frontend\ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [\App\Http\Controllers\Frontend\ProjectWorkspaceController::class, 'show'])->name('projects.show');
    Route::put('projects/{project}', [\App\Http\Controllers\Frontend\ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [\App\Http\Controllers\Frontend\ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('projects/{project}/members', [\App\Http\Controllers\Frontend\ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::delete('projects/{project}/members/{projectMember}', [\App\Http\Controllers\Frontend\ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
    Route::post('projects/{project}/quotes', [QuoteController::class, 'store'])->name('projects.quotes.store');
    Route::post('projects/{project}/quotes/create-from-list/{listId}', [QuoteController::class, 'createFromList'])->name('projects.quotes.create-from-list');
    Route::post('projects/{project}/crosswalk', [PlanCrosswalkController::class, 'store'])->name('projects.crosswalk.store');
    Route::get('projects/{quote}/workspace', [\App\Http\Controllers\Frontend\ProjectWorkspaceController::class, 'legacyRedirect'])->name('project.workspace');
    /* Org Settings */
    Route::get('org-admin/settings', [\App\Http\Controllers\Frontend\OrgSettingsController::class, 'index'])->name('org-admin.settings');
    Route::post('org-admin/settings', [\App\Http\Controllers\Frontend\OrgSettingsController::class, 'update'])->name('org-admin.settings.update');
    Route::delete('org-admin/settings/delete', [\App\Http\Controllers\Frontend\OrgSettingsController::class, 'destroy'])->name('org-admin.settings.destroy');
    Route::post('org-admin/settings/transfer', [\App\Http\Controllers\Frontend\OrgSettingsController::class, 'transferOwnership'])->name('org-admin.settings.transfer');
    /* Delegations */
    Route::get('org-admin/delegations', [\App\Http\Controllers\Frontend\DelegationController::class, 'index'])->name('org-admin.delegations.index');
    Route::post('org-admin/delegations', [\App\Http\Controllers\Frontend\DelegationController::class, 'store'])->name('org-admin.delegations.store');
    Route::delete('org-admin/delegations/{delegation}', [\App\Http\Controllers\Frontend\DelegationController::class, 'destroy'])->name('org-admin.delegations.destroy');
    /* API Tokens */
    Route::get('org-admin/api-tokens', [\App\Http\Controllers\Frontend\ApiTokenController::class, 'index'])->name('org-admin.api-tokens.index');
    Route::post('org-admin/api-tokens', [\App\Http\Controllers\Frontend\ApiTokenController::class, 'store'])->name('org-admin.api-tokens.store');
    Route::delete('org-admin/api-tokens/{apiToken}', [\App\Http\Controllers\Frontend\ApiTokenController::class, 'destroy'])->name('org-admin.api-tokens.destroy');
    Route::get('view-lists', [ListController::class, 'viewLists'])->name('view-lists');
    Route::post('remove-list/{id}', [ListController::class, 'removList'])->name('remove-lists');
    Route::get('list-view/{id}/{slug}', [ListController::class, 'viewListDetail'])->name('list-detail');
    Route::post('list-detail-data/{id}',[ListController::class,'listDetailData'])->name('list-detail-data');
    Route::post('remove-lists-items/{id}', [ListController::class, 'removListItem'])->name('remove-lists-items');
    Route::post('update-list-item-quantity', [ListController::class, 'updateListItemQuantity'])->name('update-list-item-quantity');
    Route::post('update-list-info/{id}', [ListController::class, 'updateListInfo'])->name('update-list-info');
    Route::get('get-saved-lists', [PalletProductController::class, 'getSavedLists'])->name('get-saved-lists');
    Route::post('save-shopping-list', [PalletProductController::class, 'saveShoppingList'])->name('save-shopping-list');
    Route::post('save-shopping-list-product-detail', [PalletProductController::class, 'saveShoppingListProductDetail'])->name('save-shopping-list-product-detail');
    
    // Get list count for navbar
    Route::get('get-list-count', [PalletProductController::class, 'getListCount'])->name('get-list-count');
Route::get('get-pallet-checkout-data', [PalletProductController::class, 'getPalletDataForCheckout'])->name('get-pallet-checkout-data');
    
    // Migrate session pallet to database after login
    Route::post('migrate-session-pallet', [PalletProductController::class, 'migrateSessionToDatabase'])->name('migrate-session-pallet');

    /* Quote/Estimate Routes */
    Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('quotes/list', [QuoteController::class, 'getEstimatesList'])->name('quotes.list');
    Route::get('quotes/customers', [QuoteController::class, 'getCustomersForEstimate'])->name('quotes.customers');
    Route::get('quotes/product-variations', [QuoteController::class, 'getProductVariationsForEstimate'])->name('quotes.product-variations');
    Route::get('quotes/services', [QuoteController::class, 'getServicesForEstimate'])->name('quotes.services');
    Route::get('quotes/{id}/details', [QuoteController::class, 'getEstimateDetails'])->name('quotes.details');
    Route::put('quotes/{id}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::put('quotes/{id}/editor', [QuoteController::class, 'saveEditor'])->name('quotes.save-editor');
    Route::put('quotes/{quoteId}/items/{itemId}', [QuoteController::class, 'updateItem'])->name('quotes.update-item');
    Route::delete('quotes/{quoteId}/items/{itemId}', [QuoteController::class, 'destroyItem'])->name('quotes.destroy-item');
    Route::delete('quotes/{id}', [QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::post('quotes/{id}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
    Route::get('quotes/{id}/pdf-preview', [QuoteController::class, 'previewPDF'])->name('quotes.pdf-preview');
    Route::get('quotes/{id}/pdf', [QuoteController::class, 'generatePDF'])->name('quotes.pdf');

    /* Customers */
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/list', [CustomerController::class, 'list'])->name('customers.list');
    Route::get('customers/{id}', [CustomerController::class, 'show'])->name('customers.show');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('customers/{id}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    /* User catalog (from saved estimates) */
    Route::get('user-products', [UserProductController::class, 'index'])->name('user-products.index');
    Route::get('user-products/list', [UserProductController::class, 'list'])->name('user-products.list');
    Route::get('user-products/{id}', [UserProductController::class, 'show'])->name('user-products.show');
    Route::post('user-products', [UserProductController::class, 'store'])->name('user-products.store');
    Route::put('user-products/{id}', [UserProductController::class, 'update'])->name('user-products.update');
    Route::post('user-products/{id}/duplicate', [UserProductController::class, 'duplicate'])->name('user-products.duplicate');
    Route::post('user-products/{id}/archive', [UserProductController::class, 'archive'])->name('user-products.archive');
    Route::post('user-products/{id}/variation', [UserProductController::class, 'addVariation'])->name('user-products.variation');
    Route::delete('user-products/{id}', [UserProductController::class, 'destroy'])->name('user-products.destroy');

    Route::get('user-services', [UserSavedServiceController::class, 'index'])->name('user-services.index');
    Route::get('user-services/list', [UserSavedServiceController::class, 'list'])->name('user-services.list');
    Route::get('user-services/{id}', [UserSavedServiceController::class, 'show'])->name('user-services.show');
    Route::post('user-services', [UserSavedServiceController::class, 'store'])->name('user-services.store');
    Route::put('user-services/{id}', [UserSavedServiceController::class, 'update'])->name('user-services.update');
    Route::delete('user-services/{id}', [UserSavedServiceController::class, 'destroy'])->name('user-services.destroy');


    // checkout
    Route::get('checkout', [CheckoutController::class, 'checkout'])->name('checkout');
    Route::post('/checkout/process', [CheckoutController::class, 'processCheckout'])->name('checkout.process');
    Route::get('/checkout/success/{order}', [CheckoutController::class, 'checkoutSuccess'])->name('checkout.success');
     
    // orders
    Route::get('view-orders', [OrderController::class, 'viewOrders'])->name('view.orders');
    Route::get('order-detail/{id}', [OrderController::class, 'orderDetail'])->name('order.details');

    // order approvals (approval_authority:A — doc §6.2)
    Route::get('order-approvals', [OrderApprovalController::class, 'index'])->name('order.approvals');
    Route::post('orders/{order}/approve', [OrderApprovalController::class, 'approve'])->name('orders.approve');
    Route::post('orders/{order}/reject', [OrderApprovalController::class, 'reject'])->name('orders.reject');

    // RFQ — buyer flow (doc §6.6)
    Route::get('rfq', [RfqController::class, 'index'])->name('rfq.index');
    Route::get('rfq/create', [RfqController::class, 'create'])->name('rfq.create');
    Route::post('rfq', [RfqController::class, 'store'])->name('rfq.store');
    Route::get('rfq/{rfq}', [RfqController::class, 'show'])->name('rfq.show');
    Route::post('rfq/{rfq}/responses/{response}/select', [RfqController::class, 'selectResponse'])->name('rfq.response.select');
    Route::post('rfq/{rfq}/convert', [RfqController::class, 'convertToOrder'])->name('rfq.convert');

    // RFQ — seller flow (doc §6.6)
    Route::get('rfq-incoming', [RfqSellerController::class, 'incoming'])->name('rfq.seller.incoming');
    Route::post('rfq/{rfq}/respond', [RfqSellerController::class, 'respond'])->name('rfq.seller.respond');
    Route::post('rfq/{rfq}/decline', [RfqSellerController::class, 'decline'])->name('rfq.seller.decline');

    // Plan crosswalk (doc §4.6)
    Route::get('plan-crosswalk', [PlanCrosswalkController::class, 'index'])->name('plan-crosswalk.index');
    Route::put('plan-crosswalk/{planCrosswalk}', [PlanCrosswalkController::class, 'update'])->name('plan-crosswalk.update');
    Route::delete('plan-crosswalk/{planCrosswalk}', [PlanCrosswalkController::class, 'destroy'])->name('plan-crosswalk.destroy');

   
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

});


/* Admin Route */

Route::middleware(['auth','verified','checkRole:admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('file/import', [DashboardController::class, 'importFile'])->name('files.import');

    /* RBAC platform-admin management */
    Route::controller(\App\Http\Controllers\Admin\RbacController::class)->prefix('rbac')->name('admin.rbac.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('roles', 'roles')->name('roles');
        Route::post('roles/{role}/permissions', 'updatePermission')->name('roles.permission');
        Route::get('users/search', 'searchUsers')->name('users.search');
        Route::get('organizations', 'organizations')->name('organizations');
        Route::get('organizations/{organization}', 'showOrganization')->name('organizations.show');
        Route::delete('organizations/{organization}', 'destroyOrganization')->name('organizations.destroy');
        Route::post('organizations/{organization}/roles', 'assignRole')->name('roles.assign');
        Route::delete('user-org-roles/{userOrgRole}', 'removeRole')->name('roles.remove');
        Route::get('audit-logs', 'auditLogs')->name('audit-logs');
        Route::get('enforcement', 'enforcement')->name('enforcement');
        Route::post('enforcement/toggle-mode', 'toggleMode')->name('enforcement.toggle-mode');
        Route::post('enforcement/orgs', 'updateEnforcedOrgs')->name('enforcement.orgs');
Route::get('users', 'users')->name('users');
        Route::post('roles/create', 'storeRole')->name('roles.create');
        Route::get('delegations-overview', 'adminDelegations')->name('delegations');
        Route::delete('delegations/{delegation}', 'revokeAdminDelegation')->name('delegations.revoke');
        Route::get('service-accounts', 'serviceAccounts')->name('service-accounts');
        Route::delete('service-accounts/{apiToken}', 'revokeAdminToken')->name('service-accounts.revoke');
        Route::get('sod', 'sod')->name('sod');
        Route::post('sod', 'storeSod')->name('sod.store');
        Route::post('sod/{sodConflictRule}/toggle', 'toggleSod')->name('sod.toggle');
        Route::delete('sod/{sodConflictRule}', 'destroySod')->name('sod.destroy');
        Route::delete('users/{user}', 'destroyUser')->name('users.destroy');
    });
    /* Division Route */
    Route::resource('divisions', DivisionController::class);
    Route::post('divisions/import', [DivisionController::class, 'importDivision'])->name('divisions.import');
    /* Specification Route */
    Route::resource('manufacturers', ManufacturerController::class);  
    /* Specification Route */
    Route::resource('specifications', SpecificationController::class);  
    /* Size Route */
    Route::resource('sizes', SizeController::class);
    /* Thickness Route */
    Route::resource('thicknesses', ThicknessController::class);
    /* Finish Route */
    Route::resource('finishes', FinishController::class);
    /* Paint Type Route */
    Route::resource('paint-types', PaintTypeController::class);
    /* Color Route */
    Route::resource('colors', ColorController::class);
    /* Color Effect Route */
    Route::resource('color-effects', ColorEffectController::class);
    /* Product Route */
    Route::resource('products', ProductController::class);
    Route::post('product/fetch-specification', [ProductController::class, 'fetchSpecification']);
    Route::post('product/fetch-material-types', [ProductController::class, 'fetchMaterialType']); 
    Route::post('product/fetch-colors', [ProductController::class, 'fetchColors']);
    Route::post('products/import', [ProductController::class, 'productImport'])->name('products.import');
    /* Product File upload */
    Route::resource('product-files', ProductFileController::class);

    /* Product Pricing */
    Route::resource('product-pricing', ProductPricingController::class);
    Route::post('get-manfacturer-products',[ProductPricingController::class, 'getManfacturerProducts'])->name('get-manfacturer-products');

    Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/orders/{order}', [App\Http\Controllers\Admin\OrderController::class, 'show'])->name('admin.orders.show');
    /* Blog Route */
    Route::resource('blogs', BlogController::class)->names([
        'index' => 'admin.blogs.index',
        'create' => 'admin.blogs.create',
        'store' => 'admin.blogs.store',
        'show' => 'admin.blogs.show',
        'edit' => 'admin.blogs.edit',
        'update' => 'admin.blogs.update',
        'destroy' => 'admin.blogs.destroy',
    ]);
});

