import React, { useState } from 'react';
import { View, StyleSheet, ScrollView, Pressable } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function AfterSurveyScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);

  const { locale, submitClarity } = useAssessmentStore();
  const copy = getAssessmentCopy(locale);

  const [standing, setStanding] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async () => {
    if (!standing || submitting) return;
    setSubmitting(true);

    try {
      await submitClarity('after', standing);
    } catch {
      // Non-fatal — proceed to results regardless
    } finally {
      router.replace('/(assessment)/results');
    }
  };

  const handleSkip = () => {
    if (submitting) return;
    router.replace('/(assessment)/results');
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

        <Typography variant="bodyLarge" style={styles.question}>
          {copy.afterSurvey.question}
        </Typography>

        <Typography
          variant="small"
          family="sans"
          color={colors.textSecondary}
          style={styles.note}
        >
          {copy.afterSurvey.note}
        </Typography>

        <View style={styles.options}>
          {copy.afterSurvey.options.map((option) => (
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
            title={submitting ? copy.common.continueLoading : copy.afterSurvey.submitButton}
            onPress={handleSubmit}
            disabled={!standing || submitting}
          />
          <Pressable onPress={handleSkip} style={styles.skip} disabled={submitting}>
            <Typography
              variant="small"
              family="sans"
              color={colors.textSecondary}
            >
              {copy.afterSurvey.skip}
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
