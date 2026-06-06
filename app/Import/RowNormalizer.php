<?php

namespace App\Import;

/**
 * Normalizes a single raw row from City_of_Choteau_02.xls into a structured
 * array ready for Interment::create(). All interpretation decisions are logged
 * via the $decisions array so the caller can write an audit trail.
 *
 * Column layout (0-indexed):
 *   0 Lastname   1 First Name   2 Lot #   3 Block #
 *   4 Age/Notes  5 Notes        6 (unnamed overflow)  7 (unnamed overflow)
 */
class RowNormalizer
{
    /** @var array<string> */
    private array $decisions = [];

    /**
     * Normalise one raw XLS row. Returns null if the row is entirely blank
     * (all eight cells empty after trimming).
     *
     * @param array<int,mixed> $rawRow  Eight elements from xlrd / PhpSpreadsheet
     * @param int              $rowNum  1-based row number in the source file
     * @return array{record: array<string,mixed>, decisions: string[], flags: array<string>}|null
     */
    public function normalize(array $rawRow, int $rowNum): ?array
    {
        $this->decisions = [];

        // Pad to 8 cols and stringify
        while (count($rawRow) < 8) {
            $rawRow[] = '';
        }

        [$lastName, $firstName, $lotRaw, $blockRaw, $ageNotes, $notes, $col6, $col7] =
            array_map(fn ($v) => $this->cellToString($v), $rawRow);

        // Skip entirely blank rows
        if ($lastName === '' && $firstName === '' && $lotRaw === '' && $blockRaw === '') {
            return null;
        }

        // Concatenate all note columns for the audit trail
        $sourceNotesRaw = implode(' | ', array_filter(
            [$ageNotes, $notes, $col6, $col7],
            fn ($s) => $s !== ''
        ));

        // ── Name ─────────────────────────────────────────────────────────────
        $lastName  = trim($lastName);
        $firstName = trim($firstName);

        // ── Age / flags from col4 (Age/Notes) ────────────────────────────────
        $ageAtDeath = null;
        $ageRaw     = null;
        $isVeteran  = false;
        $isCremation = false;
        $cremationPlacement = null;
        $isInfant   = false;
        $isDisinterment = false;
        $notes_out  = '';

        // Merge all four note columns for flag scanning
        $allNotes = strtolower(implode(' ', [$ageNotes, $notes, $col6, $col7]));

        // ── Infant detection ─────────────────────────────────────────────────
        // Patterns: Baby, (Baby), Infant, (Infant), N mos., N days, N weeks,
        //           N hrs old, Child, N year[s], N 1/2 years, N mo.
        $infantPatterns = [
            '/^\s*\(?\s*baby\s*\)?\s*$/i',
            '/^\s*\(?\s*infant\s*\)?\s*$/i',
            '/^\s*child\s*$/i',
            '/\b\d+\s*(mos?\.?|months?|weeks?|days?|hrs?\s*old|hours?\s*old)\b/i',
            '/\b\d+\s*(?:or\s*\d+\s*)?yrs?\.?\b/i',
            '/\b\d+\s*(?:\d+\/\d+\s*)?years?\b/i',
        ];
        foreach ($infantPatterns as $pat) {
            if (preg_match($pat, $ageNotes)) {
                $isInfant = true;
                // Strip wrapping parens from "(Baby)" → "Baby"
                $ageRaw = trim(preg_replace('/^\((.+)\)$/', '$1', trim($ageNotes)));
                $this->log("infant from Age/Notes: {$ageRaw}");
                break;
            }
        }

        // (Baby) / (baby) appearing anywhere in the notes columns also triggers infant
        if (!$isInfant && preg_match('/\(?\s*baby\s*\)?/i', $allNotes)) {
            $isInfant = true;
            $this->log('infant from notes columns');
        }

        // ── Numeric age from col4 ─────────────────────────────────────────────
        if (!$isInfant && $ageNotes !== '') {
            // Pure number (XLS numeric cell already converted to float string "87.0")
            if (preg_match('/^\s*(\d+)(?:\.0+)?\s*$/', $ageNotes, $m)) {
                $ageAtDeath = (int) $m[1];
            }
            // "38 Ashes" — age mixed with ashes keyword
            elseif (preg_match('/^\s*(\d+)\s+ashes?\s*$/i', $ageNotes, $m)) {
                $ageAtDeath = (int) $m[1];
                $isCremation = true;
                $this->log("age+ashes in Age/Notes: {$ageNotes}");
            }
        }

        // ── Veteran detection ────────────────────────────────────────────────
        // Col4: "Vet", "VET", "Veteran" alone (no age → not a real age)
        if (preg_match('/^\s*(vet(?:eran)?)\s*$/i', $ageNotes)) {
            $isVeteran = true;
            $this->log("veteran from Age/Notes: {$ageNotes}");
        }
        // Col6 or col7: "Vet", "VET", "Veteran"
        if (preg_match('/\bvet(?:eran)?\b/i', $col6) || preg_match('/\bvet(?:eran)?\b/i', $col7)) {
            $isVeteran = true;
            $this->log('veteran from col6/col7');
        }
        // Notes col5: "Vet, Ashes" style
        if (preg_match('/\bvet(?:eran)?\b/i', $notes) && !preg_match('/veteran\s+spouse/i', $notes)) {
            $isVeteran = true;
            $this->log('veteran from Notes col');
        }
        // Veteran Spouse is NOT is_veteran — keep only as a text note
        if (preg_match('/veteran\s+spouse/i', $allNotes)) {
            $isVeteran = false; // override any mistaken set
            $notes_out = $this->appendNote($notes_out, 'Veteran Spouse');
            $this->log('veteran spouse — not flagging is_veteran');
        }

        // BLK field sometimes contains "Vet Plot" or "- Vet" annotations — strip, flag
        if (preg_match('/\bvet\b/i', $blockRaw)) {
            $isVeteran = true;
            $this->log("veteran noted in block field: {$blockRaw}");
        }

        // ── Cremation detection ───────────────────────────────────────────────
        // Scan all note columns (case-insensitive)
        $ashPattern = '/\bashes?\b|\bcremation\b|\bcremated\b/i';
        if (preg_match($ashPattern, $allNotes)) {
            $isCremation = true;
            $this->log('cremation flag detected');
        }

        // Placement: foot
        $footPattern = '/\b(?:foot|ft\.?|at\s+foot|foot\s+grave)\b/i';
        // Placement: head
        $headPattern = '/\b(?:at\s+head|ashes\s+at\s+head|@\s*head|head)\b/i';
        // Placement: only
        $onlyPattern = '/\bashes?\s+only\b/i';
        // Placement: full burial
        $fullPattern = '/\bfull\s+burial\b/i';

        if ($isCremation) {
            $combined = implode(' ', [$ageNotes, $notes, $col6, $col7]);
            // "full burial" in Age/Notes is its own category
            if (preg_match('/^\s*full\s+burial\s*$/i', $ageNotes)) {
                $cremationPlacement = 'full';
                $isCremation = true;
                $this->log('cremation_placement=full from Age/Notes');
            } elseif (preg_match($onlyPattern, $combined)) {
                $cremationPlacement = 'only';
                $this->log('cremation_placement=only');
            } elseif (preg_match($footPattern, $combined)) {
                $cremationPlacement = 'foot';
                $this->log('cremation_placement=foot');
            } elseif (preg_match($headPattern, $combined)) {
                $cremationPlacement = 'head';
                $this->log('cremation_placement=head');
            }
        }

        // "full burial" in Age/Notes alone
        if (preg_match('/^\s*full\s+burial\s*$/i', $ageNotes)) {
            $isCremation = true;
            $cremationPlacement = 'full';
            $this->log('full burial from Age/Notes');
        }

        // ── Disinterment ──────────────────────────────────────────────────────
        if (preg_match('/disinterment/i', $allNotes)) {
            $isDisinterment = true;
            if (preg_match('/disinterment\s+[\d\/]+/i', $allNotes, $m)) {
                $notes_out = $this->appendNote($notes_out, trim($m[0]));
            }
            $this->log('disinterment detected');
        }

        // ── Lot normalization ─────────────────────────────────────────────────
        $lotNormalized = $this->normalizeLot($lotRaw);

        // ── Block normalization ───────────────────────────────────────────────
        $blockNormalized = $this->normalizeBlock($blockRaw);

        // ── Residual notes ────────────────────────────────────────────────────
        // Free-form notes col5 that are not flags
        $notesToAppend = $this->extractResidualNotes($notes, $col6, $col7,
            $isVeteran, $isCremation, $isDisinterment);
        if ($notesToAppend !== '') {
            $notes_out = $this->appendNote($notes_out, $notesToAppend);
        }

        // ── Flags ─────────────────────────────────────────────────────────────
        $flags = [];
        if ($isVeteran)     $flags[] = 'veteran';
        if ($isCremation)   $flags[] = 'cremation';
        if ($isInfant)      $flags[] = 'infant';
        if ($isDisinterment) $flags[] = 'disinterment';

        $record = [
            'last_name'           => $lastName,
            'first_name'          => $firstName ?: null,
            'age_at_death'        => $ageAtDeath,
            'age_raw'             => $isInfant && $ageRaw ? $ageRaw : null,
            'interment_date'      => null,
            'interment_date_raw'  => null,
            'lot'                 => $lotNormalized['lot'],
            'lot_number'          => $lotNormalized['lot_number'],
            'lot_qualifier'       => $lotNormalized['lot_qualifier'],
            'block'               => $blockNormalized['block'],
            'block_number'        => $blockNormalized['block_number'],
            'block_suffix'        => $blockNormalized['block_suffix'],
            'is_veteran'          => $isVeteran,
            'is_cremation'        => $isCremation,
            'cremation_placement' => $cremationPlacement,
            'is_infant'           => $isInfant,
            'is_disinterment'     => $isDisinterment,
            'notes'               => $notes_out ?: null,
            'source_notes_raw'    => $sourceNotesRaw ?: null,
            'import_row'          => $rowNum,
        ];

        return [
            'record'    => $record,
            'decisions' => $this->decisions,
            'flags'     => $flags,
        ];
    }

    // ── Lot normalizer ────────────────────────────────────────────────────────

    private function normalizeLot(string $raw): array
    {
        $raw = trim($raw);
        $normalized = preg_replace('/\s+/', ' ', $raw); // collapse double spaces

        if ($normalized === '' || $normalized === '?') {
            if ($raw !== '') {
                $this->log("lot unparseable: {$raw}");
            }
            return ['lot' => $raw ?: null, 'lot_number' => null, 'lot_qualifier' => null];
        }

        // Flagged/review lots
        if (preg_match('/^(LOT\s*)?ANNEX|^[NS]\s*\d+\/\d+\s*Annex|^(N|S)\s+(Side|1\/2\s+Annex)|^UNPLATTED|^SW\s+CORNER/i', $normalized)) {
            $this->log("lot flagged for review: {$raw}");
            return ['lot' => $normalized, 'lot_number' => null, 'lot_qualifier' => null];
        }

        // Strip "LOT" prefix (may be missing or typo'd)
        $body = preg_replace('/^LOT\s*/i', '', $normalized);

        // Extract leading lot number (may be integer or letter code like A, AA, LOT B, etc.)
        // Standard: "4", "4 N 1/2", "4B", "48/49"
        if (preg_match('/^(\d+)\s*(.*)?$/', $body, $m)) {
            $lotNumber = (int) $m[1];
            $qualifier = trim($m[2] ?? '');
            // Remove trailing "foot" / "head" placement hints embedded in lot field
            $qualifier = $this->stripPlacementFromQualifier($qualifier);
            return [
                'lot'           => $normalized,
                'lot_number'    => $lotNumber,
                'lot_qualifier' => $qualifier !== '' ? $qualifier : null,
            ];
        }

        // Letter-coded lots (A, B, AA, BB, etc.) — non-numeric, flag and preserve
        $this->log("lot has non-numeric identifier: {$raw}");
        return ['lot' => $normalized, 'lot_number' => null, 'lot_qualifier' => null];
    }

    private function stripPlacementFromQualifier(string $q): string
    {
        return trim(preg_replace('/\b(?:foot|head|ft\.?)\b/i', '', $q));
    }

    // ── Block normalizer ──────────────────────────────────────────────────────

    private function normalizeBlock(string $raw): array
    {
        // Collapse double spaces, strip trailing whitespace (data quality issue #7/#8)
        $normalized = preg_replace('/\s+/', ' ', trim($raw));

        if ($normalized === '') {
            return ['block' => null, 'block_number' => null, 'block_suffix' => null];
        }

        // Flagged blocks
        if (preg_match('/annex|unplatted|Vet\s*Plot|Baby\s*Plot|2-ROW/i', $normalized)) {
            $this->log("block flagged for review: {$raw}");
            return ['block' => $normalized, 'block_number' => null, 'block_suffix' => null];
        }

        // Strip "BLK" prefix
        $body = preg_replace('/^BLK\s*/i', '', $normalized);

        // Strip appended vet/plot annotations: "41B - Vet", "93B vetplot"
        $body = preg_replace('/[\s\-]+(?:vet(?:\s*plot)?|plot)$/i', '', trim($body));

        // "NNB" or "NN-A" suffix pattern — number + optional letter suffix
        if (preg_match('/^(\d+)\s*([A-Za-z])?\s*$/', trim($body), $m)) {
            $blockNumber = (int) $m[1];
            $suffix      = isset($m[2]) && $m[2] !== '' ? strtoupper($m[2]) : null;
            return [
                'block'        => $normalized,
                'block_number' => $blockNumber,
                'block_suffix' => $suffix,
            ];
        }

        // Non-standard: "2/29", "29/28", "85B " (already handled above usually)
        $this->log("block non-standard: {$raw}");
        return ['block' => $normalized, 'block_number' => null, 'block_suffix' => null];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Convert an XLS cell value to a trimmed string.
     * PhpSpreadsheet returns dates as DateTimeInterface; xlrd gives floats for XL_CELL_DATE.
     * For date cells, format as Y-m-d rather than the raw float serial.
     */
    private function cellToString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        // Float that came from a numeric cell — preserve as-is (age or date serial)
        if (is_float($value)) {
            // If it looks like an integer age (e.g. 87.0) return "87.0" so parser handles it
            return (string) $value;
        }
        return trim((string) $value);
    }

    /**
     * Collect residual free-form notes that aren't captured by the flag detectors.
     * Strips out pure flag words so they don't clutter the notes field.
     */
    private function extractResidualNotes(
        string $notes,
        string $col6,
        string $col7,
        bool $isVeteran,
        bool $isCremation,
        bool $isDisinterment
    ): string {
        $parts = [];
        foreach ([$notes, $col6, $col7] as $raw) {
            $s = trim($raw);
            if ($s === '') {
                continue;
            }
            // If the entire cell is just a flag word or well-known phrase already captured, skip it
            if (preg_match('/^\s*(vet(?:eran)?|ashes?|cremation|foot|head|only|veteran\s+spouse)\s*$/i', $s)) {
                continue;
            }
            $parts[] = $s;
        }
        return implode('; ', $parts);
    }

    private function appendNote(string $existing, string $addition): string
    {
        $addition = trim($addition);
        if ($addition === '') {
            return $existing;
        }
        return $existing === '' ? $addition : $existing.'; '.$addition;
    }

    private function log(string $message): void
    {
        $this->decisions[] = $message;
    }
}
