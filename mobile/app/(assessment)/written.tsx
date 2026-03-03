import React, { useState } from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { TextInput } from '../../components/ui/TextInput';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { colors, spacing } from '../../constants/theme';

export default function WrittenAssessmentScreen() {
  const router = useRouter();
  const { currentQuestion, answers, setAnswer } = useAssessmentStore();
  const [text, setText] = useState(answers[currentQuestion] ?? '');

  // Placeholder question -- real questions will come from the API
  const question =
    'Describe a moment in your life when you felt most engaged and alive. What were you doing, and what about that experience felt meaningful to you?';

  const handleSave = () => {
    setAnswer(currentQuestion, text);
  };

  const handleContinue = () => {
    handleSave();
    router.push('/(assessment)/synthesis');
  };

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.progress}>
            <Typography
              variant="caption"
              family="sans"
              color={colors.accent}
            >
              Question {currentQuestion + 1}
            </Typography>
          </View>

          <Typography variant="heading" style={styles.question}>
            {question}
          </Typography>

          <View style={styles.divider} />

          <TextInput
            value={text}
            onChangeText={setText}
            placeholder="Take your time. Write whatever comes to mind..."
            minHeight={200}
          />

          <View style={styles.actions}>
            <Button
              title="Continue"
              onPress={handleContinue}
              disabled={text.trim().length === 0}
            />
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  flex: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.xl,
  },
  progress: {
    marginBottom: spacing.xxl,
  },
  question: {
    marginBottom: spacing.lg,
  },
  divider: {
    height: 1,
    backgroundColor: colors.divider,
    marginBottom: spacing.lg,
  },
  actions: {
    marginTop: spacing.xxl,
    paddingBottom: spacing.xl,
  },
});
