import React, { useEffect, useCallback } from 'react';
import {
  View,
  StyleSheet,
  FlatList,
  Pressable,
  ActivityIndicator,
  Linking,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Typography } from '../../components/ui/Typography';
import { useJobStore, type JobListingSummary } from '../../stores/jobStore';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import { useAuthStore } from '../../stores/authStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

function MatchBadge({ percent, colors }: { percent: number; colors: any }) {
  const isHigh = percent >= 80;
  return (
    <View
      style={[
        styles.matchBadge,
        isHigh
          ? { backgroundColor: colors.text }
          : { borderWidth: 1, borderColor: colors.divider },
      ]}
    >
      <Typography
        variant="small"
        family="sans"
        color={isHigh ? colors.background : colors.textSecondary}
        style={styles.matchText}
      >
        {percent}%
      </Typography>
    </View>
  );
}

function formatSalary(min: number | null, max: number | null): string {
  const fmt = (n: number) => `$${Math.round(n / 1000)}k`;
  if (min && max) return `${fmt(min)}\u2013${fmt(max)}`;
  if (min) return `${fmt(min)}+`;
  if (max) return `Up to ${fmt(max)}`;
  return '';
}

function timeAgo(dateStr: string | null): string {
  if (!dateStr) return '';
  const days = Math.floor((Date.now() - new Date(dateStr).getTime()) / 86400000);
  if (days === 0) return 'Today';
  if (days === 1) return '1d';
  if (days < 7) return `${days}d`;
  if (days < 30) return `${Math.floor(days / 7)}w`;
  return `${Math.floor(days / 30)}mo`;
}

function JobCard({
  job,
  colors,
  onSave,
}: {
  job: JobListingSummary;
  colors: any;
  onSave: (id: string, saved: boolean) => void;
}) {
  const handleApply = () => {
    if (job.source_url) {
      Linking.openURL(job.source_url);
    }
  };

  return (
    <View style={[styles.card, { borderColor: colors.divider }]}>
      <View style={styles.cardHeader}>
        <View style={styles.cardTitleRow}>
          <Typography variant="body" style={styles.jobTitle} numberOfLines={2}>
            {job.title}
          </Typography>
          {job.match_percent !== undefined && (
            <MatchBadge percent={job.match_percent} colors={colors} />
          )}
        </View>
        <Typography variant="small" family="sans" color={colors.textSecondary}>
          {job.company_name}
          {job.location ? ` \u00B7 ${job.location}` : ''}
          {job.is_remote ? ' \u00B7 Remote' : ''}
        </Typography>
      </View>

      <View style={styles.cardMeta}>
        {(job.salary_min || job.salary_max) ? (
          <Typography variant="small" family="sans" color={colors.textSecondary}>
            {formatSalary(job.salary_min, job.salary_max)}
          </Typography>
        ) : null}
        {job.posted_at ? (
          <Typography variant="small" family="sans" color={colors.accent}>
            {timeAgo(job.posted_at)}
          </Typography>
        ) : null}
        {job.categories.slice(0, 1).map((cat) => (
          <Typography
            key={cat.slug}
            variant="small"
            family="sans"
            color={colors.accent}
          >
            {cat.name}
          </Typography>
        ))}
      </View>

      <View style={styles.cardActions}>
        {job.source_url && (
          <Pressable onPress={handleApply} style={[styles.applyBtn, { backgroundColor: colors.text }]}>
            <Typography variant="small" family="sans" color={colors.background}>
              Apply
            </Typography>
          </Pressable>
        )}
        <Pressable
          onPress={() => onSave(job.id, !!job.is_saved)}
          style={[styles.saveBtn, { borderColor: colors.divider }]}
        >
          <Typography variant="small" family="sans" color={colors.textSecondary}>
            {job.is_saved ? 'Saved' : 'Save'}
          </Typography>
        </Pressable>
      </View>
    </View>
  );
}

export default function JobsScreen() {
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();
  const { user } = useAuthStore();
  const {
    recommended,
    isLoading,
    fetchRecommended,
    saveJob,
    unsaveJob,
  } = useJobStore();

  useEffect(() => {
    if (isEnabled('job_discovery') && user) {
      fetchRecommended();
    }
  }, []);

  const handleSave = useCallback(
    (id: string, isSaved: boolean) => {
      if (isSaved) {
        unsaveJob(id);
      } else {
        saveJob(id);
      }
    },
    [saveJob, unsaveJob]
  );

  if (!isEnabled('job_discovery')) {
    return null;
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={styles.header}>
        <Typography variant="heading">Jobs</Typography>
        <Typography variant="small" family="sans" color={colors.textSecondary}>
          Matched to your vocational calling
        </Typography>
      </View>

      <View style={[styles.divider, { backgroundColor: colors.divider }]} />

      {recommended.length === 0 && !isLoading ? (
        <View style={styles.emptyState}>
          <Typography variant="body" style={styles.emptyTitle}>
            {user
              ? 'No job recommendations yet'
              : 'Sign in to see personalized matches'}
          </Typography>
          <Typography
            variant="small"
            color={colors.textSecondary}
            style={styles.emptyBody}
          >
            Complete your assessment to see jobs aligned with your vocational
            pathways.
          </Typography>
        </View>
      ) : (
        <FlatList
          data={recommended}
          keyExtractor={(item) => item.id}
          renderItem={({ item }) => (
            <JobCard job={item} colors={colors} onSave={handleSave} />
          )}
          contentContainerStyle={styles.listContent}
          ItemSeparatorComponent={() => <View style={{ height: spacing.sm }} />}
          ListFooterComponent={
            isLoading ? (
              <ActivityIndicator
                style={styles.loader}
                color={colors.text}
              />
            ) : null
          }
          showsVerticalScrollIndicator={false}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.xl,
    paddingBottom: spacing.md,
  },
  divider: {
    height: 1,
    marginHorizontal: spacing.lg,
  },
  listContent: {
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
  },
  card: {
    borderWidth: 1,
    padding: spacing.md,
  },
  cardHeader: {
    marginBottom: spacing.sm,
  },
  cardTitleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: spacing.sm,
  },
  jobTitle: {
    flex: 1,
  },
  matchBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
  },
  matchText: {
    fontSize: 10,
  },
  cardMeta: {
    flexDirection: 'row',
    gap: spacing.sm,
    flexWrap: 'wrap',
    marginBottom: spacing.sm,
  },
  cardActions: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.xs,
  },
  applyBtn: {
    paddingHorizontal: spacing.md,
    paddingVertical: 6,
  },
  saveBtn: {
    paddingHorizontal: spacing.md,
    paddingVertical: 6,
    borderWidth: 1,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
  },
  emptyTitle: {
    textAlign: 'center',
    marginBottom: spacing.sm,
  },
  emptyBody: {
    textAlign: 'center',
  },
  loader: {
    paddingVertical: spacing.lg,
  },
});
