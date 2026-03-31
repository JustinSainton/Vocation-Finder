import React, { useState } from 'react';
import { View, StyleSheet, Pressable } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import {
  ASSESSMENT_LOCALE_OPTIONS,
  getAssessmentCopy,
} from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { spacing, layout } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useAssessmentStore } from '../../stores/assessmentStore';

export default function OrientationScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const [checked, setChecked] = useState(false);
  const locale = useAssessmentStore((state) => state.locale);
  const setLocalePreferences = useAssessmentStore((state) => state.setLocalePreferences);
  const copy = getAssessmentCopy(locale);

  const handleSpeak = () => {
    router.push('/(assessment)/conversation');
  };

  const handleWrite = () => {
    router.push('/(assessment)/written');
  };

  const toggleCheck = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setChecked((prev) => !prev);
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.body}>
          <Typography variant="heading" style={styles.title}>
            {copy.orientation.title}
          </Typography>

          <Typography
            variant="small"
            family="sans"
            color={colors.textSecondary}
            style={styles.languageLabel}
          >
            {copy.orientation.languageLabel}
          </Typography>

          <View style={styles.languageOptions}>
            {ASSESSMENT_LOCALE_OPTIONS.map((option) => {
              const isActive = option.locale === locale;

              return (
                <Pressable
                  key={option.locale}
                  onPress={() => setLocalePreferences(option.locale, option.speechLocale)}
                  style={[
                    styles.languageChip,
                    isActive && styles.languageChipActive,
                  ]}
                >
                  <Typography
                    variant="small"
                    family="sans"
                    color={isActive ? colors.background : colors.text}
                  >
                    {option.nativeLabel}
                  </Typography>
                </Pressable>
              );
            })}
          </View>

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
            title={copy.orientation.speak}
            onPress={handleSpeak}
            disabled={!checked}
          />
          <Button
            title={copy.orientation.write}
            onPress={handleWrite}
            variant="secondary"
            disabled={!checked}
            style={styles.secondaryButton}
          />
        </View>
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
    content: {
      flex: 1,
      paddingHorizontal: spacing.lg,
      justifyContent: 'space-between',
      paddingTop: spacing.section,
      paddingBottom: spacing.xxl,
    },
    body: {},
    title: {
      marginBottom: spacing.xl,
    },
    languageLabel: {
      marginBottom: spacing.sm,
      textTransform: 'uppercase',
      letterSpacing: 0.8,
    },
    languageOptions: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.sm,
      marginBottom: spacing.xl,
    },
    languageChip: {
      borderWidth: 1,
      borderColor: colors.divider,
      borderRadius: 999,
      paddingVertical: 10,
      paddingHorizontal: 14,
      backgroundColor: colors.background,
    },
    languageChipActive: {
      backgroundColor: colors.text,
      borderColor: colors.text,
    },
    paragraph: {
      marginBottom: spacing.md,
    },
    timeNote: {
      marginTop: spacing.sm,
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
    secondaryButton: {
      marginTop: spacing.md,
    },
  });
