import React from 'react';
import { View, StyleSheet, ScrollView } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { colors, spacing } from '../../constants/theme';

export default function OrientationScreen() {
  const router = useRouter();

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.content}>
          <Typography variant="heading" style={styles.title}>
            Before we begin
          </Typography>

          <Typography variant="body" style={styles.paragraph}>
            Find a quiet place where you can reflect without interruption.
            The questions ahead are not tests -- they are invitations to
            think carefully about what matters to you.
          </Typography>

          <Typography variant="body" style={styles.paragraph}>
            You may choose to respond by speaking aloud or by writing. Both
            paths lead to the same destination.
          </Typography>

          <View style={styles.divider} />

          <Typography
            variant="small"
            family="sans"
            color={colors.textSecondary}
            style={styles.modeLabel}
          >
            Choose your mode
          </Typography>

          <View style={styles.actions}>
            <Button
              title="Conversation"
              onPress={() => router.push('/(assessment)/conversation')}
            />
            <Button
              title="Written reflection"
              variant="secondary"
              onPress={() => router.push('/(assessment)/written')}
            />
          </View>
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
    justifyContent: 'center',
  },
  content: {
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.xxl,
  },
  title: {
    marginBottom: spacing.xl,
  },
  paragraph: {
    marginBottom: spacing.md,
  },
  divider: {
    height: 1,
    backgroundColor: colors.divider,
    marginVertical: spacing.xl,
  },
  modeLabel: {
    marginBottom: spacing.md,
    textTransform: 'uppercase',
    letterSpacing: 1,
  },
  actions: {
    gap: spacing.md,
  },
});
