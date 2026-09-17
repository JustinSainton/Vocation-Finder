import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import OrgLayout from '../../Layouts/OrgLayout';

interface Props {
    organization: { id: string; name: string; slug: string };
    settings: { staff_may_read_portraits: boolean };
}

/**
 * The settings an organization owns rather than we do.
 *
 * Written as prose with a control in it rather than a row of toggles, because
 * the only setting here decides who may read a minor's account of themselves.
 * An administrator should have to read a sentence before changing it.
 */
export default function OrgSettings() {
    const { organization, settings, status } = usePage().props as unknown as Props & {
        status?: string;
    };
    const [staffMayRead, setStaffMayRead] = useState(settings.staff_may_read_portraits);
    const [saving, setSaving] = useState(false);

    const save = (value: boolean) => {
        setStaffMayRead(value);
        setSaving(true);
        router.put(
            `/org/${organization.slug}/settings`,
            { staff_may_read_portraits: value },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <OrgLayout title="Settings" orgName={organization.name} orgSlug={organization.slug}>
            <div className="max-w-[640px]">
                <p className="type-eyebrow">Settings</p>
                <h1 className="mt-2 font-serif text-3xl text-[var(--color-text)]">
                    Who may read a student&rsquo;s portrait
                </h1>

                <p className="mt-6 text-[var(--color-text-secondary)]">
                    A portrait is what the engine wrote from a student&rsquo;s own answers, in
                    their own words. By default your counsellors and administrators can read
                    the portrait of any student in {organization.name} — they cannot do the
                    work with them otherwise.
                </p>

                <p className="mt-4 text-[var(--color-text-secondary)]">
                    Turning this off does not hide anything from the student and does not
                    delete anything. It only means staff see that a student has completed an
                    assessment, not what they said.
                </p>

                <fieldset className="mt-8 border-t border-[var(--color-divider)] pt-6">
                    <legend className="sr-only">Staff access to portraits</legend>
                    {[
                        {
                            value: true,
                            label: 'Staff may read portraits',
                            detail: 'Counsellors and administrators can open a student’s result.',
                        },
                        {
                            value: false,
                            label: 'Students only',
                            detail: 'Nobody but the student can open their result.',
                        },
                    ].map((option) => (
                        <label
                            key={String(option.value)}
                            className="mt-3 flex cursor-pointer gap-3 border border-[var(--color-divider)] p-4"
                        >
                            <input
                                type="radio"
                                name="staff_may_read_portraits"
                                checked={staffMayRead === option.value}
                                onChange={() => save(option.value)}
                                disabled={saving}
                                className="mt-1"
                            />
                            <span>
                                <span className="block text-[var(--color-text)]">{option.label}</span>
                                <span className="block text-sm text-[var(--color-text-secondary)]">
                                    {option.detail}
                                </span>
                            </span>
                        </label>
                    ))}
                </fieldset>

                {/*
                  * Coaching conversations and the vocational brain are not
                  * governed by this and never have been. Saying so here keeps an
                  * administrator from assuming the switch is broader than it is.
                  */}
                <p className="mt-8 border-l-2 border-[var(--color-accent)] pl-4 text-sm text-[var(--color-text-secondary)]">
                    This setting never covers coaching conversations or a student&rsquo;s
                    vocational brain. Those are not readable by staff or parents under any
                    setting.
                </p>

                {status && <p className="type-meta mt-6">{status}</p>}
            </div>
        </OrgLayout>
    );
}
