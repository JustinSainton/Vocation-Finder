import React, { useEffect, useRef } from 'react';
import { Animated, StyleSheet, View } from 'react-native';
import { spacing } from '../../constants/theme';

interface TypingIndicatorProps {
  color?: string;
  size?: number;
}

/**
 * Rolling three-dot typing indicator in the style of Claude/ChatGPT. Each dot
 * rises and fades on a staggered loop so the motion reads as "the coach is
 * writing" rather than a loading spinner. Uses only native-driven transforms.
 */
export function TypingIndicator({
  color = '#8F8FF5',
  size = 6,
}: TypingIndicatorProps) {
  const dots = useRef([
    new Animated.Value(0),
    new Animated.Value(0),
    new Animated.Value(0),
  ]).current;

  useEffect(() => {
    const STAGGER = 160;
    const RISE = 260;

    const loops = dots.map((value, i) =>
      Animated.loop(
        Animated.sequence([
          Animated.delay(i * STAGGER),
          Animated.timing(value, {
            toValue: 1,
            duration: RISE,
            useNativeDriver: true,
          }),
          Animated.timing(value, {
            toValue: 0,
            duration: RISE,
            useNativeDriver: true,
          }),
          Animated.delay((dots.length - 1 - i) * STAGGER),
        ])
      )
    );

    loops.forEach((loop) => loop.start());

    return () => loops.forEach((loop) => loop.stop());
  }, [dots]);

  return (
    <View
      style={styles.row}
      accessibilityLabel="The coach is writing"
      accessibilityRole="progressbar"
    >
      {dots.map((value, i) => (
        <Animated.View
          key={i}
          style={[
            styles.dot,
            {
              width: size,
              height: size,
              borderRadius: size / 2,
              backgroundColor: color,
              opacity: value.interpolate({
                inputRange: [0, 1],
                outputRange: [0.3, 1],
              }),
              transform: [
                {
                  translateY: value.interpolate({
                    inputRange: [0, 1],
                    outputRange: [-1, -3],
                  }),
                },
              ],
            },
          ]}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    paddingVertical: 2,
  },
  dot: {},
});