import React, { useEffect, useRef, useCallback } from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  TextInput as RNTextInput,
} from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Typography } from '../../components/ui/Typography';
import { Button } from '../../components/ui/Button';
import { TextInput } from '../../components/ui/TextInput';
import { useAssessmentStore } from '../../stores/assessmentStore';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

export default function WrittenAssessmentScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const styles = getStyles(colors);
  const scrollRef = useRef<ScrollView>(null);
  const inputRef = useRef<RNTextInput>(null);

  const {
    currentQuestion,
    totalQuestions,
    answers,
    questions,
    questionsLoading,
    questionsError,
    assessmentId,
    fetchQuestions,
    createAssessment,
    saveAnswerToApi,
    setCurrentQuestion,
  } = useAssessmentStore();

  // Fetch questions and create assessment on mount
  useEffect(() => {
    const init = async () => {
      if (questions.length === 0) {
        await fetchQuestions();
      }
      if (!assessmentId) {
        await createAssessment('written');
      }
    };
    init();
  }, []);

  // Scroll to top and focus input when question changes
  useEffect(() => {
    scrollRef.current?.scrollTo({ y: 0, animated: false });
    // Small delay to let the layout settle before focusing
    const timer = setTimeout(() => {
      inputRef.current?.focus();
    }, 100);
    return () => clearTimeout(timer);
  }, [currentQuestion]);

  const currentAnswer = answers[currentQuestion] ?? '';
  const question = questions[currentQuestion];
  const isLastQuestion = currentQuestion === totalQuestions - 1;

  const handleTextChange = useCallback(
    (text: string) => {
      saveAnswerToApi(currentQuestion, text);
    },
    [currentQuestion, saveAnswerToApi]
  );

  const handleContinue = async () => {
    if (isLastQuestion) {
      router.push('/(assessment)/synthesis');
    } else {
      setCurrentQuestion(currentQuestion + 1);
    }
  };

  const handleBack = () => {
    if (currentQuestion > 0) {
      setCurrentQuestion(currentQuestion - 1);
    }
  };

  // Loading state
  if (questionsLoading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centered}>
          <Typography variant="body" color={colors.textSecondary}>
            Preparing your questions...
          </Typography>
        </View>
      </SafeAreaView>
    );
  }

  // Error state
  if (questionsError) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centered}>
          <Typography variant="body" color={colors.textSecondary}>
            {questionsError}
          </Typography>
          <View style={styles.retryAction}>
            <Button title="Try again" onPress={fetchQuestions} />
          </View>
        </View>
      </SafeAreaView>
    );
  }

  if (questions.length === 0) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centered}>
          <Typography variant="body" color={colors.textSecondary}>
            No questions are available yet. Please try again shortly.
          </Typography>
          <View style={styles.retryAction}>
            <Button title="Retry" onPress={fetchQuestions} />
          </View>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <ScrollView
          ref={scrollRef}
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          {/* Category label */}
          {question?.category_name ? (
            <Typography
              variant="caption"
              family="sans"
              color={colors.accent}
              style={styles.category}
            >
              {question.category_name}
            </Typography>
          ) : null}

          {/* Question text */}
          <Typography variant="heading" style={styles.question}>
            {question?.question_text}
          </Typography>

          {/* Text input -- using defaultValue to avoid JS-to-native flicker */}
          <TextInput
            ref={inputRef}
            key={`question-${currentQuestion}`}
            defaultValue={currentAnswer}
            onChangeText={handleTextChange}
            placeholder="Take your time. Write freely."
            minHeight={200}
          />

          {/* Bottom area */}
          <View style={styles.bottomArea}>
            {/* Question indicator */}
            <Typography
              variant="caption"
              family="sans"
              color={colors.accent}
              style={styles.indicator}
            >
              Question {currentQuestion + 1} of {totalQuestions}
            </Typography>

            {/* Continue button */}
            <Button
              title={isLastQuestion ? 'Finish assessment' : 'Continue'}
              onPress={handleContinue}
              disabled={currentAnswer.trim().length === 0}
            />

            {/* Back link */}
            {currentQuestion > 0 ? (
              <Pressable
                onPress={handleBack}
                style={styles.backButton}
                hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
              >
                <Typography
                  variant="small"
                  family="sans"
                  color={colors.textSecondary}
                >
                  Back
                </Typography>
              </Pressable>
            ) : null}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const getStyles = (colors: { background: string }) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    flex: {
      flex: 1,
    },
    centered: {
      flex: 1,
      justifyContent: 'center',
      alignItems: 'center',
      paddingHorizontal: spacing.lg,
    },
    retryAction: {
      marginTop: spacing.lg,
    },
    scrollContent: {
      flexGrow: 1,
      paddingHorizontal: spacing.lg,
      paddingTop: spacing.xxl,
      paddingBottom: spacing.xl,
    },
    category: {
      textTransform: 'uppercase',
      letterSpacing: 1.2,
      marginBottom: spacing.lg,
    },
    question: {
      marginBottom: spacing.xl,
    },
    bottomArea: {
      marginTop: spacing.xxl,
      paddingBottom: spacing.lg,
    },
    indicator: {
      marginBottom: spacing.lg,
    },
    backButton: {
      alignItems: 'center',
      paddingVertical: spacing.md,
      marginTop: spacing.sm,
    },
  });
