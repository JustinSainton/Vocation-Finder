import { useEffect, useState } from 'react';
import { Redirect } from 'expo-router';
import { useAssessmentStore } from '../stores/assessmentStore';
import type { VocationalProfile } from '../services/api';

/**
 * DEV-ONLY preview of the results / "what to do next" screen.
 *
 * Seeds the assessment store with representative profile data and forwards to
 * the real results screen so the UI can be inspected without completing an
 * assessment or hitting the backend. Reach it in a dev build via:
 *   xcrun simctl openurl booted vocationfinder://dev-preview
 *
 * This route renders nothing in production builds.
 */
const SAMPLE_RESULTS: VocationalProfile = {
  id: 'dev-preview',
  opening_synthesis:
    'There is a steadiness in the way you move toward people who are struggling. Across your answers, a clear thread emerges: you are drawn not to abstract problems but to the concrete needs of real people, and you find energy in patiently walking alongside someone until they can stand on their own. You care less about being recognized than about whether the work actually helped.',
  vocational_orientation:
    'Your calling centers on direct human care expressed through teaching and formation. You are happiest in roles where relationship is the medium of the work — where progress is measured in another person’s growth rather than in output. A secondary current runs toward building and organizing, which suggests you will eventually want to shape the structures that let care happen at scale, not only deliver it one to one.',
  primary_pathways: [
    'Special education or learning-support teaching',
    'Youth or community program coordination',
    'Counseling, social work, or pastoral care',
    'Vocational mentoring and workforce development',
  ],
  specific_considerations:
    'Your strong pull toward others can make it hard to protect your own limits. Watch for the pattern where you absorb responsibility that is not yours to carry. The same sensitivity that makes you good at this work will exhaust you if you do not build rhythms of rest and clear boundaries. Naming what is yours to fix — and what is not — will be a lifelong practice.',
  next_steps: [
    'Spend a half-day shadowing someone whose daily work is direct care — a teacher, counselor, or program lead — and notice which moments make you lean in.',
    'Volunteer for six weeks in a mentoring or tutoring role to test the calling against reality before committing to a degree path.',
    'Talk with two people who do this work about what sustains them and what burns them out, and write down what you hear.',
    'Identify one nearby program or credential (a certificate, an associate track, an apprenticeship) and find out its first concrete entry requirement.',
    'Begin a short weekly reflection on where you felt most useful that week — patterns here will sharpen your direction.',
  ],
  ministry_integration:
    'For you, this work is not adjacent to ministry — it is ministry. Showing up faithfully for one person who feels unseen is a way of loving your neighbor that needs no separate justification. As you grow, consider how your care can also form others to care, multiplying the work beyond what your own hands can reach.',
  primary_domain: 'caring for and forming people',
  mode_of_work: 'hands-on, relational',
  secondary_orientation: 'building and organizing',
  matched_pathway_blurbs: [
    {
      name: 'Teaching & Formation',
      description:
        'Helping others learn, grow, and become more fully themselves — in classrooms, programs, or one-to-one.',
      ministry_connection:
        'Forming people is sacred work; you participate in another person’s becoming.',
      career_pathways: ['Special education teacher', 'Program mentor', 'Adult education instructor'],
    },
    {
      name: 'Healing & Care',
      description:
        'Walking with people through difficulty and helping restore what has been broken or lost.',
      ministry_connection: 'Care for the hurting is among the oldest expressions of love in action.',
      career_pathways: ['Counselor', 'Social worker', 'Community health worker'],
    },
  ],
  created_at: '2026-06-17T00:00:00Z',
};

export default function DevPreviewScreen() {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (!__DEV__) {
      return;
    }

    useAssessmentStore.setState({
      results: SAMPLE_RESULTS,
      assessmentId: 'dev-preview',
      guestToken: 'dev-preview',
      tier: 'free',
      upgradeMessage:
        'Create a free account to save your results and unlock your full guided pathway.',
      resultsError: null,
      status: 'completed',
    });
    setReady(true);
  }, []);

  if (!__DEV__) {
    return <Redirect href="/" />;
  }

  if (!ready) {
    return null;
  }

  return <Redirect href="/(assessment)/results" />;
}
