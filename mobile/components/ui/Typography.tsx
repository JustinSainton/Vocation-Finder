import React from 'react';
import { Text, TextStyle } from 'react-native';
import { typography } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

type Variant = 'body' | 'bodyLarge' | 'heading' | 'headingLarge' | 'small' | 'caption';
type Family = 'serif' | 'sans';

interface TypographyProps {
  variant?: Variant;
  family?: Family;
  color?: string;
  style?: TextStyle | TextStyle[];
  children: React.ReactNode;
}

const variantStyles: Record<Variant, TextStyle> = {
  body: {
    fontSize: typography.sizes.body,
    lineHeight: typography.sizes.body * typography.lineHeight.body,
  },
  bodyLarge: {
    fontSize: typography.sizes.bodyLarge,
    lineHeight: typography.sizes.bodyLarge * typography.lineHeight.body,
  },
  heading: {
    fontSize: typography.sizes.heading,
    lineHeight: typography.sizes.heading * typography.lineHeight.heading,
  },
  headingLarge: {
    fontSize: typography.sizes.headingLarge,
    lineHeight: typography.sizes.headingLarge * typography.lineHeight.heading,
  },
  small: {
    fontSize: typography.sizes.small,
    lineHeight: typography.sizes.small * typography.lineHeight.body,
  },
  caption: {
    fontSize: typography.sizes.caption,
    lineHeight: typography.sizes.caption * typography.lineHeight.body,
  },
};

export function Typography({
  variant = 'body',
  family = 'serif',
  color,
  style,
  children,
}: TypographyProps) {
  const { colors } = useTheme();
  const fontFamily =
    family === 'serif'
      ? typography.fontFamily.serif
      : typography.fontFamily.sans;

  return (
    <Text
      style={[
        variantStyles[variant],
        { fontFamily, color: color ?? colors.text },
        style,
      ]}
    >
      {children}
    </Text>
  );
}
