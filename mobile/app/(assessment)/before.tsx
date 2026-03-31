import React, { useState } from 'react';
import { View, StyleSheet, ScrollView } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { ScoreSelector } from '../../components/ui/ScoreSelector';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function BeforeSurveyScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);

  const params = useLocalSearchParams<{ mode?: string }>();
  const assessmentMode = params.mode === 'conversation' ? 'conversation' : 'written';
  const nextRoute =
    assessmentMode === 'conversation' ? '/(assessment)/conversation' : '/(assessment)/written';

  const { locale, createAssessment, submitSurvey } = useAssessmentStore();
  const copy = getAssessmentCopy(locale);

  const [clarityScore, setClarityScore] = useState<number | null>(null);
  const [readinessScore, setReadinessScore] = useState<number | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const canSubmit = clarityScore !== null && readinessScore !== null && !submitting;

  const handleBegin = async () => {
    if (!canSubmit) return;
    setSubmitting(true);

    try {
      await createAssessment(assessmentMode);
      await submitSurvey('before', clarityScore!, readinessScore!);
      await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      router.push(nextRoute as any);
    } catch {
      // Even if survey fails, proceed to the assessment
      router.push(nextRoute as any);
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
          {copy.beforeSurvey.title}
        </Typography>

        <Typography
          variant="body"
          color={colors.textSecondary}
          style={styles.subtitle}
        >
          {copy.beforeSurvey.subtitle}
        </Typography>

        <View style={styles.divider} />

        {/* Question 1 */}
        <View style={styles.question}>
          <Typography variant="bodyLarge" style={styles.questionText}>
            {copy.beforeSurvey.clarityQuestion}
          </Typography>
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.scaleLabel}
          >
            {copy.beforeSurvey.clarityScale}
          </Typography>
          <ScoreSelector value={clarityScore} onChange={setClarityScore} colors={colors} />
        </View>

        {/* Question 2 */}
        <View style={styles.question}>
          <Typography variant="bodyLarge" style={styles.questionText}>
            {copy.beforeSurvey.readinessQuestion}
          </Typography>
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.scaleLabel}
          >
            {copy.beforeSurvey.readinessScale}
          </Typography>
          <ScoreSelector value={readinessScore} onChange={setReadinessScore} colors={colors} />
        </View>

        <View style={styles.actions}>
          <Button
            title={submitting ? copy.common.continueLoading : copy.beforeSurvey.beginButton}
            onPress={handleBegin}
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
