import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  AssessmentLocale,
  DEFAULT_ASSESSMENT_LOCALE,
  detectDeviceLocale,
  getAssessmentCopy,
  normalizeAssessmentLocale,
} from '../constants/assessmentLocale';
import {
  assessmentApi,
  Question,
  VocationalProfile,
  ConversationTurnResponse,
} from '../services/api';

type AssessmentMode = 'conversation' | 'written' | null;
type AssessmentStatus =
  | 'idle'
  | 'orientation'
  | 'in_progress'
  | 'analyzing'
  | 'failed'
  | 'completed';

export type ConversationState =
  | 'idle'
  | 'listening'
  | 'processing'
  | 'speaking'
  | 'error';

interface AssessmentState {
  // Core state
  assessmentId: string | null;
  guestToken: string | null;
  guestName: string | undefined;
  mode: AssessmentMode;
  status: AssessmentStatus;
  currentQuestion: number;
  totalQuestions: number;
  answers: Record<number, string>;
  locale: AssessmentLocale;
  speechLocale: AssessmentLocale;
  questionsLocale: AssessmentLocale | null;

  // Questions fetched from the API
  questions: Question[];
  questionsLoading: boolean;
  questionsError: string | null;

  // Conversation mode
  sessionId: string | null;
  conversationState: ConversationState;
  conversationError: string | null;
  aiResponseText: string | null;

  // Results
  results: VocationalProfile | null;
  resultsLoading: boolean;
  resultsError: string | null;
  resultsStatusMessage: string | null;
  tier: 'free' | 'paid' | null;
  lockedSections: string[];
  upgradeMessage: string | null;

  // Simple setters
  setMode: (mode: AssessmentMode) => void;
  setStatus: (status: AssessmentStatus) => void;
  setCurrentQuestion: (index: number) => void;
  setAnswer: (questionIndex: number, answer: string) => void;
  setLocalePreferences: (locale: AssessmentLocale, speechLocale?: AssessmentLocale) => void;

  // API actions
  fetchQuestions: () => Promise<void>;
  createAssessment: (mode: 'written' | 'conversation') => Promise<string>;
  saveAnswerToApi: (questionIndex: number, answer: string) => Promise<void>;
  submitSurvey: (type: 'before' | 'after', clarityScore: number, actionScore: number) => Promise<void>;
  completeAssessment: () => Promise<void>;
  fetchResults: () => Promise<VocationalProfile | null>;

  // Conversation actions
  setConversationState: (state: ConversationState) => void;
  startConversationSession: () => Promise<void>;
  handleConversationTurn: (payload: {
    transcript?: string;
    transcriptLocale?: AssessmentLocale;
    transcriptConfidence?: number | null;
    audioUri?: string;
    durationSeconds?: number;
    clientProcessing?: {
      stt_engine?: string;
      tts_engine?: string;
      app_version?: string;
    };
  }) => Promise<ConversationTurnResponse | null>;
  completeConversationSession: () => Promise<void>;

  reset: () => void;
}

const initialState = {
  assessmentId: null as string | null,
  guestToken: null as string | null,
  guestName: undefined as string | undefined,
  mode: null as AssessmentMode,
  status: 'idle' as AssessmentStatus,
  currentQuestion: 0,
  totalQuestions: 0,
  answers: {} as Record<number, string>,
  locale: detectDeviceLocale(),
  speechLocale: detectDeviceLocale(),
  questionsLocale: null as AssessmentLocale | null,
  questions: [] as Question[],
  questionsLoading: false,
  questionsError: null as string | null,
  sessionId: null as string | null,
  conversationState: 'idle' as ConversationState,
  conversationError: null as string | null,
  aiResponseText: null as string | null,
  results: null as VocationalProfile | null,
  resultsLoading: false,
  resultsError: null as string | null,
  resultsStatusMessage: null as string | null,
  tier: null as 'free' | 'paid' | null,
  lockedSections: [] as string[],
  upgradeMessage: null as string | null,
};

let saveTimeout: ReturnType<typeof setTimeout> | null = null;

export const useAssessmentStore = create<AssessmentState>()(
  persist(
    (set, get) => ({
      ...initialState,

      setMode: (mode) => set({ mode }),
      setStatus: (status) => set({ status }),
      setCurrentQuestion: (index) => set({ currentQuestion: index }),
      setLocalePreferences: (locale, speechLocale) =>
        set((state) => {
          const nextLocale = normalizeAssessmentLocale(locale);
          const nextSpeechLocale = normalizeAssessmentLocale(speechLocale ?? locale);
          const localeChanged =
            state.locale !== nextLocale || state.speechLocale !== nextSpeechLocale;

          return {
            locale: nextLocale,
            speechLocale: nextSpeechLocale,
            questions: localeChanged ? [] : state.questions,
            totalQuestions: localeChanged ? 0 : state.totalQuestions,
            questionsLocale: localeChanged ? null : state.questionsLocale,
          };
        }),

      setAnswer: (questionIndex, answer) =>
        set((state) => ({
          answers: { ...state.answers, [questionIndex]: answer },
        })),

      fetchQuestions: async () => {
        const locale = get().locale;
        const copy = getAssessmentCopy(locale);

        set({ questionsLoading: true, questionsError: null });
        try {
          const data = await assessmentApi.getQuestions(locale);
          const questions = data.data;

          if (questions.length === 0) {
            set({
              questions: [],
              totalQuestions: 0,
              questionsLocale: locale,
              questionsLoading: false,
              questionsError: copy.written.noneAvailable,
            });
            return;
          }

          set({
            questions,
            totalQuestions: questions.length,
            questionsLocale: locale,
            questionsLoading: false,
          });
        } catch (err: any) {
          set({
            questionsLoading: false,
            questionsError: err?.message ?? copy.written.noneAvailable,
          });
        }
      },

      createAssessment: async (mode) => {
        const { locale, speechLocale } = get();
        const data = await assessmentApi.createAssessment(mode, locale, speechLocale);
        set({
          assessmentId: data.id,
          guestToken: data.guest_token,
          mode,
          status: 'in_progress',
          currentQuestion: 0,
          answers: {},
          results: null,
          resultsError: null,
          resultsStatusMessage: null,
        });
        return data.id;
      },

      saveAnswerToApi: async (questionIndex, answer) => {
        set((state) => ({
          answers: { ...state.answers, [questionIndex]: answer },
        }));

        if (saveTimeout) clearTimeout(saveTimeout);
        saveTimeout = setTimeout(async () => {
          const { assessmentId, questions, guestToken } = get();
          if (!assessmentId || !questions[questionIndex]) return;

          try {
            await assessmentApi.saveAnswer(
              assessmentId,
              questions[questionIndex].id,
              answer,
              guestToken ?? undefined,
              get().locale
            );
          } catch {
            // Saved locally, will retry
          }
        }, 500);
      },

      completeAssessment: async () => {
        const { assessmentId, guestToken } = get();
        if (!assessmentId) return;

        set({ status: 'analyzing' });
        await assessmentApi.completeAssessment(assessmentId, guestToken ?? undefined);
      },

      submitSurvey: async (type, clarityScore, actionScore) => {
        const { assessmentId, guestToken } = get();
        if (!assessmentId) return;

        try {
          await assessmentApi.submitSurvey(
            assessmentId,
            type,
            clarityScore,
            actionScore,
            guestToken ?? undefined
          );
        } catch {
          // Survey failures are non-fatal — do not block the user flow
        }
      },

      fetchResults: async () => {
        const { assessmentId, guestToken } = get();
        if (!assessmentId) return null;

        set({ resultsLoading: true, resultsError: null });

        try {
          const data = await assessmentApi.getResults(assessmentId, guestToken ?? undefined);

          if (data.status === 'completed' && data.profile) {
            set({
              results: data.profile,
              resultsLoading: false,
              status: 'completed',
              resultsError: null,
              resultsStatusMessage: null,
              tier: data.tier ?? 'paid',
              lockedSections: data.locked_sections ?? [],
              upgradeMessage: data.upgrade_message ?? null,
            });
            return data.profile;
          }

          if (data.status === 'failed') {
            set({
              resultsLoading: false,
              status: 'failed',
              resultsError: data.message ?? 'We encountered an issue analyzing your assessment.',
              resultsStatusMessage: null,
            });
            return null;
          }

          set({
            resultsLoading: false,
            status: 'analyzing',
            resultsStatusMessage: data.message ?? null,
          });
          return null;
        } catch (err: any) {
          set({
            resultsLoading: false,
            resultsError: err?.message ?? 'Failed to load results',
          });
          return null;
        }
      },

      setConversationState: (conversationState) => set({ conversationState }),

      startConversationSession: async () => {
        const { assessmentId, locale, speechLocale } = get();
        if (!assessmentId) return;

        try {
          const data = await assessmentApi.startConversation(assessmentId, {
            locale,
            speechLocale,
          });
          set({
            sessionId: data.session_id,
            currentQuestion: data.current_question_index,
            conversationState: 'idle',
            conversationError: null,
          });
        } catch (err: any) {
          set({
            conversationState: 'error',
            conversationError: err?.message ?? 'Failed to start conversation',
          });
        }
      },

      handleConversationTurn: async (payload) => {
        const { sessionId, locale, speechLocale } = get();
        if (!sessionId) return null;
        const copy = getAssessmentCopy(locale);

        set({ conversationState: 'processing', conversationError: null });

        try {
          let transcript = payload.transcript?.trim() ?? '';
          let transcriptLocale = payload.transcriptLocale ?? speechLocale;
          let audioPath: string | undefined;

          if (!transcript) {
            if (!payload.audioUri) {
              throw {
                message: copy.conversation.noSpeechDetected,
                status: 422,
              };
            }

            const audioUpload = await assessmentApi.uploadConversationAudio(sessionId, payload.audioUri, {
              locale,
              speechLocale,
            });

            transcript = audioUpload.transcript?.trim() ?? '';
            transcriptLocale = audioUpload.transcript_locale;
            audioPath = audioUpload.audio_path;
          }

          if (!transcript) {
            throw {
              message: copy.conversation.noSpeechDetected,
              status: 422,
            };
          }

          const response = await assessmentApi.processTurn(sessionId, {
            transcript,
            transcriptLocale,
            transcriptConfidence: payload.transcriptConfidence,
            audioPath,
            durationSeconds: payload.durationSeconds,
            clientProcessing: payload.clientProcessing,
          });
          set({
            aiResponseText: response.response,
            currentQuestion: response.current_question_index,
            conversationState: 'speaking',
          });
          return response;
        } catch (err: any) {
          set({
            conversationState: 'error',
            conversationError: err?.message ?? 'Failed to process audio',
          });
          return null;
        }
      },

      completeConversationSession: async () => {
        const { sessionId } = get();
        if (!sessionId) return;

        set({ status: 'analyzing' });
        try {
          await assessmentApi.completeConversation(sessionId);
        } catch (err: any) {
          set({
            conversationState: 'error',
            conversationError: err?.message ?? 'Failed to complete conversation',
          });
        }
      },

      reset: () => {
        if (saveTimeout) clearTimeout(saveTimeout);
        const { locale, speechLocale } = get();

        set({
          ...initialState,
          locale,
          speechLocale,
        });
      },
    }),
    {
      name: 'assessment-storage',
      storage: createJSONStorage(() => AsyncStorage),
      partialize: (state) => ({
        assessmentId: state.assessmentId,
        guestToken: state.guestToken,
        mode: state.mode,
        status: state.status,
        currentQuestion: state.currentQuestion,
        totalQuestions: state.totalQuestions,
        answers: state.answers,
        locale: state.locale,
        speechLocale: state.speechLocale,
        questions: state.questions,
        questionsLocale: state.questionsLocale,
        results: state.results,
        // sessionId intentionally NOT persisted — sessions are ephemeral
        // and stale IDs cause errors on next launch
      }),
    }
  )
);
