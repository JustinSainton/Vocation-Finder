import React from 'react';
import { View, StyleSheet, ScrollView } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { useAuthStore } from '../../stores/authStore';
import { colors, spacing } from '../../constants/theme';

export default function ProfileScreen() {
  const router = useRouter();
  const { user, isGuest, logout } = useAuthStore();

  const handleLogout = () => {
    logout();
    router.replace('/(auth)/login');
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <Typography variant="heading" style={styles.title}>
          Profile
        </Typography>

        <View style={styles.divider} />

        {isGuest ? (
          <View style={styles.section}>
            <Typography variant="body" color={colors.textSecondary}>
              You are using Vocation Finder as a guest. Create an account
              to save your assessment history.
            </Typography>
            <Button
              title="Create account"
              onPress={() => router.push('/(auth)/register')}
              style={styles.button}
            />
          </View>
        ) : (
          <View style={styles.section}>
            <View style={styles.field}>
              <Typography
                variant="caption"
                family="sans"
                color={colors.accent}
                style={styles.fieldLabel}
              >
                Name
              </Typography>
              <Typography variant="body">
                {user?.name ?? 'Not set'}
              </Typography>
            </View>

            <View style={styles.field}>
              <Typography
                variant="caption"
                family="sans"
                color={colors.accent}
                style={styles.fieldLabel}
              >
                Email
              </Typography>
              <Typography variant="body">
                {user?.email ?? '---'}
              </Typography>
            </View>
          </View>
        )}

        <View style={styles.divider} />

        <Button
          title="Sign out"
          variant="secondary"
          onPress={handleLogout}
        />
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
    paddingVertical: spacing.xl,
  },
  title: {
    marginBottom: spacing.md,
  },
  divider: {
    height: 1,
    backgroundColor: colors.divider,
    marginVertical: spacing.xl,
  },
  section: {
    gap: spacing.md,
  },
  field: {
    marginBottom: spacing.lg,
  },
  fieldLabel: {
    textTransform: 'uppercase',
    letterSpacing: 1,
    marginBottom: spacing.xs,
  },
  button: {
    alignSelf: 'flex-start',
  },
});
