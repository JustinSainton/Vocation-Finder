<?php

namespace App\Enums;

use App\Models\CollegeResource;

/**
 * The things that exist at essentially every US college, and what each is
 * actually for.
 *
 * The vision asks the tool to map out "everything publicly available about
 * that university and that city". The tempting implementation is a table of
 * specific offices, hours and phone numbers for each institution — and the
 * moment that data is typed from memory rather than imported, it is a
 * fabricated record about a real organisation that a student will act on. The
 * same refusal as the college net prices in 4.2.
 *
 * So this enum carries what is true of every campus, and
 * {@see CollegeResource} carries the institution-specific links
 * when somebody has actually imported them. A student always gets the accurate
 * general answer; they get the specific URL when we genuinely have one.
 *
 * The descriptions are deliberately about **what it is for and who uses it**,
 * because the reason first-generation students do not use these services is
 * almost never that they cannot find the building. It is that they believe
 * these places are for people who are failing.
 */
enum CampusResourceKind: string
{
    case Advising = 'advising';
    case Tutoring = 'tutoring';
    case WritingCenter = 'writing_center';
    case CareerServices = 'career_services';
    case FinancialAid = 'financial_aid';
    case Accessibility = 'accessibility';
    case Counseling = 'counseling';
    case FoodSecurity = 'food_security';
    case Transit = 'transit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Advising => 'Academic advising',
            self::Tutoring => 'Tutoring',
            self::WritingCenter => 'The writing center',
            self::CareerServices => 'Career services',
            self::FinancialAid => 'Financial aid office',
            self::Accessibility => 'Disability and accessibility services',
            self::Counseling => 'Counseling',
            self::FoodSecurity => 'Food pantry and meal support',
            self::Transit => 'Getting around',
        };
    }

    /**
     * What it is for, in terms that do not imply the student is in trouble.
     */
    public function description(): string
    {
        return match ($this) {
            self::Advising => 'The person who signs off on what you take and knows which requirements you have actually met. Book them once a term, not once a crisis.',
            self::Tutoring => 'Free, and used most by the students getting the best grades. That is the part nobody tells you.',
            self::WritingCenter => 'They read drafts. Not for spelling — for whether the argument holds. Take them something unfinished.',
            self::CareerServices => 'They hold the employer relationships and the internship lists, and most of it never gets posted publicly. Go in your first year, not your last.',
            self::FinancialAid => 'Your aid is recalculated every year, and it changes if your family\'s situation changes. Telling them early is how it gets fixed; telling them in April is how it does not.',
            self::Accessibility => 'Accommodations are a legal right, they are not a favour, and they cover far more than most people assume — including conditions you would not think to mention.',
            self::Counseling => 'Free sessions, usually a fixed number a year. Being overwhelmed in your first term is the ordinary experience, not the exceptional one.',
            self::FoodSecurity => 'Most campuses have a pantry and most students who need one do not use it. Using it is not a statement about you.',
            self::Transit => 'Your student ID is often a bus pass. Work out how you get to a job or an internship across town before you need to, because that is what quietly rules them out.',
        };
    }

    /**
     * The one question to walk in and ask. A student who has a sentence ready
     * goes in; a student who has to invent one at the desk does not.
     */
    public function opener(): string
    {
        return match ($this) {
            self::Advising => '"Can we look at whether what I am taking still adds up to the degree I think it does?"',
            self::Tutoring => '"I am following the lectures but I am losing it on the problem sets. Can somebody work through one with me?"',
            self::WritingCenter => '"This is a draft and it is not finished. Does the argument hold up?"',
            self::CareerServices => '"What do students in my subject usually do the summer after second year, and how do they get it?"',
            self::FinancialAid => '"My family\'s situation has changed since the form. What do you need from me?"',
            self::Accessibility => '"What does the process look like, and what would I need to document?"',
            self::Counseling => '"I would like to talk to somebody. What is the wait, and what do I do in the meantime?"',
            self::FoodSecurity => '"What are the hours, and do I need to bring anything?"',
            self::Transit => '"Does my ID work on the city buses, and where do I get the pass?"',
        };
    }
}
