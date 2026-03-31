import React, { useState } from 'react';
import { View, StyleSheet, ScrollView } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { ScoreSelector } from '../../components/ui/ScoreSelector';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function AfterSurveyScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);

  const { locale, submitSurvey } = useAssessmentStore();
  const copy = getAssessmentCopy(locale);

  const [clarityScore, setClarityScore] = useState<number | null>(null);
  const [likelihoodScore, setLikelihoodScore] = useState<number | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const canSubmit = clarityScore !== null && likelihoodScore !== null && !submitting;

  const handleSubmit = async () => {
    if (!canSubmit) return;
    setSubmitting(true);

    try {
      await submitSurvey('after', clarityScore!, likelihoodScore!);
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      // Non-fatal — proceed to results regardless
    } finally {
      router.replace('/(assessment)/results');
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        <Typography variant="heading" style={styles.title}>
          {copy.afterSurvey.title}
        </Typography>

        <Typography
          variant="body"
          color={colors.textSecondary}
          style={styles.subtitle}
        >
          {copy.afterSurvey.subtitle}
        </Typography>

        <View style={styles.divider} />

        {/* Question 1 */}
        <View style={styles.question}>
          <Typography variant="bodyLarge" style={styles.questionText}>
            {copy.afterSurvey.clarityQuestion}
          </Typography>
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.scaleLabel}
          >
            {copy.afterSurvey.clarityScale}
          </Typography>
          <ScoreSelector value={clarityScore} onChange={setClarityScore} colors={colors} />
        </View>

        {/* Question 2 */}
        <View style={styles.question}>
          <Typography variant="bodyLarge" style={styles.questionText}>
            {copy.afterSurvey.likelihoodQuestion}
          </Typography>
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.scaleLabel}
          >
            {copy.afterSurvey.likelihoodScale}
          </Typography>
          <ScoreSelector value={likelihoodScore} onChange={setLikelihoodScore} colors={colors} />
        </View>

        <View style={styles.actions}>
          <Button
            title={submitting ? copy.common.continueLoading : copy.afterSurvey.submitButton}
            onPress={handleSubmit}
            disabled={!canSubmit}
          />
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: { background: string; divider: string }) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    scrollContent: {
      paddingHorizontal: spacing.lg,
      paddingTop: spacing.xxl,
      paddingBottom: spacing.section,
    },
    title: {
      marginBottom: spacing.md,
    },
    subtitle: {
      marginBottom: spacing.sm,
    },
    divider: {
      height: 1,
      backgroundColor: colors.divider,
      marginVertical: spacing.xl,
    },
    question: {
      marginBottom: spacing.xxl,
    },
    questionText: {
      marginBottom: spacing.sm,
    },
    scaleLabel: {
      marginBottom: spacing.xs ?? 4,
    },
    actions: {
      marginTop: spacing.md,
    },
  });
