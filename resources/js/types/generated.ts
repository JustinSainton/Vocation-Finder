export type BrainstormInvitationData = {
opens_with: RecurringThemeData | null,
prompt: string,
};
export type CoachActionData = {
id: string,
title: string,
rationale: string | null,
};
export type CoachHandoffData = {
state: 'open' | 'account' | 'consent' | 'checkout' | 'later',
eyebrow: string,
headline: string,
body: string,
href: string | null,
cta: string | null,
starters: string[],
};
export type CoachSettledData = {
items: (CoachThreadMessageData| CoachThreadStepData)[],
current_action: CoachActionData | null,
starters: string[],
};
export type CoachStateData = {
current_action: CoachActionData | null,
readiness: ReadinessData,
habits: HabitData[],
invitation: BrainstormInvitationData | null,
starters: string[],
opening: 'first' | 'returning' | null,
};
export type CoachThreadMessageData = {
type: 'message',
id: string,
role: 'user' | 'assistant',
content: string,
at: string,
};
export type CoachThreadStepData = {
type: 'step',
id: string,
title: string,
rationale: string | null,
status: 'active' | 'completed' | 'skipped',
at: string,
};
export type CrisisResourceData = {
name: string,
contact: string,
note: string,
};
export type CrisisSupportData = {
heading: string,
body: string[],
resources: CrisisResourceData[],
};
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type HabitData = {
id: string,
title: string,
why: string | null,
cadence: string,
standing: string,
meaning: string,
next_move: string,
answered_today: boolean,
};
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type ReadinessData = {
level: string,
level_label: string,
level_description: string,
what_moves_it: string,
factors: Record<string, ReadinessFactorData>,
history: ReadinessPointData[],
};
export type ReadinessFactorData = {
label: string,
standing: string,
move: string,
};
export type ReadinessPointData = {
on: string,
level: string,
because?: string,
};
export type RecurringThemeData = {
term: string,
entry_count: number,
first_said_on: string,
last_said_on: string,
in_their_words: SaidData[],
needs_human: boolean,
};
export type SaidData = {
said_on: string,
content: string,
};
