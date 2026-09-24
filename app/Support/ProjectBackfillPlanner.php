<?php

namespace App\Support;

class ProjectBackfillPlanner
{
    public static function trimText(?string $s): ?string
    {
        if ($s === null) {
            return '';
        }

        return preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $s);
    }

    public static function normalize(?string $s): string
    {
        $trimmed = self::trimText($s);
        if ($trimmed === null) {
            return '';
        }

        $collapsed = preg_replace('/[\s\p{Z}]+/u', ' ', $trimmed);

        return $collapsed === null ? '' : mb_strtolower($collapsed, 'UTF-8');
    }

    public static function isValidText(?string $s): bool
    {
        return $s === null || preg_match('//u', $s) === 1;
    }

    /**
     * @param array{
     *   owner_id: int,
     *   quotes: array<int, array<string, mixed>>,
     *   active_org_ids: array<int, int>,
     *   legacy_members: array<int, array<string, mixed>>,
     *   crosswalk: array<int, array<string, mixed>>,
     *   attached: array<int, array<string, mixed>>,
     *   user_active_orgs: array<int, array<int, int>>,
     *   overrides: array<int, array<string, mixed>>,
     *   run_at: string,
     *   existing_members: callable(int): array<int, int>,
     *   existing_codes: callable(int): array<int, string>,
     * } $ctx
     * @return array{groups: array, unresolved: array, warnings: array, candidate_merges: array, fatal: array}
     */
    public function plan(array $ctx): array
    {
        $ownerId = $ctx['owner_id'];
        $overrides = $ctx['overrides'];
        $activeOrgs = array_values(array_unique(array_map('intval', $ctx['active_org_ids'])));
        sort($activeOrgs);

        $quotes = $ctx['quotes'];
        usort($quotes, fn ($a, $b) => $a['id'] <=> $b['id']);

        $legacyByQuote = [];
        foreach ($ctx['legacy_members'] as $row) {
            $legacyByQuote[$row['quote_id']][] = $row;
        }

        $warnings = [];
        $unresolved = [];
        $fatal = [];

        foreach ($ctx['attached'] as $att) {
            $ov = $overrides[$att['quote_id']] ?? null;
            if ($ov === null) {
                continue;
            }
            $orgDiffers = $ov['org_id'] !== null && (int) $ov['org_id'] !== (int) $att['project_org_id'];
            $nameDiffers = ($ov['project_name'] ?? '') !== ''
                && self::normalize($ov['project_name']) !== self::normalize($att['project_title'] ?? null);
            if ($orgDiffers || $nameDiffers) {
                $warnings[] = ['overrides', $ov['line'], 'attached_quote_org_or_name_ignored'];
            }
        }

        $resolved = [];
        foreach ($quotes as $q) {
            $qid = $q['id'];
            $class = $this->classify($q);
            if ($class['invalid']) {
                $warnings[] = ['normalize', $qid, 'project_name_invalid_utf8'];
            }
            if (! self::isValidText($q['project_address'] ?? null)) {
                $warnings[] = ['normalize', $qid, 'project_address_invalid_utf8'];
            }

            $ov = $overrides[$qid] ?? null;
            [$orgId, $how] = $this->resolveOrg($qid, $ov, $activeOrgs, $legacyByQuote[$qid] ?? []);

            if ($orgId === null) {
                $unresolved[] = $this->unresolvedRow($q, $ownerId, $how, $activeOrgs, $legacyByQuote[$qid] ?? []);

                continue;
            }

            $keyNorm = $ov['key_norm'] ?? '';
            if ($keyNorm !== '') {
                $kind = 'named';
                $value = $keyNorm;
                $source = 'override';
            } elseif ($class['unnamed']) {
                $kind = 'singleton';
                $value = (string) $qid;
                $source = 'singleton';
            } else {
                $kind = 'named';
                $value = $class['norm'];
                $source = 'name';
            }

            $resolved[] = [
                'q' => $q,
                'org_id' => $orgId,
                'how' => $how,
                'kind' => $kind,
                'value' => $value,
                'source' => $source,
                'class' => $class,
            ];
        }

        $candidateMerges = $this->candidateMerges($resolved, $ownerId);

        $groupsByKey = [];
        foreach ($resolved as $r) {
            $key = json_encode([$r['org_id'], $ownerId, $r['kind'], $r['value']]);
            if (! isset($groupsByKey[$key])) {
                $groupsByKey[$key] = [
                    'key' => $key,
                    'org_id' => $r['org_id'],
                    'owner_id' => $ownerId,
                    'kind' => $r['kind'],
                    'value' => $r['value'],
                    'group_key' => $r['kind'].':'.$r['value'],
                    'org_resolution' => $r['how'],
                    'key_source' => $r['source'],
                    'quotes' => [],
                    'classes' => [],
                ];
            } elseif ($r['source'] === 'override') {
                $groupsByKey[$key]['key_source'] = 'override';
            }
            $groupsByKey[$key]['quotes'][] = $r['q'];
            $groupsByKey[$key]['classes'][$r['q']['id']] = $r['class'];
        }

        $siblings = $this->siblingMap($ctx['attached'], $ownerId, $overrides);

        $groups = [];
        foreach ($groupsByKey as $g) {
            $names = [];
            foreach ($g['quotes'] as $q) {
                $ov = $overrides[$q['id']] ?? null;
                if ($ov !== null && ($ov['project_name'] ?? '') !== '') {
                    $names[$ov['project_name']][] = $ov['line'];
                }
            }
            if (count($names) > 1) {
                foreach ($names as $lines) {
                    foreach ($lines as $line) {
                        $fatal[] = [$line, 'conflicting_project_name'];
                    }
                }
            }

            $existing = $siblings[$g['key']] ?? [];
            $decision = $this->decideSibling($g['quotes'], $existing);
            if (is_string($decision)) {
                foreach ($g['quotes'] as $q) {
                    $unresolved[] = $this->unresolvedRow($q, $ownerId, $decision, $activeOrgs, $legacyByQuote[$q['id']] ?? []);
                }

                continue;
            }

            $g['action'] = $decision === null ? 'create' : 'reuse';
            $g['project_id'] = $decision['project_id'] ?? null;
            $g['existing_project'] = $decision;

            if ($decision === null) {
                $g['project'] = $this->planProject($g, $overrides, $ownerId, $ctx['run_at']);
                $g['address_conflicts'] = $g['project']['address_conflicts'];
                unset($g['project']['address_conflicts']);
            } else {
                $g['project'] = [
                    'name' => $decision['project_title'],
                    'address' => $decision['project_address'],
                    'created_at' => $decision['project_created_at'],
                    'deleted_at' => $decision['project_deleted_at'],
                ];
                $g['address_conflicts'] = [];
            }

            $existingUsers = $g['action'] === 'reuse' ? array_map('intval', ($ctx['existing_members'])($g['project_id'])) : [];
            $userActive = $ctx['user_active_orgs'];
            $userActive[$ownerId] = $activeOrgs;
            $memberPlan = $this->planMembers($g, $legacyByQuote, $userActive, $existingUsers, $ctx['run_at']);
            $g['members'] = $memberPlan['members'];
            $g['rehomed'] = $memberPlan['rehomed'];
            $g['mismatch'] = $memberPlan['mismatch'];
            $g['widening'] = $memberPlan['widening'];

            $existingCodes = $g['action'] === 'reuse' ? ($ctx['existing_codes'])($g['project_id']) : [];
            $g['crosswalk'] = $this->planCrosswalk($g, $ctx['crosswalk'], $existingCodes);

            $g['legacy_org_ids'] = [];
            foreach ($g['quotes'] as $q) {
                $g['legacy_org_ids'][$q['id']] = $this->distinctOrgIds($legacyByQuote[$q['id']] ?? []);
            }
            $g['owner_active_org_ids'] = $activeOrgs;

            unset($g['classes']);
            $groups[] = $g;
        }

        return [
            'groups' => $groups,
            'unresolved' => $unresolved,
            'warnings' => $warnings,
            'candidate_merges' => $candidateMerges,
            'fatal' => $fatal,
        ];
    }

    /**
     * @return array{unnamed: bool, h1: bool, invalid: bool, norm: string}
     */
    private function classify(array $q): array
    {
        $pn = $q['project_name'] ?? null;
        if (! self::isValidText($pn)) {
            return ['unnamed' => true, 'h1' => false, 'invalid' => true, 'norm' => ''];
        }

        $norm = self::normalize($pn);
        if ($norm === '' || $norm === 'estimate') {
            return ['unnamed' => true, 'h1' => false, 'invalid' => false, 'norm' => $norm];
        }

        if ($norm === self::normalize($q['name'] ?? null)) {
            return ['unnamed' => true, 'h1' => true, 'invalid' => false, 'norm' => $norm];
        }

        return ['unnamed' => false, 'h1' => false, 'invalid' => false, 'norm' => $norm];
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function resolveOrg(int $qid, ?array $ov, array $activeOrgs, array $legacyRows): array
    {
        if ($ov !== null && $ov['org_id'] !== null) {
            return [(int) $ov['org_id'], 'override'];
        }

        if (count($activeOrgs) === 1) {
            return [$activeOrgs[0], 'single_org'];
        }

        if (count($activeOrgs) === 0) {
            return [null, 'no_active_org'];
        }

        $orgs = $this->distinctOrgIds($legacyRows);
        if (count($orgs) === 1 && in_array($orgs[0], $activeOrgs, true)) {
            return [$orgs[0], 'membership'];
        }

        return [null, 'multi_org_no_consensus'];
    }

    private function distinctOrgIds(array $rows): array
    {
        $orgs = array_values(array_unique(array_map(fn ($r) => (int) $r['org_id'], $rows)));
        sort($orgs);

        return $orgs;
    }

    private function unresolvedRow(array $q, int $ownerId, string $reason, array $activeOrgs, array $legacyRows): array
    {
        return [
            'quote_id' => $q['id'],
            'quote_number' => $q['quote_number'],
            'owner_user_id' => $ownerId,
            'reason' => $reason,
            'owner_active_org_ids' => $activeOrgs,
            'legacy_member_org_ids' => $this->distinctOrgIds($legacyRows),
        ];
    }

    private function candidateMerges(array $resolved, int $ownerId): array
    {
        $buckets = [];
        foreach ($resolved as $r) {
            $norm = $r['class']['norm'];
            $isH1 = $r['class']['h1'] && $r['source'] !== 'override';
            $isNamed = ! $r['class']['unnamed'] && $r['source'] !== 'override';
            if (! $isH1 && ! $isNamed) {
                continue;
            }
            $key = json_encode([$r['org_id'], $norm]);
            $buckets[$key]['org_id'] = $r['org_id'];
            $buckets[$key]['norm'] = $norm;
            $buckets[$key]['ids'][] = $r['q']['id'];
            $buckets[$key]['h1'] = ($buckets[$key]['h1'] ?? false) || $isH1;
        }

        $out = [];
        foreach ($buckets as $b) {
            if ($b['h1']) {
                sort($b['ids']);
                $out[] = ['org_id' => $b['org_id'], 'owner_user_id' => $ownerId, 'normalized_name' => $b['norm'], 'quote_ids' => $b['ids']];
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> group key => project rows keyed by project id
     */
    private function siblingMap(array $attached, int $ownerId, array $overrides): array
    {
        $map = [];
        foreach ($attached as $att) {
            if ((int) $att['project_created_by'] !== $ownerId) {
                continue;
            }

            $keyNorm = ($overrides[$att['quote_id']]['key_norm'] ?? '');
            if ($keyNorm !== '') {
                $kind = 'named';
                $value = $keyNorm;
            } else {
                $class = $this->classify(['project_name' => $att['project_name'], 'name' => $att['name']]);
                if ($class['unnamed']) {
                    $kind = 'singleton';
                    $value = (string) $att['quote_id'];
                } else {
                    $kind = 'named';
                    $value = $class['norm'];
                }
            }

            $key = json_encode([(int) $att['project_org_id'], $ownerId, $kind, $value]);
            $map[$key][(int) $att['project_id']] = [
                'project_id' => (int) $att['project_id'],
                'project_title' => $att['project_title'],
                'project_address' => $att['project_address'],
                'project_created_at' => $att['project_created_at'],
                'project_deleted_at' => $att['project_deleted_at'],
                'trashed' => $att['project_deleted_at'] !== null,
            ];
        }

        return $map;
    }

    /**
     * @return array|string|null null = create, array = reuse this project, string = unresolved reason
     */
    private function decideSibling(array $quotes, array $existing): array|string|null
    {
        if (count($existing) === 0) {
            return null;
        }

        if (count($existing) > 1) {
            return 'ambiguous_existing_project';
        }

        $project = reset($existing);
        if ($project['trashed']) {
            $hasLive = false;
            foreach ($quotes as $q) {
                if ($q['deleted_at'] === null) {
                    $hasLive = true;
                }
            }
            if ($hasLive) {
                return 'sibling_project_trashed';
            }
        }

        return $project;
    }

    private function planProject(array $g, array $overrides, int $ownerId, string $runAt): array
    {
        $quotes = $g['quotes'];
        $lowest = $quotes[0];

        $overrideName = null;
        foreach ($quotes as $q) {
            $n = $overrides[$q['id']]['project_name'] ?? '';
            if ($n !== '') {
                $overrideName = $n;
                break;
            }
        }

        if ($overrideName !== null) {
            $name = $overrideName;
        } else {
            $named = array_values(array_filter($quotes, fn ($q) => ! $g['classes'][$q['id']]['unnamed']));
            $name = '';
            if ($g['kind'] === 'named' && $named !== []) {
                $name = (string) self::trimText($this->mostRecent($named)['project_name']);
            } else {
                $recent = $this->mostRecent($quotes);
                $norm = self::normalize($recent['name'] ?? null);
                if ($norm !== '' && $norm !== 'estimate') {
                    $name = (string) self::trimText($recent['name']);
                }
            }
            if ($name === '') {
                $name = 'Untitled project ('.$lowest['quote_number'].')';
            }
        }
        $name = mb_substr($name, 0, 255, 'UTF-8');

        $withAddress = array_values(array_filter($quotes, fn ($q) => self::normalize($q['project_address'] ?? null) !== ''));
        $address = null;
        $conflicts = [];
        if ($withAddress !== []) {
            $chosen = $this->mostRecent($withAddress);
            $address = (string) self::trimText($chosen['project_address']);
            $chosenNorm = self::normalize($chosen['project_address']);
            foreach ($withAddress as $q) {
                if (self::normalize($q['project_address']) !== $chosenNorm) {
                    $conflicts[] = ['chosen_address' => $address, 'quote_id' => $q['id'], 'quote_address' => (string) self::trimText($q['project_address'])];
                }
            }
        }

        $createdAt = $this->earliestCreatedAt($quotes) ?? $runAt;

        $deletedAt = null;
        $allTrashed = true;
        $latest = null;
        foreach ($quotes as $q) {
            if ($q['deleted_at'] === null) {
                $allTrashed = false;
            } elseif ($latest === null || strcmp($q['deleted_at'], $latest) > 0) {
                $latest = $q['deleted_at'];
            }
        }
        if ($allTrashed) {
            $deletedAt = $latest;
        }

        return [
            'org_id' => $g['org_id'],
            'name' => $name,
            'status' => 'active',
            'address' => $address,
            'bid_due_at' => null,
            'created_by' => $ownerId,
            'created_at' => $createdAt,
            'updated_at' => $runAt,
            'deleted_at' => $deletedAt,
            'address_conflicts' => $conflicts,
        ];
    }

    private function mostRecent(array $quotes): array
    {
        usort($quotes, function ($a, $b) {
            return $this->cmpDesc($a['updated_at'] ?? null, $b['updated_at'] ?? null)
                ?: $this->cmpDesc($a['created_at'] ?? null, $b['created_at'] ?? null)
                ?: $b['id'] <=> $a['id'];
        });

        return $quotes[0];
    }

    private function earliestCreatedAt(array $quotes): ?string
    {
        $best = null;
        foreach ($quotes as $q) {
            if ($q['created_at'] !== null && ($best === null || strcmp($q['created_at'], $best) < 0)) {
                $best = $q['created_at'];
            }
        }

        return $best;
    }

    private function cmpAsc(?string $a, ?string $b): int
    {
        if ($a === $b) {
            return 0;
        }
        if ($a === null) {
            return 1;
        }
        if ($b === null) {
            return -1;
        }

        return strcmp($a, $b);
    }

    private function cmpDesc(?string $a, ?string $b): int
    {
        if ($a === $b) {
            return 0;
        }
        if ($a === null) {
            return 1;
        }
        if ($b === null) {
            return -1;
        }

        return strcmp($b, $a);
    }

    private function planMembers(array $g, array $legacyByQuote, array $userActiveOrgs, array $existingUsers, string $runAt): array
    {
        $orgId = $g['org_id'];
        $ownerId = $g['owner_id'];
        $projectId = $g['project_id'];

        $eligible = [];
        $rehomed = [];
        $mismatch = [];
        $allByUser = [];

        foreach ($g['quotes'] as $q) {
            foreach ($legacyByQuote[$q['id']] ?? [] as $row) {
                $uid = (int) $row['user_id'];
                $allByUser[$uid][] = $row;
                $userOrgs = $userActiveOrgs[$uid] ?? [];

                if ((int) $row['org_id'] === $orgId) {
                    $eligible[$uid][] = $row;
                } elseif (in_array($orgId, $userOrgs, true)) {
                    $eligible[$uid][] = $row;
                    $rehomed[] = ['row' => $row, 'project_org_id' => $orgId];
                } else {
                    $mismatch[] = ['row' => $row, 'project_org_id' => $orgId, 'user_active_org_ids' => $userOrgs];
                }
            }
        }

        $userIds = array_keys($eligible);
        if (! in_array($ownerId, $userIds, true)) {
            $userIds[] = $ownerId;
        }

        $members = [];
        foreach ($userIds as $uid) {
            $rows = $eligible[$uid] ?? [];
            usort($rows, fn ($a, $b) => $this->cmpAsc($a['granted_at'] ?? null, $b['granted_at'] ?? null) ?: $a['id'] <=> $b['id']);
            $activeRows = array_values(array_filter($rows, fn ($r) => (bool) $r['is_active']));
            $chosen = $activeRows[0] ?? $rows[0] ?? null;

            $isOwner = $uid === $ownerId;
            $explicitRemoval = false;
            if ($isOwner) {
                $all = $allByUser[$uid] ?? [];
                $explicitRemoval = $all !== [] && array_filter($all, fn ($r) => (bool) $r['is_active']) === [];
                $active = ! $explicitRemoval;
            } else {
                $active = $activeRows !== [];
            }

            if ($chosen !== null) {
                $grantedBy = $chosen['granted_by'];
                $grantedAt = $chosen['granted_at'];
            } else {
                $grantedBy = null;
                $grantedAt = $this->earliestCreatedAt($g['quotes']) ?? $runAt;
            }

            if (in_array($uid, $existingUsers, true)) {
                $action = 'existing_untouched';
            } elseif ($explicitRemoval) {
                $action = 'owner_explicitly_removed';
            } else {
                $action = $active ? 'inserted_active' : 'inserted_inactive';
            }

            $sourceIds = array_map(fn ($r) => $r['id'], $rows);
            sort($sourceIds);

            $members[] = [
                'user_id' => $uid,
                'org_id' => $orgId,
                'is_active' => $active,
                'granted_by' => $grantedBy,
                'granted_at' => $grantedAt,
                'is_owner' => $isOwner,
                'member_active_in_org' => in_array($orgId, $userActiveOrgs[$uid] ?? [], true),
                'source_row_ids' => $sourceIds,
                'action' => $action,
            ];
        }

        usort($members, function ($a, $b) {
            $am = $a['source_row_ids'] === [] ? PHP_INT_MAX : $a['source_row_ids'][0];
            $bm = $b['source_row_ids'] === [] ? PHP_INT_MAX : $b['source_row_ids'][0];

            return $am <=> $bm ?: $a['user_id'] <=> $b['user_id'];
        });

        $widening = [];
        foreach ($members as $m) {
            if ($m['is_owner'] || ! $m['is_active'] || $m['action'] !== 'inserted_active') {
                continue;
            }
            $gained = [];
            foreach ($g['quotes'] as $q) {
                $hasActive = false;
                foreach ($legacyByQuote[$q['id']] ?? [] as $row) {
                    if ((int) $row['user_id'] === $m['user_id'] && (bool) $row['is_active']) {
                        $hasActive = true;
                    }
                }
                if (! $hasActive) {
                    $gained[] = $q['id'];
                }
            }
            if ($gained !== []) {
                $widening[] = ['user_id' => $m['user_id'], 'gained_quote_ids' => $gained];
            }
        }

        return ['members' => $members, 'rehomed' => $rehomed, 'mismatch' => $mismatch, 'widening' => $widening];
    }

    private function planCrosswalk(array $g, array $crosswalkRows, array $existingCodes): array
    {
        $quoteIds = array_flip(array_map(fn ($q) => $q['id'], $g['quotes']));
        $orgId = $g['org_id'];

        $link = [];
        $conflicts = [];
        $codeCounts = array_count_values(array_map('strval', $existingCodes));
        $linkedRows = [];

        foreach ($crosswalkRows as $row) {
            if (! isset($quoteIds[$row['quote_id']])) {
                continue;
            }
            if ((int) $row['org_id'] === $orgId) {
                $link[] = (int) $row['id'];
                $linkedRows[] = $row;
                $code = (string) $row['plan_line_code'];
                $codeCounts[$code] = ($codeCounts[$code] ?? 0) + 1;
            } else {
                $conflicts[] = ['row' => $row, 'type' => 'org_mismatch', 'linked' => false];
            }
        }

        foreach ($linkedRows as $row) {
            if ($codeCounts[(string) $row['plan_line_code']] > 1) {
                $conflicts[] = ['row' => $row, 'type' => 'duplicate_code', 'linked' => true];
            }
        }

        sort($link);

        return ['link_ids' => $link, 'conflicts' => $conflicts];
    }
}
