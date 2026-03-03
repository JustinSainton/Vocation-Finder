import React, { useEffect } from 'react';
import { StyleSheet, Pressable } from 'react-native';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withRepeat,
  withTiming,
  withSequence,
  withSpring,
  Easing,
  SharedValue,
  interpolate,
  cancelAnimation,
} from 'react-native-reanimated';
import { colors } from '../constants/theme';
import type { ConversationState } from '../stores/assessmentStore';

const ORB_SIZE = 160;
const GLOW_SIZE = ORB_SIZE + 40;

interface AudioOrbProps {
  state: ConversationState;
  audioLevel: SharedValue<number>;
  onPress?: () => void;
}

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

export function AudioOrb({ state, audioLevel, onPress }: AudioOrbProps) {
  const breathe = useSharedValue(1);
  const glowOpacity = useSharedValue(0.35);

  useEffect(() => {
    cancelAnimation(breathe);
    cancelAnimation(glowOpacity);

    switch (state) {
      case 'idle': {
        // Slow breathing
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.06, { duration: 2400, easing: Easing.inOut(Easing.ease) }),
            withTiming(1, { duration: 2400, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        glowOpacity.value = withRepeat(
          withSequence(
            withTiming(0.5, { duration: 2400, easing: Easing.inOut(Easing.ease) }),
            withTiming(0.25, { duration: 2400, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        break;
      }
      case 'listening': {
        // Audio level drives the orb — breathe is base scale, audioLevel adds reactivity
        breathe.value = withTiming(1, { duration: 200 });
        glowOpacity.value = withTiming(0.6, { duration: 200 });
        break;
      }
      case 'processing': {
        // Dimmed, slower breathing
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.03, { duration: 3200, easing: Easing.inOut(Easing.ease) }),
            withTiming(1, { duration: 3200, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        glowOpacity.value = withTiming(0.15, { duration: 400 });
        break;
      }
      case 'speaking': {
        // Gentle steady pulse
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.05, { duration: 1600, easing: Easing.inOut(Easing.ease) }),
            withTiming(1, { duration: 1600, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        glowOpacity.value = withRepeat(
          withSequence(
            withTiming(0.55, { duration: 1600, easing: Easing.inOut(Easing.ease) }),
            withTiming(0.35, { duration: 1600, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        break;
      }
      case 'error': {
        breathe.value = withTiming(1, { duration: 300 });
        glowOpacity.value = withTiming(0.1, { duration: 300 });
        break;
      }
    }
  }, [state]);

  const orbStyle = useAnimatedStyle(() => {
    if (state === 'listening') {
      // React to audio level
      const levelScale = interpolate(audioLevel.value, [0, 1], [1, 1.2]);
      return {
        transform: [{ scale: levelScale }],
      };
    }
    return {
      transform: [{ scale: breathe.value }],
    };
  });

  const glowStyle = useAnimatedStyle(() => {
    if (state === 'listening') {
      const levelScale = interpolate(audioLevel.value, [0, 1], [1, 1.25]);
      return {
        opacity: glowOpacity.value,
        transform: [{ scale: levelScale }],
      };
    }
    return {
      opacity: glowOpacity.value,
      transform: [{ scale: breathe.value }],
    };
  });

  const orbBorderColor = state === 'error' ? '#B91C1C' : colors.text;
  const glowBorderColor = state === 'error' ? '#B91C1C' : colors.accent;

  return (
    <AnimatedPressable
      onPress={onPress}
      style={styles.container}
    >
      <Animated.View
        style={[
          styles.glow,
          { borderColor: glowBorderColor },
          glowStyle,
        ]}
      />
      <Animated.View
        style={[
          styles.orb,
          { borderColor: orbBorderColor },
          orbStyle,
        ]}
      />
    </AnimatedPressable>
  );
}

const styles = StyleSheet.create({
  container: {
    width: GLOW_SIZE,
    height: GLOW_SIZE,
    alignItems: 'center',
    justifyContent: 'center',
  },
  glow: {
    position: 'absolute',
    width: GLOW_SIZE,
    height: GLOW_SIZE,
    borderRadius: GLOW_SIZE / 2,
    borderWidth: 1,
  },
  orb: {
    width: ORB_SIZE,
    height: ORB_SIZE,
    borderRadius: ORB_SIZE / 2,
    borderWidth: 1.5,
  },
});
