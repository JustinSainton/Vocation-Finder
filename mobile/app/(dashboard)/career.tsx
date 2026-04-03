import React, { useEffect } from 'react';
import { View, StyleSheet, ScrollView, TextInput, Pressable } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { useCareerProfileStore, type CareerProfile } from '../../stores/careerProfileStore';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

function EmptyState({ colors, onAdd }: { colors: any; onAdd: () => void }) {
  return (
    <View style={styles.emptyState}>
      <Typography variant="heading" style={styles.emptyTitle}>
        Build Your Career Profile
      </Typography>
      <Typography variant="body" color={colors.textSecondary} style={styles.emptyBody}>
        Add your experience, education, and skills. This powers personalized
        resume generation and job matching.
      </Typography>
      <Button title="Get started" onPress={onAdd} style={styles.emptyButton} />
    </View>
  );
}

function SectionHeader({ title, colors }: { title: string; colors: any }) {
  return (
    <Typography
      variant="caption"
      family="sans"
      color={colors.accent}
      style={styles.sectionLabel}
    >
      {title}
    </Typography>
  );
}

export default function CareerScreen() {
  const { colors } = useTheme();
  const { profile, isLoading, fetchProfile, updateProfile } = useCareerProfileStore();
  const { isEnabled } = useFeatureFlags();

  useEffect(() => {
    if (isEnabled('career_profile')) {
      fetchProfile();
    }
  }, []);

  if (!isEnabled('career_profile')) {
    return null;
  }

  const hasContent =
    (profile?.work_history?.length ?? 0) > 0 ||
    (profile?.education?.length ?? 0) > 0 ||
    (profile?.skills?.length ?? 0) > 0;

  const handleAddFirstEntry = () => {
    updateProfile({
      work_history: [{ company: '', position: '', startDate: '', endDate: '', summary: '' }],
    });
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <Typography variant="heading" style={styles.title}>
          Career Profile
        </Typography>

        {profile?.import_source && (
          <Typography variant="small" family="sans" color={colors.textSecondary}>
            Imported from {profile.import_source.replace('_', ' ')}
          </Typography>
        )}

        <View style={[styles.divider, { backgroundColor: colors.divider }]} />

        {!hasContent ? (
          <EmptyState colors={colors} onAdd={handleAddFirstEntry} />
        ) : (
          <View style={styles.sections}>
            {(profile?.work_history?.length ?? 0) > 0 && (
              <View style={styles.section}>
                <SectionHeader title="Work Experience" colors={colors} />
                {profile!.work_history!.map((entry, i) => (
                  <View
                    key={i}
                    style={[styles.card, { borderColor: colors.divider }]}
                  >
                    <Typography variant="body" style={styles.cardTitle}>
                      {entry.position || 'Untitled Position'}
                    </Typography>
                    <Typography variant="small" family="sans" color={colors.textSecondary}>
                      {entry.company}
                      {entry.startDate ? ` · ${entry.startDate}` : ''}
                      {entry.endDate ? ` – ${entry.endDate}` : ' – Present'}
                    </Typography>
                    {entry.summary ? (
                      <Typography
                        variant="small"
                        color={colors.textSecondary}
                        style={styles.cardSummary}
                      >
                        {entry.summary}
                      </Typography>
                    ) : null}
                  </View>
                ))}
              </View>
            )}

            {(profile?.education?.length ?? 0) > 0 && (
              <View style={styles.section}>
                <SectionHeader title="Education" colors={colors} />
                {profile!.education!.map((entry, i) => (
                  <View
                    key={i}
                    style={[styles.card, { borderColor: colors.divider }]}
                  >
                    <Typography variant="body" style={styles.cardTitle}>
                      {entry.institution || 'Untitled Institution'}
                    </Typography>
                    <Typography variant="small" family="sans" color={colors.textSecondary}>
                      {[entry.studyType, entry.area].filter(Boolean).join(' in ')}
                    </Typography>
                  </View>
                ))}
              </View>
            )}

            {(profile?.skills?.length ?? 0) > 0 && (
              <View style={styles.section}>
                <SectionHeader title="Skills" colors={colors} />
                <View style={styles.skillsRow}>
                  {profile!.skills!.map((skill, i) => (
                    <View
                      key={i}
                      style={[styles.skillTag, { borderColor: colors.divider }]}
                    >
                      <Typography variant="small" family="sans">
                        {skill.name}
                      </Typography>
                    </View>
                  ))}
                </View>
              </View>
            )}
          </View>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.xl,
  },
  title: {
    marginBottom: spacing.sm,
  },
  divider: {
    height: 1,
    marginVertical: spacing.xl,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: spacing.xxl,
  },
  emptyTitle: {
    textAlign: 'center',
    marginBottom: spacing.md,
  },
  emptyBody: {
    textAlign: 'center',
    marginBottom: spacing.xl,
    paddingHorizontal: spacing.lg,
  },
  emptyButton: {
    alignSelf: 'center',
  },
  sections: {
    gap: spacing.xl,
  },
  section: {
    gap: spacing.md,
  },
  sectionLabel: {
    textTransform: 'uppercase',
    letterSpacing: 1,
  },
  card: {
    borderWidth: 1,
    padding: spacing.md,
  },
  cardTitle: {
    marginBottom: 2,
  },
  cardSummary: {
    marginTop: spacing.sm,
  },
  skillsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  skillTag: {
    borderWidth: 1,
    paddingVertical: 4,
    paddingHorizontal: spacing.sm,
  },
});
