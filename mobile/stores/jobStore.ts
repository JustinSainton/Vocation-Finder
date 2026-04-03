import { create } from 'zustand';
import { api } from '../services/api';

interface JobCategory {
  slug: string;
  name: string;
}

export interface JobListingSummary {
  id: string;
  title: string;
  company_name: string;
  location: string | null;
  is_remote: boolean;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string;
  source_url: string | null;
  posted_at: string | null;
  match_score?: number;
  match_percent?: number;
  is_saved?: boolean;
  categories: JobCategory[];
}

export interface JobListingDetail extends JobListingSummary {
  company_url: string | null;
  description: string | null;
  required_skills: string[] | null;
  source: string;
}

interface JobFilters {
  search?: string;
  pathway?: string;
  remote?: boolean;
  salary_min?: number;
}

interface JobState {
  jobs: JobListingSummary[];
  recommended: JobListingSummary[];
  currentJob: JobListingDetail | null;
  isLoading: boolean;
  error: string | null;
  filters: JobFilters;
  page: number;
  hasMore: boolean;

  fetchJobs: (reset?: boolean) => Promise<void>;
  fetchRecommended: () => Promise<void>;
  fetchJob: (id: string) => Promise<void>;
  saveJob: (id: string) => Promise<void>;
  unsaveJob: (id: string) => Promise<void>;
  setFilters: (filters: Partial<JobFilters>) => void;
}

export const useJobStore = create<JobState>()((set, get) => ({
  jobs: [],
  recommended: [],
  currentJob: null,
  isLoading: false,
  error: null,
  filters: {},
  page: 1,
  hasMore: true,

  fetchJobs: async (reset = false) => {
    const { filters, page, jobs } = get();
    const currentPage = reset ? 1 : page;
    set({ isLoading: true, error: null });

    try {
      const params = new URLSearchParams();
      params.set('page', String(currentPage));
      if (filters.search) params.set('search', filters.search);
      if (filters.pathway) params.set('pathway', filters.pathway);
      if (filters.remote) params.set('remote', 'true');
      if (filters.salary_min) params.set('salary_min', String(filters.salary_min));

      const response = await api.get<{
        data: JobListingSummary[];
        current_page: number;
        last_page: number;
      }>(`/jobs?${params.toString()}`);

      set({
        jobs: reset ? response.data : [...jobs, ...response.data],
        page: currentPage + 1,
        hasMore: response.current_page < response.last_page,
        isLoading: false,
      });
    } catch (err: any) {
      set({ isLoading: false, error: err?.message ?? 'Failed to load jobs' });
    }
  },

  fetchRecommended: async () => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get<{ data: JobListingSummary[] }>('/jobs/recommended');
      set({ recommended: response.data, isLoading: false });
    } catch (err: any) {
      set({ isLoading: false, error: err?.message ?? 'Failed to load recommendations' });
    }
  },

  fetchJob: async (id) => {
    set({ isLoading: true, error: null });
    try {
      const response = await api.get<{ data: JobListingDetail }>(`/jobs/${id}`);
      set({ currentJob: response.data, isLoading: false });
    } catch (err: any) {
      set({ isLoading: false, error: err?.message ?? 'Failed to load job' });
    }
  },

  saveJob: async (id) => {
    try {
      await api.post(`/jobs/${id}/save`);
      const { jobs, recommended, currentJob } = get();
      const markSaved = (j: JobListingSummary) =>
        j.id === id ? { ...j, is_saved: true } : j;
      set({
        jobs: jobs.map(markSaved),
        recommended: recommended.map(markSaved),
        currentJob: currentJob?.id === id ? { ...currentJob, is_saved: true } : currentJob,
      });
    } catch {}
  },

  unsaveJob: async (id) => {
    try {
      await api.delete(`/jobs/${id}/save`);
      const { jobs, recommended, currentJob } = get();
      const markUnsaved = (j: JobListingSummary) =>
        j.id === id ? { ...j, is_saved: false } : j;
      set({
        jobs: jobs.map(markUnsaved),
        recommended: recommended.map(markUnsaved),
        currentJob: currentJob?.id === id ? { ...currentJob, is_saved: false } : currentJob,
      });
    } catch {}
  },

  setFilters: (newFilters) => {
    set((state) => ({
      filters: { ...state.filters, ...newFilters },
      page: 1,
      hasMore: true,
    }));
  },
}));
