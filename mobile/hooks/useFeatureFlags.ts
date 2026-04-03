import { useEffect, useCallback } from 'react';
import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { api } from '../services/api';

type FeatureFlags = Record<string, boolean>;

interface FeatureFlagState {
  flags: FeatureFlags;
  lastFetched: number | null;
  setFlags: (flags: FeatureFlags) => void;
}

const STALE_AFTER_MS = 5 * 60 * 1000; // 5 minutes

const useFeatureFlagStore = create<FeatureFlagState>()(
  persist(
    (set) => ({
      flags: {},
      lastFetched: null,
      setFlags: (flags) => set({ flags, lastFetched: Date.now() }),
    }),
    {
      name: 'feature-flags-storage',
      storage: createJSONStorage(() => AsyncStorage),
    }
  )
);

export function useFeatureFlags() {
  const { flags, lastFetched, setFlags } = useFeatureFlagStore();

  const refresh = useCallback(async () => {
    try {
      const data = await api.get<FeatureFlags>('/features');
      setFlags(data);
    } catch {
      // Use cached flags on failure
    }
  }, [setFlags]);

  useEffect(() => {
    const isStale = !lastFetched || Date.now() - lastFetched > STALE_AFTER_MS;
    if (isStale) {
      refresh();
    }
  }, [lastFetched, refresh]);

  const isEnabled = useCallback(
    (key: string): boolean => flags[key] ?? false,
    [flags]
  );

  return { flags, isEnabled, refresh };
}
