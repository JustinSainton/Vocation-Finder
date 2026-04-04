import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  StyleSheet,
  SectionList,
  Pressable,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { useApplicationStore, type JobApplicationSummary } from '../../stores/applicationStore';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

const STATUS_ORDER = ['saved', 'applied', 'phone_screen', 'interviewing', 'offered', 'accepted', 'rejected', 'ghosted', 'withdrawn', 'declined'];
const STATUS_LABELS: Record<string, string> = {
  saved: 'Saved', applied: 'Applied', phone_screen: 'Phone Screen',
  interviewing: 'Interviewing', offered: 'Offered', accepted: 'Accepted',
  rejected: 'Rejected', declined: 'Declined', withdrawn: 'Withdrawn', ghosted: 'Ghosted',
};

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function ApplicationCard({ app, colors }: { app: JobApplicationSummary; colors: any }) {
  return (
    <View style={[styles.card, { borderColor: colors.divider }]}>
      <View style={styles.cardContent}>
        <Typography variant="body" numberOfLines={1}>{app.job_title}</Typography>
        <Typography variant="small" family="sans" color={colors.textSecondary}>
          {app.company_name}
          {app.applied_at ? ` \u00B7 ${formatDate(app.applied_at)}` : ''}
        </Typography>
        {app.next_action ? (
          <Typography variant="small" family="sans" color={colors.accent} style={styles.nextAction}>
            Next: {app.next_action}
          </Typography>
        ) : null}
      </View>
      <Typography variant="small" family="sans" color={colors.accent}>
        {formatDate(app.updated_at)}
      </Typography>
    </View>
  );
}

export default function ApplicationsScreen() {
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();
  const { applications, analytics, isLoading, fetchApplications, fetchAnalytics } = useApplicationStore();

  useEffect(() => {
    if (isEnabled('application_tracking')) {
      fetchApplications();
      fetchAnalytics();
    }
  }, []);

  if (!isEnabled('application_tracking')) return null;

  // Group by status
  const sections = STATUS_ORDER
    .map((status) => ({
      title: STATUS_LABELS[status] ?? status,
      data: applications.filter((a) => a.status === status),
    }))
    .filter((s) => s.data.length > 0);

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={styles.header}>
        <Typography variant="heading">Applications</Typography>
        {analytics && (
          <View style={styles.statsRow}>
            <View style={styles.stat}>
              <Typography variant="small" family="sans" color={colors.accent}>Total</Typography>
              <Typography variant="body">{analytics.total_applications}</Typography>
            </View>
            <View style={styles.stat}>
              <Typography variant="small" family="sans" color={colors.accent}>Weekly</Typography>
              <Typography variant="body">{analytics.weekly_velocity}/wk</Typography>
            </View>
            {analytics.avg_response_days ? (
              <View style={styles.stat}>
                <Typography variant="small" family="sans" color={colors.accent}>Avg Response</Typography>
                <Typography variant="body">{analytics.avg_response_days}d</Typography>
              </View>
            ) : null}
          </View>
        )}
      </View>

      <View style={[styles.divider, { backgroundColor: colors.divider }]} />

      {isLoading ? (
        <ActivityIndicator style={styles.loader} color={colors.text} />
      ) : sections.length === 0 ? (
        <View style={styles.emptyState}>
          <Typography variant="body" style={styles.emptyTitle}>No applications yet</Typography>
          <Typography variant="small" color={colors.textSecondary} style={styles.emptyBody}>
            Save a job from the Jobs tab to start tracking your applications.
          </Typography>
        </View>
      ) : (
        <SectionList
          sections={sections}
          keyExtractor={(item) => item.id}
          renderSectionHeader={({ section }) => (
            <View style={[styles.sectionHeader, { backgroundColor: colors.background }]}>
              <Typography variant="caption" family="sans" color={colors.accent} style={styles.sectionLabel}>
                {section.title} ({section.data.length})
              </Typography>
            </View>
          )}
          renderItem={({ item }) => <ApplicationCard app={item} colors={colors} />}
          contentContainerStyle={styles.list}
          stickySectionHeadersEnabled
          showsVerticalScrollIndicator={false}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: { paddingHorizontal: spacing.lg, paddingTop: spacing.xl, paddingBottom: spacing.md },
  statsRow: { flexDirection: 'row', gap: spacing.lg, marginTop: spacing.md },
  stat: {},
  divider: { height: 1, marginHorizontal: spacing.lg },
  list: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xl },
  sectionHeader: { paddingTop: spacing.md, paddingBottom: spacing.xs },
  sectionLabel: { textTransform: 'uppercase', letterSpacing: 1 },
  card: { borderWidth: 1, padding: spacing.md, marginBottom: spacing.xs, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  cardContent: { flex: 1, marginRight: spacing.sm },
  nextAction: { marginTop: 2 },
  loader: { flex: 1, justifyContent: 'center' },
  emptyState: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: spacing.xl },
  emptyTitle: { textAlign: 'center', marginBottom: spacing.sm },
  emptyBody: { textAlign: 'center' },
});
