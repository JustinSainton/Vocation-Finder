import React from 'react';
import { View, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { colors, spacing } from '../../constants/theme';

export default function ConversationScreen() {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Typography variant="heading" style={styles.title}>
          Conversation
        </Typography>

        <Typography variant="body" color={colors.textSecondary}>
          The audio conversation interface will appear here. You will speak
          your responses aloud, and the system will guide you through a
          series of reflective prompts.
        </Typography>

        <View style={styles.placeholder}>
          <View style={styles.circle} />
          <Typography
            variant="small"
            family="sans"
            color={colors.accent}
            style={styles.placeholderText}
          >
            Audio interface placeholder
          </Typography>
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
    alignItems: 'center',
  },
  title: {
    marginBottom: spacing.lg,
    textAlign: 'center',
  },
  placeholder: {
    marginTop: spacing.xxl,
    alignItems: 'center',
  },
  circle: {
    width: 120,
    height: 120,
    borderRadius: 60,
    borderWidth: 1,
    borderColor: colors.divider,
    marginBottom: spacing.md,
  },
  placeholderText: {
    textAlign: 'center',
  },
});
