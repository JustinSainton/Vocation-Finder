import { Tabs } from 'expo-router';
import { useTheme } from '../../hooks/useTheme';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';

export default function DashboardLayout() {
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarStyle: {
          backgroundColor: colors.background,
          borderTopColor: colors.divider,
          borderTopWidth: 1,
        },
        tabBarActiveTintColor: colors.text,
        tabBarInactiveTintColor: colors.accent,
        tabBarLabelStyle: {
          fontFamily: 'Satoshi-Medium',
          fontSize: 11,
          letterSpacing: 0.3,
        },
        lazy: true,
      }}
    >
      <Tabs.Screen
        name="index"
        options={{ title: 'Home' }}
      />
      <Tabs.Screen
        name="assessments"
        options={{ title: 'Assessments' }}
      />
      <Tabs.Screen
        name="jobs"
        options={{
          title: 'Jobs',
          href: isEnabled('job_discovery') ? '/(dashboard)/jobs' : null,
        }}
      />
      <Tabs.Screen
        name="career"
        options={{
          title: 'Career',
          href: isEnabled('career_profile') ? '/(dashboard)/career' : null,
        }}
      />
      <Tabs.Screen
        name="resumes"
        options={{
          title: 'Resumes',
          href: isEnabled('resume_builder') ? '/(dashboard)/resumes' : null,
        }}
      />
      <Tabs.Screen
        name="applications"
        options={{
          title: 'Applications',
          href: isEnabled('application_tracking') ? '/(dashboard)/applications' : null,
        }}
      />
      <Tabs.Screen
        name="job-detail"
        options={{ href: null }}
      />
      <Tabs.Screen
        name="resume-coach"
        options={{ href: null }}
      />
      <Tabs.Screen
        name="profile"
        options={{ title: 'Profile' }}
      />
    </Tabs>
  );
}
