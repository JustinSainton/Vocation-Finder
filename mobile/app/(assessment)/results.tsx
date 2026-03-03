import React, { useEffect, useRef, useState } from 'react';
import { View, StyleSheet, ScrollView, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { TextInput } from '../../components/ui/TextInput';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { assessmentApi } from '../../services/api';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

const POLL_INTERVAL = 5000;

export default function ResultsScreen() {
  const router = useRouter();
  const { colors, isDark } = useTheme();
  const styles = getStyles(colors, isDark);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const {
    results,
    fetchResults,
    assessmentId,
    guestToken,
    tier,
    upgradeMessage,
    reset,
  } = useAssessmentStore();

  const [emailValue, setEmailValue] = useState('');
  const [emailSending, setEmailSending] = useState(false);
  const [emailSent, setEmailSent] = useState(false);

  // Poll for results until they arrive
  useEffect(() => {
    if (results) return;

    fetchResults();

    pollRef.current = setInterval(async () => {
      const profile = await fetchResults();
      if (profile && pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      }
    }, POLL_INTERVAL);

    return () => {
      if (pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
      }
    };
  }, []);

  const handleEmailResults = async () => {
    if (!assessmentId || !emailValue.trim()) return;

    setEmailSending(true);
    try {
      await assessmentApi.emailResults(assessmentId, emailValue.trim());
      setEmailSent(true);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      Alert.alert(
        'Could not send',
        'We were unable to email your results. Please try again later.'
      );
    } finally {
      setEmailSending(false);
    }
  };

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
          <Typography variant="bodyLarge" style={styles.waitingText}>
            Your vocational portrait is being prepared.
          </Typography>
          <Typography
            variant="body"
            color={colors.textSecondary}
            style={styles.waitingSub}
          >
            This may take a moment. Your reflections deserve careful attention.
          </Typography>
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
          Vocational Orientation
        </Typography>
        <Typography variant="body" style={styles.section}>
          {results.vocational_orientation}
        </Typography>

        {/* Meta badges */}
        <View style={styles.metaRow}>
          <View style={styles.metaBadge}>
            <Typography variant="caption" family="sans" color={colors.accent}>
              Primary Domain
            </Typography>
            <Typography variant="body" style={styles.metaValue}>
              {results.primary_domain}
            </Typography>
          </View>
          <View style={styles.metaBadge}>
            <Typography variant="caption" family="sans" color={colors.accent}>
              Mode of Work
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
                Secondary Orientation
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
              Primary Pathways
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
              Specific Considerations
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
              Next Steps
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
              Ministry Integration
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
          Save your results
        </Typography>
        <Typography
          variant="body"
          color={colors.textSecondary}
          style={styles.emailBody}
        >
          Enter your email to receive a beautifully formatted copy of your
          vocational portrait.
        </Typography>

        {emailSent ? (
          <Typography variant="body" color={colors.accent}>
            Sent — check your inbox.
          </Typography>
        ) : (
          <View style={styles.emailRow}>
            <View style={styles.emailInput}>
              <TextInput
                placeholder="your@email.com"
                keyboardType="email-address"
                autoCapitalize="none"
                value={emailValue}
                onChangeText={setEmailValue}
              />
            </View>
            <View style={styles.emailButtonWrap}>
              <Button
                title={emailSending ? 'Sending...' : 'Send'}
                onPress={handleEmailResults}
                disabled={emailSending || !emailValue.trim()}
              />
            </View>
          </View>
        )}

        <View style={styles.divider} />

        {/* Actions */}
        <View style={styles.actions}>
          <Button title="Return home" onPress={handleReturnHome} />
          <Button
            title="Take assessment again"
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
          This vocational portrait was generated with the assistance of
          artificial intelligence based on your written reflections. It is
          intended as a tool for discernment, not a definitive assessment. We
          encourage you to discuss these results with a trusted mentor, spiritual
          director, or counselor.
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
