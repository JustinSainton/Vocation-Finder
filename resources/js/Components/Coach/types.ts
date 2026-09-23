import type { Support } from '@/Components/SupportBlock';

export interface CoachAction {
    id: string;
    title: string;
    rationale: string | null;
}

export interface ThreadMessage {
    type: 'message';
    id: string;
    role: 'user' | 'assistant';
    content: string;
    at: string;
}

export interface ThreadStep {
    type: 'step';
    id: string;
    title: string;
    rationale: string | null;
    status: 'active' | 'completed' | 'skipped';
    at: string;
}

export type ThreadItem = ThreadMessage | ThreadStep;

export type Opening = 'first' | 'returning' | null;

/** What the server returns once a turn is persisted, streamed or not. */
export interface Settled {
    items: ThreadItem[];
    current_action: CoachAction | null;
    starters: string[];
}

export type StreamEvent =
    | { type: 'status'; label: string }
    | { type: 'delta'; text: string }
    | ({ type: 'done' } & Settled)
    | { type: 'error'; message: string };

export type { Support };
