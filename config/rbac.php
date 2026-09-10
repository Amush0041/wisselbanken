<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enforcement mode
    |--------------------------------------------------------------------------
    | 'audit'   — the middleware logs what it WOULD have blocked but lets every
    |             request through unchanged (Phase 2 — zero user impact).
    | 'enforce' — the middleware returns 403 on a failed check (Phase 3).
    |
    | Kept in env so the switch from audit → enforce is a config change, never a
    | code change.
    */
    'mode' => env('RBAC_MODE', 'audit'),

    /*
    |--------------------------------------------------------------------------
    | Enforcement batches (Phase 3)
    |--------------------------------------------------------------------------
    | When mode = 'enforce', only routes whose batch is listed here are actually
    | blocked; everything else still runs in audit mode. This is how enforcement
    | is rolled out in safe, risk-ordered batches (admin → read → write → approve).
    | An empty list with mode 'enforce' behaves like full enforcement.
    */
    'enforce_batches' => array_filter(explode(',', (string) env('RBAC_ENFORCE_BATCHES', ''))),

    /*
    |--------------------------------------------------------------------------
    | Current-organization session key
    |--------------------------------------------------------------------------
    | A user may belong to several organizations. This session key holds the org
    | the user is currently acting in; the middleware falls back to the user's
    | first active org when it is not set.
    */
    'current_org_session_key' => 'rbac_current_org_id',

    /*
    |--------------------------------------------------------------------------
    | Registration onboarding bundles (plan §6.1)
    |--------------------------------------------------------------------------
    | At registration the org type + team size decide which starting roles are
    | auto-assigned, so a solo operator does not have to self-assign 4-5 roles on
    | day one. Team sizes not in `small_team_sizes` are treated as "larger teams"
    | and get the owner-only bundle.
    */
    'team_sizes' => ['solo', '2-5', '6-20', '20+'],

    'small_team_sizes' => ['solo', '2-5'],

    'onboarding' => [
        // org_type slug => bundle key (only applied for small teams)
        'buyer_org_types' => ['subcontractor', 'general_contractor', 'owner'],
        'manufacturer_org_types' => ['manufacturer', 'distributor_stocking', 'distributor_non_stocking'],

        'bundles' => [
            'buyer_small' => [
                'organization_owner',
                'procurement_manager',
                'estimator',
                'executive_approver',
            ],
            'manufacturer_small' => [
                'manufacturer_admin',
                'product_manager',
                'catalog_data_steward',
            ],
            // Larger teams (any type), and any small team that is neither buyer nor
            // manufacturer: the owner is created and assigns the rest manually.
            'owner_only' => [
                'organization_owner',
            ],
        ],
    ],

];
