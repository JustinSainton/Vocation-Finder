import React from 'react';
import { View, StyleSheet, ScrollView, Pressable } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function DashboardScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.header}>
          <Typography variant="headingLarge">Vocation Finder</Typography>
          <Pressable onPress={() => router.push('/(dashboard)/profile')}>
            <Typography
              variant="small"
              family="sans"
              color={colors.textSecondary}
            >
              Profile
            </Typography>
          </Pressable>
        </View>

        <View style={styles.divider} />

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
            Begin a new vocational assessment to discover or revisit your
            deepest professional inclinations.
          </Typography>
          <Button
            title="Start assessment"
            onPress={() => router.push('/(assessment)')}
            style={styles.cta}
          />
        </View>

        <View style={styles.divider} />

        <View style={styles.section}>
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.sectionLabel}
          >
            Past assessments
          </Typography>
          <Typography variant="body" color={colors.textSecondary}>
            Your previous assessments will appear here.
          </Typography>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: { background: string; divider: string }) =>
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
    header: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'baseline',
      marginBottom: spacing.md,
    },
    divider: {
      height: 1,
      backgroundColor: colors.divider,
      marginVertical: spacing.xl,
    },
    section: {
      marginBottom: spacing.md,
    },
    sectionLabel: {
      textTransform: 'uppercase',
      letterSpacing: 1,
      marginBottom: spacing.sm,
    },
    sectionDescription: {
      marginBottom: spacing.lg,
    },
    cta: {
      alignSelf: 'flex-start',
    },
  });
