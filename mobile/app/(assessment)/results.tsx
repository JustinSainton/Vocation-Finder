import React, { useCallback, useEffect, useRef, useState } from 'react';
import { View, StyleSheet, ScrollView, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { TextInput } from '../../components/ui/TextInput';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { assessmentApi } from '../../services/api';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

const POLL_INTERVAL = 5000;
const POLL_TIMEOUT_MS = 120000;

export default function ResultsScreen() {
  const router = useRouter();
  const { colors, isDark } = useTheme();
  const styles = getStyles(colors, isDark);
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
  const [emailSending, setEmailSending] = useState(false);
  const [emailSent, setEmailSent] = useState(false);
  const [isTakingLong, setIsTakingLong] = useState(false);

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

  const handleReturnHome = () => {
    reset();
    router.replace('/(dashboard)');
  };

  const handleStartOver = () => {
    reset();
    router.replace('/(assessment)');
  };

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
              <Typography variant="bodyLarge" style={styles.waitingText}>
                {copy.results.notReadyTitle}
              </Typography>
              <Typography
                variant="body"
                color={colors.textSecondary}
                style={styles.waitingSub}
              >
                {resultsStatusMessage ??
                  copy.results.notReadyBody}
              </Typography>
              {isTakingLong ? (
                <>
                  <Typography
                    variant="small"
                    family="sans"
                    color={colors.textSecondary}
                    style={styles.waitingSub}
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
        {/* Opening Synthesis */}
        <Typography variant="bodyLarge" style={styles.section}>
          {results.opening_synthesis}
        </Typography>

        <View style={styles.divider} />

        {/* Vocational Orientation */}
        <Typography variant="heading" style={styles.sectionHeading}>
          {copy.results.headings.vocationalOrientation}
        </Typography>
        <Typography variant="body" style={styles.section}>
          {results.vocational_orientation}
        </Typography>

        {/* Meta badges */}
        <View style={styles.metaRow}>
          <View style={styles.metaBadge}>
            <Typography variant="caption" family="sans" color={colors.accent}>
              {copy.results.headings.primaryDomain}
            </Typography>
            <Typography variant="body" style={styles.metaValue}>
              {results.primary_domain}
            </Typography>
          </View>
          <View style={styles.metaBadge}>
            <Typography variant="caption" family="sans" color={colors.accent}>
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
              <Typography variant="caption" family="sans" color={colors.accent}>
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
            <Typography variant="heading" style={styles.sectionHeading}>
              {copy.results.headings.primaryPathways}
            </Typography>
            {results.primary_pathways.map((pathway, i) => (
              <View key={i} style={styles.pathwayCard}>
                <Typography variant="body">{pathway}</Typography>
              </View>
            ))}
            <View style={styles.divider} />
          </>
        ) : null}

        {/* Specific Considerations */}
        {results.specific_considerations ? (
          <>
            <Typography variant="heading" style={styles.sectionHeading}>
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
            <Typography variant="heading" style={styles.sectionHeading}>
              {copy.results.headings.nextSteps}
            </Typography>
            {results.next_steps.map((step, i) => (
              <View key={i} style={styles.stepRow}>
                <View style={styles.stepNumber}>
                  <Typography
                    variant="small"
                    family="sans"
                    color={colors.background}
                    style={styles.stepNumberText}
                  >
                    {String(i + 1)}
                  </Typography>
                </View>
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
            <Typography variant="heading" style={styles.sectionHeading}>
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

        {/* Email results */}
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
          <Typography variant="body" color={colors.accent}>
            {copy.results.emailSent}
          </Typography>
        ) : (
          <View style={styles.emailRow}>
            <View style={styles.emailInput}>
              <TextInput
                placeholder={copy.results.emailPlaceholder}
                keyboardType="email-address"
                autoCapitalize="none"
                value={emailValue}
                onChangeText={setEmailValue}
              />
            </View>
            <View style={styles.emailButtonWrap}>
              <Button
                title={emailSending ? copy.results.emailSending : copy.results.emailSend}
                onPress={handleEmailResults}
                disabled={emailSending || !emailValue.trim()}
              />
            </View>
          </View>
        )}

        <View style={styles.divider} />

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
          color={colors.accent}
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
  },
  isDark: boolean
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
    pathwayCard: {
      backgroundColor: isDark ? 'rgba(24, 31, 43, 0.72)' : '#F5F5F0',
      borderLeftWidth: 3,
      borderLeftColor: colors.accent,
      paddingHorizontal: spacing.md,
      paddingVertical: spacing.sm,
      marginBottom: spacing.sm,
    },
    stepRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      marginBottom: spacing.md,
    },
    stepNumber: {
      width: 24,
      height: 24,
      borderRadius: 12,
      backgroundColor: colors.text,
      alignItems: 'center',
      justifyContent: 'center',
      marginRight: spacing.md,
      marginTop: 3,
    },
    stepNumberText: {
      textAlign: 'center',
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
    emailRow: {
      flexDirection: 'row',
      gap: spacing.sm,
    },
    emailInput: {
      flex: 1,
    },
    emailButtonWrap: {
      justifyContent: 'flex-end',
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
