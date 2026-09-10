<?php

namespace App\Services\Rbac;

use Illuminate\Support\Facades\DB;

/**
 * Cross-org relationship enforcement (document §4.5).
 *
 * The org_relationships table is the authority on which organizations may interact.
 * All cross-org operations must pass through this service before proceeding.
 *
 * Relationship types:
 *   buyer_seller            — buyer org may send RFQs to seller org
 *   gc_subcontractor        — GC org may include subcontractor org in projects
 *   distributor_manufacturer — distributor org may resell manufacturer's products
 *   manufacturer_rep_agency — manufacturer authorizes rep agency to sell on its behalf
 *   gpo_member              — GPO org grants member org access to negotiated pricing
 *   delegation              — general inter-org delegation
 */
class OrgRelationshipService
{
    /**
     * Check whether an active directional relationship exists from → to.
     */
    public function hasActive(int $fromOrgId, int $toOrgId, string $relationshipType): bool
    {
        return DB::table('org_relationships')
            ->where('from_org_id', $fromOrgId)
            ->where('to_org_id', $toOrgId)
            ->where('relationship_type', $relationshipType)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check whether a relationship exists in either direction (bidirectional partners).
     */
    public function hasActiveEither(int $orgAId, int $orgBId, string $relationshipType): bool
    {
        return DB::table('org_relationships')
            ->where('relationship_type', $relationshipType)
            ->where('is_active', true)
            ->where(function ($q) use ($orgAId, $orgBId) {
                $q->where(fn ($q2) => $q2->where('from_org_id', $orgAId)->where('to_org_id', $orgBId))
                  ->orWhere(fn ($q2) => $q2->where('from_org_id', $orgBId)->where('to_org_id', $orgAId));
            })
            ->exists();
    }

    /**
     * Return the IDs of all active partner orgs the given org has outgoing relationships with
     * of the specified type (e.g., seller orgs a buyer has buyer_seller links to).
     *
     * @return array<int, int>
     */
    public function partnerIds(int $fromOrgId, string $relationshipType): array
    {
        return DB::table('org_relationships')
            ->where('from_org_id', $fromOrgId)
            ->where('relationship_type', $relationshipType)
            ->where('is_active', true)
            ->pluck('to_org_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Return partner IDs from either direction of the relationship.
     *
     * @return array<int, int>
     */
    public function partnerIdsEither(int $orgId, string $relationshipType): array
    {
        $outgoing = DB::table('org_relationships')
            ->where('from_org_id', $orgId)
            ->where('relationship_type', $relationshipType)
            ->where('is_active', true)
            ->pluck('to_org_id');

        $incoming = DB::table('org_relationships')
            ->where('to_org_id', $orgId)
            ->where('relationship_type', $relationshipType)
            ->where('is_active', true)
            ->pluck('from_org_id');

        return $outgoing->merge($incoming)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Assert that from_org may interact with to_org under the given relationship type.
     */
    public function canInteract(int $fromOrgId, int $toOrgId, string $relationshipType): bool
    {
        return $this->hasActive($fromOrgId, $toOrgId, $relationshipType);
    }

    /**
     * Check whether an org has ANY active outgoing relationship of the given type.
     * Used to verify that a distributor or rep agency has at least one principal
     * before they can respond to RFQs as a reseller.
     */
    public function hasAnyActive(int $orgId, string $relationshipType): bool
    {
        return DB::table('org_relationships')
            ->where('from_org_id', $orgId)
            ->where('relationship_type', $relationshipType)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Verify that a seller org is authorized to sell/respond to RFQs
     * based on its org type and active relationship mesh.
     *
     * - distributor_* orgs must have at least one active distributor_manufacturer relationship.
     * - rep_agency orgs must have at least one active manufacturer_rep_agency relationship.
     * - All other seller types (manufacturer, etc.) are implicitly authorized.
     *
     * Returns null when authorized; returns an error message string when not.
     */
    public function sellerAuthorizationError(int $sellerOrgId, string $sellerOrgType): ?string
    {
        if (str_starts_with($sellerOrgType, 'distributor')) {
            if (! $this->hasAnyActive($sellerOrgId, 'distributor_manufacturer')) {
                return 'This distributor has no active manufacturer authorization. Establish a Distributor → Manufacturer connection in Connections before responding to RFQs.';
            }
        }

        if ($sellerOrgType === 'rep_agency') {
            if (! $this->hasAnyActive($sellerOrgId, 'manufacturer_rep_agency')) {
                return 'This rep agency has no active manufacturer principal. Establish a Manufacturer → Rep Agency connection before responding to RFQs.';
            }
        }

        return null;
    }
}
