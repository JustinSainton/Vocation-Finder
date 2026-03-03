import React from 'react';
import { View, StyleSheet, ScrollView, Pressable } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { useAuthStore } from '../../stores/authStore';
import { spacing, type ThemePreference } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function ProfileScreen() {
  const router = useRouter();
  const { user, isGuest, logout } = useAuthStore();
  const { colors, preference, setPreference } = useTheme();
  const styles = getStyles(colors);

  const handleLogout = () => {
    logout();
    router.replace('/(auth)/login');
  };

  const handleThemeSelect = (nextPreference: ThemePreference) => {
    if (nextPreference === preference) {
      return;
    }

    setPreference(nextPreference);
  };

  const options: { value: ThemePreference; label: string }[] = [
    { value: 'system', label: 'System' },
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
  ];

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

        <View style={styles.section}>
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.fieldLabel}
          >
            Appearance
          </Typography>

          <View style={styles.themeOptions}>
            {options.map((option) => {
              const active = preference === option.value;

              return (
                <Pressable
                  key={option.value}
                  onPress={() => {
                    handleThemeSelect(option.value);
                  }}
                  style={[
                    styles.themeOption,
                    active && styles.themeOptionActive,
                  ]}
                >
                  <Typography
                    variant="small"
                    family="sans"
                    color={active ? colors.buttonText : colors.textSecondary}
                  >
                    {option.label}
                  </Typography>
                </Pressable>
              );
            })}
          </View>
        </View>

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

const getStyles = (colors: {
  background: string;
  divider: string;
  buttonBg: string;
}) =>
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
    themeOptions: {
      flexDirection: 'row',
      gap: spacing.sm,
    },
    themeOption: {
      borderWidth: 1,
      borderColor: colors.divider,
      borderRadius: 999,
      paddingVertical: 8,
      paddingHorizontal: 12,
    },
    themeOptionActive: {
      backgroundColor: colors.buttonBg,
      borderColor: colors.buttonBg,
    },
    button: {
      alignSelf: 'flex-start',
    },
  });
