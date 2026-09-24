import React, { useEffect, useState } from 'react';
import { View, ScrollView, StyleSheet, Pressable } from 'react-native';
import { Link, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { spacing, layout } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { DemoBadge } from '../../components/DemoBadge';
import { DemoSignIn } from '../../components/DemoSignIn';
import { useAuthStore } from '../../stores/authStore';

export default function AssessmentLandingScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const [checked, setChecked] = useState(false);
  const locale = useAssessmentStore((state) => state.locale);
  const copy = getAssessmentCopy(locale);
  const signedIn = useAuthStore((state) => state.user !== null);

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
      <ScrollView
        style={styles.flex}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        <DemoBadge />
        <Typography
          variant="caption"
          family="sans"
          color={colors.textSecondary}
          style={styles.eyebrow}
        >
          {copy.orientation.eyebrow}
        </Typography>

        <Typography variant="heading" style={styles.headline}>
          {copy.orientation.title}
        </Typography>

        <Typography variant="body" style={styles.paragraph}>
          {copy.orientation.introOne}
        </Typography>

        <Typography variant="body" style={styles.paragraph}>
          {copy.orientation.introTwo}
        </Typography>

        {/* The app opens here, so this is the only sign-in a new install sees. */}
        {!signedIn ? (
          <View style={styles.signInRow}>
            <Typography variant="small" color={colors.textSecondary}>
              Already have an account?{' '}
            </Typography>
            <Link href="/(auth)/login">
              <Typography variant="small" color={colors.text}>
                Sign in
              </Typography>
            </Link>
          </View>
        ) : null}

        <DemoSignIn />
      </ScrollView>

      <View style={styles.footer}>
        <Typography
          variant="small"
          family="sans"
          color={colors.textSecondary}
          style={styles.timeNote}
        >
          {copy.orientation.timeNote}
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

        <Button
          title={copy.orientation.beginCta}
          onPress={handleContinue}
          disabled={!canProceed}
        />
      </View>
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
    flex: {
      flex: 1,
    },
    scrollContent: {
      paddingHorizontal: spacing.lg,
      paddingTop: spacing.lg,
      paddingBottom: spacing.xl,
    },
    footer: {
      paddingHorizontal: spacing.lg,
      paddingBottom: spacing.lg,
      backgroundColor: colors.background,
    },
    eyebrow: {
      marginBottom: spacing.md,
      letterSpacing: 1.2,
      textTransform: 'uppercase',
    },
    headline: {
      marginBottom: spacing.lg,
    },
    paragraph: {
      marginBottom: spacing.md,
    },
    timeNote: {
      marginBottom: spacing.sm,
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
    signInRow: {
      flexDirection: 'row',
      marginTop: spacing.sm,
    },
  });
