import React from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import * as Haptics from 'expo-haptics';
import { Typography } from '../ui/Typography';
import { useTheme } from '../../hooks/useTheme';
import { radius, spacing } from '../../constants/theme';

interface Props {
  starters: string[];
  disabled?: boolean;
  onPick: (starter: string) => void;
  /**
   * The horizontal padding of whatever contains this row. The row bleeds
   * back out by the same amount so chips scroll all the way to the screen
   * edge instead of being clipped at the container's padding.
   */
  gutter?: number;
}

/**
 * One-tap ways in, drawn from the student's own portrait. A blank box is the
 * decision friction the product exists to remove.
 *
 * One horizontal row so it never takes over the screen; each chip is capped
 * in width and wraps to two lines, so a long starter is readable in full
 * rather than running off the edge.
 */
export function CoachStarterChips({ starters, disabled, onPick, gutter = 0 }: Props) {
  const { colors } = useTheme();

  if (starters.length === 0) return null;

  return (
    <View style={styles.container}>
      <Typography variant="eyebrow" color={colors.muted} style={styles.label}>
        Start with
      </Typography>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        style={{ marginHorizontal: -gutter }}
        contentContainerStyle={[styles.chips, { paddingHorizontal: gutter }]}
      >
        {starters.map((starter) => (
          <Pressable
            key={starter}
            accessibilityRole="button"
            accessibilityLabel={starter}
            disabled={disabled}
            onPress={() => {
              Haptics.selectionAsync().catch(() => null);
              onPick(starter);
            }}
            style={({ pressed }) => [
              styles.chip,
              { borderColor: pressed ? colors.text : colors.divider, opacity: disabled ? 0.4 : 1 },
            ]}
          >
            <Typography variant="small" family="sans" color={colors.text} numberOfLines={2} style={styles.chipText}>
              {starter}
            </Typography>
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { paddingTop: spacing.sm },
  label: { marginBottom: spacing.xs },
  chips: { flexDirection: 'row', alignItems: 'stretch', gap: spacing.xs },
  chip: {
    maxWidth: 240,
    borderWidth: 1,
    borderRadius: radius.xs,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    minHeight: 44,
    justifyContent: 'center',
  },
  chipText: { fontSize: 14, lineHeight: 19 },
});
