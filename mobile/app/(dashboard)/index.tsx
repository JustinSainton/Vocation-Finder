import React, { useCallback, useEffect, useState } from 'react';
import { View, StyleSheet, ScrollView, RefreshControl } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
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

export default function DashboardScreen() {
  const router = useRouter();
  const { colors } = useTheme();
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
        <Typography variant="headingLarge">
          {greeting()}{user?.name ? `, ${user.name.split(' ')[0]}` : ''}
        </Typography>

        <View style={styles.divider} />

        {/* Vocational Profile Summary */}
        {data?.profile_summary ? (
          <View style={styles.section}>
            <Typography
              variant="caption"
              family="sans"
              color={colors.accent}
              style={styles.sectionLabel}
            >
              Your vocational portrait
            </Typography>
            <Typography variant="bodyLarge" style={styles.domain}>
              {data.profile_summary.primary_domain}
            </Typography>
            <Typography variant="small" family="sans" color={colors.textSecondary}>
              {data.profile_summary.mode_of_work}
            </Typography>
            {data.profile_summary.primary_pathways?.length > 0 && (
              <View style={styles.pathwayChips}>
                {data.profile_summary.primary_pathways.slice(0, 3).map((p, i) => (
                  <View key={i} style={styles.chip}>
                    <Typography variant="caption" family="sans" color={colors.text}>
                      {p}
                    </Typography>
                  </View>
                ))}
              </View>
            )}
            <View style={styles.divider} />
          </View>
        ) : null}

        {/* In-progress assessment */}
        {data?.in_progress_assessment ? (
          <View style={styles.section}>
            <Typography
              variant="caption"
              family="sans"
              color={colors.accent}
              style={styles.sectionLabel}
            >
              Continue your assessment
            </Typography>
            <Typography variant="body" style={styles.sectionDescription}>
              You have an assessment in progress.
            </Typography>
            <Button
              title="Continue"
              onPress={() => router.push('/(assessment)')}
            />
            <View style={styles.divider} />
          </View>
        ) : (
          <View style={styles.section}>
            <Typography
              variant="caption"
              family="sans"
              color={colors.accent}
              style={styles.sectionLabel}
            >
              New assessment
            </Typography>
            <Typography variant="body" style={styles.sectionDescription}>
              {data?.profile_summary
                ? 'Retake your vocational assessment to see how your direction has evolved.'
                : 'Begin a vocational assessment to discover your deepest professional inclinations.'}
            </Typography>
            <Button
              title="Start assessment"
              onPress={() => router.push('/(assessment)')}
            />
            <View style={styles.divider} />
          </View>
        )}

        {/* Mentor Notes */}
        {data?.mentor_notes && data.mentor_notes.length > 0 ? (
          <View style={styles.section}>
            <Typography
              variant="caption"
              family="sans"
              color={colors.accent}
              style={styles.sectionLabel}
            >
              From your mentor
            </Typography>
            {data.mentor_notes.map((note) => (
              <View key={note.id} style={styles.noteCard}>
                <Typography variant="body">{note.content}</Typography>
                <Typography
                  variant="caption"
                  family="sans"
                  color={colors.textSecondary}
                  style={styles.noteMeta}
                >
                  {note.mentor_name} · {new Date(note.created_at).toLocaleDateString()}
                </Typography>
              </View>
            ))}
            <View style={styles.divider} />
          </View>
        ) : null}

        {/* Organization */}
        {data?.organizations && data.organizations.length > 0 ? (
          <View style={styles.section}>
            <Typography
              variant="caption"
              family="sans"
              color={colors.accent}
              style={styles.sectionLabel}
            >
              Your organization{data.organizations.length > 1 ? 's' : ''}
            </Typography>
            {data.organizations.map((org) => (
              <Typography key={org.id} variant="body" color={colors.textSecondary}>
                {org.name} · {org.role}
              </Typography>
            ))}
          </View>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: { background: string; divider: string; text: string; accent: string }) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    scrollContent: {
      flexGrow: 1,
      paddingHorizontal: spacing.lg,
      paddingVertical: spacing.xl,
    },
    divider: {
      height: 1,
      backgroundColor: colors.divider,
      marginVertical: spacing.xl,
    },
    section: {},
    sectionLabel: {
      textTransform: 'uppercase',
      letterSpacing: 1,
      marginBottom: spacing.sm,
    },
    sectionDescription: {
      marginBottom: spacing.lg,
    },
    domain: {
      marginBottom: 4,
    },
    pathwayChips: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.sm,
      marginTop: spacing.md,
    },
    chip: {
      borderWidth: 1,
      borderColor: colors.divider,
      borderRadius: 999,
      paddingVertical: 6,
      paddingHorizontal: 12,
    },
    noteCard: {
      borderWidth: 1,
      borderColor: colors.divider,
      padding: spacing.md,
      marginBottom: spacing.sm,
    },
    noteMeta: {
      marginTop: spacing.sm,
    },
  });
