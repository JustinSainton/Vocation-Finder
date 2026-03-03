import React, { useEffect, useState } from 'react';
import { View, StyleSheet } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { colors, spacing } from '../../constants/theme';

export default function SynthesisScreen() {
  const router = useRouter();
  const [dots, setDots] = useState('');

  // Simple animated ellipsis
  useEffect(() => {
    const interval = setInterval(() => {
      setDots((prev) => (prev.length >= 3 ? '' : prev + '.'));
    }, 600);
    return () => clearInterval(interval);
  }, []);

  // Auto-advance after synthesis (placeholder timing)
  useEffect(() => {
    const timeout = setTimeout(() => {
      router.replace('/(assessment)/results');
    }, 5000);
    return () => clearTimeout(timeout);
  }, [router]);

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Typography variant="heading" style={styles.title}>
          Synthesizing
        </Typography>

        <Typography
          variant="bodyLarge"
          color={colors.textSecondary}
          style={styles.description}
        >
          We are carefully considering your responses{dots}
        </Typography>

        <Typography
          variant="small"
          color={colors.accent}
          style={styles.note}
        >
          This will take a moment. Your reflections deserve careful
          attention.
        </Typography>
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
    alignItems: 'center',
  },
  title: {
    marginBottom: spacing.lg,
    textAlign: 'center',
  },
  description: {
    textAlign: 'center',
    marginBottom: spacing.xl,
  },
  note: {
    textAlign: 'center',
  },
});
