<?php

// Canonical RBAC permission matrix — one row per (role, permission_group) pair.
// Access levels: F (Full) > A (Approve) > O (Own) > S (Submit) > R (Read).
// Only groups a role genuinely uses are listed; absent = no access.
// Groups marked "future" (financial_access, reporting_and_analytics, etc.) are included
// where already appropriate so enforcement can be activated without a re-seed.
return [

    // ─── PLATFORM ────────────────────────────────────────────────────────────

    'platform_super_admin' => [
        'organization_management'          => 'F',
        'user_management'                  => 'F',
        'project_management'               => 'F',
        'estimate_management'              => 'F',
        'quote_rfq_management'             => 'F',
        'procurement'                      => 'F',
        'contract_and_agreement_management'=> 'F',
        'approval_authority'               => 'F',
        'budget_and_cost_control'          => 'F',
        'invoice_and_payment_processing'   => 'F',
        'order_fulfillment'                => 'F',
        'returns_and_rma'                  => 'F',
        'product_management'               => 'F',
        'pricing_management'               => 'F',
        'catalog_taxonomy_and_data_quality'=> 'F',
        'inventory_and_availability'       => 'F',
        'compliance_and_certification'     => 'F',
        'manufacturer_controls'            => 'F',
        'financial_access'                 => 'F',
        'reporting_and_analytics'          => 'F',
        'ai_extraction_review'             => 'F',
        'seller_payout_and_settlement'     => 'F',
        'delegation_and_impersonation'     => 'F',
        'audit_and_logging'                => 'F',
        'system_administration'            => 'F',
    ],

    'platform_operations' => [
        'user_management'                  => 'F',
        'system_administration'            => 'F',
        'audit_and_logging'                => 'F',
        'reporting_and_analytics'          => 'F',
        'ai_extraction_review'             => 'F',
        'delegation_and_impersonation'     => 'O',
        'organization_management'          => 'R',
        'project_management'               => 'R',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'R',
        'procurement'                      => 'R',
        'approval_authority'               => 'R',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'manufacturer_controls'            => 'R',
        'catalog_taxonomy_and_data_quality'=> 'R',
        'order_fulfillment'                => 'R',
        'compliance_and_certification'     => 'R',
    ],

    'platform_support' => [
        'delegation_and_impersonation'     => 'O',
        'user_management'                  => 'R',
        'organization_management'          => 'R',
        'project_management'               => 'R',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'R',
        'procurement'                      => 'R',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'manufacturer_controls'            => 'R',
        'order_fulfillment'                => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'platform_data_reviewer' => [
        'product_management'               => 'R',
        'catalog_taxonomy_and_data_quality'=> 'R',
        'ai_extraction_review'             => 'A',
        'organization_management'          => 'R',
        'user_management'                  => 'R',
        'project_management'               => 'R',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'R',
        'procurement'                      => 'R',
        'pricing_management'               => 'R',
        'manufacturer_controls'            => 'R',
        'order_fulfillment'                => 'R',
        'audit_and_logging'                => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'platform_catalog_admin' => [
        'product_management'               => 'F',
        'catalog_taxonomy_and_data_quality'=> 'F',
        'ai_extraction_review'             => 'A',
        'manufacturer_controls'            => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'audit_and_logging'                => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'platform_compliance_officer' => [
        'compliance_and_certification'     => 'F',
        'audit_and_logging'                => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'platform_finance_billing' => [
        'invoice_and_payment_processing'   => 'F',
        'financial_access'                 => 'F',
        'seller_payout_and_settlement'     => 'F',
        'organization_management'          => 'R',
        'audit_and_logging'                => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    // ─── CROSS-FUNCTIONAL ────────────────────────────────────────────────────

    'api_system' => [
        'order_fulfillment'                => 'F',
        'product_management'               => 'F',
        'pricing_management'               => 'F',
        'inventory_and_availability'       => 'F',
        'procurement'                      => 'R',
        'quote_rfq_management'             => 'R',
        'manufacturer_controls'            => 'R',
        'catalog_taxonomy_and_data_quality'=> 'R',
    ],

    'auditor_read_all' => [
        'organization_management'          => 'F',
        'audit_and_logging'                => 'R',
        'project_management'               => 'R',
        'procurement'                      => 'R',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'R',
        'product_management'               => 'R',
        'user_management'                  => 'R',
        'delegation_and_impersonation'     => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'manufacturer_controls'            => 'R',
        'catalog_taxonomy_and_data_quality'=> 'R',
        'financial_access'                 => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'integration_service_account' => [
        'order_fulfillment'                => 'F',
        'product_management'               => 'F',
        'pricing_management'               => 'F',
        'inventory_and_availability'       => 'F',
        'procurement'                      => 'R',
        'quote_rfq_management'             => 'R',
        'manufacturer_controls'            => 'R',
        'catalog_taxonomy_and_data_quality'=> 'R',
    ],

    'delegate_proxy' => [
        'delegation_and_impersonation'     => 'O',
        'procurement'                      => 'R',
        'project_management'               => 'R',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'R',
    ],

    // ─── ORGANIZATION ────────────────────────────────────────────────────────

    'organization_owner' => [
        'organization_management'          => 'F',
        'user_management'                  => 'F',
        'delegation_and_impersonation'     => 'F',
        'procurement'                      => 'F',
        'approval_authority'               => 'A',
        'estimate_management'              => 'F',
        'project_management'               => 'F',
        'quote_rfq_management'             => 'F',
        'contract_and_agreement_management'=> 'F',
        'budget_and_cost_control'          => 'A',
        'financial_access'                 => 'F',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'audit_and_logging'                => 'R',
        'reporting_and_analytics'          => 'F',
        'system_administration'            => 'R',
        'seller_payout_and_settlement'     => 'R',
    ],

    'organization_admin' => [
        'organization_management'          => 'F',
        'user_management'                  => 'F',
        'delegation_and_impersonation'     => 'O',
        'project_management'               => 'F',
        'approval_authority'               => 'S',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'R',
        'procurement'                      => 'R',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'audit_and_logging'                => 'R',
        'reporting_and_analytics'          => 'R',
        'system_administration'            => 'R',
    ],

    'executive_approver' => [
        'approval_authority'               => 'A',
        'contract_and_agreement_management'=> 'A',
        'budget_and_cost_control'          => 'A',
        'quote_rfq_management'             => 'A',
        'procurement'                      => 'R',
        'estimate_management'              => 'R',
        'project_management'               => 'R',
        'pricing_management'               => 'R',
        'financial_access'                 => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'financial_admin' => [
        'budget_and_cost_control'          => 'F',
        'invoice_and_payment_processing'   => 'F',
        'financial_access'                 => 'F',
        'approval_authority'               => 'A',
        'organization_management'          => 'R',
        'user_management'                  => 'O',
        'procurement'                      => 'R',
        'estimate_management'              => 'R',
        'project_management'               => 'R',
        'quote_rfq_management'             => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'audit_and_logging'                => 'R',
        'reporting_and_analytics'          => 'R',
        'seller_payout_and_settlement'     => 'R',
    ],

    // ─── PROCUREMENT ─────────────────────────────────────────────────────────

    'procurement_manager' => [
        'procurement'                      => 'F',
        'quote_rfq_management'             => 'F',
        'contract_and_agreement_management'=> 'F',
        'approval_authority'               => 'A',
        'estimate_management'              => 'R',
        'project_management'               => 'R',
        'product_management'               => 'R',
        'user_management'                  => 'R',
        'organization_management'          => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'audit_and_logging'                => 'R',
        'budget_and_cost_control'          => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'procurement_coordinator' => [
        'procurement'                      => 'O',
        'quote_rfq_management'             => 'F',
        'estimate_management'              => 'R',
        'project_management'               => 'R',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
    ],

    'requisitioner' => [
        'procurement'                      => 'S',
        'quote_rfq_management'             => 'S',
        'estimate_management'              => 'R',
        'project_management'               => 'R',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
    ],

    'ap_invoice_clerk' => [
        'invoice_and_payment_processing'   => 'F',
        'procurement'                      => 'R',
        'order_fulfillment'                => 'R',
        'financial_access'                 => 'O',
        'estimate_management'              => 'R',
        'project_management'               => 'R',
        'quote_rfq_management'             => 'R',
    ],

    'contract_manager' => [
        'contract_and_agreement_management'=> 'F',
        'approval_authority'               => 'S',
        'estimate_management'              => 'R',
        'project_management'               => 'R',
        'quote_rfq_management'             => 'R',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'budget_owner' => [
        'budget_and_cost_control'          => 'A',
        'procurement'                      => 'R',
        'estimate_management'              => 'R',
        'project_management'               => 'R',
        'quote_rfq_management'             => 'R',
        'organization_management'          => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'financial_access'                 => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    // ─── PROJECT OPERATIONS ──────────────────────────────────────────────────

    'project_manager' => [
        'project_management'               => 'F',
        'estimate_management'              => 'F',
        'quote_rfq_management'             => 'F',
        'procurement'                      => 'F',
        'approval_authority'               => 'S',
        'user_management'                  => 'S',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'audit_and_logging'                => 'R',
        'budget_and_cost_control'          => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'project_engineer' => [
        'project_management'               => 'O',
        'estimate_management'              => 'O',
        'quote_rfq_management'             => 'S',
        'compliance_and_certification'     => 'R',
    ],

    'estimator' => [
        'estimate_management'              => 'F',
        'procurement'                      => 'F',
        'project_management'               => 'F',
        'quote_rfq_management'             => 'S',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'user_management'                  => 'F',
    ],

    'superintendent' => [
        'project_management'               => 'R',
        'order_fulfillment'                => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'viewer_read_only' => [
        'project_management'               => 'R',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'R',
        'procurement'                      => 'R',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    // ─── MANUFACTURER / SELLER ───────────────────────────────────────────────

    'manufacturer_admin' => [
        'manufacturer_controls'            => 'F',
        'product_management'               => 'F',
        'catalog_taxonomy_and_data_quality'=> 'F',
        'pricing_management'               => 'F',
        'order_fulfillment'                => 'F',
        'inventory_and_availability'       => 'F',
        'organization_management'          => 'F',
        'user_management'                  => 'F',
        'quote_rfq_management'             => 'F',
        'approval_authority'               => 'A',
        'audit_and_logging'                => 'R',
        'reporting_and_analytics'          => 'F',
        'returns_and_rma'                  => 'A',
        'seller_payout_and_settlement'     => 'A',
    ],

    'product_manager' => [
        'product_management'               => 'F',
        'catalog_taxonomy_and_data_quality'=> 'F',
        'manufacturer_controls'            => 'O',
        'quote_rfq_management'             => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'inventory_and_availability'       => 'R',
        'compliance_and_certification'     => 'R',
    ],

    'catalog_data_steward' => [
        'catalog_taxonomy_and_data_quality'=> 'F',
        'product_management'               => 'O',
        'manufacturer_controls'            => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'ai_extraction_review'             => 'S',
    ],

    'order_fulfillment_csr' => [
        'order_fulfillment'                => 'F',
        'returns_and_rma'                  => 'F',
        'procurement'                      => 'R',
        'quote_rfq_management'             => 'R',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'inventory_and_availability'       => 'R',
    ],

    'pricing_manager' => [
        'pricing_management'               => 'A',
        'product_management'               => 'R',
        'manufacturer_controls'            => 'R',
        'catalog_taxonomy_and_data_quality'=> 'R',
        'order_fulfillment'                => 'R',
        'contract_and_agreement_management'=> 'R',
        'reporting_and_analytics'          => 'R',
    ],

    'sales_rep' => [
        'quote_rfq_management'             => 'F',
        'product_management'               => 'R',
        'manufacturer_controls'            => 'R',
        'pricing_management'               => 'R',
        'order_fulfillment'                => 'R',
        'returns_and_rma'                  => 'R',
    ],

    'account_manager' => [
        'quote_rfq_management'             => 'F',
        'contract_and_agreement_management'=> 'S',
        'pricing_management'               => 'O',
        'product_management'               => 'R',
        'order_fulfillment'                => 'R',
        'reporting_and_analytics'          => 'R',
        'returns_and_rma'                  => 'R',
        'seller_payout_and_settlement'     => 'R',
    ],

    'returns_rma_handler' => [
        'returns_and_rma'                  => 'F',
        'order_fulfillment'                => 'R',
    ],

    'compliance_certifications' => [
        'compliance_and_certification'     => 'F',
        'product_management'               => 'R',
    ],

    'inventory_manager' => [
        'inventory_and_availability'       => 'F',
        'order_fulfillment'                => 'R',
    ],

    'logistics_coordinator' => [
        'order_fulfillment'                => 'O',
        'inventory_and_availability'       => 'R',
        'returns_and_rma'                  => 'R',
    ],

    'technical_rep' => [
        'quote_rfq_management'             => 'R',
        'product_management'               => 'R',
        'compliance_and_certification'     => 'R',
    ],

    // ─── DESIGN & SPECIFICATION ──────────────────────────────────────────────

    'architect' => [
        'project_management'               => 'R',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'S',
        'product_management'               => 'R',
        'pricing_management'               => 'R',
        'compliance_and_certification'     => 'R',
    ],

    'engineer' => [
        'project_management'               => 'R',
        'estimate_management'              => 'R',
        'quote_rfq_management'             => 'A',
        'product_management'               => 'R',
        'compliance_and_certification'     => 'R',
    ],

    'specifier' => [
        'product_management'               => 'R',
        'catalog_taxonomy_and_data_quality'=> 'R',
        'compliance_and_certification'     => 'R',
    ],

    'designer' => [
        'product_management'               => 'R',
    ],

    'spec_reviewer_ahj' => [
        'project_management'               => 'R',
        'compliance_and_certification'     => 'R',
    ],

    'consultant' => [
        'project_management'               => 'R',
        'reporting_and_analytics'          => 'R',
    ],

    // ─── FIELD OPERATIONS ────────────────────────────────────────────────────

    'subcontractor_pm' => [
        'project_management'               => 'O',
        'procurement'                      => 'O',
        'order_fulfillment'                => 'R',
    ],

    'foreman' => [
        'project_management'               => 'R',
        'order_fulfillment'                => 'R',
    ],

    'installer' => [
        'project_management'               => 'R',
    ],

    'qa_qc' => [
        'project_management'               => 'R',
        'compliance_and_certification'     => 'R',
        'reporting_and_analytics'          => 'O',
    ],

    'field_viewer' => [
        'project_management'               => 'R',
    ],

];
