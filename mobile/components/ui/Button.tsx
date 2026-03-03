import React from 'react';
import {
  Pressable,
  StyleSheet,
  ViewStyle,
  TextStyle,
} from 'react-native';
import { Typography } from './Typography';
import { colors, spacing, layout } from '../../constants/theme';

interface ButtonProps {
  title: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary';
  disabled?: boolean;
  style?: ViewStyle;
}

export function Button({
  title,
  onPress,
  variant = 'primary',
  disabled = false,
  style,
}: ButtonProps) {
  const isPrimary = variant === 'primary';

  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={({ pressed }) => [
        styles.base,
        isPrimary ? styles.primary : styles.secondary,
        disabled && styles.disabled,
        pressed && !disabled && styles.pressed,
        style,
      ]}
    >
      <Typography
        variant="body"
        family="sans"
        color={isPrimary ? colors.buttonText : colors.text}
        style={styles.label}
      >
        {title}
      </Typography>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    minHeight: layout.touchTarget,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primary: {
    backgroundColor: colors.buttonBg,
  },
  secondary: {
    backgroundColor: 'transparent',
    borderWidth: 1,
    borderColor: colors.divider,
  },
  disabled: {
    opacity: 0.4,
  },
  pressed: {
    opacity: 0.8,
  },
  label: {
    fontSize: 18,
  } as TextStyle,
});
