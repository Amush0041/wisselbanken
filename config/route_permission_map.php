<?php

/*
|--------------------------------------------------------------------------
| Route → permission map
|--------------------------------------------------------------------------
| Maps every authenticated route to: permission_group_slug, required_level,
| enforcement batch, and (optionally) the route param that carries a project id.
|
| Key   : "METHOD uri" — exact HTTP verb + Laravel route URI (no leading slash,
|          params shown as {param} matching the route definition exactly).
|          Use "* uri" only when ALL verbs on that URI share the same level.
| Value : [permission_group_slug, required_level, 'batch' => batch, 'project_param' => param]
|          or [..., 'quote_param' => param]  (never both)
|          - batch: 'admin' | 'read' | 'write' | 'approve'  (Phase 3 enforcement order)
|          - project_param: name of the route param holding a projects.id
|          - quote_param: name of the route param holding a quotes.id (resolved to its project)
|
| Anything not listed is ignored by RbacAudit (no check, passes through).
| Routes guarded only by the legacy checkRole:admin are still listed here so
| audit-mode logs cover the full application surface.
|
| Access level hierarchy: F > A > O > S > R
|   F  Full     create, read, update, delete
|   A  Approve  approve actions submitted by others
|   O  Own      act only on own / org records
|   S  Submit   create or request, cannot approve
|   R  Read     view only
|
| Enforcement batches (Phase 3 rollout order, lowest → highest risk):
|   admin   platform-admin management operations
|   read    read-only data fetches
|   write   create / submit / update operations
|   approve approve / delete operations
*/

return [

    // =========================================================================
    // USER WORKSPACE
    // =========================================================================

    // user-dashboard and workspace are accessible to any authenticated org member —
    // no permission entry here means RbacAudit passes them through unconditionally.
    // org-admin/my-roles is intentionally open to every authenticated org member
    // (self-scope only: it lists the caller's own roles and delegations).

    // =========================================================================
    // ORG ADMIN — member & role management (plan §6.3 peel-off)
    // =========================================================================

    // Pages — read (any org member with user_management:R can see team dashboard)
    'GET org-admin'                               => ['user_management', 'R',  'batch' => 'read'],
    'GET org-admin/overview'                      => ['user_management', 'R',  'batch' => 'read'],
    'GET org-admin/roles-list'                    => ['user_management', 'R',  'batch' => 'read'],
    'GET org-admin/audit-log'                     => ['audit_and_logging', 'R', 'batch' => 'read'],
    'GET org-admin/settings'                      => ['organization_management', 'R', 'batch' => 'read'],

    // Role assignment & peel-off
    'POST org-admin/roles'                        => ['user_management', 'F',  'batch' => 'admin'],
    'POST org-admin/peel-off'                     => ['user_management', 'F',  'batch' => 'admin'],
    'DELETE org-admin/roles/{userOrgRole}'        => ['user_management', 'F',  'batch' => 'admin'],

    // Invite new member
    'POST org-admin/invite'                       => ['user_management', 'F',  'batch' => 'admin'],

    // Role designer (write/admin — editing platform-managed matrix)
    'PUT org-admin/roles-list/permissions'        => ['user_management', 'F',  'batch' => 'admin'],
    'POST org-admin/roles-list/create'            => ['user_management', 'F',  'batch' => 'admin'],

    // Org settings — update & destroy
    'POST org-admin/settings'                     => ['organization_management', 'F', 'batch' => 'write'],
    'POST org-admin/settings/transfer'            => ['user_management', 'F',  'batch' => 'admin'],
    'DELETE org-admin/settings/delete'            => ['user_management', 'F',  'batch' => 'admin'],

    // Delegations (plan §5.4)
    'GET org-admin/delegations'                   => ['delegation_and_impersonation', 'R', 'batch' => 'read'],
    'POST org-admin/delegations'                  => ['delegation_and_impersonation', 'O', 'batch' => 'write'],
    'DELETE org-admin/delegations/{delegation}'   => ['delegation_and_impersonation', 'O', 'batch' => 'approve'],

    // API tokens for service accounts (plan §4.3)
    'GET org-admin/api-tokens'                    => ['delegation_and_impersonation', 'R', 'batch' => 'read'],
    'POST org-admin/api-tokens'                   => ['delegation_and_impersonation', 'F', 'batch' => 'admin'],
    'DELETE org-admin/api-tokens/{apiToken}'      => ['delegation_and_impersonation', 'F', 'batch' => 'admin'],

    // Org Connections (plan §4.5)
    'GET org-admin/connections'                         => ['organization_management', 'R', 'batch' => 'read'],
    'POST org-admin/connections'                        => ['organization_management', 'F', 'batch' => 'admin'],
    'DELETE org-admin/connections/{orgRelationship}'    => ['organization_management', 'F', 'batch' => 'admin'],

    // Project Members (plan §3.5)
    'GET org-admin/projects'                            => ['project_management', 'R', 'batch' => 'read'],
    'POST org-admin/projects/members'                   => ['project_management', 'F', 'batch' => 'admin'],
    'DELETE org-admin/projects/members/{projectMember}' => ['project_management', 'F', 'batch' => 'admin'],
    'GET projects/{quote}/workspace'                    => ['estimate_management', 'R', 'batch' => 'read', 'quote_param' => 'quote'],

    // Projects (entity CRUD and membership)
    'GET projects'                                      => ['project_management', 'R', 'batch' => 'read'],
    'GET projects/list'                                 => ['project_management', 'R', 'batch' => 'read'],
    'GET projects/{project}'                            => ['project_management', 'R', 'batch' => 'read',    'project_param' => 'project'],
    'POST projects'                                     => ['project_management', 'S', 'batch' => 'write'],
    'PUT projects/{project}'                            => ['project_management', 'O', 'batch' => 'write',   'project_param' => 'project'],
    'DELETE projects/{project}'                         => ['project_management', 'F', 'batch' => 'approve', 'project_param' => 'project'],
    'POST projects/{project}/members'                   => ['project_management', 'F', 'batch' => 'admin',   'project_param' => 'project'],
    'DELETE projects/{project}/members/{projectMember}' => ['project_management', 'F', 'batch' => 'admin',   'project_param' => 'project'],
    'POST projects/{project}/quotes'                    => ['estimate_management', 'S', 'batch' => 'write',  'project_param' => 'project'],
    'POST projects/{project}/quotes/create-from-list/{listId}' => ['estimate_management', 'S', 'batch' => 'write', 'project_param' => 'project'],
    'POST projects/{project}/crosswalk'                 => ['estimate_management', 'F', 'batch' => 'write',  'project_param' => 'project'],

    // =========================================================================
    // SAVED LISTS
    // =========================================================================

    'GET view-lists'                          => ['project_management', 'R', 'batch' => 'read'],
    'GET get-saved-lists'                     => ['project_management', 'R', 'batch' => 'read'],
    // get-list-count is a navbar badge call fired on every page — not gated so it
    // never triggers a denial popup for users without project_management:R.
    'GET list-view/{id}/{slug}'               => ['project_management', 'R', 'batch' => 'read'],
    'POST list-detail-data/{id}'              => ['project_management', 'R', 'batch' => 'read'],
    'POST save-shopping-list'                 => ['project_management', 'S', 'batch' => 'write'],
    'POST save-shopping-list-product-detail'  => ['project_management', 'S', 'batch' => 'write'],
    'POST update-list-info/{id}'              => ['project_management', 'O', 'batch' => 'write'],
    'POST update-list-item-quantity'          => ['project_management', 'O', 'batch' => 'write'],
    'POST remove-list/{id}'                   => ['project_management', 'O', 'batch' => 'write'],
    'POST remove-lists-items/{id}'            => ['project_management', 'O', 'batch' => 'write'],

    // =========================================================================
    // QUOTES / ESTIMATES  (plan §6.6, project-scoped)
    // =========================================================================

    // Read — display & data-fetch
    'GET quotes'                              => ['estimate_management', 'R', 'batch' => 'read'],
    'GET quotes/list'                         => ['estimate_management', 'R', 'batch' => 'read'],
    'GET quotes/customers'                    => ['estimate_management', 'R', 'batch' => 'read'],
    'GET quotes/product-variations'           => ['estimate_management', 'R', 'batch' => 'read'],
    'GET quotes/services'                     => ['estimate_management', 'R', 'batch' => 'read'],
    'GET quotes/{id}/details'                 => ['estimate_management', 'R', 'batch' => 'read',    'quote_param' => 'id'],
    'GET quotes/{id}/pdf-preview'             => ['estimate_management', 'R', 'batch' => 'read',    'quote_param' => 'id'],
    'GET quotes/{id}/pdf'                     => ['estimate_management', 'R', 'batch' => 'read',    'quote_param' => 'id'],

    // Submit — create new estimates (S: can create, cannot approve)
    'POST quotes/{id}/duplicate'              => ['estimate_management', 'O', 'batch' => 'write',   'quote_param' => 'id'],

    // Own — edit existing estimates the user owns
    'PUT quotes/{id}'                         => ['estimate_management', 'O', 'batch' => 'write',   'quote_param' => 'id'],
    'PUT quotes/{id}/editor'                  => ['estimate_management', 'O', 'batch' => 'write',   'quote_param' => 'id'],
    'PUT quotes/{quoteId}/items/{itemId}'     => ['estimate_management', 'O', 'batch' => 'write',   'quote_param' => 'quoteId'],

    // Full — delete estimates and line items (approve-batch: highest stakes)
    'DELETE quotes/{id}'                      => ['estimate_management', 'F', 'batch' => 'approve', 'quote_param' => 'id'],
    'DELETE quotes/{quoteId}/items/{itemId}'  => ['estimate_management', 'F', 'batch' => 'approve', 'quote_param' => 'quoteId'],

    // =========================================================================
    // CUSTOMERS  (org-scoped buyer data)
    // =========================================================================

    'GET customers'        => ['quote_rfq_management', 'R', 'batch' => 'read'],
    'GET customers/list'   => ['quote_rfq_management', 'R', 'batch' => 'read'],
    'GET customers/{id}'   => ['quote_rfq_management', 'R', 'batch' => 'read'],
    'POST customers'       => ['user_management', 'S', 'batch' => 'write'],
    'PUT customers/{id}'   => ['user_management', 'O', 'batch' => 'write'],
    'DELETE customers/{id}'=> ['user_management', 'F', 'batch' => 'approve'],

    // =========================================================================
    // USER CATALOG — custom products & services
    // =========================================================================

    'GET user-products'                     => ['product_management', 'R', 'batch' => 'read'],
    'GET user-products/list'                => ['product_management', 'R', 'batch' => 'read'],
    'GET user-products/{id}'                => ['product_management', 'R', 'batch' => 'read'],
    'POST user-products'                    => ['product_management', 'S', 'batch' => 'write'],
    'PUT user-products/{id}'                => ['product_management', 'O', 'batch' => 'write'],
    'POST user-products/{id}/duplicate'     => ['product_management', 'O', 'batch' => 'write'],
    'POST user-products/{id}/archive'       => ['product_management', 'O', 'batch' => 'write'],
    'POST user-products/{id}/variation'     => ['product_management', 'O', 'batch' => 'write'],
    'DELETE user-products/{id}'             => ['product_management', 'F', 'batch' => 'approve'],

    'GET user-services'         => ['product_management', 'R', 'batch' => 'read'],
    'GET user-services/list'    => ['product_management', 'R', 'batch' => 'read'],
    'GET user-services/{id}'    => ['product_management', 'R', 'batch' => 'read'],
    'POST user-services'        => ['product_management', 'S', 'batch' => 'write'],
    'PUT user-services/{id}'    => ['product_management', 'O', 'batch' => 'write'],
    'DELETE user-services/{id}' => ['product_management', 'F', 'batch' => 'approve'],

    // =========================================================================
    // PROCUREMENT — checkout & orders  (plan §6.6 Convert to PO)
    // =========================================================================

    'GET get-pallet-checkout-data'  => ['procurement', 'R', 'batch' => 'read'],
    'POST migrate-session-pallet'   => ['procurement', 'S', 'batch' => 'write'],
    'GET checkout'                  => ['procurement', 'R', 'batch' => 'read'],
    'POST checkout/process'         => ['procurement', 'S', 'batch' => 'write'],
    'GET checkout/success/{order}'  => ['procurement', 'R', 'batch' => 'read'],

    // =========================================================================
    // ORDER VIEWING — anyone who can procure can see their own orders
    // =========================================================================

    'GET view-orders'       => ['procurement', 'R', 'batch' => 'read'],
    'GET order-detail/{id}' => ['procurement', 'R', 'batch' => 'read'],

    // =========================================================================
    // ORDER APPROVALS  (approval_authority)
    // =========================================================================

    'GET order-approvals'           => ['approval_authority', 'A', 'batch' => 'approve'],
    'POST orders/{order}/approve'   => ['approval_authority', 'A', 'batch' => 'approve'],
    'POST orders/{order}/reject'    => ['approval_authority', 'A', 'batch' => 'approve'],

    // =========================================================================
    // QUOTE / RFQ MANAGEMENT  (quote_rfq_management)
    // =========================================================================

    // Buyer — read
    'GET rfq'                                           => ['quote_rfq_management', 'R', 'batch' => 'read'],
    'GET rfq/{rfq}'                                     => ['quote_rfq_management', 'R', 'batch' => 'read'],

    // Buyer — submit (send RFQ)
    'GET rfq/create'                                    => ['quote_rfq_management', 'S', 'batch' => 'write'],
    'POST rfq'                                          => ['quote_rfq_management', 'S', 'batch' => 'write'],

    // Buyer — own (select response)
    'POST rfq/{rfq}/responses/{response}/select'        => ['quote_rfq_management', 'O', 'batch' => 'write'],

    // Buyer — full (convert to order)
    'POST rfq/{rfq}/convert'                            => ['quote_rfq_management', 'F', 'batch' => 'write'],

    // Seller — submit (respond / decline)
    'GET rfq-incoming'                                  => ['quote_rfq_management', 'R', 'batch' => 'read'],
    'POST rfq/{rfq}/respond'                            => ['quote_rfq_management', 'S', 'batch' => 'write'],
    'POST rfq/{rfq}/decline'                            => ['quote_rfq_management', 'S', 'batch' => 'write'],

    // =========================================================================
    // PLAN CROSSWALK (doc §4.6)
    // Estimator / PM = F (full CRUD); Procurement / Requisitioner / Exec Approver = R
    // =========================================================================
    'GET plan-crosswalk'                                         => ['estimate_management', 'R',  'batch' => 'read'],
    'PUT plan-crosswalk/{planCrosswalk}'                         => ['estimate_management', 'F',  'batch' => 'write'],
    'DELETE plan-crosswalk/{planCrosswalk}'                      => ['estimate_management', 'F',  'batch' => 'approve'],

    // =========================================================================
    // ADMIN — DASHBOARD & IMPORTS
    // =========================================================================

    'GET admin/dashboard'    => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/file/import' => ['system_administration', 'F', 'batch' => 'admin'],

    // =========================================================================
    // ADMIN — RBAC MANAGEMENT  (system_administration)
    // =========================================================================

    'GET admin/rbac'                                        => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/rbac/roles'                                  => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/rbac/roles/create'                          => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/rbac/roles/{role}/permissions'              => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/rbac/users'                                  => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/rbac/users/search'                           => ['system_administration', 'F', 'batch' => 'admin'],
    'DELETE admin/rbac/users/{user}'                        => ['system_administration', 'F', 'batch' => 'admin'],
    'DELETE admin/rbac/user-org-roles/{userOrgRole}'        => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/rbac/organizations'                          => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/rbac/organizations/{organization}'           => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/rbac/organizations/{organization}/roles'    => ['system_administration', 'F', 'batch' => 'admin'],
    'DELETE admin/rbac/organizations/{organization}'        => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/rbac/audit-logs'                             => ['audit_and_logging',     'F', 'batch' => 'admin'],
    'GET admin/rbac/enforcement'                            => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/rbac/enforcement/toggle-mode'               => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/rbac/enforcement/orgs'                      => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/rbac/delegations-overview'                   => ['delegation_and_impersonation', 'F', 'batch' => 'admin'],
    'DELETE admin/rbac/delegations/{delegation}'            => ['delegation_and_impersonation', 'F', 'batch' => 'admin'],
    'GET admin/rbac/sod'                                    => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/rbac/sod'                                   => ['system_administration', 'F', 'batch' => 'admin'],
    'DELETE admin/rbac/sod/{sodConflictRule}'               => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/rbac/sod/{sodConflictRule}/toggle'          => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/rbac/service-accounts'                       => ['system_administration', 'F', 'batch' => 'admin'],
    'DELETE admin/rbac/service-accounts/{apiToken}'         => ['system_administration', 'F', 'batch' => 'admin'],

    // =========================================================================
    // ADMIN — DIVISIONS  (catalog_taxonomy_and_data_quality)
    // =========================================================================

    'GET admin/divisions'                    => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/divisions/create'             => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/divisions'                   => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/divisions/import'            => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'GET admin/divisions/{division}'         => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/divisions/{division}/edit'    => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PUT admin/divisions/{division}'         => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PATCH admin/divisions/{division}'       => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'DELETE admin/divisions/{division}'      => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'approve'],

    // =========================================================================
    // ADMIN — MANUFACTURERS  (manufacturer_controls)
    // =========================================================================

    'GET admin/manufacturers'                      => ['manufacturer_controls', 'R', 'batch' => 'read'],
    'GET admin/manufacturers/create'               => ['manufacturer_controls', 'F', 'batch' => 'admin'],
    'POST admin/manufacturers'                     => ['manufacturer_controls', 'F', 'batch' => 'admin'],
    'GET admin/manufacturers/{manufacturer}'       => ['manufacturer_controls', 'R', 'batch' => 'read'],
    'GET admin/manufacturers/{manufacturer}/edit'  => ['manufacturer_controls', 'F', 'batch' => 'admin'],
    'PUT admin/manufacturers/{manufacturer}'       => ['manufacturer_controls', 'F', 'batch' => 'admin'],
    'PATCH admin/manufacturers/{manufacturer}'     => ['manufacturer_controls', 'F', 'batch' => 'admin'],
    'DELETE admin/manufacturers/{manufacturer}'    => ['manufacturer_controls', 'F', 'batch' => 'approve'],

    // =========================================================================
    // ADMIN — SPECIFICATIONS, SIZES, THICKNESSES, FINISHES, PAINT TYPES,
    //         COLORS, COLOR EFFECTS  (catalog_taxonomy_and_data_quality)
    // =========================================================================

    'GET admin/specifications'                           => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/specifications/create'                    => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/specifications'                          => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'GET admin/specifications/{specification}'           => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/specifications/{specification}/edit'      => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PUT admin/specifications/{specification}'           => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PATCH admin/specifications/{specification}'         => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'DELETE admin/specifications/{specification}'        => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'approve'],

    'GET admin/sizes'                => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/sizes/create'         => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/sizes'               => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'GET admin/sizes/{size}'         => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/sizes/{size}/edit'    => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PUT admin/sizes/{size}'         => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PATCH admin/sizes/{size}'       => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'DELETE admin/sizes/{size}'      => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'approve'],

    'GET admin/thicknesses'                  => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/thicknesses/create'           => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/thicknesses'                 => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'GET admin/thicknesses/{thickness}'      => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/thicknesses/{thickness}/edit' => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PUT admin/thicknesses/{thickness}'      => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PATCH admin/thicknesses/{thickness}'    => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'DELETE admin/thicknesses/{thickness}'   => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'approve'],

    'GET admin/finishes'               => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/finishes/create'        => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/finishes'              => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'GET admin/finishes/{finish}'      => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/finishes/{finish}/edit' => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PUT admin/finishes/{finish}'      => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PATCH admin/finishes/{finish}'    => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'DELETE admin/finishes/{finish}'   => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'approve'],

    'GET admin/paint-types'                    => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/paint-types/create'             => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/paint-types'                   => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'GET admin/paint-types/{paint_type}'       => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/paint-types/{paint_type}/edit'  => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PUT admin/paint-types/{paint_type}'       => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PATCH admin/paint-types/{paint_type}'     => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'DELETE admin/paint-types/{paint_type}'    => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'approve'],

    'GET admin/colors'               => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/colors/create'        => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/colors'              => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'GET admin/colors/{color}'       => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/colors/{color}/edit'  => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PUT admin/colors/{color}'       => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PATCH admin/colors/{color}'     => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'DELETE admin/colors/{color}'    => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'approve'],

    'GET admin/color-effects'                          => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/color-effects/create'                   => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'POST admin/color-effects'                         => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'GET admin/color-effects/{color_effect}'           => ['catalog_taxonomy_and_data_quality', 'R', 'batch' => 'read'],
    'GET admin/color-effects/{color_effect}/edit'      => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PUT admin/color-effects/{color_effect}'           => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'PATCH admin/color-effects/{color_effect}'         => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'admin'],
    'DELETE admin/color-effects/{color_effect}'        => ['catalog_taxonomy_and_data_quality', 'F', 'batch' => 'approve'],

    // =========================================================================
    // ADMIN — PRODUCTS  (product_management)
    // =========================================================================

    'GET admin/products'                    => ['product_management', 'R', 'batch' => 'read'],
    'GET admin/products/create'             => ['product_management', 'F', 'batch' => 'admin'],
    'POST admin/products'                   => ['product_management', 'F', 'batch' => 'admin'],
    'POST admin/products/import'            => ['product_management', 'F', 'batch' => 'admin'],
    'GET admin/products/{product}'          => ['product_management', 'R', 'batch' => 'read'],
    'GET admin/products/{product}/edit'     => ['product_management', 'F', 'batch' => 'admin'],
    'PUT admin/products/{product}'          => ['product_management', 'F', 'batch' => 'admin'],
    'PATCH admin/products/{product}'        => ['product_management', 'F', 'batch' => 'admin'],
    'DELETE admin/products/{product}'       => ['product_management', 'F', 'batch' => 'approve'],

    // AJAX helpers used by the product form
    'POST admin/product/fetch-specification'  => ['product_management', 'R', 'batch' => 'read'],
    'POST admin/product/fetch-material-types' => ['product_management', 'R', 'batch' => 'read'],
    'POST admin/product/fetch-colors'         => ['product_management', 'R', 'batch' => 'read'],

    // =========================================================================
    // ADMIN — PRODUCT FILES  (product_management)
    // =========================================================================

    'GET admin/product-files'                          => ['product_management', 'R', 'batch' => 'read'],
    'GET admin/product-files/create'                   => ['product_management', 'F', 'batch' => 'admin'],
    'POST admin/product-files'                         => ['product_management', 'F', 'batch' => 'admin'],
    'GET admin/product-files/{product_file}'           => ['product_management', 'R', 'batch' => 'read'],
    'GET admin/product-files/{product_file}/edit'      => ['product_management', 'F', 'batch' => 'admin'],
    'PUT admin/product-files/{product_file}'           => ['product_management', 'F', 'batch' => 'admin'],
    'PATCH admin/product-files/{product_file}'         => ['product_management', 'F', 'batch' => 'admin'],
    'DELETE admin/product-files/{product_file}'        => ['product_management', 'F', 'batch' => 'approve'],

    // =========================================================================
    // ADMIN — PRODUCT PRICING  (pricing_management)
    // =========================================================================

    'GET admin/product-pricing'                              => ['pricing_management', 'R', 'batch' => 'read'],
    'GET admin/product-pricing/create'                       => ['pricing_management', 'F', 'batch' => 'admin'],
    'POST admin/product-pricing'                             => ['pricing_management', 'F', 'batch' => 'admin'],
    'POST admin/get-manfacturer-products'                    => ['pricing_management', 'R', 'batch' => 'read'],
    'GET admin/product-pricing/{product_pricing}'            => ['pricing_management', 'R', 'batch' => 'read'],
    'GET admin/product-pricing/{product_pricing}/edit'       => ['pricing_management', 'F', 'batch' => 'admin'],
    'PUT admin/product-pricing/{product_pricing}'            => ['pricing_management', 'F', 'batch' => 'admin'],
    'PATCH admin/product-pricing/{product_pricing}'          => ['pricing_management', 'F', 'batch' => 'admin'],
    'DELETE admin/product-pricing/{product_pricing}'         => ['pricing_management', 'F', 'batch' => 'approve'],

    // =========================================================================
    // ADMIN — ORDERS  (order_fulfillment)
    // =========================================================================

    'GET admin/orders'         => ['order_fulfillment', 'R', 'batch' => 'read'],
    'GET admin/orders/{order}' => ['order_fulfillment', 'R', 'batch' => 'read'],

    // =========================================================================
    // ADMIN — BLOGS  (system_administration)
    // =========================================================================

    'GET admin/blogs'              => ['system_administration', 'R', 'batch' => 'read'],
    'GET admin/blogs/create'       => ['system_administration', 'F', 'batch' => 'admin'],
    'POST admin/blogs'             => ['system_administration', 'F', 'batch' => 'admin'],
    'GET admin/blogs/{blog}'       => ['system_administration', 'R', 'batch' => 'read'],
    'GET admin/blogs/{blog}/edit'  => ['system_administration', 'F', 'batch' => 'admin'],
    'PUT admin/blogs/{blog}'       => ['system_administration', 'F', 'batch' => 'admin'],
    'PATCH admin/blogs/{blog}'     => ['system_administration', 'F', 'batch' => 'admin'],
    'DELETE admin/blogs/{blog}'    => ['system_administration', 'F', 'batch' => 'approve'],

];
