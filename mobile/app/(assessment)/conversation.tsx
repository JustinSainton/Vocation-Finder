import React, { useEffect } from 'react';
import { View, StyleSheet, Pressable } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { Typography } from '../../components/ui/Typography';
import { AudioOrb } from '../../components/AudioOrb';
import { useConversationFlow } from '../../hooks/useConversationFlow';
import { getAssessmentCopy } from '../../constants/assessmentLocale';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useAssessmentStore } from '../../stores/assessmentStore';

export default function ConversationScreen() {
  const router = useRouter();
  const { colors, isDark } = useTheme();
  const styles = getStyles(colors, isDark);
  const locale = useAssessmentStore((state) => state.locale);
  const copy = getAssessmentCopy(locale);
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

  // Auto-start: play intro and first question as soon as questions are ready
  useEffect(() => {
    if (!introPlayed && currentQuestionText && conversationState === 'idle') {
      const timer = setTimeout(() => {
        playIntroAndFirstQuestion();
      }, 300);
      return () => clearTimeout(timer);
    }
  }, [introPlayed, currentQuestionText, conversationState, playIntroAndFirstQuestion]);

  // Navigate to after survey when conversation is complete
  useEffect(() => {
    if (isComplete) {
      router.replace('/(assessment)/after');
    }
  }, [isComplete]);

  const handleOrbPress = async () => {
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

    if (conversationState === 'speaking') {
      stopSpeaking();
      return;
    }

    if (conversationState === 'idle' || conversationState === 'error') {
      startRecording();
    } else if (conversationState === 'listening') {
      stopRecording();
    }
    // Do nothing if processing
  };

  const agentState =
    conversationState === 'processing'
      ? 'thinking'
      : conversationState === 'listening'
        ? 'listening'
        : conversationState === 'speaking'
          ? 'talking'
          : null;

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
            {copy.conversation.title}
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
            agentState={agentState}
            inputLevel={audioLevel}
            outputLevel={speakingLevel}
            colors={isDark ? ['#38BDF8', '#A78BFA'] : ['#2563EB', '#22C55E']}
            onPress={handleOrbPress}
          />

          <Typography
            variant="body"
            color={conversationState === 'error' ? '#B91C1C' : colors.textSecondary}
            style={styles.stateLabel}
          >
            {conversationState === 'idle' && !introPlayed
              ? copy.conversation.tapToBegin
              : {
                  idle: copy.conversation.idle,
                  listening: copy.conversation.listening,
                  processing: copy.conversation.processing,
                  speaking: copy.conversation.speaking,
                  error: copy.conversation.error,
                }[conversationState]}
          </Typography>

          {conversationState === 'idle' && introPlayed ? (
            <Pressable onPress={handleOrbPress} style={styles.secondaryAction}>
              <Typography variant="small" family="sans" color={colors.textSecondary}>
                {copy.conversation.tapToRecord}
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
                ? copy.conversation.progress(currentQuestion, totalQuestions)
                : copy.conversation.starting}
            </Typography>
          </View>
        </View>
      </View>
    </SafeAreaView>
  );
}

const getStyles = (
  colors: {
    background: string;
    accent: string;
    text: string;
    textSecondary: string;
    divider: string;
  },
  isDark: boolean
) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: colors.background,
    },
    bgBlobOne: {
      position: 'absolute',
      width: 260,
      height: 260,
      borderRadius: 130,
      backgroundColor: isDark ? 'rgba(14, 116, 144, 0.26)' : 'rgba(186, 230, 253, 0.35)',
      top: -70,
      right: -90,
    },
    bgBlobTwo: {
      position: 'absolute',
      width: 220,
      height: 220,
      borderRadius: 110,
      backgroundColor: isDark ? 'rgba(180, 83, 9, 0.22)' : 'rgba(254, 215, 170, 0.28)',
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
      backgroundColor: isDark ? 'rgba(18, 23, 32, 0.88)' : 'rgba(255, 255, 255, 0.78)',
      borderWidth: 1,
      borderColor: colors.divider,
      borderRadius: 18,
      paddingVertical: spacing.lg,
      paddingHorizontal: spacing.md,
      boxShadow: isDark
        ? '0 10px 32px rgba(0, 0, 0, 0.28)'
        : '0 10px 32px rgba(28, 25, 23, 0.10)',
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
      backgroundColor: isDark ? 'rgba(28, 35, 47, 0.72)' : 'rgba(255, 255, 255, 0.72)',
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
      backgroundColor: isDark ? 'rgba(24, 31, 43, 0.82)' : 'rgba(255, 255, 255, 0.82)',
    },
    indicator: {
      textAlign: 'center',
    },
  });
