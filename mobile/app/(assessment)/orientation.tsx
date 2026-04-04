import React, { useEffect, useState } from 'react';
import { View, ScrollView, StyleSheet, Pressable } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import {
  getAssessmentCopy,
} from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { SingleLineInput } from '../../components/ui/SingleLineInput';
import { spacing, layout } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { useAuthStore } from '../../stores/authStore';
export default function OrientationScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const [checked, setChecked] = useState(false);
  const locale = useAssessmentStore((state) => state.locale);
  const copy = getAssessmentCopy(locale);

  // Pre-fill name from auth store if user is logged in
  const authUser = useAuthStore((s) => s.user);
  const [firstName, setFirstName] = useState(authUser?.name?.split(' ')[0] ?? '');

  useEffect(() => {
    const prefetch = async () => {
      const state = useAssessmentStore.getState();
      if (state.questions.length === 0 || state.questionsLocale !== state.locale) {
        await state.fetchQuestions();
      }
      // Warmup STT/TTS via dynamic require to avoid native module crash at startup
      try {
        const { warmupStt } = require('../../services/sttService');
        warmupStt();
      } catch {}
      try {
        const { warmupTts } = require('../../services/ttsService');
        warmupTts();
      } catch {}
    };
    prefetch();
  }, [locale]);

  const handleSpeak = () => {
    // Store the name for use in the conversation greeting
    useAssessmentStore.setState({ guestName: firstName.trim() || undefined });
    router.push('/(assessment)/before?mode=conversation');
  };

  const handleWrite = () => {
    useAssessmentStore.setState({ guestName: firstName.trim() || undefined });
    router.push('/(assessment)/before?mode=written');
  };

  const toggleCheck = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setChecked((prev) => !prev);
  };

  const canProceed = checked && firstName.trim().length > 0;

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
        <View style={styles.content}>
        <View style={styles.body}>
          <Typography variant="heading" style={styles.title}>
            {copy.orientation.title}
          </Typography>

          {/* Name input */}
          <View style={styles.nameSection}>
            <Typography
              variant="caption"
              family="sans"
              color={colors.textSecondary}
              style={styles.nameLabel}
            >
              What should we call you?
            </Typography>
            <SingleLineInput
              value={firstName}
              onChangeText={setFirstName}
              placeholder="Your first name"
              autoCapitalize="words"
              autoFocus={!firstName}
              returnKeyType="done"
            />
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
          <View style={styles.actionRow}>
            <View style={styles.actionHalf}>
              <Button
                title="Speak"
                onPress={handleSpeak}
                disabled={!canProceed}
              />
            </View>
            <View style={styles.actionHalf}>
              <Button
                title="Write"
                onPress={handleWrite}
                variant="secondary"
                disabled={!canProceed}
              />
            </View>
          </View>
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
    title: {
      marginBottom: spacing.xl,
    },
    nameSection: {
      marginBottom: spacing.xl,
    },
    nameLabel: {
      textTransform: 'uppercase',
      letterSpacing: 1,
      marginBottom: spacing.sm,
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
    actionRow: {
      flexDirection: 'row',
      gap: spacing.md,
    },
    actionHalf: {
      flex: 1,
    },
  });
