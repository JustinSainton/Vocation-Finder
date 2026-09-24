import React, { useCallback, useEffect, useState } from 'react';
import { View, StyleSheet, ScrollView, Alert, Pressable } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { AudioOrb } from '../../components/AudioOrb';
import { DemoBadge } from '../../components/DemoBadge';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { TextInput } from '../../components/ui/TextInput';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { useAuthStore } from '../../stores/authStore';
import { assessmentApi, coachApi } from '../../services/api';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import { useAnalysisResultsPolling } from '../../hooks/useAnalysisResultsPolling';
import { authApi } from '../../services/auth';
import { spacing, radius, layout, palettes } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

type ThemeColors = (typeof palettes)['light'];

function formatElapsed(totalSeconds: number): string {
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${minutes}:${String(seconds).padStart(2, '0')} elapsed`;
}

export default function ResultsScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const {
    results,
    assessmentId,
    guestToken,
    tier,
    upgradeMessage,
    resultsError,
    resultsStatusMessage,
    locale,
    reset,
  } = useAssessmentStore();
  const { isTakingLong, retryResults } = useAnalysisResultsPolling();
  const copy = getAssessmentCopy(locale);

  const [emailValue, setEmailValue] = useState('');
  const [passwordValue, setPasswordValue] = useState('');
  const [emailSending, setEmailSending] = useState(false);
  const [emailSent, setEmailSent] = useState(false);
  const [creatingAccount, setCreatingAccount] = useState(false);
  const [accountCreated, setAccountCreated] = useState(false);
  const [accountError, setAccountError] = useState<string | null>(null);

  const isLoggedIn = !!useAuthStore((s) => s.token);
  const guestName = useAssessmentStore((s) => s.guestName);
  const { isEnabled } = useFeatureFlags();
  const coachEnabled = isEnabled('pathway_coach');

  /*
   * The portrait ends at the coach, not at a list. Which door is open is
   * the server's call — the same predicate the coach enforces — so this
   * never promises a coach the student cannot have.
   */
  const [coachDoor, setCoachDoor] = useState<
    { state: 'open'; starters: string[] } | { state: 'blocked'; reason: string } | null
  >(null);

  useEffect(() => {
    if (!results || !isLoggedIn || !coachEnabled) return;
    let cancelled = false;
    coachApi
      .state()
      .then((s) => {
        if (!cancelled) setCoachDoor({ state: 'open', starters: s.starters ?? [] });
      })
      .catch((err: { status?: number; message?: string }) => {
        if (!cancelled && err?.status === 403) {
          setCoachDoor({ state: 'blocked', reason: err.message ?? '' });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [results, isLoggedIn, coachEnabled, accountCreated]);

  const openCoach = (say?: string) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => null);
    router.push(say ? { pathname: '/(dashboard)/coach', params: { say } } : '/(dashboard)/coach');
  };

  const handleEmailResults = useCallback(async () => {
    if (!assessmentId || !emailValue.trim()) return;

    setEmailSending(true);
    try {
      await assessmentApi.emailResults(assessmentId, emailValue.trim(), guestToken ?? undefined);
      setEmailSent(true);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      Alert.alert(copy.results.emailErrorTitle, copy.results.emailErrorBody);
    } finally {
      setEmailSending(false);
    }
  }, [assessmentId, copy.results.emailErrorBody, copy.results.emailErrorTitle, emailValue, guestToken]);

  const handleCreateAccount = useCallback(async () => {
    if (!emailValue.trim() || passwordValue.length < 6) return;
    setCreatingAccount(true);
    setAccountError(null);
    try {
      const data = await authApi.register(
        guestName ?? 'User',
        emailValue.trim(),
        passwordValue,
        passwordValue,
        guestToken ?? undefined
      );
      useAuthStore.getState().setAuth(data.token, data.user);
      setAccountCreated(true);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch (err: any) {
      const message: string = err?.message ?? '';
      if (message.toLowerCase().includes('already been taken')) {
        // Email belongs to an existing account — offer the sign-in path
        // inline instead of a dead-end alert.
        setAccountError(message);
      } else {
        Alert.alert('Could not create account', err?.message ?? 'Please try again.');
      }
    } finally {
      setCreatingAccount(false);
    }
  }, [emailValue, passwordValue, guestName, guestToken]);

  const handleReturnHome = () => {
    reset();
    router.replace('/(dashboard)');
  };

  const handleStartOver = () => {
    reset();
    router.replace('/(assessment)');
  };

  // Elapsed timer + quiet haptic pulse while waiting. The AudioOrb carries
  // the motion; the wait itself stays honest and quiet.
  const [elapsed, setElapsed] = useState(0);

  useEffect(() => {
    if (results || resultsError) return;
    setElapsed(0);
    const timer = setInterval(() => {
      setElapsed((s) => s + 1);
    }, 1000);
    const hapticTimer = setInterval(() => {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    }, 3000);
    return () => {
      clearInterval(timer);
      clearInterval(hapticTimer);
    };
  }, [results, resultsError]);

  // Waiting for results
  if (!results) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centered}>
          {resultsError ? (
            <>
              <Typography variant="bodyLarge" style={styles.waitingText}>
                {copy.results.errorTitle}
              </Typography>
              <Typography
                variant="body"
                color={colors.textSecondary}
                style={styles.waitingSub}
              >
                {resultsError}
              </Typography>
              <View style={styles.waitingActions}>
                <Button title={copy.common.tryAgain} onPress={retryResults} />
                <Button
                  title={copy.common.startOver}
                  variant="secondary"
                  onPress={handleStartOver}
                />
              </View>
            </>
          ) : (
            <>
              <View style={styles.orbSlot}>
                <AudioOrb agentState="thinking" />
              </View>

              <Typography variant="bodyLarge" style={styles.waitingText}>
                {copy.results.notReadyTitle}
              </Typography>
              <Typography
                variant="meta"
                color={colors.textSecondary}
                style={styles.timer}
              >
                {formatElapsed(elapsed)}
              </Typography>
              <Typography
                variant="body"
                color={colors.textSecondary}
                style={styles.waitingSub}
              >
                {resultsStatusMessage ?? copy.results.notReadyBody}
              </Typography>
              <Typography
                variant="small"
                family="sans"
                color={colors.textSecondary}
                style={styles.leaveNote}
              >
                You can leave — your portrait will be here when you return.
              </Typography>
              {isTakingLong ? (
                <>
                  <Typography
                    variant="small"
                    family="sans"
                    color={colors.textSecondary}
                    style={[styles.waitingSub, { marginTop: spacing.xl }]}
                  >
                    {copy.results.takingLong}
                  </Typography>
                  <View style={styles.waitingActions}>
                    <Button title={copy.results.checkAgain} onPress={retryResults} />
                  </View>
                </>
              ) : null}
            </>
          )}
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <DemoBadge />

        {/* Document head: mono eyebrow + confidence badge + opening lead */}
        <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
          Your vocational articulation
        </Typography>
        {results.confidence ? (
          <View style={styles.confidencePill}>
            <Typography variant="eyebrow" color={colors.muted}>
              {results.confidence}
            </Typography>
          </View>
        ) : null}
        <Typography variant="bodyLarge" style={styles.lead}>
          {results.opening_synthesis}
        </Typography>

        <View style={styles.divider} />

        {/* Vocational Orientation — editorial card */}
        <View style={styles.card}>
          <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
            {copy.results.headings.vocationalOrientation}
          </Typography>
          <Typography variant="body">{results.vocational_orientation}</Typography>
        </View>

        {/* Domain / mode / secondary — full-width stacked meta, hairline rules */}
        <View style={styles.card}>
          <View style={styles.metaBlock}>
            <Typography variant="eyebrow" color={colors.muted}>
              {copy.results.headings.primaryDomain}
            </Typography>
            <Typography variant="body" style={styles.metaValue}>
              {results.primary_domain}
            </Typography>
          </View>
          <View style={styles.metaRule} />
          <View style={styles.metaBlock}>
            <Typography variant="eyebrow" color={colors.muted}>
              {copy.results.headings.modeOfWork}
            </Typography>
            <Typography variant="body" style={styles.metaValue}>
              {results.mode_of_work}
            </Typography>
          </View>
          {results.secondary_orientation ? (
            <>
              <View style={styles.metaRule} />
              <View style={styles.metaBlock}>
                <Typography variant="eyebrow" color={colors.muted}>
                  {copy.results.headings.secondaryOrientation}
                </Typography>
                <Typography variant="body" style={styles.metaValue}>
                  {results.secondary_orientation}
                </Typography>
              </View>
            </>
          ) : null}
        </View>

        {/* Primary Pathways — accent quote rules */}
        {results.primary_pathways && results.primary_pathways.length > 0 ? (
          <View style={styles.sectionBlock}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
              {copy.results.headings.primaryPathways}
            </Typography>
            {results.primary_pathways.map((pathway, i) => (
              <View key={i} style={styles.quoteBlock}>
                <Typography variant="bodyLarge">{pathway}</Typography>
              </View>
            ))}
          </View>
        ) : null}

        {/* Matched Pathway Blurbs — editorial cards */}
        {results.matched_pathway_blurbs && results.matched_pathway_blurbs.length > 0 ? (
          <View style={styles.sectionBlock}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
              {copy.results.headings.vocationalPathways}
            </Typography>
            {results.matched_pathway_blurbs.map((blurb, i) => (
              <View key={i} style={styles.blurbCard}>
                <Typography
                  variant="eyebrow"
                  color={colors.muted}
                  style={styles.blurbName}
                >
                  {blurb.name}
                </Typography>
                <Typography variant="body" style={styles.blurbDesc}>
                  {blurb.description}
                </Typography>
                <Typography variant="small" color={colors.textSecondary} style={styles.blurbMinistry}>
                  {blurb.ministry_connection}
                </Typography>
              </View>
            ))}
          </View>
        ) : null}

        {/* Specific Considerations */}
        {results.specific_considerations ? (
          <View style={styles.sectionBlock}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
              {copy.results.headings.specificConsiderations}
            </Typography>
            <Typography variant="body">{results.specific_considerations}</Typography>
          </View>
        ) : null}

        {/* Next Steps — hairline-separated rows */}
        {results.next_steps && results.next_steps.length > 0 ? (
          <View style={styles.sectionBlock}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
              {copy.results.headings.nextSteps}
            </Typography>
            {results.next_steps.map((step, i) => (
              <View key={i} style={styles.stepRow}>
                <Typography variant="body">{step}</Typography>
              </View>
            ))}
          </View>
        ) : null}

        {/* Ministry Integration */}
        {results.ministry_integration ? (
          <View style={styles.sectionBlock}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
              {copy.results.headings.ministryIntegration}
            </Typography>
            <Typography variant="body">{results.ministry_integration}</Typography>
          </View>
        ) : null}

        {/* The coach — the portrait's last word, not a list */}
        {coachEnabled && (coachDoor || !isLoggedIn) ? (
          <View style={styles.coachDoor}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
              {coachDoor?.state === 'open' ? 'Your coach is ready' : 'Your coach'}
            </Typography>
            <Typography variant="displaySm" style={styles.coachHeadline}>
              {coachDoor?.state === 'open'
                ? 'Your coach has read this. It will speak first.'
                : coachDoor?.state === 'blocked'
                  ? 'Your coach starts from this portrait.'
                  : 'This portrait is where your coach starts.'}
            </Typography>
            <Typography variant="body" color={colors.textSecondary} style={styles.formIntro}>
              {coachDoor?.state === 'open'
                ? 'It starts from what you just wrote, asks what the questions could not, and ends with one concrete thing for you to do this week.'
                : coachDoor?.state === 'blocked'
                  ? coachDoor.reason
                  : 'Create your account below and your coach will open the conversation with what you wrote here.'}
            </Typography>
            {coachDoor?.state === 'open' ? (
              <>
                <Button title="Start with your coach" onPress={() => openCoach()} style={styles.formButton} />
                {coachDoor.starters.length > 0 ? (
                  <View style={styles.starterBlock}>
                    <Typography variant="eyebrow" color={colors.muted} style={styles.eyebrowGap}>
                      Or walk in with a question
                    </Typography>
                    <View style={styles.starterChips}>
                      {coachDoor.starters.map((starter) => (
                        <Pressable
                          key={starter}
                          accessibilityRole="button"
                          onPress={() => openCoach(starter)}
                          style={({ pressed }) => [styles.starterChip, pressed && { borderColor: colors.text }]}
                        >
                          <Typography variant="small" family="sans">{starter}</Typography>
                        </Pressable>
                      ))}
                    </View>
                  </View>
                ) : null}
              </>
            ) : null}
          </View>
        ) : null}

        {/* Upgrade prompt for free tier — policy callout */}
        {tier === 'free' && upgradeMessage ? (
          <View style={styles.policyCallout}>
            <Typography variant="body" italic>
              {upgradeMessage}
            </Typography>
          </View>
        ) : null}

        {/* Save results — account creation for guests, email for logged-in users */}
        {!isLoggedIn ? (
          <View style={styles.sectionBlock}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
              Save your portrait
            </Typography>
            <Typography
              variant="body"
              color={colors.textSecondary}
              style={styles.formIntro}
            >
              Create an account to access your vocational portrait anytime and track your journey.
            </Typography>

            {accountCreated ? (
              <Typography variant="body">
                Account created! Your portrait is saved.
              </Typography>
            ) : (
              <View style={styles.formStack}>
                <TextInput
                  placeholder="Email address"
                  keyboardType="email-address"
                  autoCapitalize="none"
                  minHeight={layout.touchTarget}
                  value={emailValue}
                  onChangeText={(text) => {
                    setEmailValue(text);
                    setAccountError(null);
                  }}
                />
                <TextInput
                  placeholder="Choose a password"
                  secureTextEntry
                  minHeight={layout.touchTarget}
                  value={passwordValue}
                  onChangeText={setPasswordValue}
                />
                {accountError ? (
                  <View style={styles.accountError}>
                    <Typography variant="small" color={colors.textSecondary}>
                      {accountError}
                    </Typography>
                    <Pressable
                      onPress={() => router.push('/(auth)/login')}
                      hitSlop={8}
                    >
                      <Typography
                        variant="small"
                        color={colors.accent}
                        style={styles.signInLink}
                      >
                        Sign in instead
                      </Typography>
                    </Pressable>
                  </View>
                ) : null}
                <Button
                  title={creatingAccount ? 'Creating account...' : 'Create account'}
                  onPress={handleCreateAccount}
                  disabled={creatingAccount || !emailValue.trim() || passwordValue.length < 6}
                  style={styles.formButton}
                />
              </View>
            )}
          </View>
        ) : (
          <View style={styles.sectionBlock}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.eyebrowGap}>
              {copy.results.headings.saveResults}
            </Typography>
            <Typography
              variant="body"
              color={colors.textSecondary}
              style={styles.formIntro}
            >
              {copy.results.emailPrompt}
            </Typography>

            {emailSent ? (
              <Typography variant="body" color={colors.textSecondary}>
                {copy.results.emailSent}
              </Typography>
            ) : (
              <View style={styles.formStack}>
                <TextInput
                  placeholder={copy.results.emailPlaceholder}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  minHeight={layout.touchTarget}
                  value={emailValue}
                  onChangeText={setEmailValue}
                />
                <Button
                  title={emailSending ? copy.results.emailSending : copy.results.emailSend}
                  onPress={handleEmailResults}
                  disabled={emailSending || !emailValue.trim()}
                  style={styles.formButton}
                />
              </View>
            )}
          </View>
        )}

        {/* Actions */}
        <View style={styles.actions}>
          <Button
            title={copy.results.returnHome}
            onPress={handleReturnHome}
            variant={coachDoor?.state === 'open' ? 'secondary' : 'primary'}
          />
          <Button
            title={copy.results.retake}
            variant="secondary"
            onPress={handleStartOver}
          />
        </View>

        {/* AI Disclaimer — policy callout */}
        <View style={[styles.policyCallout, styles.disclaimerCallout]}>
          <Typography variant="small" color={colors.textSecondary}>
            {copy.results.disclaimer}
          </Typography>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: ThemeColors) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    centered: {
      flex: 1,
      justifyContent: 'center',
      paddingHorizontal: spacing.xl,
    },
    orbSlot: {
      alignSelf: 'center',
      marginBottom: spacing.lg,
    },
    waitingText: {
      textAlign: 'center',
      marginBottom: spacing.lg,
    },
    waitingSub: {
      textAlign: 'center',
    },
    waitingActions: {
      width: '100%',
      gap: spacing.md,
      marginTop: spacing.lg,
    },
    timer: {
      textAlign: 'center',
      marginTop: spacing.md,
      marginBottom: spacing.md,
    },
    leaveNote: {
      textAlign: 'center',
      marginTop: spacing.xl,
    },
    scrollContent: {
      paddingHorizontal: spacing.xl,
      paddingTop: spacing.xxl,
      paddingBottom: spacing.section,
    },
    eyebrowGap: {
      marginBottom: spacing.md,
    },
    lead: {
      marginBottom: spacing.xs,
    },
    confidencePill: {
      alignSelf: 'flex-start',
      borderWidth: 1,
      borderColor: colors.divider,
      borderRadius: radius.pill,
      paddingVertical: spacing.xxs,
      paddingHorizontal: spacing.sm,
      marginBottom: spacing.lg,
    },
    divider: {
      height: 1,
      backgroundColor: colors.divider,
      marginVertical: spacing.xl,
    },
    card: {
      backgroundColor: colors.surfaceCard,
      borderRadius: radius.md,
      padding: spacing.xl,
      marginBottom: spacing.xl,
    },
    metaBlock: {
      width: '100%',
      paddingVertical: spacing.sm,
    },
    metaValue: {
      marginTop: spacing.xs,
    },
    metaRule: {
      height: 1,
      backgroundColor: colors.dividerSoft,
    },
    sectionBlock: {
      marginBottom: spacing.xl,
    },
    coachDoor: {
      borderTopWidth: 2,
      borderTopColor: colors.text,
      paddingTop: spacing.xl,
      marginBottom: spacing.xl,
    },
    coachHeadline: {
      marginBottom: spacing.md,
    },
    starterBlock: {
      marginTop: spacing.lg,
    },
    starterChips: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.xs,
    },
    starterChip: {
      borderWidth: 1,
      borderColor: colors.divider,
      borderRadius: radius.xs,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      minHeight: layout.touchTarget,
      justifyContent: 'center',
    },
    quoteBlock: {
      borderLeftWidth: 2,
      borderLeftColor: colors.accent,
      paddingLeft: spacing.lg,
      paddingVertical: spacing.xxs,
      marginBottom: spacing.lg,
    },
    blurbCard: {
      backgroundColor: colors.surfaceCard,
      borderRadius: radius.md,
      padding: spacing.lg,
      marginBottom: spacing.md,
    },
    blurbName: {
      marginBottom: spacing.sm,
    },
    blurbDesc: {
      marginBottom: spacing.sm,
    },
    blurbMinistry: {
      fontStyle: 'italic',
    },
    stepRow: {
      borderBottomWidth: 1,
      borderBottomColor: colors.dividerSoft,
      paddingVertical: spacing.md,
    },
    policyCallout: {
      backgroundColor: colors.accentWash,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: colors.divider,
      borderRadius: radius.sm,
      padding: spacing.lg,
      marginBottom: spacing.xl,
    },
    disclaimerCallout: {
      marginBottom: 0,
    },
    formIntro: {
      marginBottom: spacing.md,
    },
    formStack: {
      gap: spacing.sm,
    },
    formButton: {
      marginTop: spacing.xs,
    },
    accountError: {
      gap: spacing.xxs,
    },
    signInLink: {
      textDecorationLine: 'underline',
    },
    actions: {
      gap: spacing.md,
      marginBottom: spacing.xl,
    },
  });
