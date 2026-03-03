import React from 'react';
import { View, StyleSheet } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { colors, spacing } from '../../constants/theme';

export default function AssessmentLandingScreen() {
  const router = useRouter();

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.header}>
          <Typography variant="headingLarge">
            Vocational Discovery
          </Typography>
          <Typography
            variant="body"
            color={colors.textSecondary}
            style={styles.description}
          >
            This assessment is designed to surface your deepest vocational
            inclinations through guided reflection. It takes approximately
            20 minutes.
          </Typography>
        </View>

        <View style={styles.divider} />

        <View style={styles.details}>
          <Typography variant="body" style={styles.detailItem}>
            A series of open-ended questions will invite you to reflect on
            your experiences, interests, and aspirations.
          </Typography>
          <Typography variant="body" style={styles.detailItem}>
            There are no right or wrong answers. Respond honestly and at
            whatever length feels natural.
          </Typography>
        </View>

        <View style={styles.actions}>
          <Button
            title="Begin"
            onPress={() => router.push('/(assessment)/orientation')}
          />
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    flex: 1,
    paddingHorizontal: spacing.lg,
    justifyContent: 'center',
  },
  header: {
    marginBottom: spacing.xl,
  },
  description: {
    marginTop: spacing.md,
  },
  divider: {
    height: 1,
    backgroundColor: colors.divider,
    marginVertical: spacing.xl,
  },
  details: {
    marginBottom: spacing.xxl,
  },
  detailItem: {
    marginBottom: spacing.md,
  },
  actions: {
    gap: spacing.md,
  },
});
