import React, { useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { Typography } from './ui/Typography';
import { Button } from './ui/Button';
import { spacing } from '../constants/theme';
import { useTheme } from '../hooks/useTheme';
import { authApi } from '../services/auth';
import { useAuthStore } from '../stores/authStore';
import { useAssessmentStore } from '../stores/assessmentStore';

/**
 * "Continue as demo". Renders nothing unless the server says demo mode is on
 * and a demo account exists, and nothing once someone is signed in.
 */
export function DemoSignIn() {
  const router = useRouter();
  const { colors } = useTheme();
  const signedIn = useAuthStore((state) => state.user !== null);
  const demoLogin = useAuthStore((state) => state.demoLogin);
  const isLoading = useAuthStore((state) => state.isLoading);
  const [available, setAvailable] = useState(false);

  useEffect(() => {
    if (signedIn) {
      return;
    }

    let active = true;
    authApi
      .demoAvailable()
      .then((data) => active && setAvailable(data.available))
      .catch(() => active && setAvailable(false));

    return () => {
      active = false;
    };
  }, [signedIn]);

  const handlePress = async () => {
    try {
      await demoLogin();
    } catch {
      return;
    }
    // Questions fetched before signing in are persisted without their demo
    // answers, so they have to be fetched again as the demo account.
    await useAssessmentStore.getState().fetchQuestions();
    router.replace('/(assessment)');
  };

  if (signedIn || !available) {
    return null;
  }

  return (
    <View style={[styles.demo, { borderTopColor: colors.divider }]}>
      <Typography variant="eyebrow" color={colors.muted}>
        Demo mode is on
      </Typography>
      <Button
        title="Continue as demo"
        variant="secondary"
        onPress={handlePress}
        disabled={isLoading}
      />
      <Typography variant="small" family="sans" color={colors.muted}>
        Signs into the demo account and opens the assessment with its answers pre-filled.
      </Typography>
    </View>
  );
}

const styles = StyleSheet.create({
  demo: {
    marginTop: spacing.xl,
    paddingTop: spacing.lg,
    borderTopWidth: 1,
    gap: spacing.sm,
  },
});
