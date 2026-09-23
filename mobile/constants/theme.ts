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
  text: '#1C1917',
  textSecondary: '#44403C',
  accent: '#3A3AA0',
  divider: '#D6D3D1',
  buttonBg: '#1C1917',
  buttonText: '#FAFAF7',
  surface: '#F0EFE9',
  surfaceElevated: '#EBEAE3',
};

export const darkColors = {
  background: '#14120F',
  text: '#FAFAF7',
  textSecondary: '#A8A29E',
  accent: '#8F8FF5',
  divider: '#3A3530',
  buttonBg: '#FAFAF7',
  buttonText: '#14120F',
  surface: '#1F1C18',
  surfaceElevated: '#2A2622',
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
