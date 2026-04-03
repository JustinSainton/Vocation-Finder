import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { api } from '../services/api';

interface WorkEntry {
  company: string;
  position: string;
  startDate: string;
  endDate: string;
  summary: string;
}

interface EducationEntry {
  institution: string;
  area: string;
  studyType: string;
  startDate: string;
  endDate: string;
}

interface SkillEntry {
  name: string;
  level: string;
}

export interface CareerProfile {
  id: string;
  work_history: WorkEntry[] | null;
  education: EducationEntry[] | null;
  skills: SkillEntry[] | null;
  certifications: string[] | null;
  volunteer: WorkEntry[] | null;
  import_source: string | null;
  imported_at: string | null;
}

interface CareerProfileState {
  profile: CareerProfile | null;
  isLoading: boolean;
  error: string | null;

  fetchProfile: () => Promise<void>;
  updateProfile: (data: Partial<CareerProfile>) => Promise<void>;
  clearProfile: () => void;
}

export const useCareerProfileStore = create<CareerProfileState>()(
  persist(
    (set) => ({
      profile: null,
      isLoading: false,
      error: null,

      fetchProfile: async () => {
        set({ isLoading: true, error: null });
        try {
          const response = await api.get<{ data: CareerProfile | null }>('/career-profile');
          set({ profile: response.data, isLoading: false });
        } catch (err: any) {
          set({ isLoading: false, error: err?.message ?? 'Failed to load career profile' });
        }
      },

      updateProfile: async (data) => {
        set({ isLoading: true, error: null });
        try {
          const response = await api.put<{ data: CareerProfile }>('/career-profile', data);
          set({ profile: response.data, isLoading: false });
        } catch (err: any) {
          set({ isLoading: false, error: err?.message ?? 'Failed to update career profile' });
          throw err;
        }
      },

      clearProfile: () => set({ profile: null, error: null }),
    }),
    {
      name: 'career-profile-storage',
      storage: createJSONStorage(() => AsyncStorage),
      partialize: (state) => ({
        profile: state.profile,
      }),
    }
  )
);
