import React, { useState } from 'react';
import { View, StyleSheet, Pressable } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { colors, spacing, layout } from '../../constants/theme';

export default function OrientationScreen() {
  const router = useRouter();
  const [checked, setChecked] = useState(false);

  const handleSpeak = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push('/(assessment)/conversation');
  };

  const handleWrite = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push('/(assessment)/written');
  };

  const toggleCheck = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setChecked((prev) => !prev);
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <View style={styles.body}>
          <Typography variant="heading" style={styles.title}>
            Before we begin
          </Typography>

          <Typography variant="body" style={styles.paragraph}>
            This is not a test. There are no right answers, no scores, and no
            judgment. The questions ahead are invitations to think honestly about
            what moves you, what frustrates you, and what you find yourself
            returning to again and again.
          </Typography>

          <Typography variant="body" style={styles.paragraph}>
            Set aside roughly 30{'\u2013'}45 minutes. This is best done in a
            quiet place, without distractions, when you can give your full
            attention to the process.
          </Typography>

          <Typography
            variant="small"
            family="sans"
            color={colors.textSecondary}
            style={styles.timeNote}
          >
            ~30{'\u2013'}45 minutes
          </Typography>

          <View style={styles.divider} />

          <Pressable
            onPress={toggleCheck}
            style={styles.checkboxRow}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
          >
            <View
              style={[
                styles.checkbox,
                checked && styles.checkboxChecked,
              ]}
            >
              {checked && (
                <View style={styles.checkmark} />
              )}
            </View>
            <Typography
              variant="body"
              style={styles.checkboxLabel}
            >
              I'm willing to answer honestly, not impressively.
            </Typography>
          </Pressable>
        </View>

        <View style={styles.actions}>
          <Button
            title="Speak your answers"
            onPress={handleSpeak}
            disabled={!checked}
          />
          <Button
            title="Write your answers"
            onPress={handleWrite}
            variant="secondary"
            disabled={!checked}
            style={styles.secondaryButton}
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
  title: {
    marginBottom: spacing.xl,
  },
  paragraph: {
    marginBottom: spacing.md,
  },
  timeNote: {
    marginTop: spacing.sm,
    marginBottom: spacing.lg,
  },
  divider: {
    height: 1,
    backgroundColor: colors.divider,
    marginVertical: spacing.lg,
  },
  checkboxRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    minHeight: layout.touchTarget,
    paddingVertical: spacing.sm,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderWidth: 1.5,
    borderColor: colors.text,
    marginRight: spacing.md,
    marginTop: 3,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkboxChecked: {
    backgroundColor: colors.text,
  },
  checkmark: {
    width: 10,
    height: 10,
    backgroundColor: colors.background,
  },
  checkboxLabel: {
    flex: 1,
  },
  actions: {
    marginTop: spacing.xxl,
  },
  secondaryButton: {
    marginTop: spacing.md,
  },
});
