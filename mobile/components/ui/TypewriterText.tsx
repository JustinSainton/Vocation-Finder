import React, { useEffect, useMemo, useState } from 'react';
import { TextStyle } from 'react-native';
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

          return prev + 1;
        });
      }, speedMs);
    }, startDelayMs);

    return () => {
      clearTimeout(startTimer);
      if (typeTimer) {
        clearInterval(typeTimer);
      }
    };
  }, [text, speedMs, startDelayMs]);

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
