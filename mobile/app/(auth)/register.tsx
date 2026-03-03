import React, { useRef, useState } from 'react';
import { View, StyleSheet, TextInput as RNTextInput, ScrollView } from 'react-native';
import { Link, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { SingleLineInput } from '../../components/ui/SingleLineInput';
import { useAuthStore } from '../../stores/authStore';
import { colors, spacing } from '../../constants/theme';

export default function RegisterScreen() {
  const router = useRouter();
  const { register, isLoading, error, clearError } = useAuthStore();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');

  const emailRef = useRef<RNTextInput>(null);
  const passwordRef = useRef<RNTextInput>(null);
  const confirmRef = useRef<RNTextInput>(null);

  const handleRegister = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await register(name, email, password, passwordConfirmation);
      router.replace('/');
    } catch {
      // Error is set in store
    }
  };

  const handleFieldChange = (setter: (val: string) => void) => (text: string) => {
    if (error) clearError();
    setter(text);
  };

  const isValid = name && email && password && passwordConfirmation;

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
      >
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
                Name
              </Typography>
              <SingleLineInput
                value={name}
                onChangeText={handleFieldChange(setName)}
                placeholder="Your name"
                autoCapitalize="words"
                autoFocus
                returnKeyType="next"
                onSubmitEditing={() => emailRef.current?.focus()}
              />
            </View>

            <View style={styles.field}>
              <Typography
                variant="caption"
                family="sans"
                color={colors.textSecondary}
              >
                Email
              </Typography>
              <SingleLineInput
                ref={emailRef}
                value={email}
                onChangeText={handleFieldChange(setEmail)}
                placeholder="you@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
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
                onChangeText={handleFieldChange(setPassword)}
                placeholder="Choose a password"
                secureTextEntry
                returnKeyType="next"
                onSubmitEditing={() => confirmRef.current?.focus()}
              />
            </View>

            <View style={styles.field}>
              <Typography
                variant="caption"
                family="sans"
                color={colors.textSecondary}
              >
                Confirm password
              </Typography>
              <SingleLineInput
                ref={confirmRef}
                value={passwordConfirmation}
                onChangeText={handleFieldChange(setPasswordConfirmation)}
                placeholder="Confirm your password"
                secureTextEntry
                returnKeyType="go"
                onSubmitEditing={handleRegister}
              />
            </View>
          </View>

          <View style={styles.actions}>
            <Button
              title="Create account"
              onPress={handleRegister}
              disabled={isLoading || !isValid}
            />
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
