import { useCallback, useEffect, useRef } from 'react';
import { Audio } from 'expo-av';
import * as Speech from 'expo-speech';
import {
  useSharedValue,
  cancelAnimation,
} from 'react-native-reanimated';
import { useAssessmentStore, ConversationState } from '../stores/assessmentStore';

const RECORDING_OPTIONS: Audio.RecordingOptions = {
  android: {
    extension: '.m4a',
    outputFormat: Audio.AndroidOutputFormat.MPEG_4,
    audioEncoder: Audio.AndroidAudioEncoder.AAC,
    sampleRate: 16000,
    numberOfChannels: 1,
    bitRate: 64000,
  },
  ios: {
    extension: '.m4a',
    outputFormat: Audio.IOSOutputFormat.MPEG4AAC,
    audioQuality: Audio.IOSAudioQuality.MEDIUM,
    sampleRate: 16000,
    numberOfChannels: 1,
    bitRate: 64000,
  },
  web: {
    mimeType: 'audio/webm',
    bitsPerSecond: 64000,
  },
};

const METERING_INTERVAL_MS = 100;

export function useConversationFlow() {
  const recording = useRef<Audio.Recording | null>(null);
  const meteringInterval = useRef<ReturnType<typeof setInterval> | null>(null);
  const audioLevel = useSharedValue(0);

  const {
    conversationState,
    conversationError,
    aiResponseText,
    currentQuestion,
    totalQuestions,
    questions,
    sessionId,
    assessmentId,
    setConversationState,
    startConversationSession,
    handleConversationTurn,
    completeConversationSession,
    createAssessment,
    fetchQuestions,
  } = useAssessmentStore();

  // Initialize session on mount
  useEffect(() => {
    const init = async () => {
      // Fetch questions if not loaded
      const state = useAssessmentStore.getState();
      if (state.questions.length === 0) {
        await fetchQuestions();
      }

      // Create assessment if needed
      if (!state.assessmentId) {
        await createAssessment('conversation');
      }

      // Start conversation session
      const latest = useAssessmentStore.getState();
      if (latest.assessmentId && !latest.sessionId) {
        await startConversationSession();
      }
    };

    init();
  }, []);

  const requestMicPermission = useCallback(async (): Promise<boolean> => {
    const { status } = await Audio.requestPermissionsAsync();
    return status === 'granted';
  }, []);

  const startRecording = useCallback(async () => {
    const granted = await requestMicPermission();
    if (!granted) {
      setConversationState('error');
      return;
    }

    try {
      // Set audio mode for recording (iOS needs allowsRecordingIOS=true)
      await Audio.setAudioModeAsync({
        allowsRecordingIOS: true,
        playsInSilentModeIOS: true,
      });

      const { recording: rec } = await Audio.Recording.createAsync(
        RECORDING_OPTIONS,
        undefined,
        METERING_INTERVAL_MS
      );

      recording.current = rec;
      setConversationState('listening');

      // Poll metering for audio level visualization
      meteringInterval.current = setInterval(async () => {
        if (!recording.current) return;
        try {
          const status = await recording.current.getStatusAsync();
          if (status.isRecording && status.metering !== undefined) {
            // Normalize dB metering to 0-1 range
            // Metering is typically -160 (silence) to 0 (max)
            const normalized = Math.max(0, Math.min(1, (status.metering + 60) / 60));
            audioLevel.value = normalized;
          }
        } catch {
          // Recording may have stopped
        }
      }, METERING_INTERVAL_MS);
    } catch {
      setConversationState('error');
    }
  }, [requestMicPermission, setConversationState, audioLevel]);

  const stopRecording = useCallback(async () => {
    if (meteringInterval.current) {
      clearInterval(meteringInterval.current);
      meteringInterval.current = null;
    }

    audioLevel.value = 0;

    if (!recording.current) return;

    try {
      await recording.current.stopAndUnloadAsync();

      // Switch audio mode for playback (iOS needs allowsRecordingIOS=false)
      await Audio.setAudioModeAsync({
        allowsRecordingIOS: false,
        playsInSilentModeIOS: true,
      });

      const uri = recording.current.getURI();
      recording.current = null;

      if (!uri) {
        setConversationState('error');
        return;
      }

      // Upload and process the turn
      const response = await handleConversationTurn(uri);

      if (response) {
        if (response.is_complete) {
          // Speak final response, then complete
          Speech.speak(response.response, {
            language: 'en-US',
            onDone: async () => {
              setConversationState('idle');
              await completeConversationSession();
            },
            onError: async () => {
              setConversationState('idle');
              await completeConversationSession();
            },
          });
        } else {
          // Speak AI response, then return to idle for next question
          Speech.speak(response.response, {
            language: 'en-US',
            onDone: () => {
              setConversationState('idle');
            },
            onError: () => {
              setConversationState('idle');
            },
          });
        }
      }
    } catch {
      setConversationState('error');
    }
  }, [
    audioLevel,
    setConversationState,
    handleConversationTurn,
    completeConversationSession,
  ]);

  // Clean up on unmount
  useEffect(() => {
    return () => {
      if (meteringInterval.current) {
        clearInterval(meteringInterval.current);
      }
      if (recording.current) {
        recording.current.stopAndUnloadAsync().catch(() => {});
      }
      Speech.stop();
      cancelAnimation(audioLevel);
    };
  }, []);

  const currentQuestionText =
    questions[currentQuestion]?.conversation_prompt ??
    questions[currentQuestion]?.question_text ??
    '';

  const isComplete = useAssessmentStore((s) => s.status === 'analyzing' || s.status === 'completed');

  return {
    startRecording,
    stopRecording,
    conversationState,
    conversationError,
    aiResponseText,
    audioLevel,
    currentQuestion,
    totalQuestions,
    currentQuestionText,
    isComplete,
  };
}
