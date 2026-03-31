import React, { useState } from 'react';
import { View, StyleSheet } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function SynthesisScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const locale = useAssessmentStore((state) => state.locale);
  const { completeAssessment } = useAssessmentStore();
  const copy = getAssessmentCopy(locale);
  const [submitting, setSubmitting] = useState(false);

  const handleContinue = async () => {
    setSubmitting(true);

    try {
      await completeAssessment();
      await Haptics.notificationAsync(
        Haptics.NotificationFeedbackType.Success
      );
      router.replace('/(assessment)/after');
    } catch {
      // If the API call fails, still navigate -- after survey can be skipped
      await Haptics.notificationAsync(
        Haptics.NotificationFeedbackType.Success
      );
      router.replace('/(assessment)/after');
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.body}>
          <Typography variant="bodyLarge" style={styles.paragraph}>
            {copy.synthesis.paragraphOne}
          </Typography>

          <Typography
            variant="body"
            color={colors.textSecondary}
            style={styles.paragraph}
          >
            {copy.synthesis.paragraphTwo}
          </Typography>
        </View>

        <View style={styles.actions}>
          <Button
            title={submitting ? copy.synthesis.preparing : copy.synthesis.continueLabel}
            onPress={handleContinue}
            disabled={submitting}
          />
        </View>
      </View>
    </SafeAreaView>
  );
}

const getStyles = (colors: { background: string }) =>
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
    paragraph: {
      marginBottom: spacing.lg,
    },
    actions: {},
  });
