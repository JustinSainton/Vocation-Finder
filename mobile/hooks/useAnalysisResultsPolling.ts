import { useCallback, useEffect, useRef, useState } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { useFocusEffect } from 'expo-router';
import * as Haptics from 'expo-haptics';
import {
  RESULTS_POLL_INTERVAL_MS,
  RESULTS_POLL_LONG_WAIT_MS,
} from '../lib/resultsPolling';
import { useAssessmentStore } from '../stores/assessmentStore';

/**
 * Poll GET /assessments/{id}/results while analysis runs.
 *
 * Resumes on screen focus and when the app returns to the foreground, and
 * treats transient network failures as non-fatal while status is analyzing.
 */
export function useAnalysisResultsPolling() {
  const results = useAssessmentStore((s) => s.results);
  const resultsError = useAssessmentStore((s) => s.resultsError);
  const status = useAssessmentStore((s) => s.status);
  const fetchResults = useAssessmentStore((s) => s.fetchResults);

  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [isTakingLong, setIsTakingLong] = useState(false);

  const shouldPoll = !results && !resultsError && status === 'analyzing';

  const clearPollingTimers = useCallback(() => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
  }, []);

  const pollOnce = useCallback(async () => {
    const profile = await fetchResults();
    if (profile) {
      clearPollingTimers();
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => null);
    }
    return profile;
  }, [clearPollingTimers, fetchResults]);

  const startPolling = useCallback(() => {
    clearPollingTimers();
    setIsTakingLong(false);

    timeoutRef.current = setTimeout(() => {
      setIsTakingLong(true);
    }, RESULTS_POLL_LONG_WAIT_MS);

    pollRef.current = setInterval(() => {
      void pollOnce();
    }, RESULTS_POLL_INTERVAL_MS);
  }, [clearPollingTimers, pollOnce]);

  useEffect(() => {
    if (!shouldPoll) {
      clearPollingTimers();
      return;
    }

    void pollOnce();
    startPolling();

    return clearPollingTimers;
  }, [shouldPoll, pollOnce, startPolling, clearPollingTimers]);

  useEffect(() => {
    if (!shouldPoll) {
      return;
    }

    const handleAppState = (nextState: AppStateStatus) => {
      if (nextState === 'active') {
        void pollOnce();
      }
    };

    const subscription = AppState.addEventListener('change', handleAppState);
    return () => subscription.remove();
  }, [shouldPoll, pollOnce]);

  useFocusEffect(
    useCallback(() => {
      if (!shouldPoll) {
        return;
      }

      void pollOnce();
    }, [shouldPoll, pollOnce]),
  );

  const retryResults = useCallback(async () => {
    useAssessmentStore.setState({ resultsError: null });
    setIsTakingLong(false);
    await pollOnce();
    if (!useAssessmentStore.getState().results && !useAssessmentStore.getState().resultsError) {
      startPolling();
    }
  }, [pollOnce, startPolling]);

  return {
    isTakingLong,
    retryResults,
    shouldPoll,
  };
}
