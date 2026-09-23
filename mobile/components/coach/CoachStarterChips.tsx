import React from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import * as Haptics from 'expo-haptics';
import { Typography } from '../ui/Typography';
import { useTheme } from '../../hooks/useTheme';
import { spacing } from '../../constants/theme';

interface Props {
  starters: string[];
  disabled?: boolean;
  onPick: (starter: string) => void;
}

/**
 * One-tap ways in, drawn from the student's own portrait. A blank box is the
 * decision friction the product exists to remove.
 */
export function CoachStarterChips({ starters, disabled, onPick }: Props) {
  const { colors } = useTheme();

  if (starters.length === 0) return null;

  return (
    <View style={styles.container}>
      <Typography variant="caption" family="sans" color={colors.textSecondary} style={styles.label}>
        START WITH
      </Typography>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={styles.chips}
      >
        {starters.map((starter) => (
          <Pressable
            key={starter}
            accessibilityRole="button"
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
            <Typography variant="small" family="sans" color={colors.text}>
              {starter}
            </Typography>
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { paddingTop: spacing.sm + 4 },
  label: { letterSpacing: 1.5, marginBottom: spacing.sm },
  chips: { flexDirection: 'row', gap: spacing.sm, paddingRight: spacing.lg },
  chip: {
    borderWidth: 1,
    borderRadius: 2,
    paddingHorizontal: spacing.sm + 4,
    paddingVertical: spacing.sm + 2,
    minHeight: 44,
    justifyContent: 'center',
  },
});
