import { Stack } from 'expo-router';
import { colors } from '../../constants/theme';

export default function AssessmentLayout() {
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
