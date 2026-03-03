import React, { useEffect } from 'react';
import { View, StyleSheet } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { AudioOrb } from '../../components/AudioOrb';
import { useConversationFlow } from '../../hooks/useConversationFlow';
import { colors, spacing } from '../../constants/theme';
import type { ConversationState } from '../../stores/assessmentStore';

const STATE_LABELS: Record<ConversationState, string> = {
  idle: 'Tap to speak',
  listening: 'Listening...',
  processing: 'Thinking...',
  speaking: 'Speaking...',
  error: 'Something went wrong. Tap to retry.',
};

export default function ConversationScreen() {
  const router = useRouter();
  const {
    startRecording,
    stopRecording,
    conversationState,
    conversationError,
    audioLevel,
    currentQuestion,
    totalQuestions,
    currentQuestionText,
    isComplete,
  } = useConversationFlow();

  // Navigate to results when conversation is complete
  useEffect(() => {
    if (isComplete) {
      router.replace('/(assessment)/results');
    }
  }, [isComplete]);

  const handleOrbPress = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

    if (conversationState === 'idle' || conversationState === 'error') {
      startRecording();
    } else if (conversationState === 'listening') {
      stopRecording();
    }
    // Do nothing if processing or speaking
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        {/* Question text */}
        <View style={styles.questionArea}>
          {currentQuestionText ? (
            <Typography
              variant="body"
              style={styles.questionText}
            >
              {currentQuestionText}
            </Typography>
          ) : null}
        </View>

        {/* Orb area */}
        <View style={styles.orbArea}>
          <AudioOrb
            state={conversationState}
            audioLevel={audioLevel}
            onPress={handleOrbPress}
          />

          {/* State label */}
          <Typography
            variant="body"
            color={conversationState === 'error' ? '#B91C1C' : colors.textSecondary}
            style={styles.stateLabel}
          >
            {STATE_LABELS[conversationState]}
          </Typography>
        </View>

        {/* Bottom progress */}
        <View style={styles.bottomArea}>
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.indicator}
          >
            {totalQuestions > 0
              ? `Question ${currentQuestion + 1} of ${totalQuestions}`
              : 'Starting conversation...'}
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
    paddingBottom: spacing.xxl,
  },
  questionArea: {
    flex: 1,
    justifyContent: 'center',
    paddingTop: spacing.xl,
  },
  questionText: {
    textAlign: 'center',
  },
  orbArea: {
    alignItems: 'center',
    paddingVertical: spacing.xl,
  },
  stateLabel: {
    marginTop: spacing.xl,
    textAlign: 'center',
  },
  bottomArea: {
    flex: 1,
    justifyContent: 'flex-end',
    alignItems: 'center',
  },
  indicator: {
    textAlign: 'center',
  },
});
