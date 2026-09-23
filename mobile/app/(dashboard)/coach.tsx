import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  View,
  StyleSheet,
  FlatList,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { SingleLineInput } from '../../components/ui/SingleLineInput';
import { CoachBubble, CoachStepMarker } from '../../components/coach/CoachBubble';
import { CoachStarterChips } from '../../components/coach/CoachStarterChips';
import { CoachThinking } from '../../components/coach/CoachThinking';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useThemeStore } from '../../stores/themeStore';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import {
  coachApi,
  type CoachAction,
  type CoachHabit,
  type CoachReadiness,
  type CoachSettled,
  type CoachState,
  type CoachSupport,
  type CoachThreadItem,
} from '../../services/api';

type Row =
  | CoachThreadItem
  | { type: 'pending'; id: string; content: string }
  | { type: 'failed'; id: string; content: string };

function Eyebrow({ children }: { children: React.ReactNode }) {
  const { colors } = useTheme();
  return (
    <Typography variant="caption" family="sans" color={colors.textSecondary} style={styles.eyebrow}>
      {children}
    </Typography>
  );
}

/**
 * The coach: the conversation is the screen.
 *
 * The coach speaks first when the server says an opener is due — straight
 * after the assessment, or on coming back after a real gap — so the student
 * never faces an empty box. The one step they are working on is pinned above
 * the thread; everything else is one tap away.
 */
export default function CoachScreen() {
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();
  const params = useLocalSearchParams<{ say?: string }>();
  const setPreference = useThemeStore((s) => s.setPreference);
  const [state, setState] = useState<CoachState | null>(null);
  const [items, setItems] = useState<CoachThreadItem[]>([]);
  const [starters, setStarters] = useState<string[]>([]);
  const [pending, setPending] = useState<string | null>(null);
  const [failed, setFailed] = useState<string | null>(null);
  const [support, setSupport] = useState<CoachSupport | null>(null);
  const [blocked, setBlocked] = useState<string | null>(null);
  const [input, setInput] = useState(typeof params.say === 'string' ? params.say : '');
  const [loading, setLoading] = useState(false);
  const [thinking, setThinking] = useState<string | null>(null);
  const [showReadiness, setShowReadiness] = useState(false);
  const [settlingAction, setSettlingAction] = useState(false);
  const [checkingHabit, setCheckingHabit] = useState<string | null>(null);
  const flatListRef = useRef<FlatList<Row>>(null);

  useEffect(() => {
    const previous = useThemeStore.getState().preference;
    setPreference('dark');
    return () => setPreference(previous);
  }, [setPreference]);

  const applySettled = useCallback((settled: CoachSettled) => {
    setItems(settled.items);
    setStarters(settled.starters);
    setState((prev) => (prev ? { ...prev, current_action: settled.current_action } : prev));
  }, []);

  const openConversation = useCallback(async (kind: CoachState['opening']) => {
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
      setStarters(coachState.starters ?? []);
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
        await openConversation(coachState.opening);
      }
    } catch (err: any) {
      if (err?.status === 403) {
        setBlocked(err?.message ?? 'The coach is not available right now.');
      }
    } finally {
      setLoading(false);
    }
  }, [openConversation]);

  useEffect(() => {
    if (isEnabled('pathway_coach')) {
      load();
    }
  }, [isEnabled, load]);

  const send = useCallback(async (raw: string) => {
    const text = raw.trim();
    if (!text || thinking) return;
    setInput('');
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
    } catch {
      setFailed(text);
    } finally {
      setPending(null);
      setThinking(null);
    }
  }, [thinking, applySettled]);

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
      const fresh = await coachApi.state();
      setState(fresh);
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => null);
    } catch {
      // The row stays unanswered; honesty keeps no matter what.
    } finally {
      setCheckingHabit(null);
    }
  }, [checkingHabit]);

  if (!isEnabled('pathway_coach')) return null;

  const action: CoachAction | null = state?.current_action ?? null;
  const readiness: CoachReadiness | null = state?.readiness ?? null;
  const habits: CoachHabit[] = state?.habits ?? [];

  const rows: Row[] = [
    ...items,
    ...(pending ? [{ type: 'pending' as const, id: 'pending', content: pending }] : []),
    ...(failed ? [{ type: 'failed' as const, id: 'failed', content: failed }] : []),
  ];

  const lastMessage = [...items].reverse().find((item) => item.type === 'message');
  const coachSpokeLast = !lastMessage || (lastMessage.type === 'message' && lastMessage.role === 'assistant');
  const showStarters = !thinking && !failed && !input && coachSpokeLast;

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]} edges={['top']}>
      <KeyboardAvoidingView
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={90}
      >
        <View style={styles.header}>
          <Eyebrow>Your coach</Eyebrow>
          {readiness ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Where you are"
              onPress={() => setShowReadiness((v) => !v)}
              style={styles.readinessToggle}
              hitSlop={12}
            >
              <Typography variant="small" family="sans" color={colors.textSecondary}>
                {readiness.level_label} {showReadiness ? '–' : '+'}
              </Typography>
            </Pressable>
          ) : null}
        </View>
        <View style={[styles.divider, { backgroundColor: colors.divider }]} />

        {loading && !state ? (
          <View style={styles.centered}>
            <ActivityIndicator size="small" color={colors.accent} />
          </View>
        ) : blocked ? (
          <View style={styles.centered}>
            <Typography variant="body" style={styles.centeredText}>{blocked}</Typography>
          </View>
        ) : (
          <FlatList
            ref={flatListRef}
            data={rows}
            keyExtractor={(item) => `${item.type}-${item.id}`}
            showsVerticalScrollIndicator={false}
            keyboardShouldPersistTaps="handled"
            onContentSizeChange={() => flatListRef.current?.scrollToEnd({ animated: true })}
            contentContainerStyle={styles.listContent}
            ListHeaderComponent={
              <View>
                {showReadiness && readiness ? (
                  <View style={styles.section}>
                    <Eyebrow>Where you are</Eyebrow>
                    <Typography variant="body" color={colors.textSecondary}>
                      {readiness.level_description}
                    </Typography>
                    <View style={styles.moveBlock}>
                      <Eyebrow>What moves it</Eyebrow>
                      <Typography variant="body">{readiness.what_moves_it}</Typography>
                    </View>
                  </View>
                ) : null}

                {action ? (
                  <View style={[styles.actionCard, { borderColor: colors.accent, backgroundColor: colors.surfaceElevated }]}>
                    <Eyebrow>Your one step</Eyebrow>
                    <Typography variant="bodyLarge" family="sans" style={styles.actionTitle}>
                      {action.title}
                    </Typography>
                    {action.rationale ? (
                      <Typography variant="body" color={colors.textSecondary} style={styles.actionRationale}>
                        {action.rationale}
                      </Typography>
                    ) : null}
                    <View style={styles.actionRow}>
                      <View style={styles.actionBtn}>
                        <Button
                          title={settlingAction ? '…' : 'I did this'}
                          onPress={() => settleAction('complete')}
                          disabled={settlingAction}
                        />
                      </View>
                      <View style={styles.actionBtn}>
                        <Button
                          title="Set it down"
                          onPress={() => settleAction('skip')}
                          disabled={settlingAction}
                          variant="secondary"
                        />
                      </View>
                    </View>
                  </View>
                ) : null}

                {habits.length > 0 ? (
                  <View style={styles.section}>
                    <Eyebrow>What you keep doing</Eyebrow>
                    {habits.map((habit) => (
                      <View key={habit.id} style={[styles.habitRow, { borderColor: colors.divider }]}>
                        <Typography variant="body" style={styles.habitTitle}>
                          {habit.title}
                        </Typography>
                        <Typography variant="small" family="sans" color={colors.textSecondary}>
                          {habit.standing} · {habit.cadence}
                        </Typography>
                        {habit.next_move ? (
                          <Typography variant="small" color={colors.textSecondary} style={styles.habitMove}>
                            {habit.next_move}
                          </Typography>
                        ) : null}
                        {!habit.answered_today ? (
                          <View style={styles.habitButtons}>
                            <View style={styles.habitBtn}>
                              <Button
                                title="I did this"
                                onPress={() => checkIn(habit.id, true)}
                                disabled={checkingHabit === habit.id}
                                variant="secondary"
                              />
                            </View>
                            <View style={styles.habitBtn}>
                              <Button
                                title="Not today"
                                onPress={() => checkIn(habit.id, false)}
                                disabled={checkingHabit === habit.id}
                                variant="secondary"
                              />
                            </View>
                          </View>
                        ) : null}
                      </View>
                    ))}
                  </View>
                ) : null}

                {state?.invitation?.prompt ? (
                  <View style={[styles.invitation, { borderLeftColor: colors.accent }]}>
                    <Eyebrow>Something you keep coming back to</Eyebrow>
                    <Typography variant="body">{state.invitation.prompt}</Typography>
                  </View>
                ) : null}

                {support ? (
                  <View style={[styles.supportCard, { borderLeftColor: colors.accent }]}>
                    <Typography variant="bodyLarge" style={styles.supportHeading}>
                      {support.heading}
                    </Typography>
                    {support.body.map((paragraph, i) => (
                      <Typography key={i} variant="body" color={colors.textSecondary} style={styles.supportBody}>
                        {paragraph}
                      </Typography>
                    ))}
                    {support.resources.map((resource, i) => (
                      <View key={i} style={styles.supportResource}>
                        <Typography variant="body">{resource.name}</Typography>
                        <Typography variant="small" family="sans" color={colors.textSecondary}>
                          {resource.contact}
                        </Typography>
                        {resource.note ? (
                          <Typography variant="small" color={colors.textSecondary}>
                            {resource.note}
                          </Typography>
                        ) : null}
                      </View>
                    ))}
                  </View>
                ) : null}
              </View>
            }
            renderItem={({ item, index }) => {
              if (item.type === 'step') return <CoachStepMarker item={item} />;
              if (item.type === 'pending') return <CoachBubble role="user" content={item.content} />;
              if (item.type === 'failed') {
                return (
                  <View>
                    <CoachBubble role="user" content={item.content} />
                    <View style={styles.failedRow}>
                      <Typography variant="small" family="sans" color={colors.textSecondary}>
                        That did not reach your coach. What you wrote is kept.
                      </Typography>
                      <Pressable accessibilityRole="button" onPress={() => send(item.content)} hitSlop={12}>
                        <Typography variant="small" family="sans" color={colors.accent}>Try again</Typography>
                      </Pressable>
                    </View>
                  </View>
                );
              }
              const previous = rows[index - 1];
              const showLabel = !previous || previous.type !== 'message' || previous.role !== item.role;
              return <CoachBubble role={item.role} content={item.content} at={item.at} showLabel={showLabel} />;
            }}
            ListFooterComponent={thinking ? <CoachThinking label={thinking} /> : null}
          />
        )}

        <View style={[styles.composer, { borderTopColor: colors.divider, backgroundColor: colors.background }]}>
          {showStarters && !blocked ? (
            <CoachStarterChips starters={starters} disabled={!!thinking} onPick={send} />
          ) : null}
          <View style={styles.inputRow}>
            <View style={styles.inputFlex}>
              <SingleLineInput
                value={input}
                onChangeText={setInput}
                placeholder="Say what you are actually thinking…"
                autoCapitalize="sentences"
                returnKeyType="send"
                onSubmitEditing={() => send(input)}
              />
            </View>
            <Pressable
              accessibilityRole="button"
              onPress={() => send(input)}
              disabled={!!thinking || !input.trim()}
              style={[styles.sendBtn, { backgroundColor: colors.text, opacity: thinking || !input.trim() ? 0.4 : 1 }]}
            >
              <Typography variant="small" family="sans" color={colors.background}>Send</Typography>
            </Pressable>
          </View>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  keyboardView: { flex: 1 },
  header: {
    paddingHorizontal: 24,
    paddingTop: 16,
    paddingBottom: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  readinessToggle: { minHeight: 32, justifyContent: 'center' },
  divider: { height: 1, marginHorizontal: 24 },
  centered: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 32 },
  centeredText: { textAlign: 'center' },
  listContent: { paddingHorizontal: 24, paddingVertical: 16 },
  eyebrow: { textTransform: 'uppercase', letterSpacing: 1.5, marginBottom: 8 },
  actionCard: {
    borderWidth: 1,
    borderRadius: 6,
    padding: spacing.lg,
    marginBottom: spacing.lg,
  },
  actionTitle: { marginBottom: 8, fontSize: 18, lineHeight: 25 },
  actionRationale: { marginBottom: 16 },
  actionRow: { flexDirection: 'row', gap: 8 },
  actionBtn: { flex: 1 },
  section: { marginBottom: 24 },
  moveBlock: { marginTop: 16 },
  habitRow: { borderBottomWidth: 1, paddingVertical: 12 },
  habitTitle: { marginBottom: 4 },
  habitMove: { marginTop: 4 },
  habitButtons: { flexDirection: 'row', gap: 8, marginTop: 12 },
  habitBtn: { flex: 1 },
  invitation: { borderLeftWidth: 2, paddingLeft: 16, marginBottom: 24 },
  supportCard: { borderLeftWidth: 2, paddingLeft: 16, marginBottom: 24 },
  supportHeading: { marginBottom: 8 },
  supportBody: { marginBottom: 8 },
  supportResource: { marginTop: 8 },
  failedRow: { flexDirection: 'row', justifyContent: 'flex-end', gap: 12, marginTop: -8, marginBottom: 16 },
  composer: { paddingHorizontal: 24, paddingBottom: 8, borderTopWidth: 1 },
  inputRow: { flexDirection: 'row', gap: 8, paddingTop: 8, alignItems: 'flex-end' },
  inputFlex: { flex: 1 },
  sendBtn: { paddingHorizontal: 16, minHeight: 44, justifyContent: 'center' },
});
