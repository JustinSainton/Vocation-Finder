import React, { useState } from 'react';
import { View, StyleSheet, ScrollView, Pressable } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
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

  const { locale, createAssessment, submitClarity } = useAssessmentStore();
  const copy = getAssessmentCopy(locale);

  const [standing, setStanding] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleBegin = async () => {
    if (!standing || submitting) return;
    setSubmitting(true);

    try {
      await createAssessment(assessmentMode);
      await submitClarity('before', standing);
      router.push(nextRoute as any);
    } catch {
      router.push(nextRoute as any);
    }
  };

  const handleSkip = async () => {
    if (submitting) return;
    setSubmitting(true);

    try {
      await createAssessment(assessmentMode);
      router.push(nextRoute as any);
    } catch {
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

        <Typography variant="bodyLarge" style={styles.question}>
          {copy.beforeSurvey.question}
        </Typography>

        <Typography
          variant="small"
          family="sans"
          color={colors.textSecondary}
          style={styles.note}
        >
          {copy.beforeSurvey.note}
        </Typography>

        <View style={styles.options}>
          {copy.beforeSurvey.options.map((option) => (
            <Pressable
              key={option.value}
              onPress={() => setStanding(option.value)}
              style={[
                styles.option,
                standing === option.value && styles.optionSelected,
              ]}
            >
              <Typography
                variant="body"
                style={standing === option.value ? styles.optionTextSelected : undefined}
              >
                {option.label}
              </Typography>
            </Pressable>
          ))}
        </View>

        <View style={styles.actions}>
          <Button
            title={submitting ? copy.common.continueLoading : copy.beforeSurvey.beginButton}
            onPress={handleBegin}
            disabled={!standing || submitting}
          />
          <Pressable onPress={handleSkip} style={styles.skip} disabled={submitting}>
            <Typography
              variant="small"
              family="sans"
              color={colors.textSecondary}
            >
              {copy.beforeSurvey.skip}
            </Typography>
          </Pressable>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: { background: string; text: string; divider: string }) =>
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
      marginBottom: spacing.xl,
    },
    question: {
      marginBottom: spacing.sm,
    },
    note: {
      marginBottom: spacing.xl,
    },
    options: {
      gap: spacing.sm,
      marginBottom: spacing.xxl,
    },
    option: {
      borderWidth: 1,
      borderColor: colors.divider,
      paddingVertical: spacing.md,
      paddingHorizontal: spacing.lg,
    },
    optionSelected: {
      borderColor: colors.text,
      backgroundColor: colors.text,
    },
    optionTextSelected: {
      color: colors.background,
    },
    actions: {
      marginTop: spacing.md,
    },
    skip: {
      alignItems: 'center',
      paddingVertical: spacing.md,
      marginTop: spacing.sm,
    },
  });
