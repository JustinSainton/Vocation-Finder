import React, { useEffect, useState } from 'react';
import { View, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withRepeat,
  withTiming,
  withSequence,
  Easing,
} from 'react-native-reanimated';
import { Typography } from '../../components/ui/Typography';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { colors, spacing } from '../../constants/theme';

type OrbState = 'idle' | 'listening' | 'thinking';

const STATE_LABELS: Record<OrbState, string> = {
  idle: 'Tap to begin',
  listening: 'Listening...',
  thinking: 'Thinking...',
};

const ORB_SIZE = 160;

export default function ConversationScreen() {
  const [orbState] = useState<OrbState>('idle');
  const { currentQuestion, totalQuestions } = useAssessmentStore();

  // Animated scale for the pulse
  const scale = useSharedValue(1);
  // Animated opacity for a subtle breathing effect
  const opacity = useSharedValue(0.35);

  useEffect(() => {
    // Gentle pulse animation -- always running
    scale.value = withRepeat(
      withSequence(
        withTiming(1.08, {
          duration: 2400,
          easing: Easing.inOut(Easing.ease),
        }),
        withTiming(1, {
          duration: 2400,
          easing: Easing.inOut(Easing.ease),
        })
      ),
      -1, // infinite
      false
    );

    opacity.value = withRepeat(
      withSequence(
        withTiming(0.55, {
          duration: 2400,
          easing: Easing.inOut(Easing.ease),
        }),
        withTiming(0.35, {
          duration: 2400,
          easing: Easing.inOut(Easing.ease),
        })
      ),
      -1,
      false
    );
  }, []);

  const orbAnimatedStyle = useAnimatedStyle(() => ({
    transform: [{ scale: scale.value }],
  }));

  const glowAnimatedStyle = useAnimatedStyle(() => ({
    opacity: opacity.value,
    transform: [{ scale: scale.value }],
  }));

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        {/* Top spacer for centering */}
        <View style={styles.topSpacer} />

        {/* Orb area */}
        <View style={styles.orbContainer}>
          {/* Outer glow ring */}
          <Animated.View style={[styles.orbGlow, glowAnimatedStyle]} />
          {/* Main orb */}
          <Animated.View style={[styles.orb, orbAnimatedStyle]} />
        </View>

        {/* State indicator */}
        <Typography
          variant="body"
          color={colors.textSecondary}
          style={styles.stateLabel}
        >
          {STATE_LABELS[orbState]}
        </Typography>

        {/* Bottom area */}
        <View style={styles.bottomArea}>
          {/* Question indicator */}
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.indicator}
          >
            {totalQuestions > 0
              ? `Question ${currentQuestion + 1} of ${totalQuestions}`
              : 'Conversation mode'}
          </Typography>

          {/* Phase note */}
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.phaseNote}
          >
            Audio conversation is coming in a future update.
          </Typography>
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    flex: 1,
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.xxl,
  },
  topSpacer: {
    flex: 1,
  },
  orbContainer: {
    width: ORB_SIZE + 40,
    height: ORB_SIZE + 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  orbGlow: {
    position: 'absolute',
    width: ORB_SIZE + 40,
    height: ORB_SIZE + 40,
    borderRadius: (ORB_SIZE + 40) / 2,
    borderWidth: 1,
    borderColor: colors.accent,
  },
  orb: {
    width: ORB_SIZE,
    height: ORB_SIZE,
    borderRadius: ORB_SIZE / 2,
    borderWidth: 1.5,
    borderColor: colors.text,
  },
  stateLabel: {
    marginTop: spacing.xl,
    textAlign: 'center',
  },
  bottomArea: {
    flex: 1,
    justifyContent: 'flex-end',
    alignItems: 'center',
  },
  indicator: {
    marginBottom: spacing.sm,
  },
  phaseNote: {
    textAlign: 'center',
  },
});
