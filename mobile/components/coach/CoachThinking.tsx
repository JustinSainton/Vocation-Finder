import React, { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, {
  Easing,
  ReduceMotion,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withTiming,
} from 'react-native-reanimated';
import { Typography } from '../ui/Typography';
import { useTheme } from '../../hooks/useTheme';
import { radius, spacing } from '../../constants/theme';

const STAGGER = 160;
const RISE = 260;
const DOT = 6;

function Dot({ index, color }: { index: number; color: string }) {
  const lift = useSharedValue(0);

  useEffect(() => {
    const ease = { duration: RISE, easing: Easing.bezier(0.2, 0, 0, 1), reduceMotion: ReduceMotion.System };
    lift.value = withDelay(
      index * STAGGER,
      withRepeat(
        withSequence(
          withTiming(1, ease),
          withTiming(0, ease),
          withTiming(0, { duration: (2 - index) * STAGGER + index * STAGGER, reduceMotion: ReduceMotion.System })
        ),
        -1
      )
    );
  }, [index, lift]);

  const style = useAnimatedStyle(() => ({
    opacity: 0.3 + lift.value * 0.7,
    transform: [{ translateY: -1 - lift.value * 2 }],
  }));

  return <Animated.View style={[styles.dot, { backgroundColor: color }, style]} />;
}

/**
 * The coach is writing: three dots that rise and fade in turn, and — when
 * the screen knows it — what the coach is doing, in the student's words
 * ("Reading your portrait"), rather than a bare spinner.
 *
 * Self-contained: no coach state, no API types, only the theme. Renders with
 * no props at all. Motion is native-thread and drops to still dots under the
 * system's reduced-motion setting.
 */
export function CoachThinking({ label = 'Thinking it through' }: { label?: string }) {
  const { colors } = useTheme();

  return (
    <View
      accessibilityRole="progressbar"
      accessibilityLabel={`The coach is writing. ${label}`}
      style={[styles.container, { backgroundColor: colors.surfaceCard }]}
    >
      <View style={styles.dots}>
        {[0, 1, 2].map((i) => (
          <Dot key={i} index={i} color={colors.accent} />
        ))}
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
    gap: spacing.sm,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.md,
  },
  dots: { flexDirection: 'row', alignItems: 'center', gap: spacing.xxs, paddingVertical: 2 },
  dot: { width: DOT, height: DOT, borderRadius: DOT / 2 },
});
