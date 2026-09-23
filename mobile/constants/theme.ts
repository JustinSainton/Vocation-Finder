import { Appearance, ColorSchemeName } from 'react-native';

export type ThemePreference = 'system' | 'light' | 'dark';
export type ThemeName = 'light' | 'dark';

/*
 * Derived from DESIGN.md, not chosen here. `DesignSystemTest` parses that
 * document and asserts these values, so a token edited in one place and not
 * the other fails the suite rather than shipping two products.
 *
 * What this replaced is worth recording: light `accent` was #A8A29E, the warm
 * stone DESIGN.md says indigo replaced; the dark canvas was #0F1216, a cool
 * blue-black the document explicitly rules out; and dark `accent` was
 * #94A3B8, a second accent hue where the document allows exactly one.
 */
export const lightColors = {
  background: '#FAFAF7',
  surfaceSoft: '#F5F5F0',
  surfaceCard: '#F0EFE9',
  surfaceStrong: '#EBEAE3',
  text: '#1C1917',
  textStrong: '#292524',
  textSecondary: '#44403C',
  muted: '#78716C',
  mutedSoft: '#A8A29E',
  accent: '#3A3AA0',
  accentStrong: '#2A2A7A',
  accentWash: '#EEEEF8',
  divider: '#D6D3D1',
  dividerSoft: '#E7E5E4',
  buttonBg: '#1C1917',
  buttonText: '#FAFAF7',
  success: '#2F7A55',
  warning: '#A8761F',
  error: '#A03530',
};

export const darkColors = {
  background: '#14120F',
  surfaceSoft: '#1F1C18',
  surfaceCard: '#1F1C18',
  surfaceStrong: '#2A2622',
  text: '#FAFAF7',
  textStrong: '#FAFAF7',
  textSecondary: '#A8A29E',
  muted: '#A8A29E',
  mutedSoft: '#78716C',
  accent: '#8F8FF5',
  accentStrong: '#8F8FF5',
  accentWash: '#2A2622',
  divider: '#3A3530',
  dividerSoft: '#3A3530',
  buttonBg: '#FAFAF7',
  buttonText: '#14120F',
  success: '#2F7A55',
  warning: '#A8761F',
  error: '#A03530',
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
    mono: 'IBMPlexMono-Regular',
    monoMedium: 'IBMPlexMono-Medium',
  },
  sizes: {
    displayXl: 64,
    displayLg: 48,
    displayMd: 36,
    displaySm: 28,
    titleLg: 22,
    titleMd: 18,
    titleSm: 16,
    body: 18,
    bodyLarge: 20,
    heading: 28,
    headingLarge: 36,
    small: 14,
    caption: 12,
    eyebrow: 12,
    meta: 12,
  },
  lineHeight: {
    body: 1.7,
    heading: 1.3,
  },
  tracking: {
    eyebrow: 1.5,
    meta: 0.5,
  },
};

export const spacing = {
  xxs: 4,
  xs: 8,
  sm: 12,
  md: 16,
  lg: 24,
  xl: 32,
  xxl: 48,
  section: 96,
  hero: 128,
};

export const radius = {
  none: 0,
  xs: 2,
  sm: 4,
  md: 6,
  lg: 8,
  pill: 9999,
};

export const layout = {
  maxWidth: 640,
  touchTarget: 48,
};
