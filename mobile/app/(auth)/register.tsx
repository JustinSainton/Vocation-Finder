import React from 'react';
import { View, StyleSheet } from 'react-native';
import { Link } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { colors, spacing } from '../../constants/theme';

export default function RegisterScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.header}>
          <Typography variant="headingLarge">Begin here</Typography>
          <Typography
            variant="body"
            color={colors.textSecondary}
            style={styles.subtitle}
          >
            Create an account to save your results.
          </Typography>
        </View>

        <View style={styles.form}>
          {/* Name, email, and password inputs will be added */}
          <Typography variant="small" color={colors.textSecondary}>
            Registration form coming soon.
          </Typography>
        </View>

        <View style={styles.actions}>
          <Button title="Create account" onPress={() => {}} />
          <View style={styles.linkRow}>
            <Typography variant="small" color={colors.textSecondary}>
              Already have an account?{' '}
            </Typography>
            <Link href="/(auth)/login">
              <Typography variant="small" color={colors.text}>
                Sign in
              </Typography>
            </Link>
          </View>
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
    marginBottom: spacing.xxl,
  },
  subtitle: {
    marginTop: spacing.sm,
  },
  form: {
    marginBottom: spacing.xl,
  },
  actions: {
    gap: spacing.md,
  },
  linkRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: spacing.md,
  },
});
