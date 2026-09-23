import React from 'react';
import { StyleSheet, View } from 'react-native';
import { Typography } from '../ui/Typography';
import { useTheme } from '../../hooks/useTheme';
import { radius, spacing } from '../../constants/theme';
import type { CoachThreadItem } from '../../services/api';

const time = (iso?: string) =>
  iso ? new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : 'now';

/**
 * Paragraphs, with list markers turned into bullets and heading hashes
 * dropped. `*emphasis*` is left in place: Typography renders it.
 */
function paragraphs(text: string): string[] {
  return text
    .replace(/\r\n/g, '\n')
    .trim()
    .split(/\n{2,}/)
    .map((block) =>
      block
        .split('\n')
        .map((line) => line.replace(/^#{1,6}\s+/, '').replace(/^\s*[-•]\s+/, '•  '))
        .join('\n')
    );
}

interface Props {
  role: 'user' | 'assistant';
  content: string;
  at?: string;
  showLabel?: boolean;
}

/**
 * The coach's words on a tonal panel; the student's outlined and quieter, so
 * their own sentences read as theirs.
 */
export function CoachBubble({ role, content, at, showLabel = true }: Props) {
  const { colors } = useTheme();
  const isCoach = role === 'assistant';

  return (
    <View style={[styles.row, isCoach ? styles.coachRow : styles.studentRow]}>
      {showLabel ? (
        <Typography variant="meta" color={colors.muted} style={styles.label}>
          {isCoach ? `Coach · ${time(at)}` : `You · ${time(at)}`}
        </Typography>
      ) : null}
      <View
        style={
          isCoach
            ? [styles.bubble, styles.coachBubble, { backgroundColor: colors.surfaceCard }]
            : [styles.bubble, styles.studentBubble, { borderColor: colors.divider }]
        }
      >
        {(isCoach ? paragraphs(content) : [content]).map((paragraph, index) => (
          <Typography
            key={index}
            variant="body"
            color={isCoach ? colors.text : colors.textSecondary}
            style={index > 0 ? [styles.text, styles.paragraphGap] : styles.text}
          >
            {paragraph}
          </Typography>
        ))}
      </View>
    </View>
  );
}

export function CoachStepMarker({ item }: { item: Extract<CoachThreadItem, { type: 'step' }> }) {
  const { colors } = useTheme();
  const word = item.status === 'completed' ? 'Done' : item.status === 'skipped' ? 'Set down' : 'In progress';

  return (
    <View style={styles.stepRow}>
      <View style={[styles.rule, { backgroundColor: colors.divider }]} />
      <Typography variant="meta" color={colors.muted} style={styles.stepText} numberOfLines={3}>
        {`Step set · ${item.title} · ${word}`}
      </Typography>
      <View style={[styles.rule, { backgroundColor: colors.divider }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { marginBottom: spacing.md },
  coachRow: { alignItems: 'stretch' },
  studentRow: { alignItems: 'flex-end' },
  label: { marginBottom: spacing.xs },
  bubble: { borderRadius: radius.md },
  coachBubble: { padding: spacing.md },
  studentBubble: {
    maxWidth: '85%',
    borderWidth: 1,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  text: { fontSize: 16, lineHeight: 25.6 },
  paragraphGap: { marginTop: spacing.sm },
  stepRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, marginBottom: spacing.md },
  rule: { flex: 1, height: 1 },
  stepText: { maxWidth: '70%', textAlign: 'center' },
});
