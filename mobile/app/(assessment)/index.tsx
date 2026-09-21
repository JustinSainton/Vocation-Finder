import React, { useEffect, useState } from 'react';
import { View, ScrollView, StyleSheet, Pressable } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { spacing, layout } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useAssessmentStore } from '../../stores/assessmentStore';

export default function AssessmentLandingScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const [checked, setChecked] = useState(false);
  const locale = useAssessmentStore((state) => state.locale);
  const copy = getAssessmentCopy(locale);

  useEffect(() => {
    const prefetch = async () => {
      const state = useAssessmentStore.getState();
      if (state.questions.length === 0 || state.questionsLocale !== state.locale) {
        await state.fetchQuestions();
      }
    };
    prefetch();
  }, [locale]);

  const handleContinue = () => {
    router.push('/(assessment)/before?mode=written');
  };

  const toggleCheck = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setChecked((prev) => !prev);
  };

  const canProceed = checked;

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
        <View style={styles.content}>
          <View style={styles.body}>
            <Typography variant="headingLarge" style={styles.headline}>
              {copy.orientation.title === 'Before we begin' ? 'Most people are taught\nto choose a career.\nVery few are taught\nto discern a calling.' : copy.orientation.title}
            </Typography>

            <Typography variant="body" style={styles.paragraph}>
              {copy.orientation.introOne}
            </Typography>

            <Typography variant="body" style={styles.paragraph}>
              {copy.orientation.introTwo}
            </Typography>

            <Typography
              variant="small"
              family="sans"
              color={colors.textSecondary}
              style={styles.timeNote}
            >
              {copy.orientation.timeNote}
            </Typography>

            <Typography
              variant="small"
              family="sans"
              color={colors.textSecondary}
              style={styles.promise}
            >
              {copy.orientation.promise}
            </Typography>

            <View style={styles.divider} />

            <Pressable
              onPress={toggleCheck}
              style={styles.checkboxRow}
              hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
            >
              <View
                style={[
                  styles.checkbox,
                  checked && styles.checkboxChecked,
                ]}
              >
                {checked && (
                  <View style={styles.checkmark} />
                )}
              </View>
              <Typography
                variant="body"
                style={styles.checkboxLabel}
              >
                {copy.orientation.checkbox}
              </Typography>
            </Pressable>
          </View>

          <View style={styles.actions}>
            <Button
              title="Continue"
              onPress={handleContinue}
              disabled={!canProceed}
            />
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: {
  background: string;
  divider: string;
  text: string;
}) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    scrollContent: {
      flexGrow: 1,
    },
    content: {
      flex: 1,
      paddingHorizontal: spacing.lg,
      justifyContent: 'space-between',
      paddingTop: spacing.section,
      paddingBottom: spacing.xxl,
    },
    body: {},
    headline: {
      marginBottom: spacing.xl,
    },
    paragraph: {
      marginBottom: spacing.md,
    },
    timeNote: {
      marginTop: spacing.sm,
    },
    promise: {
      marginTop: spacing.xs,
      marginBottom: spacing.lg,
    },
    divider: {
      height: 1,
      backgroundColor: colors.divider,
      marginVertical: spacing.lg,
    },
    checkboxRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      minHeight: layout.touchTarget,
      paddingVertical: spacing.sm,
    },
    checkbox: {
      width: 22,
      height: 22,
      borderWidth: 1.5,
      borderColor: colors.text,
      marginRight: spacing.md,
      marginTop: 3,
      alignItems: 'center',
      justifyContent: 'center',
    },
    checkboxChecked: {
      backgroundColor: colors.text,
    },
    checkmark: {
      width: 10,
      height: 10,
      backgroundColor: colors.background,
    },
    checkboxLabel: {
      flex: 1,
    },
    actions: {
      marginTop: spacing.xxl,
    },
  });
