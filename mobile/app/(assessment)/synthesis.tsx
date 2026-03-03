import React, { useState } from 'react';
import { View, StyleSheet } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function SynthesisScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const { completeAssessment } = useAssessmentStore();
  const [submitting, setSubmitting] = useState(false);

  const handleContinue = async () => {
    setSubmitting(true);

    try {
      await completeAssessment();
      await Haptics.notificationAsync(
        Haptics.NotificationFeedbackType.Success
      );
      router.replace('/(assessment)/results');
    } catch {
      // If the API call fails, still navigate -- results screen will poll
      await Haptics.notificationAsync(
        Haptics.NotificationFeedbackType.Success
      );
      router.replace('/(assessment)/results');
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.body}>
          <Typography variant="bodyLarge" style={styles.paragraph}>
            We're now looking for patterns across what you shared — not isolated
            answers, but the story they tell together.
          </Typography>

          <Typography
            variant="body"
            color={colors.textSecondary}
            style={styles.paragraph}
          >
            Your reflections deserve careful attention. What comes next is not a
            summary — it is an articulation of what already lives within your
            responses.
          </Typography>
        </View>

        <View style={styles.actions}>
          <Button
            title={submitting ? 'Preparing...' : 'Continue'}
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
