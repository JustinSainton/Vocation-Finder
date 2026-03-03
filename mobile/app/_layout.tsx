import { useEffect } from 'react';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import * as SplashScreen from 'expo-splash-screen';
import { useFonts } from 'expo-font';
import {
  Literata_400Regular,
  Literata_500Medium,
  Literata_600SemiBold,
  Literata_700Bold,
  Literata_400Regular_Italic,
} from '@expo-google-fonts/literata';
import { colors } from '../constants/theme';
import { useAuthStore } from '../stores/authStore';

SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const checkAuth = useAuthStore((s) => s.checkAuth);

  const [fontsLoaded] = useFonts({
    'Literata-Regular': Literata_400Regular,
    'Literata-Medium': Literata_500Medium,
    'Literata-SemiBold': Literata_600SemiBold,
    'Literata-Bold': Literata_700Bold,
    'Literata-Italic': Literata_400Regular_Italic,
    'Satoshi-Regular': require('../assets/fonts/Satoshi-Regular.ttf'),
    'Satoshi-Medium': require('../assets/fonts/Satoshi-Medium.ttf'),
    'Satoshi-Bold': require('../assets/fonts/Satoshi-Bold.ttf'),
  });

  // Check persisted token on mount
  useEffect(() => {
    checkAuth();
  }, []);

  useEffect(() => {
    if (fontsLoaded) {
      SplashScreen.hideAsync().catch(() => null);
    }
  }, [fontsLoaded]);

  if (!fontsLoaded) {
    return null;
  }

  return (
    <>
      <StatusBar style="dark" />
      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: colors.background },
          animation: 'fade',
        }}
      />
    </>
  );
}
