import React from 'react';
import {
  Pressable,
  StyleSheet,
  ViewStyle,
  TextStyle,
} from 'react-native';
import * as Haptics from 'expo-haptics';
import { Typography } from './Typography';
import { spacing, layout } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

type HapticStyle = 'none' | 'light' | 'medium' | 'heavy' | 'soft' | 'rigid';

interface ButtonProps {
  title: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary';
  disabled?: boolean;
  hapticStyle?: HapticStyle;
  style?: ViewStyle;
}

export function Button({
  title,
  onPress,
  variant = 'primary',
  disabled = false,
  hapticStyle = 'light',
  style,
}: ButtonProps) {
  const { colors } = useTheme();
  const isPrimary = variant === 'primary';

  const handlePressIn = () => {
    if (disabled || hapticStyle === 'none') {
      return;
    }

    const styleMap: Record<Exclude<HapticStyle, 'none'>, Haptics.ImpactFeedbackStyle> = {
      light: Haptics.ImpactFeedbackStyle.Light,
      medium: Haptics.ImpactFeedbackStyle.Medium,
      heavy: Haptics.ImpactFeedbackStyle.Heavy,
      soft: Haptics.ImpactFeedbackStyle.Soft,
      rigid: Haptics.ImpactFeedbackStyle.Rigid,
    };

    Haptics.impactAsync(styleMap[hapticStyle]).catch(() => null);
  };

  return (
    <Pressable
      onPress={onPress}
      onPressIn={handlePressIn}
      disabled={disabled}
      style={({ pressed }) => [
        baseStyles.base,
        isPrimary
          ? { backgroundColor: colors.buttonBg }
          : {
              backgroundColor: 'transparent',
              borderWidth: 1,
              borderColor: colors.divider,
            },
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

const baseStyles = StyleSheet.create({
  base: {
    minHeight: layout.touchTarget,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
