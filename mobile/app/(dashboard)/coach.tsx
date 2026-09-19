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
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { SingleLineInput } from '../../components/ui/SingleLineInput';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useThemeStore } from '../../stores/themeStore';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import {
  coachApi,
  type CoachAction,
  type CoachHabit,
  type CoachMessage,
  type CoachReadiness,
  type CoachState,
} from '../../services/api';

interface ThreadMessage {
  id: string;
  role: string;
  content: string;
}

interface SupportResource {
  name: string;
  contact: string;
  note?: string;
}

interface SupportCard {
  heading: string;
  body: string[];
  resources: SupportResource[];
}

function Eyebrow({ children }: { children: React.ReactNode }) {
  const { colors } = useTheme();
  return (
    <Typography
      variant="caption"
      family="sans"
      color={colors.textSecondary}
      style={styles.eyebrow}
    >
      {children}
    </Typography>
  );
}

export default function CoachScreen() {
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();
  const setPreference = useThemeStore((s) => s.setPreference);
  const [state, setState] = useState<CoachState | null>(null);
  const [messages, setMessages] = useState<ThreadMessage[]>([]);
  const [support, setSupport] = useState<SupportCard | null>(null);
  const [blocked, setBlocked] = useState<string | null>(null);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [settlingAction, setSettlingAction] = useState(false);
  const [checkingHabit, setCheckingHabit] = useState<string | null>(null);
  const flatListRef = useRef<FlatList>(null);

  useEffect(() => {
    const previous = useThemeStore.getState().preference;
    setPreference('dark');
    return () => setPreference(previous);
  }, [setPreference]);

  const load = useCallback(async () => {
    setLoading(true);
    setBlocked(null);
    try {
      const [coachState, history] = await Promise.all([
        coachApi.state(),
        coachApi.history(),
      ]);
      setState(coachState);
      setMessages(
        history.messages.map((m: CoachMessage, i: number) => ({
          id: `h-${i}`,
          role: m.role,
          content: m.content,
        }))
      );
    } catch (err: any) {
      if (err?.status === 403) {
        setBlocked(err?.message ?? 'The coach is not available right now.');
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isEnabled('pathway_coach')) {
      load();
    }
  }, [isEnabled, load]);

  const sendMessage = useCallback(async () => {
    const text = input.trim();
    if (!text || sending) return;
    setInput('');
    setSupport(null);
    setMessages((prev) => [...prev, { id: `u-${Date.now()}`, role: 'user', content: text }]);
    setSending(true);
    try {
      const res = await coachApi.message(text);
      if (res.support) {
        setSupport(res.support as SupportCard);
      } else if (res.message) {
        setMessages((prev) => [...prev, { id: `a-${Date.now()}`, role: 'assistant', content: res.message as string }]);
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => null);
      }
      const fresh = await coachApi.state();
      setState(fresh);
    } catch {
      setMessages((prev) => [
        ...prev,
        { id: `e-${Date.now()}`, role: 'assistant', content: 'Something went wrong. Could you try again?' },
      ]);
    } finally {
      setSending(false);
    }
  }, [input, sending]);

  const settleAction = useCallback(async (kind: 'complete' | 'skip') => {
    const action = state?.current_action;
    if (!action || settlingAction) return;
    setSettlingAction(true);
    try {
      const res = kind === 'complete'
        ? await coachApi.completeAction(action.id)
        : await coachApi.skipAction(action.id);
      setState((prev) => (prev ? { ...prev, current_action: res.current_action } : prev));
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

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]} edges={['top']}>
      <KeyboardAvoidingView
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={90}
      >
        <View style={styles.header}>
          <Eyebrow>Your coach</Eyebrow>
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
            data={messages}
            keyExtractor={(item) => item.id}
            showsVerticalScrollIndicator={false}
            onContentSizeChange={() => flatListRef.current?.scrollToEnd()}
            contentContainerStyle={styles.listContent}
            ListHeaderComponent={
              <View>
                {action ? (
                  <View style={[styles.actionCard, { borderColor: colors.accent }]}>
                    <Eyebrow>What you are doing now</Eyebrow>
                    <Typography variant="bodyLarge" style={styles.actionTitle}>
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
                          title={settlingAction ? '…' : 'Done'}
                          onPress={() => settleAction('complete')}
                          disabled={settlingAction}
                          variant="secondary"
                        />
                      </View>
                      <View style={styles.actionBtn}>
                        <Button
                          title="Set down"
                          onPress={() => settleAction('skip')}
                          disabled={settlingAction}
                          variant="secondary"
                        />
                      </View>
                    </View>
                  </View>
                ) : null}

                {readiness ? (
                  <View style={styles.section}>
                    <Eyebrow>Where you are</Eyebrow>
                    <Typography variant="bodyLarge" style={styles.readinessLevel}>
                      {readiness.level_label}
                    </Typography>
                    <Typography variant="body" color={colors.textSecondary}>
                      {readiness.level_description}
                    </Typography>
                    <View style={styles.moveBlock}>
                      <Eyebrow>What moves it</Eyebrow>
                      <Typography variant="body">{readiness.what_moves_it}</Typography>
                    </View>
                  </View>
                ) : null}

                {habits.length > 0 ? (
                  <View style={styles.section}>
                    <Eyebrow>Your habits</Eyebrow>
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
                          <View style={styles.habitRow2}>
                            <View style={styles.habitBtn}>
                              <Button
                                title="Yes"
                                onPress={() => checkIn(habit.id, true)}
                                disabled={checkingHabit === habit.id}
                                variant="secondary"
                              />
                            </View>
                            <View style={styles.habitBtn}>
                              <Button
                                title="No"
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
                  <View style={[styles.invitation, { borderColor: colors.divider }]}>
                    <Eyebrow>An invitation</Eyebrow>
                    <Typography variant="body">{state.invitation.prompt}</Typography>
                  </View>
                ) : null}

                {support ? (
                  <View style={[styles.supportCard, { borderColor: colors.accent }]}>
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

                {messages.length > 0 ? (
                  <View style={styles.threadLabel}>
                    <Eyebrow>The conversation</Eyebrow>
                  </View>
                ) : null}
              </View>
            }
            renderItem={({ item }) => (
              <View
                style={[
                  styles.bubble,
                  item.role === 'user'
                    ? [styles.userBubble, { borderColor: colors.divider }]
                    : [styles.assistantBubble, { backgroundColor: 'rgba(250,250,247,0.06)' }],
                ]}
              >
                <Typography variant="body" style={styles.bubbleText}>
                  {item.content}
                </Typography>
              </View>
            )}
            ListFooterComponent={
              sending ? (
                <View style={[styles.assistantBubble, { backgroundColor: 'rgba(250,250,247,0.06)' }]}>
                  <ActivityIndicator size="small" color={colors.accent} />
                </View>
              ) : null
            }
          />
        )}

        <View style={[styles.inputRow, { borderTopColor: colors.divider, backgroundColor: colors.background }]}>
          <View style={styles.inputFlex}>
            <SingleLineInput
              value={input}
              onChangeText={setInput}
              placeholder="Talk to your coach…"
              autoCapitalize="sentences"
              returnKeyType="send"
              onSubmitEditing={sendMessage}
            />
          </View>
          <Pressable
            onPress={sendMessage}
            disabled={sending || !input.trim()}
            style={[styles.sendBtn, { backgroundColor: colors.text, opacity: sending || !input.trim() ? 0.5 : 1 }]}
          >
            <Typography variant="small" family="sans" color={colors.background}>Send</Typography>
          </Pressable>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  keyboardView: { flex: 1 },
  header: { paddingHorizontal: 24, paddingTop: 16, paddingBottom: 8 },
  divider: { height: 1, marginHorizontal: 24 },
  centered: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 32 },
  centeredText: { textAlign: 'center' },
  listContent: { paddingHorizontal: 24, paddingVertical: 16 },
  eyebrow: { textTransform: 'uppercase', letterSpacing: 1.5, marginBottom: 8 },
  actionCard: {
    borderWidth: 1,
    paddingHorizontal: 16,
    paddingVertical: 16,
    marginBottom: 24,
  },
  actionTitle: { marginBottom: 8 },
  actionRationale: { marginBottom: 16 },
  actionRow: { flexDirection: 'row', gap: 8 },
  actionBtn: { flex: 1 },
  section: { marginBottom: 24 },
  readinessLevel: { marginBottom: 8 },
  moveBlock: { marginTop: 16 },
  habitRow: { borderWidth: 1, paddingHorizontal: 16, paddingVertical: 12, marginBottom: 8 },
  habitTitle: { marginBottom: 4 },
  habitMove: { marginTop: 4 },
  habitRow2: { flexDirection: 'row', gap: 8, marginTop: 12 },
  habitBtn: { flex: 1 },
  invitation: { borderWidth: 1, paddingHorizontal: 16, paddingVertical: 12, marginBottom: 24 },
  supportCard: { borderWidth: 1, paddingHorizontal: 16, paddingVertical: 16, marginBottom: 24 },
  supportHeading: { marginBottom: 8 },
  supportBody: { marginBottom: 8 },
  supportResource: { marginTop: 8 },
  threadLabel: { marginBottom: 8 },
  bubble: { maxWidth: '85%', paddingHorizontal: 16, paddingVertical: 8, marginBottom: 8 },
  userBubble: { alignSelf: 'flex-end', borderWidth: 1 },
  assistantBubble: { alignSelf: 'flex-start' },
  bubbleText: { lineHeight: 20 },
  inputRow: { flexDirection: 'row', gap: 8, paddingHorizontal: 24, paddingVertical: 8, borderTopWidth: 1, alignItems: 'flex-end' },
  inputFlex: { flex: 1 },
  sendBtn: { paddingHorizontal: 16, paddingVertical: 10, justifyContent: 'center' },
});
