import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { typography } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';

/**
 * The tab bar is capped at five places: Home, Assessments, Jobs, Career,
 * Profile. Everything else in this group stays a resolvable route but is
 * hidden from the bar via `href: null`. Jobs and Career keep their
 * feature-flag gating exactly; the flag decides whether the tab appears.
 */
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
        tabBarInactiveTintColor: colors.muted,
        tabBarLabelStyle: {
          fontFamily: typography.fontFamily.sansMedium,
          fontSize: 14,
          letterSpacing: 0,
        },
        lazy: true,
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons
              name={focused ? 'home' : 'home-outline'}
              size={size}
              color={color}
            />
          ),
        }}
      />
      <Tabs.Screen
        name="assessments"
        options={{
          title: 'Assessments',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons
              name={focused ? 'document-text' : 'document-text-outline'}
              size={size}
              color={color}
            />
          ),
        }}
      />
      <Tabs.Screen
        name="jobs"
        options={{
          title: 'Jobs',
          href: isEnabled('job_discovery') ? '/(dashboard)/jobs' : null,
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons
              name={focused ? 'briefcase' : 'briefcase-outline'}
              size={size}
              color={color}
            />
          ),
        }}
      />
      <Tabs.Screen
        name="career"
        options={{
          title: 'Career',
          href: isEnabled('career_profile') ? '/(dashboard)/career' : null,
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons
              name={focused ? 'compass' : 'compass-outline'}
              size={size}
              color={color}
            />
          ),
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profile',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons
              name={focused ? 'person' : 'person-outline'}
              size={size}
              color={color}
            />
          ),
        }}
      />

      {/* Routes below resolve inside this navigator but never appear in the bar. */}
      <Tabs.Screen name="coach" options={{ href: null }} />
      <Tabs.Screen name="resumes" options={{ href: null }} />
      <Tabs.Screen name="applications" options={{ href: null }} />
      <Tabs.Screen name="job-detail" options={{ href: null }} />
      <Tabs.Screen name="resume-coach" options={{ href: null }} />
      <Tabs.Screen name="career-coach" options={{ href: null }} />
    </Tabs>
  );
}
