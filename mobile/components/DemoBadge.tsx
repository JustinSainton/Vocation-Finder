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
    <View
      accessibilityRole="text"
      style={[styles.badge, { borderColor: colors.divider }]}
    >
      <View style={[styles.dot, { backgroundColor: colors.accent }]} />
      <Typography variant="eyebrow" color={colors.muted}>
        Demo mode · pre-filled as {demo.persona}
      </Typography>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    borderWidth: 1,
    borderRadius: 999,
    paddingVertical: 4,
    paddingHorizontal: 12,
    marginBottom: spacing.lg,
  },
  dot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
});
