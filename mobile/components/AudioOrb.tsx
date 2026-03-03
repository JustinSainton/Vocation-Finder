import React, { useEffect } from 'react';
import { StyleSheet, Pressable } from 'react-native';
import Animated, {
  cancelAnimation,
  Easing,
  interpolate,
  SharedValue,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSequence,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import type { ConversationState } from '../stores/assessmentStore';

const ORB_SIZE = 190;
const HALO_SIZE = ORB_SIZE + 74;
const ORBIT_SIZE = ORB_SIZE + 28;

interface AudioOrbProps {
  state: ConversationState;
  audioLevel: SharedValue<number>;
  speechLevel?: SharedValue<number>;
  onPress?: () => void;
}

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

type OrbPalette = {
  halo: string;
  ring: string;
  core: string;
  spark: string;
};

function paletteForState(state: ConversationState): OrbPalette {
  switch (state) {
    case 'listening':
      return {
        halo: 'rgba(59, 130, 246, 0.26)',
        ring: '#2563EB',
        core: '#0F172A',
        spark: '#60A5FA',
      };
    case 'speaking':
      return {
        halo: 'rgba(34, 197, 94, 0.24)',
        ring: '#16A34A',
        core: '#052E16',
        spark: '#86EFAC',
      };
    case 'processing':
      return {
        halo: 'rgba(245, 158, 11, 0.22)',
        ring: '#D97706',
        core: '#451A03',
        spark: '#FCD34D',
      };
    case 'error':
      return {
        halo: 'rgba(239, 68, 68, 0.24)',
        ring: '#B91C1C',
        core: '#450A0A',
        spark: '#FCA5A5',
      };
    case 'idle':
    default:
      return {
        halo: 'rgba(168, 162, 158, 0.24)',
        ring: '#57534E',
        core: '#1C1917',
        spark: '#D6D3D1',
      };
  }
}

export function AudioOrb({ state, audioLevel, speechLevel, onPress }: AudioOrbProps) {
  const breathe = useSharedValue(1);
  const spin = useSharedValue(0);
  const shimmer = useSharedValue(0.3);
  const pressedScale = useSharedValue(1);

  useEffect(() => {
    cancelAnimation(breathe);
    cancelAnimation(spin);
    cancelAnimation(shimmer);

    switch (state) {
      case 'idle': {
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.03, { duration: 2400, easing: Easing.inOut(Easing.ease) }),
            withTiming(1, { duration: 2400, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        spin.value = withRepeat(withTiming(360, { duration: 22000, easing: Easing.linear }), -1, false);
        shimmer.value = withRepeat(
          withSequence(
            withTiming(0.42, { duration: 2400, easing: Easing.inOut(Easing.ease) }),
            withTiming(0.24, { duration: 2400, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        break;
      }
      case 'listening': {
        breathe.value = withTiming(1, { duration: 200 });
        spin.value = withRepeat(withTiming(360, { duration: 6000, easing: Easing.linear }), -1, false);
        shimmer.value = withRepeat(
          withSequence(
            withTiming(0.68, { duration: 600, easing: Easing.inOut(Easing.ease) }),
            withTiming(0.34, { duration: 600, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        break;
      }
      case 'processing': {
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.015, { duration: 1800, easing: Easing.inOut(Easing.ease) }),
            withTiming(0.99, { duration: 1800, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        spin.value = withRepeat(withTiming(-360, { duration: 16000, easing: Easing.linear }), -1, false);
        shimmer.value = withTiming(0.16, { duration: 280 });
        break;
      }
      case 'speaking': {
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.045, { duration: 1300, easing: Easing.inOut(Easing.ease) }),
            withTiming(1, { duration: 1300, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        spin.value = withRepeat(withTiming(360, { duration: 7000, easing: Easing.linear }), -1, false);
        shimmer.value = withRepeat(
          withSequence(
            withTiming(0.74, { duration: 620, easing: Easing.inOut(Easing.ease) }),
            withTiming(0.42, { duration: 620, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        break;
      }
      case 'error': {
        breathe.value = withTiming(1, { duration: 300 });
        spin.value = withTiming(0, { duration: 300 });
        shimmer.value = withTiming(0.14, { duration: 300 });
        break;
      }
    }
  }, [state]);

  const containerStyle = useAnimatedStyle(() => {
    const activeLevel = state === 'listening'
      ? audioLevel.value
      : state === 'speaking'
        ? speechLevel?.value ?? 0
        : 0;
    const reactiveScale = interpolate(activeLevel, [0, 1], [1, 1.14]);

    return {
      transform: [{ scale: pressedScale.value * breathe.value * reactiveScale }],
    };
  });

  const haloStyle = useAnimatedStyle(() => {
    const activeLevel = state === 'listening'
      ? audioLevel.value
      : state === 'speaking'
        ? speechLevel?.value ?? 0
        : 0;
    const scale = interpolate(activeLevel, [0, 1], [1, 1.28]);

    return {
      opacity: shimmer.value,
      transform: [{ scale }],
    };
  });

  const orbitStyle = useAnimatedStyle(() => ({
    transform: [{ rotate: `${spin.value}deg` }],
  }));

  const orbitReverseStyle = useAnimatedStyle(() => ({
    transform: [{ rotate: `${-spin.value * 0.68}deg` }],
  }));

  const corePulseStyle = useAnimatedStyle(() => {
    const activeLevel = state === 'listening'
      ? audioLevel.value
      : state === 'speaking'
        ? speechLevel?.value ?? 0
        : 0;

    return {
      transform: [{ scale: interpolate(activeLevel, [0, 1], [1, 1.22]) }],
      opacity: interpolate(activeLevel, [0, 1], [0.34, 0.78]),
    };
  });

  const palette = paletteForState(state);

  return (
    <AnimatedPressable
      onPress={onPress}
      onPressIn={() => {
        pressedScale.value = withSpring(0.965, { damping: 12, stiffness: 220 });
      }}
      onPressOut={() => {
        pressedScale.value = withSpring(1, { damping: 12, stiffness: 220 });
      }}
      style={[styles.container, containerStyle]}
    >
      <Animated.View
        style={[
          styles.halo,
          { backgroundColor: palette.halo },
          haloStyle,
        ]}
      />
      <Animated.View
        style={[
          styles.orbit,
          { borderColor: palette.ring },
          orbitStyle,
        ]}
      />
      <Animated.View
        style={[
          styles.orbitReverse,
          { borderColor: palette.spark },
          orbitReverseStyle,
        ]}
      />
      <Animated.View
        style={[
          styles.corePulse,
          { backgroundColor: palette.spark },
          corePulseStyle,
        ]}
      />
      <Animated.View
        style={[
          styles.core,
          {
            backgroundColor: palette.core,
            borderColor: palette.spark,
          },
        ]}
      />
    </AnimatedPressable>
  );
}

const styles = StyleSheet.create({
  container: {
    width: HALO_SIZE,
    height: HALO_SIZE,
    alignItems: 'center',
    justifyContent: 'center',
  },
  halo: {
    position: 'absolute',
    width: HALO_SIZE,
    height: HALO_SIZE,
    borderRadius: HALO_SIZE / 2,
  },
  orbit: {
    position: 'absolute',
    width: ORBIT_SIZE,
    height: ORBIT_SIZE,
    borderRadius: ORBIT_SIZE / 2,
    borderWidth: 1.8,
    borderStyle: 'dashed',
  },
  orbitReverse: {
    position: 'absolute',
    width: ORBIT_SIZE - 18,
    height: ORBIT_SIZE - 18,
    borderRadius: (ORBIT_SIZE - 18) / 2,
    borderWidth: 1.4,
    borderStyle: 'dashed',
  },
  corePulse: {
    position: 'absolute',
    width: ORB_SIZE - 44,
    height: ORB_SIZE - 44,
    borderRadius: (ORB_SIZE - 44) / 2,
  },
  core: {
    width: ORB_SIZE,
    height: ORB_SIZE,
    borderRadius: ORB_SIZE / 2,
    borderWidth: 1.8,
    boxShadow: '0 18px 48px rgba(0, 0, 0, 0.22)',
  },
});
