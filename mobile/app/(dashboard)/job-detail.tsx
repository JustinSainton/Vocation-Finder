import React, { useEffect } from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  Pressable,
  Linking,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Typography } from '../../components/ui/Typography';
import { useJobStore } from '../../stores/jobStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

function formatSalary(min: number | null, max: number | null): string {
  const fmt = (n: number) => `$${Math.round(n / 1000)}k`;
  if (min && max) return `${fmt(min)}\u2013${fmt(max)}`;
  if (min) return `${fmt(min)}+`;
  if (max) return `Up to ${fmt(max)}`;
  return '';
}

export default function JobDetailScreen() {
  const { colors } = useTheme();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { currentJob, isLoading, fetchJob, saveJob, unsaveJob } = useJobStore();

  useEffect(() => {
    if (id) fetchJob(id);
  }, [id]);

  if (isLoading || !currentJob) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <ActivityIndicator style={styles.loader} color={colors.text} />
      </SafeAreaView>
    );
  }

  const handleApply = () => {
    if (currentJob.source_url) Linking.openURL(currentJob.source_url);
  };

  const handleSave = () => {
    if (currentJob.is_saved) {
      unsaveJob(currentJob.id);
    } else {
      saveJob(currentJob.id);
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <Pressable onPress={() => router.back()}>
          <Typography variant="small" family="sans" color={colors.textSecondary}>
            {'\u2190'} Back
          </Typography>
        </Pressable>

        <View style={styles.header}>
          <View style={styles.titleRow}>
            <Typography variant="heading" style={styles.title}>
              {currentJob.title}
            </Typography>
            {currentJob.match_percent !== undefined && (
              <View style={[styles.matchBadge, { backgroundColor: currentJob.match_percent >= 80 ? colors.text : 'transparent', borderWidth: currentJob.match_percent < 80 ? 1 : 0, borderColor: colors.divider }]}>
                <Typography variant="small" family="sans" color={currentJob.match_percent >= 80 ? colors.background : colors.textSecondary}>
                  {currentJob.match_percent}%
                </Typography>
              </View>
            )}
          </View>
          <Typography variant="body" color={colors.textSecondary}>
            {currentJob.company_name}
            {currentJob.location ? ` \u00B7 ${currentJob.location}` : ''}
            {currentJob.is_remote ? ' \u00B7 Remote' : ''}
          </Typography>
          {(currentJob.salary_min || currentJob.salary_max) ? (
            <Typography variant="small" family="sans" color={colors.textSecondary} style={styles.salary}>
              {formatSalary(currentJob.salary_min, currentJob.salary_max)}
            </Typography>
          ) : null}
        </View>

        <View style={[styles.divider, { backgroundColor: colors.divider }]} />

        {currentJob.categories.length > 0 && (
          <View style={styles.section}>
            <Typography variant="caption" family="sans" color={colors.accent} style={styles.sectionLabel}>
              Vocational Pathways
            </Typography>
            <View style={styles.tags}>
              {currentJob.categories.map((cat) => (
                <View key={cat.slug} style={[styles.tag, { borderColor: colors.divider }]}>
                  <Typography variant="small" family="sans">{cat.name}</Typography>
                </View>
              ))}
            </View>
          </View>
        )}

        {currentJob.required_skills && currentJob.required_skills.length > 0 && (
          <View style={styles.section}>
            <Typography variant="caption" family="sans" color={colors.accent} style={styles.sectionLabel}>
              Required Skills
            </Typography>
            <View style={styles.tags}>
              {currentJob.required_skills.map((skill, i) => (
                <View key={i} style={[styles.tag, { borderColor: colors.divider }]}>
                  <Typography variant="small" family="sans" color={colors.textSecondary}>
                    {typeof skill === 'string' ? skill : (skill as any).name}
                  </Typography>
                </View>
              ))}
            </View>
          </View>
        )}

        {currentJob.description && (
          <View style={styles.section}>
            <Typography variant="caption" family="sans" color={colors.accent} style={styles.sectionLabel}>
              Description
            </Typography>
            <Typography variant="body" color={colors.textSecondary} style={styles.description}>
              {currentJob.description.replace(/<[^>]*>/g, '')}
            </Typography>
          </View>
        )}

        <View style={styles.actions}>
          {currentJob.source_url && (
            <Pressable onPress={handleApply} style={[styles.primaryBtn, { backgroundColor: colors.text }]}>
              <Typography variant="small" family="sans" color={colors.background}>
                Apply on {currentJob.source}
              </Typography>
            </Pressable>
          )}
          <Pressable onPress={handleSave} style={[styles.secondaryBtn, { borderColor: colors.divider }]}>
            <Typography variant="small" family="sans" color={colors.textSecondary}>
              {currentJob.is_saved ? 'Unsave' : 'Save Job'}
            </Typography>
          </Pressable>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  content: { paddingHorizontal: spacing.lg, paddingVertical: spacing.xl },
  loader: { flex: 1, justifyContent: 'center' },
  header: { marginTop: spacing.lg },
  titleRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: spacing.sm },
  title: { flex: 1 },
  matchBadge: { paddingHorizontal: 8, paddingVertical: 4 },
  salary: { marginTop: spacing.xs },
  divider: { height: 1, marginVertical: spacing.lg },
  section: { marginBottom: spacing.lg },
  sectionLabel: { textTransform: 'uppercase', letterSpacing: 1, marginBottom: spacing.sm },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  tag: { borderWidth: 1, paddingHorizontal: spacing.sm, paddingVertical: 4 },
  description: { lineHeight: 22 },
  actions: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.lg },
  primaryBtn: { flex: 1, paddingVertical: 12, alignItems: 'center' },
  secondaryBtn: { flex: 1, paddingVertical: 12, alignItems: 'center', borderWidth: 1 },
});
