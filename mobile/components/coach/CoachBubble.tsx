import React from 'react';
import { StyleSheet, View } from 'react-native';
import { Typography } from '../ui/Typography';
import { useTheme } from '../../hooks/useTheme';
import { spacing } from '../../constants/theme';
import type { CoachThreadItem } from '../../services/api';

const time = (iso?: string) =>
  iso ? new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : 'now';

/**
 * Models write light markdown whether asked to or not. Asterisks in front of
 * a sixteen-year-old read as broken, so emphasis markers and heading hashes
 * are removed and list markers become bullets.
 */
function paragraphs(text: string): string[] {
  return text
    .replace(/\r\n/g, '\n')
    .trim()
    .split(/\n{2,}/)
    .map((block) =>
      block
        .split('\n')
        .map((line) => line.replace(/^#{1,6}\s+/, '').replace(/^\s*[-*]\s+/, '•  '))
        .join('\n')
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/(^|\s)[*_]([^*_\n]+)[*_]/g, '$1$2')
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
        <Typography variant="caption" family="sans" color={colors.textSecondary} style={styles.label}>
          {isCoach ? `Coach · ${time(at)}` : `You · ${time(at)}`}
        </Typography>
      ) : null}
      <View
        style={
          isCoach
            ? [styles.bubble, styles.coachBubble, { backgroundColor: colors.surface }]
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
      <Typography variant="caption" family="sans" color={colors.textSecondary} style={styles.stepText} numberOfLines={2}>
        {`STEP SET · ${item.title} · ${word}`}
      </Typography>
      <View style={[styles.rule, { backgroundColor: colors.divider }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { marginBottom: spacing.md + 4 },
  coachRow: { alignItems: 'stretch' },
  studentRow: { alignItems: 'flex-end' },
  label: { letterSpacing: 0.5, marginBottom: spacing.xs + 2 },
  bubble: { borderRadius: 6 },
  coachBubble: { padding: spacing.md + 4 },
  studentBubble: {
    maxWidth: '85%',
    borderWidth: 1,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm + 4,
  },
  text: { fontSize: 16, lineHeight: 25.6 },
  paragraphGap: { marginTop: spacing.sm + 4 },
  stepRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm + 4, marginBottom: spacing.md + 4 },
  rule: { flex: 1, height: 1 },
  stepText: { maxWidth: '70%', textAlign: 'center', letterSpacing: 0.5 },
});
