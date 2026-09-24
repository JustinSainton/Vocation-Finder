/**
 * Shared polling/resume rules for the analysis waiting screen.
 *
 * Mirrors the web Results page: transient failures while status is still
 * `analyzing` should not stop polling or show a dead-end error.
 */

export const RESULTS_POLL_INTERVAL_MS = 5000;
export const RESULTS_POLL_LONG_WAIT_MS = 120000;

export type ResultsFetchError = {
  message?: string;
  status?: number;
};

/** HTTP statuses that mean "keep waiting" rather than "show error UI". */
export function isTransientResultsFetchError(error: ResultsFetchError): boolean {
  const status = error.status ?? 0;

  if (status === 0) {
    return true;
  }

  if (status === 408 || status === 429) {
    return true;
  }

  if (status >= 500 && status !== 501) {
    return true;
  }

  return false;
}

/** Whether fetchResults should surface resultsError for this failure. */
export function shouldSurfaceResultsFetchError(
  assessmentStatus: 'idle' | 'orientation' | 'in_progress' | 'analyzing' | 'failed' | 'completed',
  error: ResultsFetchError,
): boolean {
  if (assessmentStatus !== 'analyzing') {
    return true;
  }

  return !isTransientResultsFetchError(error);
}
