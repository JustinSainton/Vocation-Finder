import React, { useCallback, useEffect, useState } from 'react';
import { View, StyleSheet, ScrollView, RefreshControl } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { spacing, radius } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import { useAuthStore } from '../../stores/authStore';
import { api } from '../../services/api';

interface DashboardData {
  profile_summary: {
    primary_domain: string;
    mode_of_work: string;
    primary_pathways: string[];
    opening_synthesis: string;
    assessment_id: string;
    completed_at: string;
  } | null;
  in_progress_assessment: {
    id: string;
    mode: string;
    status: string;
  } | null;
  mentor_notes: {
    id: string;
    content: string;
    mentor_name: string;
    created_at: string;
  }[];
  organizations: {
    id: string;
    name: string;
    role: string;
  }[];
  stats: {
    total_assessments: number;
    completed_assessments: number;
  };
}

type Palette = ReturnType<typeof useTheme>['colors'];

export default function DashboardScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();
  const styles = getStyles(colors);
  const user = useAuthStore((s) => s.user);
  const [data, setData] = useState<DashboardData | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const fetchDashboard = useCallback(async () => {
    try {
      const result = await api.get<DashboardData>('/me/dashboard');
      setData(result);
    } catch {
      // Silent fail — show empty state
    }
  }, []);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchDashboard();
    setRefreshing(false);
  }, [fetchDashboard]);

  const greeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        {/* Page meta — the greeting is context, not the headline */}
        <Typography variant="eyebrow" color={colors.muted} style={styles.pageMeta}>
          {greeting()}{user?.name ? `, ${user.name.split(' ')[0]}` : ''}
        </Typography>

        {/* Vocational portrait — the centrepiece */}
        {data?.profile_summary ? (
          <View style={styles.section}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.sectionLabel}>
              Your vocational portrait
            </Typography>
            <View style={styles.portraitCard}>
              <Typography variant="displaySm" style={styles.domain}>
                {data.profile_summary.primary_domain}
              </Typography>
              <Typography variant="meta" color={colors.muted} style={styles.modeOfWork}>
                {data.profile_summary.mode_of_work}
              </Typography>
              {data.profile_summary.opening_synthesis ? (
                <Typography
                  variant="body"
                  color={colors.textSecondary}
                  style={styles.synthesis}
                >
                  {data.profile_summary.opening_synthesis}
                </Typography>
              ) : null}
              {data.profile_summary.primary_pathways?.length > 0 && (
                <View style={styles.pathwayChips}>
                  {data.profile_summary.primary_pathways.slice(0, 3).map((pathway, index) => (
                    <View key={index} style={styles.chip}>
                      <Typography variant="meta" color={colors.textSecondary}>
                        {pathway}
                      </Typography>
                    </View>
                  ))}
                </View>
              )}
              <Typography variant="meta" color={colors.mutedSoft}>
                Completed {new Date(data.profile_summary.completed_at).toLocaleDateString()}
              </Typography>
            </View>
          </View>
        ) : null}

        {/* Assessment call to action */}
        {data?.in_progress_assessment ? (
          <View style={styles.section}>
            <View style={styles.ctaCard}>
              <Typography variant="eyebrow" color={colors.accent} style={styles.sectionLabel}>
                Continue your assessment
              </Typography>
              <Typography variant="displaySm" style={styles.ctaStatement}>
                You have an assessment in progress.
              </Typography>
              <Button
                title="Continue"
                onPress={() => router.push('/(assessment)')}
              />
            </View>
          </View>
        ) : (
          <View style={styles.section}>
            <View style={styles.ctaCard}>
              <Typography variant="eyebrow" color={colors.accent} style={styles.sectionLabel}>
                New assessment
              </Typography>
              <Typography variant="displaySm" style={styles.ctaStatement}>
                {data?.profile_summary
                  ? 'Retake your vocational assessment to see how your direction has evolved.'
                  : 'Begin a vocational assessment to discover your deepest professional inclinations.'}
              </Typography>
              <Button
                title="Start assessment"
                onPress={() => router.push('/(assessment)')}
              />
            </View>
          </View>
        )}

        {/* Student coach — front door (gated behind pathway_coach flag) */}
        {isEnabled('pathway_coach') && (
          <View style={styles.section}>
            <View style={styles.ctaCard}>
              <Typography variant="eyebrow" color={colors.accent} style={styles.sectionLabel}>
                Your coach
              </Typography>
              <Typography variant="displaySm" style={styles.ctaStatement}>
                Walk with a coach through your next step — one action at a time.
              </Typography>
              <Button
                title="Talk to your coach"
                onPress={() => router.push('/(dashboard)/coach')}
              />
            </View>
          </View>
        )}

        {/* Mentor notes — quote-block treatment */}
        {data?.mentor_notes && data.mentor_notes.length > 0 ? (
          <View style={styles.section}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.sectionLabel}>
              From your mentor
            </Typography>
            {data.mentor_notes.map((note) => (
              <View key={note.id} style={styles.noteCard}>
                <Typography variant="body">{note.content}</Typography>
                <Typography
                  variant="meta"
                  color={colors.muted}
                  style={styles.noteMeta}
                >
                  {note.mentor_name} · {new Date(note.created_at).toLocaleDateString()}
                </Typography>
              </View>
            ))}
          </View>
        ) : null}

        {/* Organizations */}
        {data?.organizations && data.organizations.length > 0 ? (
          <View style={styles.section}>
            <Typography variant="eyebrow" color={colors.accent} style={styles.sectionLabel}>
              Your organization{data.organizations.length > 1 ? 's' : ''}
            </Typography>
            {data.organizations.map((org) => (
              <Typography
                key={org.id}
                variant="body"
                color={colors.textSecondary}
                style={styles.orgRow}
              >
                {org.name} · {org.role}
              </Typography>
            ))}
          </View>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: Palette) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    scrollContent: {
      flexGrow: 1,
      paddingHorizontal: spacing.lg,
      paddingTop: spacing.xl,
      paddingBottom: spacing.xxl,
    },
    pageMeta: {
      marginBottom: spacing.xl,
    },
    section: {
      marginBottom: spacing.xxl,
    },
    sectionLabel: {
      marginBottom: spacing.md,
    },
    portraitCard: {
      backgroundColor: colors.surfaceCard,
      borderRadius: radius.md,
      padding: spacing.xl,
    },
    domain: {
      marginBottom: spacing.xs,
    },
    modeOfWork: {
      marginBottom: spacing.lg,
    },
    synthesis: {
      marginBottom: spacing.lg,
    },
    pathwayChips: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.xs,
      marginBottom: spacing.lg,
    },
    chip: {
      backgroundColor: colors.surfaceStrong,
      borderRadius: radius.pill,
      paddingVertical: spacing.xxs,
      paddingHorizontal: spacing.sm,
    },
    ctaCard: {
      borderWidth: 1,
      borderColor: colors.divider,
      borderRadius: radius.md,
      padding: spacing.xl,
    },
    ctaStatement: {
      marginBottom: spacing.lg,
    },
    noteCard: {
      borderLeftWidth: 2,
      borderLeftColor: colors.accent,
      paddingLeft: spacing.lg,
      marginBottom: spacing.lg,
    },
    noteMeta: {
      marginTop: spacing.sm,
    },
    orgRow: {
      marginBottom: spacing.xs,
    },
  });
