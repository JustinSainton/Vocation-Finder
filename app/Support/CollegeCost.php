<?php

namespace App\Support;

use App\Enums\IncomeBand;
use App\Models\College;
use App\Models\User;

/**
 * What a college would actually cost this student.
 *
 * The vision asks for "the real cost picture", and the word doing the work is
 * *real*. The published price is the most misleading number in American
 * education: it is the number that stops students applying, and at any school
 * with an endowment it is almost never the number anyone from a low-income
 * household pays. A tool that repeats the sticker price is a tool that helps
 * the wrong students rule themselves out.
 *
 * So the headline figure here is the average net price for the student's own
 * federal income bracket, and the published price is demoted to context. Both
 * are looked up, never estimated — {@see IncomeBand} uses the federal brackets
 * precisely so that no interpolation is needed, because an interpolated cost
 * shown to a seventeen-year-old deciding whether college is possible is a
 * guess dressed as a fact.
 *
 * When the student has not told us their bracket, this class says so in a
 * sentence rather than silently falling back to the sticker price and letting
 * it read as the answer.
 */
class CollegeCost
{
    /**
     * @return array{
     *     published: int,
     *     published_label: string,
     *     estimated: ?int,
     *     basis: string,
     *     caveat: string,
     * }
     */
    public function for(User $student, College $college): array
    {
        $published = $this->publishedPrice($student, $college);
        $band = $student->household_income_band;
        $net = $this->netPrice($college, $band);

        return [
            'published' => $published,
            'published_label' => $this->publishedLabel($student, $college),
            'estimated' => $net,
            'basis' => $net === null
                ? 'Published price, before any aid.'
                : 'Average paid by students from households earning '.$band->label().', after grants and scholarships.',
            'caveat' => $this->caveat($college, $band, $net, $published),
        ];
    }

    /**
     * The sticker: tuition for this student's residency, plus living costs.
     *
     * Room and board is added rather than footnoted because leaving it out is
     * how a student budgets for a year and runs out of money in March.
     */
    public function publishedPrice(User $student, College $college): int
    {
        $tuition = $college->control->chargesResidentTuition() && $student->home_state === $college->state
            ? $college->tuition_in_state
            : $college->tuition_out_of_state;

        return $tuition + $college->room_and_board;
    }

    /**
     * Looked up, never interpolated. A bracket with no published figure
     * returns null, which the caller must render as "we do not know" rather
     * than as zero.
     */
    public function netPrice(College $college, ?IncomeBand $band): ?int
    {
        if ($band === null) {
            return null;
        }

        $value = ($college->net_price_by_income ?? [])[$band->value] ?? null;

        return $value === null ? null : (int) $value;
    }

    protected function publishedLabel(User $student, College $college): string
    {
        if (! $college->control->chargesResidentTuition()) {
            return 'Published price';
        }

        return $student->home_state === $college->state
            ? 'Published price, in-state'
            : 'Published price, out-of-state';
    }

    /**
     * The sentence that does the actual work.
     */
    protected function caveat(College $college, ?IncomeBand $band, ?int $net, int $published): string
    {
        if ($band === null) {
            return 'This is the published price, which is almost never what a family pays. Tell us your household income bracket and we will show you what students like you actually paid here.';
        }

        if ($net === null) {
            return 'This school has not published what families in your bracket actually paid. The published price is the only figure available, and it is usually higher than the real one — ask their financial aid office directly.';
        }

        if ($net < $published) {
            return 'Students in your bracket paid '.$this->money($published - $net).' less than the published price here, on average. Aid is not a discount you have to negotiate — it is applied after you apply.';
        }

        return 'Aid here does not usually bring the price down much below the published figure. That is worth knowing before you spend an application fee.';
    }

    protected function money(int $dollars): string
    {
        return '$'.number_format($dollars);
    }
}
