import { Stack } from 'expo-router';
import { useTheme } from '../../hooks/useTheme';

export default function AssessmentLayout() {
  const { colors } = useTheme();

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
