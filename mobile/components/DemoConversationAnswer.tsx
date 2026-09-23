import React, { useState } from 'react';
import { StyleSheet, TextInput, View } from 'react-native';
import { Typography } from './ui/Typography';
import { Button } from './ui/Button';
import { spacing, typography } from '../constants/theme';
import { useTheme } from '../hooks/useTheme';

interface Props {
  answer: string;
  disabled: boolean;
  onSubmit: (text: string) => void;
}

/**
 * The voice flow's pre-filled answer. Sent as a transcript in place of a
 * recording, so a demo can move through the conversation without speaking.
 * Mount with a key per question so the text resets when the question does.
 */
export function DemoConversationAnswer({ answer, disabled, onSubmit }: Props) {
  const { colors } = useTheme();
  const [text, setText] = useState(answer);

  return (
    <View style={[styles.panel, { borderColor: colors.divider }]}>
      <Typography variant="eyebrow" color={colors.muted}>
        Demo answer · edit before sending
      </Typography>
      <TextInput
        multiline
        value={text}
        onChangeText={setText}
        textAlignVertical="top"
        style={[styles.input, { color: colors.text, borderBottomColor: colors.divider }]}
      />
      <Button
        title="Send this answer"
        variant="secondary"
        onPress={() => onSubmit(text)}
        disabled={disabled || text.trim().length === 0}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  panel: {
    borderWidth: 1,
    borderRadius: 8,
    padding: spacing.md,
    gap: spacing.sm,
  },
  input: {
    height: 120,
    borderBottomWidth: 1,
    fontFamily: typography.fontFamily.serif,
    fontSize: typography.sizes.small,
    lineHeight: typography.sizes.small * typography.lineHeight.body,
    paddingVertical: spacing.xs,
  },
});
