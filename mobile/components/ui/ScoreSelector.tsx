import React from 'react';
import { View, Pressable, StyleSheet } from 'react-native';
import * as Haptics from 'expo-haptics';
import { Typography } from './Typography';
import { spacing } from '../../constants/theme';

interface ScoreSelectorProps {
  value: number | null;
  onChange: (score: number) => void;
  colors: { text: string; background: string; divider: string };
}

export function ScoreSelector({ value, onChange, colors }: ScoreSelectorProps) {
  const handlePress = async (n: number) => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    onChange(n);
  };

  return (
    <View style={styles.row}>
      {Array.from({ length: 10 }, (_, i) => i + 1).map((n) => (
        <Pressable
          key={n}
          onPress={() => handlePress(n)}
          style={[
            styles.chip,
            { borderColor: colors.divider, backgroundColor: colors.background },
            value === n && { borderColor: colors.text, backgroundColor: colors.text },
          ]}
        >
          <Typography
            variant="small"
            family="sans"
            color={value === n ? colors.background : colors.text}
          >
            {String(n)}
          </Typography>
        </Pressable>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: spacing.md,
  },
  chip: {
    width: 40,
    height: 40,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
