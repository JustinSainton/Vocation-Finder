import React, { useEffect, useMemo, useState } from 'react';
import { TextStyle } from 'react-native';
import * as Haptics from 'expo-haptics';
import { Typography } from './Typography';

type Variant = 'body' | 'bodyLarge' | 'heading' | 'headingLarge' | 'small' | 'caption';
type Family = 'serif' | 'sans';

interface TypewriterTextProps {
  text: string;
  variant?: Variant;
  family?: Family;
  color?: string;
  style?: TextStyle | TextStyle[];
  speedMs?: number;
  startDelayMs?: number;
  showCursor?: boolean;
  enableHaptics?: boolean;
  hapticEveryNChars?: number;
}

export function TypewriterText({
  text,
  variant = 'headingLarge',
  family = 'serif',
  color,
  style,
  speedMs = 18,
  startDelayMs = 120,
  showCursor = true,
  enableHaptics = false,
  hapticEveryNChars = 3,
}: TypewriterTextProps) {
  const [visibleChars, setVisibleChars] = useState(0);

  useEffect(() => {
    setVisibleChars(0);
    let typeTimer: ReturnType<typeof setInterval> | undefined;

    const startTimer = setTimeout(() => {
      typeTimer = setInterval(() => {
        setVisibleChars((prev) => {
          if (prev >= text.length) {
            if (typeTimer) {
              clearInterval(typeTimer);
            }
            return prev;
          }

          const next = prev + 1;

          if (enableHaptics) {
            const typedChar = text.charAt(next - 1);
            const shouldPulse =
              (typedChar.trim().length > 0 && next % Math.max(1, hapticEveryNChars) === 0) ||
              /[.!?,;:\n]/.test(typedChar);

            if (shouldPulse) {
              Haptics.selectionAsync().catch(() => null);
            }
          }

          return next;
        });
      }, speedMs);
    }, startDelayMs);

    return () => {
      clearTimeout(startTimer);
      if (typeTimer) {
        clearInterval(typeTimer);
      }
    };
  }, [enableHaptics, hapticEveryNChars, text, speedMs, startDelayMs]);

  const visibleText = useMemo(() => text.slice(0, visibleChars), [text, visibleChars]);
  const isComplete = visibleChars >= text.length;

  return (
    <Typography
      variant={variant}
      family={family}
      color={color}
      style={style}
    >
      {visibleText}
      {showCursor && !isComplete ? '|' : ''}
    </Typography>
  );
}
