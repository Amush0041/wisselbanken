<?php

// 20 organization types shown at registration (matches document §4.4).
// Distributor is split into Stocking and Non-Stocking as the document specifies — these represent
// fundamentally different operating models (inventory ownership vs. drop-ship).
// [slug, name, purpose]
return [
    ['owner', 'Owner', 'Property owner/developer'],
    ['architect', 'Architect', 'Architectural firm'],
    ['engineering_firm', 'Engineering Firm', 'Engineering consultant'],
    ['general_contractor', 'General Contractor', 'General contractor'],
    ['subcontractor', 'Subcontractor', 'Trade contractor'],
    ['manufacturer', 'Manufacturer', 'Product manufacturer'],
    ['fabricator', 'Fabricator', 'Custom fabricator'],
    ['distributor_stocking', 'Distributor (Stocking)', 'Stocking distributor — holds inventory on-hand'],
    ['distributor_non_stocking', 'Distributor (Non-Stocking)', 'Non-stocking distributor — drop-ships direct from manufacturer'],
    ['logistics_provider', 'Logistics Provider', 'Shipping/logistics company'],
    ['consultant', 'Consultant', 'External consultant/advisor'],
    ['service_vendor', 'Service Vendor', 'Ancillary service provider'],
    ['platform_internal', 'Platform Internal', 'Wisselbanken internal operations'],
    ['buying_group_gpo', 'Buying Group / GPO', 'Group purchasing org negotiating on behalf of members'],
    ['manufacturer_s_rep_sales_agency', 'Manufacturer\'s Rep / Sales Agency', 'Independent agency selling for multiple manufacturers'],
    ['testing_certification_body', 'Testing / Certification Body', 'Issues product certifications and evaluations (e.g. ICC-ES)'],
    ['government_ahj', 'Government / AHJ', 'Authority having jurisdiction, public buyer'],
    ['rental_equipment_provider', 'Rental / Equipment Provider', 'Equipment and tool rental supplier'],
    ['financial_surety_partner', 'Financial / Surety Partner', 'Lender, factoring, or surety/bonding partner'],
];
