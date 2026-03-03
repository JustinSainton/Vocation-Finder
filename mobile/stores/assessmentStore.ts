import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';

type AssessmentMode = 'conversation' | 'written' | null;
type AssessmentStatus =
  | 'idle'
  | 'orientation'
  | 'in-progress'
  | 'synthesis'
  | 'complete';

interface AssessmentState {
  assessmentId: string | null;
  mode: AssessmentMode;
  status: AssessmentStatus;
  currentQuestion: number;
  totalQuestions: number;
  answers: Record<number, string>;

  setAssessmentId: (id: string) => void;
  setMode: (mode: AssessmentMode) => void;
  setStatus: (status: AssessmentStatus) => void;
  setCurrentQuestion: (index: number) => void;
  setTotalQuestions: (total: number) => void;
  setAnswer: (questionIndex: number, answer: string) => void;
  reset: () => void;
}

const initialState = {
  assessmentId: null,
  mode: null as AssessmentMode,
  status: 'idle' as AssessmentStatus,
  currentQuestion: 0,
  totalQuestions: 0,
  answers: {} as Record<number, string>,
};

export const useAssessmentStore = create<AssessmentState>()(
  persist(
    (set) => ({
      ...initialState,

      setAssessmentId: (id) => set({ assessmentId: id }),

      setMode: (mode) => set({ mode }),

      setStatus: (status) => set({ status }),

      setCurrentQuestion: (index) => set({ currentQuestion: index }),

      setTotalQuestions: (total) => set({ totalQuestions: total }),

      setAnswer: (questionIndex, answer) =>
        set((state) => ({
          answers: { ...state.answers, [questionIndex]: answer },
        })),

      reset: () => set(initialState),
    }),
    {
      name: 'assessment-storage',
      storage: createJSONStorage(() => AsyncStorage),
    }
  )
);
