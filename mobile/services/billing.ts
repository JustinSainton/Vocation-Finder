import { api } from './api';

export interface AccessCheck {
  has_access: boolean;
  credits: number;
  org_quota: {
    assessments_used: number;
    assessments_limit: number | null;
    has_remaining: boolean;
  } | null;
}

export interface BillingUsage {
  assessments_this_period: number;
  assessments_limit: number | null;
  credits: number;
  plan_name: string | null;
}

export interface CheckoutResponse {
  checkout_url: string;
}

export const billingApi = {
  /** Check if the current user can take an assessment */
  accessCheck: () =>
    api.get<AccessCheck>('/billing/access-check'),

  /** Get billing usage stats */
  usage: () =>
    api.get<BillingUsage>('/billing/usage'),

  /** Get a checkout URL for an individual plan */
  checkoutIndividual: (plan: string) =>
    api.post<CheckoutResponse>('/billing/checkout/individual', { plan }),

  /** Get a checkout URL for an organization plan */
  checkoutOrganization: (plan: string) =>
    api.post<CheckoutResponse>('/billing/checkout/organization', { plan }),

  /** Get the Stripe billing portal URL */
  billingPortal: () =>
    api.get<{ url: string }>('/billing/portal'),
};
