<?php

namespace App\Console\Commands;

use App\Enums\AdmissionStanding;
use App\Enums\CollegeControl;
use App\Enums\CollegeKind;
use App\Enums\IncomeBand;
use App\Models\College;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Load colleges from the federal College Scorecard extract.
 *
 * There is deliberately no seeder full of hand-typed figures for real
 * institutions. A net price is a number a seventeen-year-old makes a financial
 * decision on, and one typed from memory into a seeder is a fabricated record
 * about a real organisation that looks exactly as trustworthy as a real one.
 * Everything in the `colleges` table therefore arrives from a file somebody
 * can point at.
 *
 * The column names below are the Scorecard's own, unchanged, so the mapping
 * can be checked against the federal data dictionary rather than trusted:
 *
 * - `CONTROL` 1/2/3 → public, private nonprofit, private for-profit
 * - `NPT41_PUB`…`NPT45_PUB` → average net price by household income bracket,
 *   which is the reason {@see IncomeBand} uses those five brackets and not
 *   brackets of our own choosing.
 *
 * A GPA range is **not** in the Scorecard — it comes from each institution's
 * Common Data Set — so `GPA_25` / `GPA_75` are optional columns and stay null
 * when absent. A null range surfaces as
 * {@see AdmissionStanding::Unknown}, which says so out loud rather
 * than guessing from acceptance rate.
 */
class ImportColleges extends Command
{
    protected $signature = 'colleges:import {path : Path to a College Scorecard CSV extract}';

    protected $description = 'Import colleges from a College Scorecard CSV extract';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            $this->error('That file has no header row.');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, array_pad($row, count($header), null));

            /*
             | A row with no name, no tuition or no application URL cannot
             | produce an honest page, so it is skipped rather than imported
             | with blanks that would render as free tuition.
             */
            if (! $this->usable($record)) {
                $skipped++;

                continue;
            }

            College::updateOrCreate(
                ['slug' => Str::slug($record['INSTNM'])],
                $this->attributes($record),
            );

            $imported++;
        }

        fclose($handle);

        $this->info("Imported {$imported}. Skipped {$skipped} rows that could not produce an honest page.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, ?string>  $record
     */
    protected function usable(array $record): bool
    {
        return filled($record['INSTNM'] ?? null)
            && filled($record['TUITIONFEE_OUT'] ?? null)
            && filled($record['APPLICATION_URL'] ?? null);
    }

    /**
     * @param  array<string, ?string>  $record
     * @return array<string, mixed>
     */
    protected function attributes(array $record): array
    {
        return [
            'name' => $record['INSTNM'],
            'city' => $record['CITY'] ?? '',
            'state' => $record['STABBR'] ?? '',
            'control' => $this->control($record['CONTROL'] ?? null),
            'kind' => $this->kind($record),
            'acceptance_rate' => $this->number($record['ADM_RATE'] ?? null),
            'gpa_25th' => $this->number($record['GPA_25'] ?? null),
            'gpa_75th' => $this->number($record['GPA_75'] ?? null),
            'test_optional' => ($record['TEST_OPTIONAL'] ?? '1') !== '0',
            'tuition_in_state' => (int) ($record['TUITIONFEE_IN'] ?? $record['TUITIONFEE_OUT']),
            'tuition_out_of_state' => (int) $record['TUITIONFEE_OUT'],
            'room_and_board' => (int) ($record['ROOMBOARD_ON'] ?? 0),
            'net_price_by_income' => $this->netPrices($record),
            'application_system' => $record['APPLICATION_SYSTEM'] ?? null,
            'application_url' => $record['APPLICATION_URL'],
            'deadline_month' => $this->number($record['DEADLINE_MONTH'] ?? null),
            'deadline_day' => $this->number($record['DEADLINE_DAY'] ?? null),
            'application_fee' => (int) ($record['APPLICATION_FEE'] ?? 0),
            'fee_waiver_available' => ($record['FEE_WAIVER'] ?? '1') !== '0',
        ];
    }

    protected function control(?string $value): CollegeControl
    {
        return match ($value) {
            '1' => CollegeControl::Public,
            '3' => CollegeControl::PrivateForProfit,
            default => CollegeControl::PrivateNonprofit,
        };
    }

    /**
     * `ICLEVEL` 2 and 3 are the two-year and under-two-year institutions —
     * community colleges and trade schools. They are imported into the same
     * table as the universities on purpose: a student whose path runs through
     * a welding certificate should not have to leave the college explorer to
     * find it.
     *
     * @param  array<string, ?string>  $record
     */
    protected function kind(array $record): CollegeKind
    {
        return match ($record['ICLEVEL'] ?? '1') {
            '2' => CollegeKind::Community,
            '3' => CollegeKind::Technical,
            default => ($record['KIND'] ?? null) === 'liberal_arts' ? CollegeKind::LiberalArts : CollegeKind::University,
        };
    }

    /**
     * The Scorecard uses `NULL` and `PrivacySuppressed` as string sentinels.
     * Both must become null rather than zero — a suppressed net price
     * rendered as $0 would be the most consequential typo in the product.
     */
    protected function number(?string $value): float|int|null
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return $value + 0;
    }

    /**
     * @param  array<string, ?string>  $record
     * @return array<string, int>
     */
    protected function netPrices(array $record): array
    {
        $columns = [
            IncomeBand::UpTo30k->value => ['NPT41_PUB', 'NPT41_PRIV'],
            IncomeBand::From30kTo48k->value => ['NPT42_PUB', 'NPT42_PRIV'],
            IncomeBand::From48kTo75k->value => ['NPT43_PUB', 'NPT43_PRIV'],
            IncomeBand::From75kTo110k->value => ['NPT44_PUB', 'NPT44_PRIV'],
            IncomeBand::Over110k->value => ['NPT45_PUB', 'NPT45_PRIV'],
        ];

        $prices = [];

        foreach ($columns as $band => $candidates) {
            foreach ($candidates as $column) {
                $value = $this->number($record[$column] ?? null);

                if ($value !== null) {
                    $prices[$band] = (int) $value;

                    break;
                }
            }
        }

        return $prices;
    }
}
