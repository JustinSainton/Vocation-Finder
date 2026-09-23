/**
 * Where a row on the assessment list goes.
 *
 * The web dashboard list (`resources/js/Pages/Dashboard.tsx`) does the same
 * thing: a completed row opens that assessment's results, and every other
 * row goes to `/assessment/written`, which creates a written session and
 * starts at the clarity baseline. The old `/assessment` orientation gate
 * is not part of that path.
 *
 * If this device already holds the in-progress assessment the row names,
 * open that session instead of creating another one.
 */
export type AssessmentListDestination =
  | { kind: 'results'; assessmentId: string }
  | {
      kind: 'continue';
      route: '/(assessment)/written' | '/(assessment)/conversation';
    }
  | { kind: 'start-written' };

export function destinationForAssessmentListItem(
  item: { id: string; status: string; mode: string },
  local: { assessmentId: string | null },
): AssessmentListDestination {
  if (item.status === 'completed') {
    return { kind: 'results', assessmentId: item.id };
  }

  const sameLocalSession =
    item.status === 'in_progress' &&
    local.assessmentId === item.id &&
    (item.mode === 'written' || item.mode === 'conversation');

  if (sameLocalSession) {
    return {
      kind: 'continue',
      route:
        item.mode === 'conversation'
          ? '/(assessment)/conversation'
          : '/(assessment)/written',
    };
  }

  return { kind: 'start-written' };
}
