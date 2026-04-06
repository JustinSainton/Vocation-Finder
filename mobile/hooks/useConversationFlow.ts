import { useCallback, useEffect, useRef, useState } from 'react';
import Constants from 'expo-constants';
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
import {
  getAssessmentCopy,
  getExpoSpeechLanguage,
  normalizeAssessmentLocale,
} from '../constants/assessmentLocale';
import { assessmentApi } from '../services/api';
import {
  isLocalSttEnabled,
  releaseLocalStt,
  transcribeLocalAudio,
  warmupLocalStt,
} from '../services/localSttBundled';
import {
  isLocalTtsEnabled,
  releaseLocalTts,
  synthesizeLocalSpeech,
  warmupLocalTts,
} from '../services/localTtsBundled';
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
    locale,
    speechLocale,
    sessionId,
    setConversationState,
    startConversationSession,
    handleConversationTurn,
    completeConversationSession,
    createAssessment,
    fetchQuestions,
  } = useAssessmentStore();
  const copy = getAssessmentCopy(locale);
  const normalizedSpeechLocale = normalizeAssessmentLocale(speechLocale);

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
      if (state.questions.length === 0 || state.questionsLocale !== state.locale) {
        await fetchQuestions();
        state = useAssessmentStore.getState();
      }

      if (state.questions.length === 0) {
        const fallbackCopy = getAssessmentCopy(state.locale);
        useAssessmentStore.setState({
          conversationState: 'error',
          conversationError:
            state.questionsError ??
            fallbackCopy.written.noneAvailable,
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

        const voiceLanguage = getExpoSpeechLanguage(normalizedSpeechLocale);
        const localePrefix = voiceLanguage.slice(0, 2).toLowerCase();
        const matchingVoices = voices.filter((voice) =>
          voice.language?.toLowerCase().startsWith(localePrefix)
        );
        const enhancedVoices = matchingVoices.filter(
          (voice) => String(voice.quality ?? '').toLowerCase() === 'enhanced'
        );

        const preferred =
          enhancedVoices.find(
            (voice) =>
              /serena|allison|karen|samantha|ava|zoe|paulina|monica|luciana|fernanda/i.test(
                voice.name ?? ''
              )
          ) ??
          enhancedVoices[0] ??
          matchingVoices.find(
            (voice) =>
              /premium|enhanced|serena|allison|karen|samantha|ava|paulina|luciana/i.test(
                voice.name ?? ''
              )
          ) ??
          voices.find((voice) => voice.language === voiceLanguage) ??
          matchingVoices[0];

        preferredVoiceRef.current = preferred?.identifier;
      })
      .catch(() => {
        preferredVoiceRef.current = undefined;
      });

    return () => {
      mounted = false;
    };
  }, [normalizedSpeechLocale]);

  useEffect(() => {
    if (!isLocalTtsEnabled() && !isLocalSttEnabled()) {
      return;
    }

    if (isLocalTtsEnabled()) {
      void warmupLocalTts(normalizedSpeechLocale).catch(() => {
        // optional optimization only
      });
    }

    if (isLocalSttEnabled()) {
      void warmupLocalStt(normalizedSpeechLocale).catch(() => {
        // optional optimization only
      });
    }
  }, [normalizedSpeechLocale]);

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
        try {
          if (isLocalTtsEnabled()) {
            const localSpeech = await synthesizeLocalSpeech(text, normalizedSpeechLocale);

            await setAudioModeAsync({
              allowsRecording: false,
              playsInSilentMode: true,
            });

            ttsPlayer.replace(localSpeech.uri);
            ttsPlayer.play();
            return;
          }
        } catch {
          // fall through to remote TTS and native fallback
        }

        try {
          const speech = await assessmentApi.synthesizeConversationSpeech(text, normalizedSpeechLocale);
          console.log('[TTS] Remote speech response:', JSON.stringify(speech));
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
            console.log('[TTS] Probe status:', probeStatus, 'for URL:', speech.audio_url);
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
        } catch (remoteTtsError) {
          console.warn('[TTS] Remote TTS failed, falling back to native speech:', remoteTtsError);
          Speech.speak(text, {
            language: getExpoSpeechLanguage(normalizedSpeechLocale),
            voice: preferredVoiceRef.current,
            rate: 0.86,
            pitch: 0.88,
            onDone: () => finalizeSpeech('done'),
            onError: () => finalizeSpeech('error'),
          });
        }
      } catch {
        finalizeSpeech('error');
      }
    },
    [
      finalizeSpeech,
      normalizedSpeechLocale,
      preferredVoiceRef,
      setConversationState,
      speakingLevel,
      startAmbientBed,
      ttsPlayer,
    ]
  );

  const stopSpeaking = useCallback(() => {
    Speech.stop();
    try {
      ttsPlayer.pause();
    } catch {
      // player may already be disposed
    }
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
        conversationError: copy.conversation.missingQuestion,
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
    copy.conversation.missingQuestion,
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

      setConversationState('processing');

      const durationSeconds =
        recorderState.durationMillis > 0
          ? Math.max(1, Math.round(recorderState.durationMillis / 1000))
          : undefined;

      let response = null;

      if (isLocalSttEnabled()) {
        try {
          const localTranscript = await transcribeLocalAudio(uri, normalizedSpeechLocale);

          response = await handleConversationTurn({
            transcript: localTranscript.text,
            transcriptLocale: localTranscript.locale,
            transcriptConfidence: localTranscript.confidence,
            durationSeconds,
            clientProcessing: {
              stt_engine: `${localTranscript.engine}:${localTranscript.modelId}`,
              tts_engine: isLocalTtsEnabled() ? 'sherpa-onnx' : 'remote-or-native',
              app_version:
                Constants.expoConfig?.version ??
                Constants.nativeAppVersion ??
                Constants.manifest2?.runtimeVersion ??
                undefined,
            },
          });
        } catch (localSttError) {
          console.warn('[STT] Local transcription failed:', localSttError);
          // On-device STT failed — models may still be downloading.
          // Set a descriptive error instead of silently falling back to
          // server upload (which will also fail without OpenAI/ElevenLabs).
          useAssessmentStore.setState({
            conversationState: 'error',
            conversationError:
              'Voice models are still preparing. Please wait a moment and try again.',
          });
          return;
        }
      }

      if (!response && !isLocalSttEnabled()) {
        // Only attempt server-side audio upload when local STT is explicitly disabled.
        response = await handleConversationTurn({
          audioUri: uri,
          durationSeconds,
          clientProcessing: {
            stt_engine: 'server-upload',
            tts_engine: isLocalTtsEnabled() ? 'sherpa-onnx' : 'remote-or-native',
            app_version:
              Constants.expoConfig?.version ??
              Constants.nativeAppVersion ??
              Constants.manifest2?.runtimeVersion ??
              undefined,
          },
        });
      }

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
    normalizedSpeechLocale,
    recorderState.durationMillis,
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
          conversationError: copy.conversation.loadingQuestions,
        });
      }
      return;
    }

    setIntroPlayed(true);
    setConversationState('speaking');

    const intro = copy.conversation.intro(totalQuestions, currentQuestionText);

    void speakText(intro, {
      onDone: () => {
        setConversationState('idle');
      },
      onError: () => {
        setConversationState('idle');
      },
    });
  }, [
    copy.conversation,
    currentQuestionText,
    introPlayed,
    setConversationState,
    speakText,
    totalQuestions,
  ]);

  // Clean up on unmount
  useEffect(() => {
    return () => {
      Speech.stop();
      try {
        ttsPlayer.pause();
      } catch {
        // player may already be disposed
      }
      try {
        ambientPlayer.pause();
      } catch {
        // player may already be disposed
      }
      void releaseLocalTts();
      void releaseLocalStt();
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
