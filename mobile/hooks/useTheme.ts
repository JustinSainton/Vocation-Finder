import { useEffect } from 'react';
import { useColorScheme } from 'react-native';
import {
  palettes,
  resolveThemeName,
  setActiveTheme,
  type ThemeName,
} from '../constants/theme';
import { useThemeStore } from '../stores/themeStore';

export function useTheme(): {
  theme: ThemeName;
  isDark: boolean;
  colors: (typeof palettes)['light'];
  preference: 'system' | 'light' | 'dark';
  setPreference: (preference: 'system' | 'light' | 'dark') => void;
} {
  const preference = useThemeStore((s) => s.preference);
  const setPreference = useThemeStore((s) => s.setPreference);
  const systemScheme = useColorScheme();

  const theme = resolveThemeName(preference, systemScheme);

  useEffect(() => {
    setActiveTheme(theme);
  }, [theme]);

  return {
    theme,
    isDark: theme === 'dark',
    colors: palettes[theme],
    preference,
    setPreference,
  };
}
