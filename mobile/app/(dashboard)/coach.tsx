import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  View,
  StyleSheet,
  FlatList,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
  type NativeScrollEvent,
  type NativeSyntheticEvent,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { CoachBubble, CoachStepMarker } from '../../components/coach/CoachBubble';
import { CoachComposer } from '../../components/coach/CoachComposer';
import { CoachThinking } from '../../components/coach/CoachThinking';
import { radius, spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useThemeStore } from '../../stores/themeStore';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import { useCoachConversation, type CoachRow } from '../../hooks/useCoachConversation';

function Eyebrow({ children }: { children: React.ReactNode }) {
  const { colors } = useTheme();
  return (
    <Typography variant="eyebrow" color={colors.muted} style={styles.eyebrow}>
      {children}
    </Typography>
  );
}

/**
 * The coach: the conversation is the screen.
 *
 * All state and network behaviour lives in `useCoachConversation`; this file
 * only draws it. The coach speaks first when the server says an opener is
 * due, the one step is pinned at the head of the thread, and everything else
 * is one tap away.
 */
export default function CoachScreen() {
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();
  const enabled = isEnabled('pathway_coach');
  const params = useLocalSearchParams<{ say?: string }>();
  const setPreference = useThemeStore((s) => s.setPreference);
  const coach = useCoachConversation(enabled);
  const [input, setInput] = useState(typeof params.say === 'string' ? params.say : '');
  const [showReadiness, setShowReadiness] = useState(false);
  // Follow the conversation only while the student is at the bottom of it;
  // scrolling up to reread something must not be yanked back down.
  const [isPinned, setIsPinned] = useState(true);
  const flatListRef = useRef<FlatList<CoachRow>>(null);
  // A programmatic scroll reports intermediate offsets on its way down; those
  // must not read as the student scrolling away.
  const ignoreScrollUntil = useRef(0);

  const followToEnd = useCallback(() => {
    ignoreScrollUntil.current = Date.now() + 600;
    flatListRef.current?.scrollToEnd({ animated: true });
  }, []);

  useEffect(() => {
    const previous = useThemeStore.getState().preference;
    setPreference('dark');
    return () => setPreference(previous);
  }, [setPreference]);

  // Follow the conversation: new turns, the thinking row, and a failed send.
  useEffect(() => {
    if (!isPinned) return;
    const timer = setTimeout(followToEnd, 50);
    return () => clearTimeout(timer);
  }, [isPinned, followToEnd, coach.thinking, coach.pending, coach.failed, coach.items.length]);

  const handleScroll = useCallback((e: NativeSyntheticEvent<NativeScrollEvent>) => {
    if (Date.now() < ignoreScrollUntil.current) return;
    const { contentOffset, layoutMeasurement, contentSize } = e.nativeEvent;
    setIsPinned(contentSize.height - (contentOffset.y + layoutMeasurement.height) < 60);
  }, []);

  const jumpToLatest = useCallback(() => {
    setIsPinned(true);
    followToEnd();
  }, [followToEnd]);

  if (!enabled) return null;

  const send = (text: string) => {
    if (!text.trim() || coach.thinking) return;
    setInput('');
    setIsPinned(true);
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => null);
    coach.send(text);
  };

  const retry = () => {
    setIsPinned(true);
    coach.retry();
  };

  const { state, blocked, support } = coach;
  const action = state?.current_action ?? null;
  const readiness = state?.readiness ?? null;
  const habits = state?.habits ?? [];

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

        {coach.loading && !state ? (
          <View style={styles.centered}>
            <ActivityIndicator size="small" color={colors.accent} />
          </View>
        ) : blocked ? (
          <View style={styles.centered}>
            <Typography variant="body" style={styles.centeredText}>{blocked}</Typography>
          </View>
        ) : (
          <View style={styles.threadArea}>
            <FlatList
              ref={flatListRef}
              data={coach.rows}
              keyExtractor={(item) => `${item.type}-${item.id}`}
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
              onScroll={handleScroll}
              scrollEventThrottle={16}
              onContentSizeChange={() => {
                if (isPinned) followToEnd();
              }}
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
                    <View style={[styles.actionCard, { borderColor: colors.accent, backgroundColor: colors.surfaceStrong }]}>
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
                            title={coach.settlingAction ? '…' : 'I did this'}
                            onPress={() => coach.settleAction('complete')}
                            disabled={coach.settlingAction}
                          />
                        </View>
                        <View style={styles.actionBtn}>
                          <Button
                            title="Set it down"
                            onPress={() => coach.settleAction('skip')}
                            disabled={coach.settlingAction}
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
                                  onPress={() => coach.checkIn(habit.id, true)}
                                  disabled={coach.checkingHabit === habit.id}
                                  variant="secondary"
                                />
                              </View>
                              <View style={styles.habitBtn}>
                                <Button
                                  title="Not today"
                                  onPress={() => coach.checkIn(habit.id, false)}
                                  disabled={coach.checkingHabit === habit.id}
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
                        <Typography variant="small" family="sans" color={colors.error} style={styles.failedText}>
                          That did not go through. Your words are kept — want to try again?
                        </Typography>
                        <Pressable
                          accessibilityRole="button"
                          onPress={retry}
                          disabled={!!coach.thinking}
                          style={[styles.retryBtn, { borderColor: colors.divider }]}
                        >
                          <Typography variant="small" family="sans" color={colors.text}>Try again</Typography>
                        </Pressable>
                      </View>
                    </View>
                  );
                }
                const previous = coach.rows[index - 1];
                const showLabel = !previous || previous.type !== 'message' || previous.role !== item.role;
                return <CoachBubble role={item.role} content={item.content} at={item.at} showLabel={showLabel} />;
              }}
              // The typing indicator. CoachThinking takes an optional label and
              // renders with none, so any other indicator can replace it here.
              ListFooterComponent={coach.thinking ? <CoachThinking label={coach.thinking} /> : null}
            />
            {!isPinned && coach.rows.length > 0 ? (
              <Pressable
                accessibilityRole="button"
                onPress={jumpToLatest}
                style={[styles.jumpPill, { backgroundColor: colors.buttonBg }]}
              >
                <Typography variant="small" family="sans" color={colors.buttonText}>
                  Jump to latest ↓
                </Typography>
              </Pressable>
            ) : null}
          </View>
        )}

        {!blocked ? (
          <CoachComposer
            value={input}
            onChangeText={setInput}
            onSend={() => send(input)}
            busy={!!coach.thinking}
            starters={coach.starters}
            showStarters={!coach.thinking && !coach.failed && !input && coach.coachSpokeLast}
            onStarter={send}
          />
        ) : null}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const GUTTER = spacing.lg;

const styles = StyleSheet.create({
  container: { flex: 1 },
  keyboardView: { flex: 1 },
  header: {
    paddingHorizontal: GUTTER,
    paddingTop: spacing.md,
    paddingBottom: spacing.xs,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  readinessToggle: { minHeight: 32, justifyContent: 'center' },
  divider: { height: 1, marginHorizontal: GUTTER },
  centered: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: spacing.xl },
  centeredText: { textAlign: 'center' },
  listContent: { paddingHorizontal: GUTTER, paddingVertical: spacing.md },
  eyebrow: { marginBottom: spacing.xs },
  actionCard: { borderWidth: 1, borderRadius: radius.md, padding: spacing.lg, marginBottom: spacing.lg },
  actionTitle: { marginBottom: spacing.xs, fontSize: 18, lineHeight: 25 },
  actionRationale: { marginBottom: spacing.md },
  actionRow: { flexDirection: 'row', gap: spacing.xs },
  actionBtn: { flex: 1 },
  section: { marginBottom: spacing.lg },
  moveBlock: { marginTop: spacing.md },
  habitRow: { borderBottomWidth: 1, paddingVertical: spacing.sm },
  habitTitle: { marginBottom: spacing.xxs },
  habitMove: { marginTop: spacing.xxs },
  habitButtons: { flexDirection: 'row', gap: spacing.xs, marginTop: spacing.sm },
  habitBtn: { flex: 1 },
  invitation: { borderLeftWidth: 2, paddingLeft: spacing.md, marginBottom: spacing.lg },
  supportCard: { borderLeftWidth: 2, paddingLeft: spacing.md, marginBottom: spacing.lg },
  supportHeading: { marginBottom: spacing.xs },
  supportBody: { marginBottom: spacing.xs },
  supportResource: { marginTop: spacing.xs },
  failedRow: { alignItems: 'flex-end', gap: spacing.xs, marginTop: -spacing.xs, marginBottom: spacing.md },
  failedText: { lineHeight: 18, textAlign: 'right' },
  retryBtn: { borderWidth: 1, paddingHorizontal: spacing.sm, paddingVertical: spacing.xxs + 2, minHeight: 32, justifyContent: 'center' },
  threadArea: { flex: 1 },
  jumpPill: {
    position: 'absolute',
    right: GUTTER,
    bottom: spacing.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    zIndex: 10,
  },
});
