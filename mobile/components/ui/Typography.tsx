import React from 'react';
import { Text, TextStyle } from 'react-native';
import { typography } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { hasEmphasis, splitEmphasis } from './emphasis';

type Variant =
  | 'displayXl'
  | 'displayLg'
  | 'displayMd'
  | 'displaySm'
  | 'titleLg'
  | 'titleMd'
  | 'titleSm'
  | 'body'
  | 'bodyLarge'
  | 'heading'
  | 'headingLarge'
  | 'small'
  | 'caption'
  | 'eyebrow'
  | 'meta';

type Family = 'serif' | 'sans' | 'mono';

interface TypographyProps {
  variant?: Variant;
  family?: Family;
  color?: string;
  italic?: boolean;
  style?: TextStyle | TextStyle[];
  numberOfLines?: number;
  children: React.ReactNode;
}

const { sizes, lineHeight, tracking } = typography;

function display(size: number, tracking: number): TextStyle {
  return {
    fontSize: size,
    lineHeight: size * 1.08,
    letterSpacing: tracking,
  };
}

const variantStyles: Record<Variant, TextStyle> = {
  displayXl: display(sizes.displayXl, -1.5),
  displayLg: display(sizes.displayLg, -1),
  displayMd: display(sizes.displayMd, -0.5),
  displaySm: display(sizes.displaySm, -0.25),
  titleLg: {
    fontSize: sizes.titleLg,
    lineHeight: sizes.titleLg * 1.3,
    letterSpacing: -0.2,
  },
  titleMd: { fontSize: sizes.titleMd, lineHeight: sizes.titleMd * 1.4 },
  titleSm: { fontSize: sizes.titleSm, lineHeight: sizes.titleSm * 1.4 },
  body: {
    fontSize: sizes.body,
    lineHeight: sizes.body * lineHeight.body,
  },
  bodyLarge: {
    fontSize: sizes.bodyLarge,
    lineHeight: sizes.bodyLarge * lineHeight.body,
  },
  heading: {
    fontSize: sizes.heading,
    lineHeight: sizes.heading * lineHeight.heading,
  },
  headingLarge: {
    fontSize: sizes.headingLarge,
    lineHeight: sizes.headingLarge * lineHeight.heading,
  },
  small: {
    fontSize: sizes.small,
    lineHeight: sizes.small * lineHeight.body,
  },
  caption: {
    fontSize: sizes.caption,
    lineHeight: sizes.caption * lineHeight.body,
  },
  eyebrow: {
    fontSize: sizes.eyebrow,
    lineHeight: sizes.eyebrow * 1.4,
    letterSpacing: tracking.eyebrow,
    textTransform: 'uppercase',
  },
  meta: {
    fontSize: sizes.meta,
    lineHeight: sizes.meta * 1.4,
    letterSpacing: tracking.meta,
  },
};

const sansVariants: Variant[] = [
  'titleLg',
  'titleMd',
  'titleSm',
  'small',
  'caption',
];

const ITALIC_FOR: Record<string, string> = {
  [typography.fontFamily.serif]: typography.fontFamily.serifItalic,
};

const BOLD_FOR: Record<string, string> = {
  [typography.fontFamily.serif]: typography.fontFamily.serifBold,
  [typography.fontFamily.sans]: typography.fontFamily.sansBold,
  [typography.fontFamily.mono]: typography.fontFamily.monoMedium,
};

function resolveFamily(variant: Variant, family: Family | undefined, italic: boolean): string {
  if (family === 'mono' || variant === 'eyebrow' || variant === 'meta') {
    return typography.fontFamily.mono;
  }

  const resolved = family ?? (sansVariants.includes(variant) ? 'sans' : 'serif');

  if (resolved === 'sans') {
    return typography.fontFamily.sans;
  }

  return italic ? typography.fontFamily.serifItalic : typography.fontFamily.serif;
}

function renderChildren(children: React.ReactNode, resolvedFamily: string): React.ReactNode {
  if (typeof children !== 'string' || !hasEmphasis(children)) {
    return children;
  }

  return splitEmphasis(children).map((span, index) => {
    const face = span.italic
      ? ITALIC_FOR[resolvedFamily]
      : span.bold
        ? BOLD_FOR[resolvedFamily]
        : undefined;

    return (
      <Text key={index} style={face ? { fontFamily: face } : undefined}>
        {span.text}
      </Text>
    );
  });
}

export function Typography({
  variant = 'body',
  family,
  color,
  italic = false,
  style,
  numberOfLines,
  children,
}: TypographyProps) {
  const { colors } = useTheme();
  const resolvedFamily = resolveFamily(variant, family, italic);

  return (
    <Text
      numberOfLines={numberOfLines}
      style={[
        variantStyles[variant],
        { fontFamily: resolvedFamily, color: color ?? colors.text },
        style,
      ]}
    >
      {renderChildren(children, resolvedFamily)}
    </Text>
  );
}
