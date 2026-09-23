import { useCallback, useEffect, useState } from 'react';
import * as Haptics from 'expo-haptics';
import {
  coachApi,
  type CoachSettled,
  type CoachState,
  type CoachSupport,
  type CoachThreadItem,
} from '../services/api';

/**
 * Ways in when the server has none to offer — an older API, or no portrait
 * yet. Normally the starters come from the student's own portrait.
 */
export const FALLBACK_STARTERS = [
  "I'm not sure what I'm good at",
  "Something's hard this week",
  'What should I do next?',
];

const startersOrFallback = (starters?: string[]) =>
  starters && starters.length > 0 ? starters : FALLBACK_STARTERS;

export type CoachRow =
  | CoachThreadItem
  | { type: 'pending'; id: string; content: string }
  | { type: 'failed'; id: string; content: string };

/**
 * Everything the coach screen knows and does, without any of how it looks.
 *
 * Kept out of the screen so the screen can change shape — a different
 * typing indicator, different starters, a different list — without touching
 * how a turn is sent, retried, persisted or opened.
 *
 * The server is the source of truth for the thread: every turn ends by
 * replacing local rows with what was stored, so what is on screen is what
 * was kept.
 */
export function useCoachConversation(enabled: boolean) {
  const [state, setState] = useState<CoachState | null>(null);
  const [items, setItems] = useState<CoachThreadItem[]>([]);
  const [starters, setStarters] = useState<string[]>([]);
  const [pending, setPending] = useState<string | null>(null);
  const [failed, setFailed] = useState<string | null>(null);
  const [support, setSupport] = useState<CoachSupport | null>(null);
  const [blocked, setBlocked] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  /** What the coach is doing while it is not yet talking, or null when idle. */
  const [thinking, setThinking] = useState<string | null>(null);
  const [settlingAction, setSettlingAction] = useState(false);
  const [checkingHabit, setCheckingHabit] = useState<string | null>(null);

  const applySettled = useCallback((settled: CoachSettled) => {
    setItems(settled.items);
    setStarters(startersOrFallback(settled.starters));
    setState((prev) => (prev ? { ...prev, current_action: settled.current_action } : prev));
  }, []);

  const open = useCallback(async (kind: CoachState['opening']) => {
    setThinking(kind === 'returning' ? 'Catching up on where you left off' : 'Reading your portrait');
    try {
      const res = await coachApi.open();
      applySettled(res);
      if (res.message) {
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => null);
      }
    } catch {
      // The thread stays as it was; the student can still speak first.
    } finally {
      setThinking(null);
    }
  }, [applySettled]);

  const load = useCallback(async () => {
    setLoading(true);
    setBlocked(null);
    try {
      const [coachState, history] = await Promise.all([coachApi.state(), coachApi.history()]);
      setState(coachState);
      setStarters(startersOrFallback(coachState.starters));
      setItems(
        history.items ??
          history.messages.map((m, i) => ({
            type: 'message' as const,
            id: m.id ?? `h-${i}`,
            role: m.role === 'user' ? ('user' as const) : ('assistant' as const),
            content: m.content,
            at: m.at ?? new Date().toISOString(),
          }))
      );
      setLoading(false);
      if (coachState.opening) {
        await open(coachState.opening);
      }
    } catch (err: any) {
      if (err?.status === 403) {
        setBlocked(err?.message ?? 'The coach is not available right now.');
      }
    } finally {
      setLoading(false);
    }
  }, [open]);

  useEffect(() => {
    if (enabled) {
      load();
    }
  }, [enabled, load]);

  const send = useCallback(async (raw: string): Promise<boolean> => {
    const text = raw.trim();
    if (!text || thinking) return false;
    setFailed(null);
    setSupport(null);
    setPending(text);
    setThinking('Thinking it through');
    try {
      const res = await coachApi.message(text);
      if (res.support) {
        setSupport(res.support);
      } else if (res.items) {
        applySettled(res as CoachSettled);
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => null);
      }
      coachApi.state().then(setState).catch(() => null);
      return true;
    } catch {
      setFailed(text);
      return false;
    } finally {
      setPending(null);
      setThinking(null);
    }
  }, [thinking, applySettled]);

  const retry = useCallback(() => (failed ? send(failed) : Promise.resolve(false)), [failed, send]);

  const settleAction = useCallback(async (kind: 'complete' | 'skip') => {
    const action = state?.current_action;
    if (!action || settlingAction) return;
    setSettlingAction(true);
    try {
      const res = kind === 'complete'
        ? await coachApi.completeAction(action.id)
        : await coachApi.skipAction(action.id);
      setState((prev) => (prev ? { ...prev, current_action: res.current_action } : prev));
      setItems((prev) =>
        prev.map((item) =>
          item.type === 'step' && item.id === action.id
            ? { ...item, status: kind === 'complete' ? 'completed' : 'skipped' }
            : item
        )
      );
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => null);
    } catch {
      // The action stays; the student can try again or talk about it.
    } finally {
      setSettlingAction(false);
    }
  }, [state?.current_action, settlingAction]);

  const checkIn = useCallback(async (habitId: string, happened: boolean) => {
    if (checkingHabit) return;
    setCheckingHabit(habitId);
    try {
      await coachApi.checkInHabit(habitId, happened);
      setState(await coachApi.state());
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => null);
    } catch {
      // The row stays unanswered; honesty keeps no matter what.
    } finally {
      setCheckingHabit(null);
    }
  }, [checkingHabit]);

  const rows: CoachRow[] = [
    ...items,
    ...(pending ? [{ type: 'pending' as const, id: 'pending', content: pending }] : []),
    ...(failed ? [{ type: 'failed' as const, id: 'failed', content: failed }] : []),
  ];

  const lastMessage = [...items].reverse().find((item) => item.type === 'message');
  const coachSpokeLast = !lastMessage || (lastMessage.type === 'message' && lastMessage.role === 'assistant');

  return {
    state,
    items,
    rows,
    starters,
    support,
    blocked,
    loading,
    thinking,
    pending,
    failed,
    coachSpokeLast,
    settlingAction,
    checkingHabit,
    load,
    send,
    retry,
    settleAction,
    checkIn,
  };
}
