import React, { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, {
  Easing,
  ReduceMotion,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withTiming,
} from 'react-native-reanimated';
import { Typography } from '../ui/Typography';
import { useTheme } from '../../hooks/useTheme';
import { spacing } from '../../constants/theme';

function Dot({ delay }: { delay: number }) {
  const { colors } = useTheme();
  const opacity = useSharedValue(0.25);

  useEffect(() => {
    opacity.value = withDelay(
      delay,
      withRepeat(
        withTiming(1, { duration: 600, easing: Easing.bezier(0.2, 0, 0, 1), reduceMotion: ReduceMotion.System }),
        -1,
        true
      )
    );
  }, [delay, opacity]);

  const style = useAnimatedStyle(() => ({ opacity: opacity.value }));

  return <Animated.View style={[styles.dot, { backgroundColor: colors.textSecondary }, style]} />;
}

/**
 * What the coach is doing while it is not yet talking, in the student's
 * words — "Reading your portrait", not a spinner.
 */
export function CoachThinking({ label }: { label: string }) {
  const { colors } = useTheme();

  return (
    <View
      accessibilityRole="progressbar"
      accessibilityLabel={label}
      style={[styles.container, { borderColor: colors.divider }]}
    >
      <View style={styles.dots}>
        <Dot delay={0} />
        <Dot delay={160} />
        <Dot delay={320} />
      </View>
      <Typography variant="small" family="sans" color={colors.textSecondary}>
        {label}
      </Typography>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm + 4,
    borderWidth: 1,
    borderRadius: 6,
    paddingHorizontal: spacing.md + 4,
    paddingVertical: spacing.md,
    marginBottom: spacing.md,
  },
  dots: { flexDirection: 'row', gap: 4 },
  dot: { width: 5, height: 5 },
});
