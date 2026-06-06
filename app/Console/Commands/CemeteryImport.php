<?php

namespace App\Console\Commands;

use App\Import\RowNormalizer;
use App\Models\Cemetery;
use App\Models\Interment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

class CemeteryImport extends Command
{
    protected $signature = 'cemetery:import
        {--file=           : Path to the XLS file}
        {--cemetery=choteau : Cemetery ID or slug (default: choteau)}
        {--dry-run         : Parse and validate without writing to database}
        {--source=         : Label for the import_source column}
        {--truncate        : Clear existing records for this cemetery before import}';

    protected $description = 'Import interment records from an XLS file into the database';

    // Row counters
    private int $processed   = 0;
    private int $imported    = 0;
    private int $skipped     = 0;
    private int $flagged     = 0;

    /** @var array<int,array{row:int,reason:string}> */
    private array $skippedRows = [];
    /** @var array<int,array{row:int,reason:string,decisions:string[]}> */
    private array $flaggedRows = [];

    private string $logPath = '';

    public function handle(): int
    {
        $file     = $this->option('file');
        $cemetery = $this->option('cemetery');
        $dryRun   = (bool) $this->option('dry-run');
        $truncate = (bool) $this->option('truncate');
        $source   = $this->option('source') ?? basename((string) $file);

        // ── Validate file ─────────────────────────────────────────────────────
        if (!$file) {
            $this->error('--file is required.');
            return self::FAILURE;
        }
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        // ── Resolve cemetery ──────────────────────────────────────────────────
        $cemeteryModel = is_numeric($cemetery)
            ? Cemetery::find((int) $cemetery)
            : Cemetery::where('slug', $cemetery)->first();

        if (!$cemeteryModel) {
            $this->error("Cemetery not found: {$cemetery}");
            return self::FAILURE;
        }

        $this->info("Cemetery : {$cemeteryModel->name}");
        $this->info("File     : {$file}");
        $this->info("Source   : {$source}");
        $this->info("Dry run  : ".($dryRun ? 'YES' : 'no'));
        $this->newLine();

        // ── Truncate ──────────────────────────────────────────────────────────
        if ($truncate && !$dryRun) {
            if (!$this->confirm("Delete all existing interments for \"{$cemeteryModel->name}\" before import?")) {
                $this->line('Aborted.');
                return self::SUCCESS;
            }
            $deleted = Interment::where('cemetery_id', $cemeteryModel->id)->delete();
            $this->warn("Deleted {$deleted} existing records.");
        }

        // ── Open log file ─────────────────────────────────────────────────────
        if (!$dryRun) {
            $logDir = storage_path('logs/imports');
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $this->logPath = $logDir.'/'.date('Y-m-d_His').'_'.pathinfo($file, PATHINFO_FILENAME).'.log';
            file_put_contents($this->logPath, "Import log — {$file} → {$cemeteryModel->name}\n".date('c')."\n\n");
        }

        // ── Load spreadsheet ──────────────────────────────────────────────────
        try {
            $spreadsheet = IOFactory::load($file);
        } catch (\Throwable $e) {
            $this->error("Failed to open file: ".$e->getMessage());
            return self::FAILURE;
        }

        $sheet      = $spreadsheet->getActiveSheet();
        $normalizer = new RowNormalizer();
        $headerSkipped = false;

        // ── Iterate rows ──────────────────────────────────────────────────────
        foreach ($sheet->getRowIterator() as $sheetRow) {
            $rowNum = $sheetRow->getRowIndex();

            // Skip header row (row 1)
            if (!$headerSkipped) {
                $headerSkipped = true;
                continue;
            }

            $rawRow = $this->extractRow($sheet, $sheetRow);
            $this->processed++;

            // Normalise
            $result = $normalizer->normalize($rawRow, $rowNum);

            // Blank row
            if ($result === null) {
                $this->recordSkip($rowNum, 'blank row');
                continue;
            }

            ['record' => $record, 'decisions' => $decisions, 'flags' => $flags] = $result;

            // Missing last name — flag for review
            if (($record['last_name'] ?? '') === '') {
                $this->recordFlag($rowNum, 'missing last_name', $decisions, $rawRow);
                continue;
            }

            // Log any interpretation decisions
            if ($decisions) {
                $this->recordDecisions($rowNum, $decisions, $record);
            }

            // Insert
            if (!$dryRun) {
                try {
                    DB::transaction(function () use ($record, $cemeteryModel, $source) {
                        Interment::create(array_merge($record, [
                            'cemetery_id'   => $cemeteryModel->id,
                            'import_source' => $source,
                        ]));
                    });
                    $this->imported++;
                } catch (\Throwable $e) {
                    $this->recordFlag($rowNum, 'DB error: '.$e->getMessage(), $decisions, $rawRow);
                }
            } else {
                $this->imported++; // dry-run counts as "would import"
            }
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $this->printSummary($dryRun);

        if (!$dryRun && $this->logPath) {
            $this->info("Log: {$this->logPath}");
        }

        return self::SUCCESS;
    }

    // ── Row extraction ────────────────────────────────────────────────────────

    /**
     * @return array<int,mixed>
     */
    private function extractRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        \PhpOffice\PhpSpreadsheet\Worksheet\Row $row
    ): array {
        $values = [];
        $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $rowIndex   = $row->getRowIndex();
        foreach ($colLetters as $colLetter) {
            $coord = $colLetter.$rowIndex;
            $cell  = $sheet->getCell($coord);
            $value = $cell->getValue();

            // Handle date serial cells (CLAUDE.md data quality issue #6)
            if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                && SpreadsheetDate::isDateTimeFormatCode(
                    $sheet->getStyle($coord)->getNumberFormat()->getFormatCode()
                )
            ) {
                try {
                    $dt = SpreadsheetDate::excelToDateTimeObject((float) $value);
                    $value = $dt->format('Y-m-d');
                } catch (\Throwable) {
                    // leave as raw
                }
            }

            $values[] = $value;
        }
        return $values;
    }

    // ── Logging helpers ───────────────────────────────────────────────────────

    private function recordSkip(int $rowNum, string $reason): void
    {
        $this->skipped++;
        $this->skippedRows[] = ['row' => $rowNum, 'reason' => $reason];
    }

    /**
     * @param array<string> $decisions
     * @param array<int,mixed> $rawRow
     */
    private function recordFlag(int $rowNum, string $reason, array $decisions, array $rawRow = []): void
    {
        $this->flagged++;
        $this->flaggedRows[] = [
            'row'       => $rowNum,
            'reason'    => $reason,
            'decisions' => $decisions,
            'raw'       => $rawRow,
        ];
        $this->writeLog("ROW {$rowNum} FLAGGED: {$reason}\n  Raw: ".json_encode($rawRow)."\n");
    }

    /**
     * @param array<string>        $decisions
     * @param array<string,mixed>  $record
     */
    private function recordDecisions(int $rowNum, array $decisions, array $record): void
    {
        $this->flagged++;  // counts as needing review
        $this->writeLog(
            "ROW {$rowNum} decisions: ".implode('; ', $decisions)."\n"
            ."  → ".json_encode(array_filter([
                'last_name'  => $record['last_name'],
                'first_name' => $record['first_name'],
                'age'        => $record['age_at_death'],
                'is_veteran' => $record['is_veteran'] ? 'yes' : null,
                'is_cremation' => $record['is_cremation'] ? 'yes' : null,
                'cremation_placement' => $record['cremation_placement'],
                'is_infant'  => $record['is_infant'] ? 'yes' : null,
            ]))."\n"
        );
    }

    private function writeLog(string $text): void
    {
        if ($this->logPath) {
            file_put_contents($this->logPath, $text."\n", FILE_APPEND);
        }
    }

    // ── Summary table ─────────────────────────────────────────────────────────

    private function printSummary(bool $dryRun): void
    {
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Rows processed',    $this->processed],
                [$dryRun ? 'Would import' : 'Imported', $this->imported],
                ['Skipped (blank)',   $this->skipped],
                ['Flagged for review (decisions logged)', $this->flagged],
            ]
        );

        if ($this->flaggedRows) {
            $this->newLine();
            $this->warn('Rows flagged for review:');
            foreach (array_slice($this->flaggedRows, 0, 20) as $item) {
                $this->line("  Row {$item['row']}: {$item['reason']}");
            }
            if (count($this->flaggedRows) > 20) {
                $remaining = count($this->flaggedRows) - 20;
                $this->line("  … and {$remaining} more (see log file)");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN — no records were written to the database.');
        }
    }
}
