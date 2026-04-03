import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  StyleSheet,
  FlatList,
  Pressable,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import { api } from '../../services/api';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

interface ResumeItem {
  id: string;
  status: 'generating' | 'ready' | 'failed';
  quality_score: number | null;
  created_at: string;
  job_listing: { id: string; title: string; company_name: string } | null;
}

export default function ResumesScreen() {
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();
  const [resumes, setResumes] = useState<ResumeItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchResumes = useCallback(async () => {
    try {
      const res = await api.get<{ data: ResumeItem[] }>('/resumes');
      setResumes(res.data);
    } catch {} finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isEnabled('resume_builder')) fetchResumes();
  }, []);

  if (!isEnabled('resume_builder')) return null;

  const STATUS_LABELS: Record<string, string> = {
    generating: 'Generating...',
    ready: 'Ready',
    failed: 'Failed',
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={styles.header}>
        <Typography variant="heading">Resumes</Typography>
        <Typography variant="small" family="sans" color={colors.textSecondary}>
          AI-generated, tailored to your calling
        </Typography>
      </View>

      <View style={[styles.divider, { backgroundColor: colors.divider }]} />

      {loading ? (
        <ActivityIndicator style={styles.loader} color={colors.text} />
      ) : resumes.length === 0 ? (
        <View style={styles.emptyState}>
          <Typography variant="body" style={styles.emptyTitle}>
            No resumes yet
          </Typography>
          <Typography variant="small" color={colors.textSecondary} style={styles.emptyBody}>
            Browse jobs and generate a tailored resume, or start a conversation with our
            resume coach to build one from scratch.
          </Typography>
        </View>
      ) : (
        <FlatList
          data={resumes}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.list}
          renderItem={({ item }) => (
            <View style={[styles.card, { borderColor: colors.divider }]}>
              <Typography variant="body" numberOfLines={2}>
                {item.job_listing
                  ? `${item.job_listing.title} at ${item.job_listing.company_name}`
                  : 'General Resume'}
              </Typography>
              <View style={styles.cardMeta}>
                <Typography
                  variant="small"
                  family="sans"
                  color={item.status === 'failed' ? '#dc2626' : item.status === 'ready' ? colors.text : colors.accent}
                >
                  {STATUS_LABELS[item.status]}
                </Typography>
                {item.quality_score !== null && (
                  <Typography variant="small" family="sans" color={colors.textSecondary}>
                    Quality: {Math.round(item.quality_score)}/100
                  </Typography>
                )}
                <Typography variant="small" family="sans" color={colors.accent}>
                  {new Date(item.created_at).toLocaleDateString()}
                </Typography>
              </View>
            </View>
          )}
          ItemSeparatorComponent={() => <View style={{ height: spacing.sm }} />}
          showsVerticalScrollIndicator={false}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: { paddingHorizontal: spacing.lg, paddingTop: spacing.xl, paddingBottom: spacing.md },
  divider: { height: 1, marginHorizontal: spacing.lg },
  loader: { flex: 1, justifyContent: 'center' },
  list: { paddingHorizontal: spacing.lg, paddingVertical: spacing.md },
  card: { borderWidth: 1, padding: spacing.md },
  cardMeta: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.xs, flexWrap: 'wrap' },
  emptyState: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: spacing.xl },
  emptyTitle: { textAlign: 'center', marginBottom: spacing.sm },
  emptyBody: { textAlign: 'center' },
});
