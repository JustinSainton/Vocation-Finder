import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
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
  mode: AssessmentMode;
  status: AssessmentStatus;
  currentQuestion: number;
  totalQuestions: number;
  answers: Record<number, string>;

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
  tier: 'free' | 'paid' | null;
  lockedSections: string[];
  upgradeMessage: string | null;

  // Simple setters
  setMode: (mode: AssessmentMode) => void;
  setStatus: (status: AssessmentStatus) => void;
  setCurrentQuestion: (index: number) => void;
  setAnswer: (questionIndex: number, answer: string) => void;

  // API actions
  fetchQuestions: () => Promise<void>;
  createAssessment: (mode: 'written' | 'conversation') => Promise<string>;
  saveAnswerToApi: (questionIndex: number, answer: string) => Promise<void>;
  completeAssessment: () => Promise<void>;
  fetchResults: () => Promise<VocationalProfile | null>;

  // Conversation actions
  setConversationState: (state: ConversationState) => void;
  startConversationSession: () => Promise<void>;
  handleConversationTurn: (audioUri: string) => Promise<ConversationTurnResponse | null>;
  completeConversationSession: () => Promise<void>;

  reset: () => void;
}

const initialState = {
  assessmentId: null as string | null,
  guestToken: null as string | null,
  mode: null as AssessmentMode,
  status: 'idle' as AssessmentStatus,
  currentQuestion: 0,
  totalQuestions: 0,
  answers: {} as Record<number, string>,
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

      setAnswer: (questionIndex, answer) =>
        set((state) => ({
          answers: { ...state.answers, [questionIndex]: answer },
        })),

      fetchQuestions: async () => {
        set({ questionsLoading: true, questionsError: null });
        try {
          const data = await assessmentApi.getQuestions();
          const questions = data.data;
          set({
            questions,
            totalQuestions: questions.length,
            questionsLoading: false,
          });
        } catch (err: any) {
          set({
            questionsLoading: false,
            questionsError: err?.message ?? 'Failed to load questions',
          });
        }
      },

      createAssessment: async (mode) => {
        const data = await assessmentApi.createAssessment(mode);
        set({
          assessmentId: data.id,
          guestToken: data.guest_token,
          mode,
          status: 'in_progress',
          currentQuestion: 0,
          answers: {},
          results: null,
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
              guestToken ?? undefined
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
              tier: data.tier ?? 'paid',
              lockedSections: data.locked_sections ?? [],
              upgradeMessage: data.upgrade_message ?? null,
            });
            return data.profile;
          }

          set({ resultsLoading: false });
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
        const { assessmentId } = get();
        if (!assessmentId) return;

        try {
          const data = await assessmentApi.startConversation(assessmentId);
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

      handleConversationTurn: async (audioUri) => {
        const { sessionId } = get();
        if (!sessionId) return null;

        set({ conversationState: 'processing', conversationError: null });

        try {
          const audioUpload = await assessmentApi.uploadConversationAudio(sessionId, audioUri);
          const response = await assessmentApi.processTurn(
            sessionId,
            audioUpload.transcript,
            audioUpload.audio_path
          );
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
        set(initialState);
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
        questions: state.questions,
        results: state.results,
        sessionId: state.sessionId,
      }),
    }
  )
);
