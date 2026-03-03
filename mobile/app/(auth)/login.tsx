import React, { useRef, useState } from 'react';
import { View, StyleSheet, TextInput as RNTextInput, ScrollView } from 'react-native';
import { Link, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { SingleLineInput } from '../../components/ui/SingleLineInput';
import { useAuthStore } from '../../stores/authStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function LoginScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const { login, isLoading, error, clearError } = useAuthStore();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const passwordRef = useRef<RNTextInput>(null);

  const handleLogin = async () => {
    try {
      await login(email, password);
      router.replace('/');
    } catch {
      // Error is set in store
    }
  };

  const handleEmailChange = (text: string) => {
    if (error) clearError();
    setEmail(text);
  };

  const handlePasswordChange = (text: string) => {
    if (error) clearError();
    setPassword(text);
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.content}>
          <View style={styles.header}>
            <Typography variant="headingLarge">Welcome back</Typography>
            <Typography
              variant="body"
              color={colors.textSecondary}
              style={styles.subtitle}
            >
              Sign in to continue your exploration.
            </Typography>
          </View>

          {error ? (
            <Typography
              variant="small"
              family="sans"
              color="#B91C1C"
              style={styles.error}
            >
              {error}
            </Typography>
          ) : null}

          <View style={styles.form}>
            <View style={styles.field}>
              <Typography
                variant="caption"
                family="sans"
                color={colors.textSecondary}
              >
                Email
              </Typography>
              <SingleLineInput
                value={email}
                onChangeText={handleEmailChange}
                placeholder="you@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
                autoFocus
                returnKeyType="next"
                onSubmitEditing={() => passwordRef.current?.focus()}
              />
            </View>

            <View style={styles.field}>
              <Typography
                variant="caption"
                family="sans"
                color={colors.textSecondary}
              >
                Password
              </Typography>
              <SingleLineInput
                ref={passwordRef}
                value={password}
                onChangeText={handlePasswordChange}
                placeholder="Your password"
                secureTextEntry
                returnKeyType="go"
                onSubmitEditing={handleLogin}
              />
            </View>
          </View>

          <View style={styles.actions}>
            <Button
              title="Sign in"
              onPress={handleLogin}
              disabled={isLoading || !email || !password}
            />
            <View style={styles.linkRow}>
              <Typography variant="small" color={colors.textSecondary}>
                Don't have an account?{' '}
              </Typography>
              <Link href="/(auth)/register">
                <Typography variant="small" color={colors.text}>
                  Register
                </Typography>
              </Link>
            </View>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const getStyles = (colors: { background: string }) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    scrollContent: {
      flexGrow: 1,
    },
    content: {
      flex: 1,
      paddingHorizontal: spacing.lg,
      justifyContent: 'center',
      paddingVertical: spacing.xxl,
    },
    header: {
      marginBottom: spacing.xl,
    },
    subtitle: {
      marginTop: spacing.sm,
    },
    error: {
      marginBottom: spacing.md,
    },
    form: {
      marginBottom: spacing.xl,
    },
    field: {
      marginBottom: spacing.lg,
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
