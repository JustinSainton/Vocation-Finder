import { useCallback, useEffect, useRef, useState } from 'react';
import {
  useAudioRecorder,
  AudioModule,
  RecordingPresets,
  setAudioModeAsync,
  useAudioRecorderState,
} from 'expo-audio';
import * as Speech from 'expo-speech';
import {
  useSharedValue,
  cancelAnimation,
} from 'react-native-reanimated';
import { useAssessmentStore } from '../stores/assessmentStore';

const METERING_INTERVAL_MS = 100;

export function useConversationFlow() {
  const audioLevel = useSharedValue(0);
  const meteringInterval = useRef<ReturnType<typeof setInterval> | null>(null);
  const [introPlayed, setIntroPlayed] = useState(false);

  const audioRecorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const recorderState = useAudioRecorderState(audioRecorder, METERING_INTERVAL_MS);

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

  // Update audioLevel from recorderState metering
  useEffect(() => {
    if (recorderState.isRecording && recorderState.metering !== undefined) {
      const normalized = Math.max(0, Math.min(1, (recorderState.metering + 60) / 60));
      audioLevel.value = normalized;
    }
  }, [recorderState.metering, recorderState.isRecording]);

  // Initialize session on mount
  useEffect(() => {
    const init = async () => {
      const state = useAssessmentStore.getState();
      if (state.questions.length === 0) {
        await fetchQuestions();
      }

      if (!state.assessmentId) {
        await createAssessment('conversation');
      }

      const latest = useAssessmentStore.getState();
      if (latest.assessmentId && !latest.sessionId) {
        await startConversationSession();
      }
    };

    init();
  }, []);

  useEffect(() => {
    if (!sessionId) {
      setIntroPlayed(false);
    }
  }, [sessionId]);

  const requestMicPermission = useCallback(async (): Promise<boolean> => {
    const status = await AudioModule.requestRecordingPermissionsAsync();
    return status.granted;
  }, []);

  const startRecording = useCallback(async () => {
    if (!sessionId) {
      await startConversationSession();

      const latestSessionId = useAssessmentStore.getState().sessionId;
      if (!latestSessionId) {
        setConversationState('error');
        return;
      }
    }

    const granted = await requestMicPermission();
    if (!granted) {
      setConversationState('error');
      return;
    }

    try {
      await setAudioModeAsync({
        allowsRecording: true,
        playsInSilentMode: true,
      });

      await audioRecorder.prepareToRecordAsync();
      audioRecorder.record();

      setConversationState('listening');
    } catch {
      setConversationState('error');
    }
  }, [requestMicPermission, setConversationState, audioRecorder, sessionId, startConversationSession]);

  const stopRecording = useCallback(async () => {
    audioLevel.value = 0;

    if (!recorderState.isRecording) return;

    try {
      await audioRecorder.stop();

      await setAudioModeAsync({
        allowsRecording: false,
        playsInSilentMode: true,
      });

      const uri = audioRecorder.uri;

      if (!uri) {
        setConversationState('error');
        return;
      }

      const response = await handleConversationTurn(uri);

      if (response) {
        if (response.is_complete) {
          Speech.speak(response.response, {
            language: 'en-US',
            onDone: () => {
              setConversationState('idle');
              completeConversationSession();
            },
            onError: () => {
              setConversationState('idle');
              completeConversationSession();
            },
          });
        } else {
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
    audioRecorder,
    recorderState.isRecording,
    setConversationState,
    handleConversationTurn,
    completeConversationSession,
  ]);

  const currentQuestionText =
    questions[currentQuestion]?.conversation_prompt ??
    questions[currentQuestion]?.question_text ??
    '';

  const playIntroAndFirstQuestion = useCallback(() => {
    if (!currentQuestionText || introPlayed) {
      return;
    }

    setIntroPlayed(true);
    setConversationState('speaking');

    const intro = totalQuestions > 0
      ? `Welcome to Vocation Finder. We'll walk through ${totalQuestions} questions together. Take your time and answer honestly. First question: ${currentQuestionText}`
      : `Welcome to Vocation Finder. First question: ${currentQuestionText}`;

    Speech.speak(intro, {
      language: 'en-US',
      onDone: () => {
        setConversationState('idle');
      },
      onError: () => {
        setConversationState('idle');
      },
    });
  }, [currentQuestionText, introPlayed, setConversationState, totalQuestions]);

  // Clean up on unmount
  useEffect(() => {
    return () => {
      Speech.stop();
      cancelAnimation(audioLevel);
    };
  }, []);

  const isComplete = useAssessmentStore((s) => s.status === 'analyzing' || s.status === 'completed');

  return {
    startRecording,
    stopRecording,
    playIntroAndFirstQuestion,
    introPlayed,
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
