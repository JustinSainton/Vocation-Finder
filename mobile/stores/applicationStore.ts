import { create } from 'zustand';
import { api } from '../services/api';

export interface JobApplicationSummary {
  id: string;
  status: string;
  company_name: string;
  job_title: string;
  job_url: string | null;
  priority: string;
  applied_at: string | null;
  next_action: string | null;
  next_action_date: string | null;
  updated_at: string;
}

export interface ApplicationEvent {
  id: string;
  event_type: string;
  from_status: string | null;
  to_status: string | null;
  details: Record<string, any> | null;
  occurred_at: string;
}

export interface JobApplicationDetail extends JobApplicationSummary {
  salary_offered: number | null;
  notes: string | null;
  source: string | null;
  contact_name: string | null;
  contact_email: string | null;
  events: ApplicationEvent[];
}

interface ApplicationState {
  applications: JobApplicationSummary[];
  currentApp: JobApplicationDetail | null;
  analytics: Record<string, any> | null;
  isLoading: boolean;
  error: string | null;

  fetchApplications: (status?: string) => Promise<void>;
  fetchApplication: (id: string) => Promise<void>;
  fetchAnalytics: () => Promise<void>;
  createApplication: (data: { job_listing_id?: string; company_name: string; job_title: string; source?: string }) => Promise<void>;
  updateStatus: (id: string, status: string) => Promise<void>;
}

export const useApplicationStore = create<ApplicationState>()((set, get) => ({
  applications: [],
  currentApp: null,
  analytics: null,
  isLoading: false,
  error: null,

  fetchApplications: async (status) => {
    set({ isLoading: true, error: null });
    try {
      const params = status ? `?status=${status}` : '';
      const res = await api.get<{ data: JobApplicationSummary[] }>(`/applications${params}`);
      set({ applications: res.data, isLoading: false });
    } catch (err: any) {
      set({ isLoading: false, error: err?.message ?? 'Failed to load applications' });
    }
  },

  fetchApplication: async (id) => {
    set({ isLoading: true, error: null });
    try {
      const res = await api.get<{ data: JobApplicationDetail }>(`/applications/${id}`);
      set({ currentApp: res.data, isLoading: false });
    } catch (err: any) {
      set({ isLoading: false, error: err?.message ?? 'Failed to load application' });
    }
  },

  fetchAnalytics: async () => {
    try {
      const res = await api.get<Record<string, any>>('/applications/analytics');
      set({ analytics: res });
    } catch {}
  },

  createApplication: async (data) => {
    set({ isLoading: true, error: null });
    try {
      await api.post('/applications', data);
      await get().fetchApplications();
    } catch (err: any) {
      set({ isLoading: false, error: err?.message ?? 'Failed to create application' });
    }
  },

  updateStatus: async (id, status) => {
    try {
      await api.put(`/applications/${id}`, { status });
      const { applications, currentApp } = get();
      set({
        applications: applications.map((a) => a.id === id ? { ...a, status } : a),
        currentApp: currentApp?.id === id ? { ...currentApp, status } : currentApp,
      });
    } catch {}
  },
}));
