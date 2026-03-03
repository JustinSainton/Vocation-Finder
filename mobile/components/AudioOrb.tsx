import React, { useEffect } from 'react';
import { Pressable, StyleSheet } from 'react-native';
import Animated, {
  cancelAnimation,
  Easing,
  interpolate,
  type SharedValue,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSequence,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { useTheme } from '../hooks/useTheme';
import type { ConversationState } from '../stores/assessmentStore';

const ORB_SIZE = 190;
const HALO_SIZE = ORB_SIZE + 74;
const ORBIT_SIZE = ORB_SIZE + 28;

export type OrbAgentState = 'thinking' | 'listening' | 'talking' | null;
type VolumeMode = 'auto' | 'manual';

interface AudioOrbProps {
  // Eleven-like API
  agentState?: OrbAgentState;
  volumeMode?: VolumeMode;
  manualInput?: number;
  manualOutput?: number;
  getInputVolume?: () => number;
  getOutputVolume?: () => number;
  colors?: [string, string];

  // Backward compatibility for existing app flow
  state?: ConversationState;
  inputLevel?: SharedValue<number>;
  outputLevel?: SharedValue<number>;
  audioLevel?: SharedValue<number>;
  speechLevel?: SharedValue<number>;

  onPress?: () => void;
}

type OrbPalette = {
  halo: string;
  ring: string;
  core: string;
  spark: string;
};

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

function mapConversationStateToAgentState(state: ConversationState | undefined): OrbAgentState {
  switch (state) {
    case 'processing':
      return 'thinking';
    case 'listening':
      return 'listening';
    case 'speaking':
      return 'talking';
    default:
      return null;
  }
}

function paletteForState(
  agentState: OrbAgentState,
  colorPair: [string, string],
  isDark: boolean
): OrbPalette {
  const [primary, secondary] = colorPair;

  switch (agentState) {
    case 'listening':
      return {
        halo: isDark ? 'rgba(59, 130, 246, 0.30)' : 'rgba(59, 130, 246, 0.24)',
        ring: primary,
        core: isDark ? '#081425' : '#0F172A',
        spark: secondary,
      };
    case 'talking':
      return {
        halo: isDark ? 'rgba(34, 197, 94, 0.30)' : 'rgba(34, 197, 94, 0.24)',
        ring: secondary,
        core: isDark ? '#06210F' : '#052E16',
        spark: primary,
      };
    case 'thinking':
      return {
        halo: isDark ? 'rgba(245, 158, 11, 0.28)' : 'rgba(245, 158, 11, 0.22)',
        ring: '#D97706',
        core: isDark ? '#2C1600' : '#451A03',
        spark: '#FCD34D',
      };
    case null:
    default:
      return {
        halo: isDark ? 'rgba(148, 163, 184, 0.20)' : 'rgba(168, 162, 158, 0.24)',
        ring: isDark ? '#7DD3FC' : '#57534E',
        core: isDark ? '#10151D' : '#1C1917',
        spark: isDark ? '#C4B5FD' : '#D6D3D1',
      };
  }
}

export function AudioOrb({
  agentState,
  volumeMode = 'manual',
  manualInput = 0,
  manualOutput = 0,
  getInputVolume,
  getOutputVolume,
  colors: customColors,
  state,
  inputLevel,
  outputLevel,
  audioLevel,
  speechLevel,
  onPress,
}: AudioOrbProps) {
  const { isDark } = useTheme();
  const defaultColors: [string, string] = isDark
    ? ['#38BDF8', '#A78BFA']
    : ['#2563EB', '#22C55E'];

  const colorPair = customColors ?? defaultColors;
  const resolvedState = agentState ?? mapConversationStateToAgentState(state);
  const palette = paletteForState(resolvedState, colorPair, isDark);

  const sampledInput = useSharedValue(0);
  const sampledOutput = useSharedValue(0);

  const resolvedInput = inputLevel ?? audioLevel ?? sampledInput;
  const resolvedOutput = outputLevel ?? speechLevel ?? sampledOutput;

  const breathe = useSharedValue(1);
  const spin = useSharedValue(0);
  const shimmer = useSharedValue(0.3);
  const pressedScale = useSharedValue(1);

  useEffect(() => {
    if (inputLevel || outputLevel || audioLevel || speechLevel) {
      return;
    }

    const readLevel = (modeValue: number, getter?: () => number) => {
      if (volumeMode === 'auto' && getter) {
        const value = getter();
        return Number.isFinite(value) ? value : 0;
      }

      return modeValue;
    };

    const timer = setInterval(() => {
      sampledInput.value = Math.max(0, Math.min(1, readLevel(manualInput, getInputVolume)));
      sampledOutput.value = Math.max(0, Math.min(1, readLevel(manualOutput, getOutputVolume)));
    }, 80);

    return () => clearInterval(timer);
  }, [
    audioLevel,
    getInputVolume,
    getOutputVolume,
    inputLevel,
    manualInput,
    manualOutput,
    outputLevel,
    sampledInput,
    sampledOutput,
    speechLevel,
    volumeMode,
  ]);

  useEffect(() => {
    cancelAnimation(breathe);
    cancelAnimation(spin);
    cancelAnimation(shimmer);

    switch (resolvedState) {
      case null: {
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.03, { duration: 2400, easing: Easing.inOut(Easing.ease) }),
            withTiming(1, { duration: 2400, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        spin.value = withRepeat(
          withTiming(360, { duration: 22000, easing: Easing.linear }),
          -1,
          false
        );
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
        spin.value = withRepeat(
          withTiming(360, { duration: 5800, easing: Easing.linear }),
          -1,
          false
        );
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
      case 'thinking': {
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.015, { duration: 1800, easing: Easing.inOut(Easing.ease) }),
            withTiming(0.99, { duration: 1800, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        spin.value = withRepeat(
          withTiming(-360, { duration: 12000, easing: Easing.linear }),
          -1,
          false
        );
        shimmer.value = withTiming(0.16, { duration: 280 });
        break;
      }
      case 'talking': {
        breathe.value = withRepeat(
          withSequence(
            withTiming(1.045, { duration: 1200, easing: Easing.inOut(Easing.ease) }),
            withTiming(1, { duration: 1200, easing: Easing.inOut(Easing.ease) })
          ),
          -1,
          false
        );
        spin.value = withRepeat(
          withTiming(360, { duration: 6500, easing: Easing.linear }),
          -1,
          false
        );
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
    }
  }, [breathe, resolvedState, shimmer, spin]);

  const containerStyle = useAnimatedStyle(() => {
    const activeLevel = resolvedState === 'listening' ? resolvedInput.value : resolvedOutput.value;
    const reactiveScale = interpolate(activeLevel, [0, 1], [1, 1.14]);

    return {
      transform: [{ scale: pressedScale.value * breathe.value * reactiveScale }],
    };
  });

  const haloStyle = useAnimatedStyle(() => {
    const activeLevel = resolvedState === 'listening' ? resolvedInput.value : resolvedOutput.value;
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
    const activeLevel = resolvedState === 'listening' ? resolvedInput.value : resolvedOutput.value;

    return {
      transform: [{ scale: interpolate(activeLevel, [0, 1], [1, 1.22]) }],
      opacity: interpolate(activeLevel, [0, 1], [0.34, 0.78]),
    };
  });

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
      <Animated.View style={[styles.halo, { backgroundColor: palette.halo }, haloStyle]} />
      <Animated.View style={[styles.orbit, { borderColor: palette.ring }, orbitStyle]} />
      <Animated.View
        style={[styles.orbitReverse, { borderColor: palette.spark }, orbitReverseStyle]}
      />
      <Animated.View style={[styles.corePulse, { backgroundColor: palette.spark }, corePulseStyle]} />
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
