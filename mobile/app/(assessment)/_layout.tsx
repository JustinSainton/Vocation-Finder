import { useEffect } from 'react';
import { Stack, useRouter } from 'expo-router';
import { useTheme } from '../../hooks/useTheme';
import { useAssessmentStore } from '../../stores/assessmentStore';

export default function AssessmentLayout() {
  const { colors } = useTheme();
  const router = useRouter();

  // Cold start: persisted analyzing sessions should land on the waiting screen.
  useEffect(() => {
    const { status, assessmentId, results } = useAssessmentStore.getState();
    if (status === 'analyzing' && assessmentId && !results) {
      router.replace('/(assessment)/results');
    }
  }, [router]);

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.background },
        animation: 'fade',
        gestureEnabled: false,
      }}
    />
  );
}
