import { Appearance, ColorSchemeName } from 'react-native';

export type ThemePreference = 'system' | 'light' | 'dark';
export type ThemeName = 'light' | 'dark';

export const lightColors = {
  background: '#FAFAF7',
  text: '#1C1917',
  textSecondary: '#78716C',
  accent: '#A8A29E',
  divider: '#E7E5E4',
  buttonBg: '#1C1917',
  buttonText: '#FAFAF7',
};

export const darkColors = {
  background: '#0F1216',
  text: '#E7E5E4',
  textSecondary: '#A8A29E',
  accent: '#94A3B8',
  divider: '#262E3A',
  buttonBg: '#E7E5E4',
  buttonText: '#0F1216',
};

export const palettes: Record<ThemeName, typeof lightColors> = {
  light: lightColors,
  dark: darkColors,
};

let activeTheme: ThemeName = Appearance.getColorScheme() === 'dark' ? 'dark' : 'light';

export function resolveThemeName(
  preference: ThemePreference,
  systemScheme: ColorSchemeName
): ThemeName {
  if (preference === 'light' || preference === 'dark') {
    return preference;
  }

  return systemScheme === 'dark' ? 'dark' : 'light';
}

export function setActiveTheme(theme: ThemeName): void {
  activeTheme = theme;
  Object.assign(colors, palettes[theme]);
}

export function getActiveTheme(): ThemeName {
  return activeTheme;
}

export const colors = {
  ...palettes.light,
};

setActiveTheme(activeTheme);

export const typography = {
  fontFamily: {
    serif: 'Literata-Regular',
    serifMedium: 'Literata-Medium',
    serifSemiBold: 'Literata-SemiBold',
    serifBold: 'Literata-Bold',
    serifItalic: 'Literata-Italic',
    sans: 'Satoshi-Regular',
    sansMedium: 'Satoshi-Medium',
    sansBold: 'Satoshi-Bold',
  },
  sizes: {
    body: 18,
    bodyLarge: 20,
    heading: 28,
    headingLarge: 36,
    small: 14,
    caption: 12,
  },
  lineHeight: {
    body: 1.7,
    heading: 1.3,
  },
};

export const spacing = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  xxl: 48,
  section: 64,
};

export const layout = {
  maxWidth: 640,
  touchTarget: 48,
};
