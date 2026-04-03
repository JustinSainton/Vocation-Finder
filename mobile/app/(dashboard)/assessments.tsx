import React, { useCallback, useEffect, useState } from 'react';
import { View, StyleSheet, ScrollView, Pressable, RefreshControl } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { api } from '../../services/api';

interface AssessmentItem {
  id: string;
  mode: string;
  status: string;
  locale: string;
  created_at: string;
  completed_at: string | null;
  vocational_profile: {
    primary_domain: string;
    mode_of_work: string;
  } | null;
}

export default function AssessmentsScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const [assessments, setAssessments] = useState<AssessmentItem[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const fetchAssessments = useCallback(async () => {
    try {
      const result = await api.get<{ data: AssessmentItem[] }>('/assessments');
      setAssessments(result.data);
    } catch {
      // Silent fail
    }
  }, []);

  useEffect(() => {
    fetchAssessments();
  }, [fetchAssessments]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchAssessments();
    setRefreshing(false);
  }, [fetchAssessments]);

  const statusColor = (status: string) => {
    if (status === 'completed') return '#16a34a';
    if (status === 'analyzing') return '#d97706';
    return colors.textSecondary;
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        <Typography variant="heading" style={styles.title}>
          Your Assessments
        </Typography>

        {assessments.length === 0 ? (
          <Typography variant="body" color={colors.textSecondary}>
            No assessments yet. Start one from the Home tab.
          </Typography>
        ) : (
          assessments.map((a) => (
            <Pressable
              key={a.id}
              style={styles.card}
              onPress={() => {
                if (a.status === 'completed') {
                  router.push(`/(assessment)/results?id=${a.id}`);
                }
              }}
            >
              <View style={styles.cardHeader}>
                <Typography variant="body">
                  {a.mode === 'conversation' ? 'Voice' : 'Written'} Assessment
                </Typography>
                <View style={[styles.statusBadge, { borderColor: statusColor(a.status) }]}>
                  <Typography
                    variant="caption"
                    family="sans"
                    color={statusColor(a.status)}
                  >
                    {a.status}
                  </Typography>
                </View>
              </View>

              {a.vocational_profile ? (
                <View style={styles.profilePreview}>
                  <Typography variant="small" family="sans" color={colors.text}>
                    {a.vocational_profile.primary_domain}
                  </Typography>
                  <Typography variant="caption" family="sans" color={colors.textSecondary}>
                    {a.vocational_profile.mode_of_work}
                  </Typography>
                </View>
              ) : null}

              <Typography variant="caption" family="sans" color={colors.accent}>
                {new Date(a.created_at).toLocaleDateString('en-US', {
                  month: 'long',
                  day: 'numeric',
                  year: 'numeric',
                })}
              </Typography>
            </Pressable>
          ))
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: { background: string; divider: string; text: string }) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    scrollContent: {
      paddingHorizontal: spacing.lg,
      paddingVertical: spacing.xl,
    },
    title: {
      marginBottom: spacing.xl,
    },
    card: {
      borderWidth: 1,
      borderColor: colors.divider,
      padding: spacing.lg,
      marginBottom: spacing.md,
    },
    cardHeader: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      marginBottom: spacing.sm,
    },
    statusBadge: {
      borderWidth: 1,
      borderRadius: 999,
      paddingVertical: 2,
      paddingHorizontal: 8,
    },
    profilePreview: {
      marginBottom: spacing.sm,
    },
  });
