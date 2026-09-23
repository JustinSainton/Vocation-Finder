import { Question } from '../services/api';
import { DemoMode, useAuthStore } from '../stores/authStore';

/**
 * Null unless the signed-in account is a demo account and the server has demo
 * mode on. Read from the account rather than trusted from the questions,
 * because questions are persisted on the device and outlive a sign-out.
 */
export function useDemoMode(): DemoMode | null {
  return useAuthStore((state) => state.user?.demo ?? null);
}

export function demoAnswerFor(demo: DemoMode | null, question: Question | undefined): string | null {
  return demo && question?.demo_answer ? question.demo_answer : null;
}
