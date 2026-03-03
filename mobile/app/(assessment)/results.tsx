import React from 'react';
import { View, StyleSheet, ScrollView } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { colors, spacing } from '../../constants/theme';

export default function ResultsScreen() {
  const router = useRouter();

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <Typography
          variant="caption"
          family="sans"
          color={colors.accent}
          style={styles.label}
        >
          Your vocational articulation
        </Typography>

        <Typography variant="headingLarge" style={styles.title}>
          Vocational Portrait
        </Typography>

        <View style={styles.divider} />

        <Typography variant="body" style={styles.paragraph}>
          Your results will appear here once the synthesis is complete.
          This page will present a detailed vocational portrait drawn from
          your reflections, identifying core themes, natural inclinations,
          and potential paths forward.
        </Typography>

        <Typography variant="body" style={styles.paragraph}>
          The portrait is not a prescription. It is a mirror -- a way of
          seeing what already lives within your responses.
        </Typography>

        <View style={styles.divider} />

        <View style={styles.actions}>
          <Button
            title="Return home"
            onPress={() => router.replace('/(dashboard)')}
          />
          <Button
            title="Take assessment again"
            variant="secondary"
            onPress={() => router.replace('/(assessment)')}
          />
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.xxl,
  },
  label: {
    textTransform: 'uppercase',
    letterSpacing: 1,
    marginBottom: spacing.md,
  },
  title: {
    marginBottom: spacing.lg,
  },
  divider: {
    height: 1,
    backgroundColor: colors.divider,
    marginVertical: spacing.xl,
  },
  paragraph: {
    marginBottom: spacing.md,
  },
  actions: {
    gap: spacing.md,
    marginTop: spacing.lg,
  },
});
