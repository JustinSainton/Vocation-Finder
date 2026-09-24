import React from 'react';
import { StyleSheet, View } from 'react-native';
import { Typography } from './ui/Typography';
import { spacing } from '../constants/theme';
import { useTheme } from '../hooks/useTheme';
import { useDemoMode } from '../hooks/useDemoMode';

export function DemoBadge() {
  const demo = useDemoMode();
  const { colors } = useTheme();

  if (!demo) {
    return null;
  }

  return (
    <View accessibilityRole="text" style={[styles.badge, { borderColor: colors.divider }]}>
      <Typography variant="eyebrow" color={colors.muted}>
        <Typography variant="eyebrow" color={colors.accent}>
          Demo mode
        </Typography>
        {` · pre-filled as ${demo.persona}`}
      </Typography>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderRadius: 999,
    paddingVertical: 4,
    paddingHorizontal: 12,
    marginBottom: spacing.lg,
  },
});
