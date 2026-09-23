import type { Support } from '@/Components/SupportBlock';
import type {
    CoachActionData,
    CoachSettledData,
    CoachStateData,
    CoachThreadMessageData,
    CoachThreadStepData,
} from '@/types/generated';

export type CoachAction = CoachActionData;

export type ThreadMessage = CoachThreadMessageData;

export type ThreadStep = CoachThreadStepData;

export type ThreadItem = ThreadMessage | ThreadStep;

export type Opening = CoachStateData['opening'];

/** What the server returns once a turn is persisted, streamed or not. */
export type Settled = CoachSettledData;

export type StreamEvent =
    | { type: 'status'; label: string }
    | { type: 'delta'; text: string }
    | ({ type: 'done' } & Settled)
    | { type: 'error'; message: string };

export type { Support };
