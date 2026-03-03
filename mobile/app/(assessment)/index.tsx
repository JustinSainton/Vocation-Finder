import React from 'react';
import { View, StyleSheet } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { TypewriterText } from '../../components/ui/TypewriterText';
import { colors, spacing } from '../../constants/theme';

const HERO_HEADLINE = [
  'Most people are taught',
  'to choose a career.',
  'Very few are taught',
  'to discern a calling.',
].join('\n');

export default function AssessmentLandingScreen() {
  const router = useRouter();

  const handleBegin = () => {
    router.push('/(assessment)/orientation');
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.body}>
          <TypewriterText
            text={HERO_HEADLINE}
            variant="headingLarge"
            style={styles.headline}
            speedMs={30}
          />

          <Typography
            variant="body"
            color={colors.textSecondary}
            style={styles.framing}
          >
            This is a space for honest reflection — not a personality quiz, not
            a career test. What follows is a guided process designed to surface
            what you may already sense but haven't yet articulated.
          </Typography>

          <Typography
            variant="body"
            color={colors.textSecondary}
            style={styles.framing}
          >
            It requires your time and your honesty. Nothing less will do.
          </Typography>
        </View>

        <View style={styles.actions}>
          <Button
            title="Start your assessment"
            onPress={handleBegin}
            hapticStyle="medium"
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
    justifyContent: 'space-between',
    paddingTop: spacing.section,
    paddingBottom: spacing.xxl,
  },
  body: {},
  headline: {
    marginBottom: spacing.xl,
  },
  framing: {
    marginBottom: spacing.md,
  },
  actions: {
    marginTop: spacing.xxl,
  },
});
