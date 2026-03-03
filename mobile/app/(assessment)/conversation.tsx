import React, { useEffect } from 'react';
import { View, StyleSheet, Pressable } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { AudioOrb } from '../../components/AudioOrb';
import { useConversationFlow } from '../../hooks/useConversationFlow';
import { colors, spacing } from '../../constants/theme';
import type { ConversationState } from '../../stores/assessmentStore';

const STATE_LABELS: Record<ConversationState, string> = {
  idle: 'Tap the orb to answer',
  listening: 'Listening... tap again to send',
  processing: 'Discerning your response...',
  speaking: 'Speaking... tap orb to skip',
  error: 'Connection issue. Tap to retry.',
};

export default function ConversationScreen() {
  const router = useRouter();
  const {
    startRecording,
    stopRecording,
    playIntroAndFirstQuestion,
    stopSpeaking,
    introPlayed,
    conversationState,
    conversationError,
    audioLevel,
    speakingLevel,
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

    if (conversationState === 'speaking') {
      stopSpeaking();
      return;
    }

    if (conversationState === 'idle' || conversationState === 'error') {
      if (!introPlayed) {
        playIntroAndFirstQuestion();
        return;
      }

      startRecording();
    } else if (conversationState === 'listening') {
      stopRecording();
    }
    // Do nothing if processing
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.bgBlobOne} />
      <View style={styles.bgBlobTwo} />

      <View style={styles.content}>
        <View style={styles.topArea}>
          <Typography
            variant="caption"
            family="sans"
            color={colors.accent}
            style={styles.topLabel}
          >
            Voice Discernment
          </Typography>

          {currentQuestionText ? (
            <View style={styles.questionCard}>
              <Typography variant="body" style={styles.questionText}>
                {currentQuestionText}
              </Typography>
            </View>
          ) : null}
        </View>

        <View style={styles.orbArea}>
          <AudioOrb
            state={conversationState}
            audioLevel={audioLevel}
            speechLevel={speakingLevel}
            onPress={handleOrbPress}
          />

          <Typography
            variant="body"
            color={conversationState === 'error' ? '#B91C1C' : colors.textSecondary}
            style={styles.stateLabel}
          >
            {conversationState === 'idle' && !introPlayed
              ? 'Tap to begin'
              : STATE_LABELS[conversationState]}
          </Typography>

          {conversationState === 'idle' && introPlayed ? (
            <Pressable onPress={handleOrbPress} style={styles.secondaryAction}>
              <Typography variant="small" family="sans" color={colors.textSecondary}>
                Tap to start recording
              </Typography>
            </Pressable>
          ) : null}

          {conversationError ? (
            <Typography
              variant="small"
              family="sans"
              color="#B91C1C"
              style={styles.errorText}
            >
              {conversationError}
            </Typography>
          ) : null}
        </View>

        <View style={styles.bottomArea}>
          <View style={styles.progressPill}>
            <Typography
              variant="caption"
              family="sans"
              color={colors.text}
              style={styles.indicator}
            >
              {totalQuestions > 0
                ? `Question ${currentQuestion + 1} of ${totalQuestions}`
                : 'Starting conversation...'}
            </Typography>
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
  bgBlobOne: {
    position: 'absolute',
    width: 260,
    height: 260,
    borderRadius: 130,
    backgroundColor: 'rgba(186, 230, 253, 0.35)',
    top: -70,
    right: -90,
  },
  bgBlobTwo: {
    position: 'absolute',
    width: 220,
    height: 220,
    borderRadius: 110,
    backgroundColor: 'rgba(254, 215, 170, 0.28)',
    bottom: -60,
    left: -70,
  },
  content: {
    flex: 1,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.xl,
    justifyContent: 'space-between',
  },
  topArea: {
    gap: spacing.md,
  },
  topLabel: {
    textTransform: 'uppercase',
    letterSpacing: 1.1,
  },
  questionCard: {
    backgroundColor: 'rgba(255, 255, 255, 0.78)',
    borderWidth: 1,
    borderColor: colors.divider,
    borderRadius: 18,
    paddingVertical: spacing.lg,
    paddingHorizontal: spacing.md,
    boxShadow: '0 10px 32px rgba(28, 25, 23, 0.10)',
  },
  questionText: {
    textAlign: 'left',
  },
  orbArea: {
    alignItems: 'center',
    paddingVertical: spacing.md,
    gap: spacing.md,
  },
  stateLabel: {
    textAlign: 'center',
  },
  secondaryAction: {
    borderWidth: 1,
    borderColor: colors.divider,
    borderRadius: 999,
    paddingVertical: 8,
    paddingHorizontal: 14,
    backgroundColor: 'rgba(255, 255, 255, 0.72)',
  },
  errorText: {
    textAlign: 'center',
    maxWidth: 320,
  },
  bottomArea: {
    alignItems: 'center',
  },
  progressPill: {
    borderWidth: 1,
    borderColor: colors.divider,
    borderRadius: 999,
    paddingVertical: 8,
    paddingHorizontal: 14,
    backgroundColor: 'rgba(255, 255, 255, 0.82)',
  },
  indicator: {
    textAlign: 'center',
  },
});
