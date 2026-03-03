import { useCallback, useEffect, useRef, useState } from 'react';
import {
  AudioModule,
  RecordingPresets,
  setAudioModeAsync,
  useAudioPlayer,
  useAudioPlayerStatus,
  useAudioRecorder,
  useAudioRecorderState,
  useAudioSampleListener,
} from 'expo-audio';
import * as Speech from 'expo-speech';
import { cancelAnimation, useSharedValue } from 'react-native-reanimated';
import { assessmentApi } from '../services/api';
import { useAssessmentStore } from '../stores/assessmentStore';

const METERING_INTERVAL_MS = 100;

export function useConversationFlow() {
  const audioLevel = useSharedValue(0);
  const speakingLevel = useSharedValue(0);
  const [introPlayed, setIntroPlayed] = useState(false);
  const preferredVoiceRef = useRef<string | undefined>(undefined);
  const onSpeechDoneRef = useRef<(() => void) | null>(null);
  const onSpeechErrorRef = useRef<(() => void) | null>(null);

  const audioRecorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const recorderState = useAudioRecorderState(audioRecorder, METERING_INTERVAL_MS);
  const ttsPlayer = useAudioPlayer(null, { updateInterval: 100 });
  const ambientPlayer = useAudioPlayer(require('../assets/audio/zen-bed.m4a'), {
    updateInterval: 500,
  });
  const ttsStatus = useAudioPlayerStatus(ttsPlayer);

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

  useAudioSampleListener(ttsPlayer, (sample) => {
    if (conversationState !== 'speaking') {
      speakingLevel.value = 0;
      return;
    }

    const frames = sample.channels[0]?.frames;

    if (!frames || frames.length === 0) {
      speakingLevel.value = 0.08;
      return;
    }

    let sum = 0;
    for (let i = 0; i < frames.length; i += 1) {
      const frame = frames[i];
      sum += frame * frame;
    }

    const rms = Math.sqrt(sum / frames.length);
    speakingLevel.value = Math.min(1, Math.max(0.08, rms * 8));
  });

  // Initialize session on mount
  useEffect(() => {
    const init = async () => {
      let state = useAssessmentStore.getState();
      if (state.questions.length === 0) {
        await fetchQuestions();
        state = useAssessmentStore.getState();
      }

      if (state.questions.length === 0) {
        useAssessmentStore.setState({
          conversationState: 'error',
          conversationError:
            state.questionsError ??
            'No assessment questions are available yet. Please try again shortly.',
        });
        return;
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

  useEffect(() => {
    let mounted = true;

    Speech.getAvailableVoicesAsync()
      .then((voices) => {
        if (!mounted || !voices?.length) {
          return;
        }

        const englishVoices = voices.filter((voice) => voice.language?.startsWith('en'));
        const enhancedEnglishVoices = englishVoices.filter(
          (voice) => String(voice.quality ?? '').toLowerCase() === 'enhanced'
        );

        const preferred =
          enhancedEnglishVoices.find(
            (voice) =>
              /serena|allison|karen|samantha|ava|zoe/i.test(voice.name ?? '')
          ) ??
          enhancedEnglishVoices[0] ??
          englishVoices.find(
            (voice) =>
              /premium|enhanced|serena|allison|karen|samantha|ava/i.test(voice.name ?? '')
          ) ??
          voices.find((voice) => voice.language === 'en-US') ??
          englishVoices[0];

        preferredVoiceRef.current = preferred?.identifier;
      })
      .catch(() => {
        preferredVoiceRef.current = undefined;
      });

    return () => {
      mounted = false;
    };
  }, []);

  const requestMicPermission = useCallback(async (): Promise<boolean> => {
    const status = await AudioModule.requestRecordingPermissionsAsync();
    return status.granted;
  }, []);

  const startAmbientBed = useCallback(async () => {
    try {
      ambientPlayer.loop = true;
      ambientPlayer.volume = 0.11;
      await ambientPlayer.seekTo(0);
      ambientPlayer.play();
    } catch {
      // best effort only
    }
  }, [ambientPlayer]);

  const stopAmbientBed = useCallback(async () => {
    try {
      ambientPlayer.pause();
      await ambientPlayer.seekTo(0);
    } catch {
      // best effort only
    }
  }, [ambientPlayer]);

  const clearSpeechCallbacks = useCallback(() => {
    onSpeechDoneRef.current = null;
    onSpeechErrorRef.current = null;
  }, []);

  const finalizeSpeech = useCallback(
    (kind: 'done' | 'error') => {
      const doneCallback = onSpeechDoneRef.current;
      const errorCallback = onSpeechErrorRef.current;

      clearSpeechCallbacks();
      speakingLevel.value = 0;
      void stopAmbientBed();

      if (kind === 'done') {
        doneCallback?.();
        return;
      }

      errorCallback?.();
    },
    [clearSpeechCallbacks, speakingLevel, stopAmbientBed]
  );

  const currentQuestionText =
    questions[currentQuestion]?.conversation_prompt ??
    questions[currentQuestion]?.question_text ??
    '';

  const speakText = useCallback(
    async (text: string, options?: { onDone?: () => void; onError?: () => void }) => {
      onSpeechDoneRef.current = options?.onDone ?? (() => setConversationState('idle'));
      onSpeechErrorRef.current = options?.onError ?? (() => setConversationState('idle'));

      setConversationState('speaking');
      speakingLevel.value = 0.08;
      await startAmbientBed();

      try {
        const speech = await assessmentApi.synthesizeConversationSpeech(text);
        const probeController = new AbortController();
        const probeTimeout = setTimeout(() => {
          probeController.abort();
        }, 4000);

        let probeStatus = 0;
        try {
          const probeResponse = await fetch(speech.audio_url, {
            method: 'GET',
            headers: {
              Range: 'bytes=0-1',
            },
            signal: probeController.signal,
          });
          probeStatus = probeResponse.status;
        } finally {
          clearTimeout(probeTimeout);
        }

        if (probeStatus === 404 || probeStatus >= 500) {
          throw new Error(`Remote TTS audio URL not reachable (${probeStatus})`);
        }

        await setAudioModeAsync({
          allowsRecording: false,
          playsInSilentMode: true,
        });

        ttsPlayer.replace(speech.audio_url);
        ttsPlayer.play();
        return;
      } catch {
        Speech.speak(text, {
          language: 'en-US',
          voice: preferredVoiceRef.current,
          rate: 0.86,
          pitch: 0.88,
          onDone: () => finalizeSpeech('done'),
          onError: () => finalizeSpeech('error'),
        });
      }
    },
    [finalizeSpeech, preferredVoiceRef, setConversationState, speakingLevel, startAmbientBed, ttsPlayer]
  );

  const stopSpeaking = useCallback(() => {
    Speech.stop();
    ttsPlayer.pause();
    void stopAmbientBed();
    finalizeSpeech('done');
  }, [finalizeSpeech, stopAmbientBed, ttsPlayer]);

  useEffect(() => {
    if (ttsStatus.didJustFinish) {
      finalizeSpeech('done');
    }
  }, [finalizeSpeech, ttsStatus.didJustFinish]);

  const startRecording = useCallback(async () => {
    if (!currentQuestionText) {
      useAssessmentStore.setState({
        conversationState: 'error',
        conversationError: 'No question is ready yet. Please try again in a moment.',
      });
      return;
    }

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

      void stopAmbientBed();
      await audioRecorder.prepareToRecordAsync();
      audioRecorder.record();

      setConversationState('listening');
    } catch {
      setConversationState('error');
    }
  }, [
    requestMicPermission,
    setConversationState,
    audioRecorder,
    sessionId,
    startConversationSession,
    currentQuestionText,
    stopAmbientBed,
  ]);

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
          await speakText(response.response, {
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
          await speakText(response.response, {
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
    speakText,
  ]);

  const playIntroAndFirstQuestion = useCallback(() => {
    if (!currentQuestionText || introPlayed) {
      if (!currentQuestionText) {
        useAssessmentStore.setState({
          conversationState: 'error',
          conversationError: 'Questions are still loading. Please try again in a moment.',
        });
      }
      return;
    }

    setIntroPlayed(true);
    setConversationState('speaking');

    const intro = totalQuestions > 0
      ? `Welcome to Vocation Finder. We'll walk through ${totalQuestions} questions together. Take your time and answer honestly. First question: ${currentQuestionText}`
      : `Welcome to Vocation Finder. First question: ${currentQuestionText}`;

    void speakText(intro, {
      onDone: () => {
        setConversationState('idle');
      },
      onError: () => {
        setConversationState('idle');
      },
    });
  }, [currentQuestionText, introPlayed, setConversationState, speakText, totalQuestions]);

  // Clean up on unmount
  useEffect(() => {
    return () => {
      Speech.stop();
      ttsPlayer.pause();
      ambientPlayer.pause();
      cancelAnimation(audioLevel);
      cancelAnimation(speakingLevel);
      clearSpeechCallbacks();
    };
  }, [ambientPlayer, audioLevel, clearSpeechCallbacks, speakingLevel, ttsPlayer]);

  const isComplete = useAssessmentStore((s) => s.status === 'analyzing' || s.status === 'completed');

  return {
    startRecording,
    stopRecording,
    playIntroAndFirstQuestion,
    stopSpeaking,
    introPlayed,
    conversationState,
    conversationError,
    aiResponseText,
    audioLevel,
    speakingLevel,
    currentQuestion,
    totalQuestions,
    currentQuestionText,
    isComplete,
  };
}
