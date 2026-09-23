import React from 'react';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';
import { Typography } from '../ui/Typography';
import { CoachStarterChips } from './CoachStarterChips';
import { useTheme } from '../../hooks/useTheme';
import { radius, spacing, typography } from '../../constants/theme';

interface Props {
  value: string;
  onChangeText: (value: string) => void;
  onSend: () => void;
  busy: boolean;
  starters: string[];
  showStarters: boolean;
  onStarter: (starter: string) => void;
}

/** Composer gutter; the starter row bleeds past it so chips scroll to the screen edge. */
export const COMPOSER_GUTTER = spacing.lg;

/**
 * Where the student writes. Multiline and growing, because what goes in here
 * is often a paragraph about their life, and at the body size the product
 * uses a single-line field cuts the placeholder off on a phone.
 */
export function CoachComposer({ value, onChangeText, onSend, busy, starters, showStarters, onStarter }: Props) {
  const { colors } = useTheme();
  const canSend = !busy && value.trim().length > 0;

  return (
    <View style={[styles.container, { borderTopColor: colors.divider, backgroundColor: colors.background }]}>
      {showStarters ? (
        <CoachStarterChips starters={starters} disabled={busy} onPick={onStarter} gutter={COMPOSER_GUTTER} />
      ) : null}
      <View style={styles.row}>
        <TextInput
          value={value}
          onChangeText={onChangeText}
          placeholder="Talk to your coach…"
          placeholderTextColor={colors.textSecondary}
          multiline
          maxLength={4000}
          autoCapitalize="sentences"
          accessibilityLabel="Message your coach"
          style={[
            styles.input,
            { color: colors.text, borderColor: colors.divider, backgroundColor: colors.surfaceCard },
          ]}
        />
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Send"
          onPress={onSend}
          disabled={!canSend}
          style={[styles.send, { backgroundColor: colors.text, opacity: canSend ? 1 : 0.4 }]}
        >
          <Typography variant="small" family="sans" color={colors.background}>
            Send
          </Typography>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    borderTopWidth: 1,
    paddingHorizontal: COMPOSER_GUTTER,
    paddingBottom: spacing.xs,
  },
  row: { flexDirection: 'row', alignItems: 'flex-end', gap: spacing.xs, paddingTop: spacing.xs },
  input: {
    flex: 1,
    minWidth: 0,
    minHeight: 44,
    maxHeight: 132,
    borderWidth: 1,
    borderRadius: radius.sm,
    paddingHorizontal: spacing.md,
    paddingTop: 11,
    paddingBottom: 11,
    fontFamily: typography.fontFamily.serif,
    fontSize: 16,
    lineHeight: 22,
  },
  send: { minHeight: 44, paddingHorizontal: spacing.md, justifyContent: 'center' },
});
