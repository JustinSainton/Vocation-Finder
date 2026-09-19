import React, { useCallback, useEffect, useRef, useState } from 'react';
import { View, StyleSheet, ScrollView, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from 'react-native-reanimated';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { TextInput } from '../../components/ui/TextInput';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { useAuthStore } from '../../stores/authStore';
import { assessmentApi } from '../../services/api';
import { authApi } from '../../services/auth';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

const POLL_INTERVAL = 5000;
const POLL_TIMEOUT_MS = 120000;

function formatElapsed(totalSeconds: number): string {
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${minutes}:${String(seconds).padStart(2, '0')} elapsed`;
}

export default function ResultsScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const {
    results,
    fetchResults,
    assessmentId,
    guestToken,
    tier,
    upgradeMessage,
    resultsError,
    resultsStatusMessage,
    locale,
    reset,
  } = useAssessmentStore();
  const copy = getAssessmentCopy(locale);

  const [emailValue, setEmailValue] = useState('');
  const [passwordValue, setPasswordValue] = useState('');
  const [emailSending, setEmailSending] = useState(false);
  const [emailSent, setEmailSent] = useState(false);
  const [creatingAccount, setCreatingAccount] = useState(false);
  const [accountCreated, setAccountCreated] = useState(false);
  const [isTakingLong, setIsTakingLong] = useState(false);

  const isLoggedIn = !!useAuthStore((s) => s.token);
  const guestName = useAssessmentStore((s) => s.guestName);

  // Poll for results until they arrive
  useEffect(() => {
    if (results || resultsError) return;

    setIsTakingLong(false);
    fetchResults();

    timeoutRef.current = setTimeout(() => {
      setIsTakingLong(true);
    }, POLL_TIMEOUT_MS);

    pollRef.current = setInterval(async () => {
      const profile = await fetchResults();
      if (profile && pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
        if (timeoutRef.current) {
          clearTimeout(timeoutRef.current);
          timeoutRef.current = null;
        }
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      }
    }, POLL_INTERVAL);

    return () => {
      if (pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
      }
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
        timeoutRef.current = null;
      }
    };
  }, [fetchResults, results, resultsError]);

  const handleRetryResults = async () => {
    setIsTakingLong(false);
    await fetchResults();
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
      Alert.alert('Could not create account', err?.message ?? 'Please try again.');
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

  // Breathing rings + elapsed timer while waiting. No spinners, no
  // staged progress theatre, no haptics: the wait is honest and quiet.
  const [elapsed, setElapsed] = useState(0);
  const breathe = useSharedValue(1);

  useEffect(() => {
    if (results || resultsError) return;
    setElapsed(0);
    breathe.value = withRepeat(
      withTiming(1.15, { duration: 2400, easing: Easing.inOut(Easing.ease) }),
      -1,
      true
    );
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
  }, [results, resultsError, breathe]);

  const ringStyle = useAnimatedStyle(() => ({
    transform: [{ scale: breathe.value }],
    opacity: 1.15 - (breathe.value - 1) * 2,
  }));

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
                <Button title={copy.common.tryAgain} onPress={handleRetryResults} />
                <Button
                  title={copy.common.startOver}
                  variant="secondary"
                  onPress={handleStartOver}
                />
              </View>
            </>
          ) : (
            <>
              <View style={styles.orbWrap}>
                <Animated.View style={[styles.orbRing, ringStyle]} />
                <View style={[styles.orbCore, { backgroundColor: colors.text }]} />
              </View>

              <Typography variant="bodyLarge" style={styles.waitingText}>
                {copy.results.notReadyTitle}
              </Typography>
              <Typography
                variant="caption"
                family="sans"
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
                {copy.results.notReadyBody}
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
                    <Button title={copy.results.checkAgain} onPress={handleRetryResults} />
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
        {/* Eyebrow + honesty badge */}
        <Typography variant="caption" family="sans" style={styles.eyebrow}>
          Your vocational articulation
        </Typography>
        {results.confidence ? (
          <View style={styles.confidencePill}>
            <Typography variant="caption" family="sans" style={styles.confidenceText}>
              {results.confidence}
            </Typography>
          </View>
        ) : null}

        {/* Opening Synthesis */}
        <Typography variant="bodyLarge" style={styles.lead}>
          {results.opening_synthesis}
        </Typography>

        <View style={styles.divider} />

        {/* Vocational Orientation */}
        <Typography variant="caption" family="sans" style={styles.eyebrow}>
          {copy.results.headings.vocationalOrientation}
        </Typography>
        <Typography variant="body" style={styles.section}>
          {results.vocational_orientation}
        </Typography>

        {/* Meta badges */}
        <View style={styles.metaRow}>
          <View style={styles.metaBadge}>
            <Typography variant="caption" family="sans" color={colors.textSecondary}>
              {copy.results.headings.primaryDomain}
            </Typography>
            <Typography variant="body" style={styles.metaValue}>
              {results.primary_domain}
            </Typography>
          </View>
          <View style={styles.metaBadge}>
            <Typography variant="caption" family="sans" color={colors.textSecondary}>
              {copy.results.headings.modeOfWork}
            </Typography>
            <Typography variant="body" style={styles.metaValue}>
              {results.mode_of_work}
            </Typography>
          </View>
        </View>
        {results.secondary_orientation ? (
          <View style={styles.metaRow}>
            <View style={styles.metaBadge}>
              <Typography variant="caption" family="sans" color={colors.textSecondary}>
                {copy.results.headings.secondaryOrientation}
              </Typography>
              <Typography variant="body" style={styles.metaValue}>
                {results.secondary_orientation}
              </Typography>
            </View>
          </View>
        ) : null}

        <View style={styles.divider} />

        {/* Primary Pathways */}
        {results.primary_pathways && results.primary_pathways.length > 0 ? (
          <>
            <Typography variant="caption" family="sans" style={styles.eyebrow}>
              {copy.results.headings.primaryPathways}
            </Typography>
            {results.primary_pathways.map((pathway, i) => (
              <View key={i} style={styles.quoteBlock}>
                <Typography variant="bodyLarge">{pathway}</Typography>
              </View>
            ))}
            <View style={styles.divider} />
          </>
        ) : null}

        {/* Matched Pathway Blurbs */}
        {results.matched_pathway_blurbs && results.matched_pathway_blurbs.length > 0 ? (
          <>
            <Typography variant="caption" family="sans" style={styles.eyebrow}>
              {copy.results.headings.vocationalPathways}
            </Typography>
            {results.matched_pathway_blurbs.map((blurb, i) => (
              <View key={i} style={styles.blurbCard}>
                <Typography
                  variant="caption"
                  family="sans"
                  color={colors.textSecondary}
                  style={styles.blurbName}
                >
                  {blurb.name}
                </Typography>
                <Typography variant="body" style={styles.blurbDesc}>
                  {blurb.description}
                </Typography>
                <Typography
                  variant="small"
                  color={colors.textSecondary}
                  style={styles.blurbMinistry}
                >
                  {blurb.ministry_connection}
                </Typography>
              </View>
            ))}
            <View style={styles.divider} />
          </>
        ) : null}

        {/* Specific Considerations */}
        {results.specific_considerations ? (
          <>
            <Typography variant="caption" family="sans" style={styles.eyebrow}>
              {copy.results.headings.specificConsiderations}
            </Typography>
            <Typography variant="body" style={styles.section}>
              {results.specific_considerations}
            </Typography>
            <View style={styles.divider} />
          </>
        ) : null}

        {/* Next Steps */}
        {results.next_steps && results.next_steps.length > 0 ? (
          <>
            <Typography variant="caption" family="sans" style={styles.eyebrow}>
              {copy.results.headings.nextSteps}
            </Typography>
            {results.next_steps.map((step, i) => (
              <View key={i} style={styles.stepRow}>
                <Typography variant="body" style={styles.stepText}>
                  {step}
                </Typography>
              </View>
            ))}
            <View style={styles.divider} />
          </>
        ) : null}

        {/* Ministry Integration */}
        {results.ministry_integration ? (
          <>
            <Typography variant="caption" family="sans" style={styles.eyebrow}>
              {copy.results.headings.ministryIntegration}
            </Typography>
            <Typography variant="body" style={styles.section}>
              {results.ministry_integration}
            </Typography>
            <View style={styles.divider} />
          </>
        ) : null}

        {/* Upgrade prompt for free tier */}
        {tier === 'free' && upgradeMessage ? (
          <View style={styles.upgradeCard}>
            <Typography variant="body" style={styles.upgradeText}>
              {upgradeMessage}
            </Typography>
          </View>
        ) : null}

        {/* Save results — account creation for guests, email for logged-in users */}
        {!isLoggedIn ? (
          <>
            <Typography variant="caption" family="sans" style={styles.eyebrow}>
              Save your portrait
            </Typography>
            <Typography
              variant="body"
              color={colors.textSecondary}
              style={styles.emailBody}
            >
              Create an account to access your vocational portrait anytime and track your journey.
            </Typography>

            {accountCreated ? (
              <Typography variant="body">
                Account created! Your portrait is saved.
              </Typography>
            ) : (
              <View style={styles.emailStack}>
                <TextInput
                  placeholder="Email address"
                  keyboardType="email-address"
                  autoCapitalize="none"
                  value={emailValue}
                  onChangeText={setEmailValue}
                />
                <TextInput
                  placeholder="Choose a password"
                  secureTextEntry
                  value={passwordValue}
                  onChangeText={setPasswordValue}
                />
                <Button
                  title={creatingAccount ? 'Creating account...' : 'Create account'}
                  onPress={handleCreateAccount}
                  disabled={creatingAccount || !emailValue.trim() || passwordValue.length < 6}
                />
              </View>
            )}
            <View style={styles.divider} />
          </>
        ) : (
          <>
            <Typography variant="heading" style={styles.sectionHeading}>
              {copy.results.headings.saveResults}
            </Typography>
            <Typography
              variant="body"
              color={colors.textSecondary}
              style={styles.emailBody}
            >
              {copy.results.emailPrompt}
            </Typography>

            {emailSent ? (
              <Typography variant="body" color={colors.textSecondary}>
                {copy.results.emailSent}
              </Typography>
            ) : (
              <View style={styles.emailStack}>
                <TextInput
                  placeholder={copy.results.emailPlaceholder}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  value={emailValue}
                  onChangeText={setEmailValue}
                />
                <Button
                  title={emailSending ? copy.results.emailSending : copy.results.emailSend}
                  onPress={handleEmailResults}
                  disabled={emailSending || !emailValue.trim()}
                />
              </View>
            )}
            <View style={styles.divider} />
          </>
        )}

        {/* Actions */}
        <View style={styles.actions}>
          <Button title={copy.results.returnHome} onPress={handleReturnHome} />
          <Button
            title={copy.results.retake}
            variant="secondary"
            onPress={handleStartOver}
          />
        </View>

        {/* AI Disclaimer */}
        <Typography
          variant="caption"
          color={colors.textSecondary}
          style={styles.disclaimer}
        >
          {copy.results.disclaimer}
        </Typography>
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (
  colors: {
    background: string;
    divider: string;
    accent: string;
    text: string;
    textSecondary: string;
  }
) =>
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
    scrollContent: {
      paddingHorizontal: spacing.xl,
      paddingTop: spacing.xxl,
      paddingBottom: spacing.section,
    },
    section: {
      marginBottom: spacing.md,
    },
    sectionHeading: {
      marginBottom: spacing.lg,
    },
    eyebrow: {
      textTransform: 'uppercase',
      letterSpacing: 1.5,
      color: colors.textSecondary,
      marginBottom: spacing.md,
    },
    lead: {
      marginBottom: spacing.md,
    },
    confidencePill: {
      alignSelf: 'flex-start',
      borderWidth: 1,
      borderColor: colors.divider,
      borderRadius: 9999,
      paddingVertical: 4,
      paddingHorizontal: 12,
      marginBottom: spacing.xl,
    },
    confidenceText: {
      textTransform: 'uppercase',
      letterSpacing: 1.5,
      color: colors.textSecondary,
    },
    divider: {
      height: 1,
      backgroundColor: colors.divider,
      marginVertical: spacing.xl,
    },
    metaRow: {
      flexDirection: 'row',
      marginTop: spacing.lg,
      gap: spacing.lg,
    },
    metaBadge: {
      flex: 1,
      paddingVertical: spacing.sm,
    },
    metaValue: {
      marginTop: 4,
    },
    quoteBlock: {
      borderLeftWidth: 2,
      borderLeftColor: colors.accent,
      paddingLeft: spacing.lg,
      paddingVertical: 4,
      marginBottom: spacing.xl,
    },
    blurbCard: {
      borderWidth: 1,
      borderColor: colors.divider,
      paddingHorizontal: spacing.md,
      paddingVertical: spacing.lg,
      marginBottom: spacing.md,
    },
    blurbName: {
      letterSpacing: 1,
      textTransform: 'uppercase',
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
      borderBottomColor: colors.divider,
      paddingVertical: spacing.md,
      marginBottom: spacing.sm,
    },
    stepText: {
      flex: 1,
    },
    upgradeCard: {
      borderWidth: 1,
      borderColor: colors.divider,
      padding: spacing.lg,
      marginBottom: spacing.xl,
    },
    upgradeText: {
      fontStyle: 'italic',
    },
    emailBody: {
      marginBottom: spacing.md,
    },
    emailStack: {
      gap: spacing.md,
    },
    orbWrap: {
      width: 72,
      height: 72,
      alignItems: 'center',
      justifyContent: 'center',
      alignSelf: 'center',
      marginBottom: spacing.xl,
    },
    orbRing: {
      position: 'absolute',
      width: 72,
      height: 72,
      borderRadius: 36,
      borderWidth: 1,
      borderColor: colors.divider,
    },
    orbCore: {
      width: 22,
      height: 22,
      borderRadius: 11,
    },
    timer: {
      textAlign: 'center',
      letterSpacing: 1,
      marginTop: spacing.md,
    },
    leaveNote: {
      textAlign: 'center',
      marginTop: spacing.xl,
    },
    actions: {
      gap: spacing.md,
      marginBottom: spacing.xl,
    },
    disclaimer: {
      textAlign: 'center',
      lineHeight: 18,
    },
  });
