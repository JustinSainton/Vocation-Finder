# Mobile App Performance Optimization Plan

**Based on:** React Native Best Practices (Callstack)
**Applied to:** Vocational Discernment Assessment Platform — Expo React Native App
**Date:** 2026-03-02

---

## Executive Summary

This document applies Callstack's React Native performance optimization guidelines to the Vocational Discernment Assessment Platform's Expo mobile app. The app has several performance-critical areas: the AudioOrb animation (60fps required), audio recording/playback memory management, the 20-question assessment flow with autosave, and offline persistence via Zustand + AsyncStorage. Each section below is prioritized by impact level (CRITICAL, HIGH, MEDIUM) following the skill's rating system.

---

## 1. FPS & Re-renders [CRITICAL]

### 1.1 AudioOrb Animation — 60fps Target

The AudioOrb is the centerpiece of the conversation interface. It must animate at a locked 60fps even while the JS thread handles audio recording, API calls (transcription, TTS, Claude), and state updates.

**Requirements:**
- Pulsing animation responding to audio input levels
- Smooth transitions between states (listening, speaking, idle)
- Haptic feedback synchronized with state changes
- Must not drop frames during network requests or audio processing

**Implementation Pattern (Reanimated on UI Thread):**

```tsx
// components/assessment/AudioOrb.tsx
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withTiming,
  withRepeat,
  withSequence,
  interpolate,
  Easing,
} from 'react-native-reanimated';
import { scheduleOnRN } from 'react-native-worklets';

type OrbState = 'idle' | 'listening' | 'speaking' | 'processing';

const AudioOrb = ({ state, audioLevel }: { state: OrbState; audioLevel: number }) => {
  const scale = useSharedValue(1);
  const opacity = useSharedValue(0.6);
  const audioLevelShared = useSharedValue(0);

  // Update audio level on UI thread — no JS thread blocking
  useEffect(() => {
    audioLevelShared.value = audioLevel;
  }, [audioLevel]);

  // State transitions with smooth animations
  useEffect(() => {
    switch (state) {
      case 'listening':
        // Responsive to voice input — scale driven by audioLevelShared
        opacity.value = withTiming(1, { duration: 300 });
        break;
      case 'speaking':
        // Gentle pulsing while AI speaks
        scale.value = withRepeat(
          withSequence(
            withTiming(1.05, { duration: 1200, easing: Easing.inOut(Easing.ease) }),
            withTiming(0.95, { duration: 1200, easing: Easing.inOut(Easing.ease) }),
          ),
          -1, // infinite
          true,
        );
        opacity.value = withTiming(0.8, { duration: 300 });
        break;
      case 'processing':
        scale.value = withTiming(0.9, { duration: 500 });
        opacity.value = withTiming(0.4, { duration: 500 });
        break;
      case 'idle':
      default:
        scale.value = withTiming(1, { duration: 500 });
        opacity.value = withTiming(0.6, { duration: 500 });
        break;
    }
  }, [state]);

  // All style computation runs on UI thread
  const animatedStyle = useAnimatedStyle(() => {
    const dynamicScale = state === 'listening'
      ? interpolate(audioLevelShared.value, [0, 1], [0.95, 1.15])
      : scale.value;

    return {
      transform: [{ scale: dynamicScale }],
      opacity: opacity.value,
    };
  });

  return (
    <Animated.View style={[styles.orb, animatedStyle]} />
  );
};
```

**Key Rules from Skill (`js-animations-reanimated.md`):**
- All animation logic runs on UI thread via `useAnimatedStyle` — never blocked by JS work
- Audio level updates flow through `useSharedValue`, not `useState`
- No heavy computation inside `useAnimatedStyle` worklets — keep them fast
- Use `scheduleOnRN` (not `runOnJS` which is Reanimated v3) to call JS functions from worklets when needed (e.g., triggering haptics)
- Reanimated 4 requires New Architecture (Fabric + TurboModules) — Expo SDK 52+ supports this

**Haptic Integration with Worklets:**

```tsx
import { scheduleOnRN } from 'react-native-worklets';
import * as Haptics from 'expo-haptics';

const triggerHaptic = (style: Haptics.ImpactFeedbackStyle) => {
  Haptics.impactAsync(style);
};

// Inside a worklet callback when state changes:
const onStateChange = (newState: OrbState) => {
  'worklet';
  if (newState === 'listening') {
    scheduleOnRN(triggerHaptic, Haptics.ImpactFeedbackStyle.Light);
  } else if (newState === 'processing') {
    scheduleOnRN(triggerHaptic, Haptics.ImpactFeedbackStyle.Medium);
  }
};
```

### 1.2 Assessment Flow Screen Transitions

When navigating between the 20 question screens, heavy work must be deferred until animations complete.

**Pattern (InteractionManager):**

```tsx
// app/(assessment)/written.tsx
import { InteractionManager } from 'react-native';

const WrittenAssessmentScreen = () => {
  const [currentQuestion, setCurrentQuestion] = useState(0);

  useEffect(() => {
    // Defer autosave sync, API calls, and analytics until
    // screen transition animation has completed
    const task = InteractionManager.runAfterInteractions(() => {
      syncPendingAnswers();
      prefetchNextQuestion(currentQuestion + 1);
    });

    return () => task.cancel();
  }, [currentQuestion]);

  return <QuestionCard question={questions[currentQuestion]} />;
};
```

### 1.3 Zustand Selector Optimization

The plan specifies Zustand for state management. Every component consuming store state must use selectors to prevent unnecessary re-renders.

**Incorrect (triggers re-render on ANY store change):**

```tsx
// BAD: Subscribes to entire store
const { currentQuestion, answers, mode } = useAssessmentStore();
```

**Correct (only re-renders when subscribed slice changes):**

```tsx
// GOOD: Granular selectors
const currentQuestion = useAssessmentStore((s) => s.currentQuestion);
const currentAnswer = useAssessmentStore((s) => s.answers[s.currentQuestion]);
const saveAnswer = useAssessmentStore((s) => s.saveAnswer);
```

**Store Design Pattern:**

```tsx
// stores/assessmentStore.ts
import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';

interface AssessmentState {
  mode: 'conversation' | 'written';
  status: 'in_progress' | 'analyzing' | 'completed' | 'abandoned';
  currentQuestion: number;
  answers: Record<number, string>;
  assessmentId: string | null;

  // Actions
  setAnswer: (questionIndex: number, text: string) => void;
  nextQuestion: () => void;
  previousQuestion: () => void;
  setMode: (mode: 'conversation' | 'written') => void;
}

const useAssessmentStore = create<AssessmentState>()(
  persist(
    (set, get) => ({
      mode: 'written',
      status: 'in_progress',
      currentQuestion: 0,
      answers: {},
      assessmentId: null,

      setAnswer: (questionIndex, text) =>
        set((state) => ({
          answers: { ...state.answers, [questionIndex]: text },
        })),

      nextQuestion: () =>
        set((state) => ({
          currentQuestion: Math.min(state.currentQuestion + 1, 19),
        })),

      previousQuestion: () =>
        set((state) => ({
          currentQuestion: Math.max(state.currentQuestion - 1, 0),
        })),

      setMode: (mode) => set({ mode }),
    }),
    {
      name: 'assessment-storage',
      storage: createJSONStorage(() => AsyncStorage),
      // Only persist essential data, not derived state
      partialize: (state) => ({
        mode: state.mode,
        status: state.status,
        currentQuestion: state.currentQuestion,
        answers: state.answers,
        assessmentId: state.assessmentId,
      }),
    },
  ),
);
```

### 1.4 React Compiler — Automatic Memoization

Since the project uses Expo SDK 52+, enable React Compiler to eliminate manual `memo`/`useMemo`/`useCallback` boilerplate across the entire mobile app.

**Setup:**

```bash
npx expo install babel-plugin-react-compiler@beta react-compiler-runtime@beta
```

```json
// app.json
{
  "expo": {
    "experiments": {
      "reactCompiler": true
    }
  }
}
```

**Before enabling**, run the health check:

```bash
npx react-compiler-healthcheck@latest
```

**ESLint integration:**

```bash
npx expo install eslint-plugin-react-compiler -- -D
```

**Impact:** Eliminates the need for manual `React.memo` on `QuestionCard`, `AudioOrb`, `ProgressIndicator`, `ResultsDocument`, and all UI components. The compiler automatically caches JSX and callbacks. Expected 4-5% TTI improvement and significant reduction in cascading re-renders during the assessment flow.

---

## 2. Bundle Size Optimization [CRITICAL]

### 2.1 Avoid Barrel Exports

The planned project structure has `components/ui/` and `components/assessment/` directories. Do NOT create `index.ts` barrel files in these directories.

**Incorrect:**

```tsx
// components/ui/index.ts — DO NOT CREATE THIS
export { Button } from './Button';
export { TextInput } from './TextInput';
export { Typography } from './Typography';

// Usage — loads ALL components even if you only need Button
import { Button } from '@/components/ui';
```

**Correct:**

```tsx
// Direct imports only
import Button from '@/components/ui/Button';
import { QuestionCard } from '@/components/assessment/QuestionCard';
import { AudioOrb } from '@/components/assessment/AudioOrb';
```

**Enforcement:**

```bash
npm install -D eslint-plugin-no-barrel-files
```

```javascript
// eslint.config.js
import noBarrelFiles from 'eslint-plugin-no-barrel-files';

export default [
  {
    plugins: { 'no-barrel-files': noBarrelFiles },
    rules: {
      'no-barrel-files/no-barrel-files': 'error',
    },
  },
];
```

### 2.2 Enable Tree Shaking (Expo SDK 52+)

```javascript
// metro.config.js
const { getDefaultConfig } = require('expo/metro-config');
const config = getDefaultConfig(__dirname);

config.transformer.getTransformOptions = async () => ({
  transform: {
    experimentalImportSupport: true,
  },
});

module.exports = config;
```

```bash
# .env
EXPO_UNSTABLE_METRO_OPTIMIZE_GRAPH=1
EXPO_UNSTABLE_TREE_SHAKING=1
```

**Expected improvement:** 10-15% bundle size reduction in production builds. This automatically handles barrel export optimization as well, removing unused exports from any barrel files in `node_modules`.

### 2.3 Bundle Analysis Workflow

Before every release, analyze the JS bundle:

```bash
# Generate bundle with source maps
EXPO_UNSTABLE_ATLAS=true npx expo export --platform ios
npx expo-atlas
```

**Red flags to watch for:**
- Entire `date-fns` or `lodash` imported through barrels
- Unused Intl polyfills (Hermes has native `Intl.DateTimeFormat` and `Intl.NumberFormat`)
- Large audio processing libraries pulled in their entirety
- Duplicate package versions in `node_modules`

### 2.4 Library Size Evaluation

Before adding any dependency, check its size:

| Planned Dependency | Estimated Size (gzipped) | Verdict |
|---|---|---|
| `zustand` | ~2 KB | Small, approved |
| `react-native-reanimated` | Native module, ~0 JS | Required for AudioOrb |
| `expo-audio` | Native module | Required for recording |
| `@react-native-async-storage/async-storage` | Native module | Required for persistence |
| `nativewind` | ~15-20 KB | Acceptable for styling consistency |
| `expo-haptics` | Native module | Required for haptic feedback |

**Caution dependencies to evaluate:**
- If using `date-fns` for any date formatting, prefer `dayjs` (~3 KB gzipped vs ~75 KB)
- If needing crypto for any auth tokens, use `react-native-quick-crypto` (native C++) over `crypto-js` (JS, slow)
- Avoid `moment.js` entirely (~70 KB)

### 2.5 R8 Code Shrinking (Android)

When building for Android release, enable R8:

```groovy
// android/app/build.gradle (after npx expo prebuild)
def enableProguardInReleaseBuilds = true

android {
    buildTypes {
        release {
            minifyEnabled true
            shrinkResources true
            proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'
        }
    }
}
```

**Expected impact:** ~30% reduction in native code size. For a typical React Native app, this can save 2-3 MB.

Use an Expo config plugin to make this persistent across `expo prebuild`:

```typescript
// plugins/withR8.ts — Expo config plugin
const { withAppBuildGradle } = require('expo/config-plugins');

module.exports = function withR8(config) {
  return withAppBuildGradle(config, (config) => {
    config.modResults.contents = config.modResults.contents.replace(
      'def enableProguardInReleaseBuilds = false',
      'def enableProguardInReleaseBuilds = true',
    );
    return config;
  });
};
```

### 2.6 Hermes Memory Mapping (Android)

For Expo SDK 52+ (which uses React Native 0.76+), check whether bundle compression is already disabled by default. If using RN < 0.79, apply manually:

```groovy
// android/app/build.gradle
android {
    androidResources {
        noCompress += ["bundle"]
    }
}
```

**Tradeoff:** +8% install size, -16% TTI. For a contemplative assessment app where first-launch experience matters deeply, the TTI improvement is worth the size tradeoff.

---

## 3. TTI (Time to Interactive) Optimization [HIGH]

### 3.1 Target Metrics

| Metric | Target | Rationale |
|---|---|---|
| Cold start TTI | < 2 seconds | Users should reach the Landing/Threshold screen quickly |
| JS Bundle Load | < 500ms | Hermes bytecode with mmap optimization |
| Screen Interactive | < 1.5s after bundle load | First meaningful paint of the threshold screen |

### 3.2 TTI Measurement Setup

```bash
npm install react-native-performance
```

```tsx
// app/_layout.tsx
import performance from 'react-native-performance';

export default function RootLayout() {
  useEffect(() => {
    performance.mark('screenInteractive');
  }, []);

  return (
    <Stack>
      <Stack.Screen name="(auth)" />
      <Stack.Screen name="(assessment)" />
      <Stack.Screen name="(dashboard)" />
    </Stack>
  );
}
```

### 3.3 Defer Non-Critical Work on Startup

The app should show the Landing/Threshold screen as fast as possible. Defer everything else:

```tsx
// app/_layout.tsx
import { InteractionManager } from 'react-native';

export default function RootLayout() {
  useEffect(() => {
    const task = InteractionManager.runAfterInteractions(() => {
      // Defer these until after the first screen is interactive:
      // - Auth token validation
      // - Font preloading (if not using SplashScreen)
      // - Analytics initialization
      // - Push notification registration
      // - Prefetching assessment questions from API
      validateAuthToken();
      initializeAnalytics();
      registerPushNotifications();
    });

    return () => task.cancel();
  }, []);

  return <Stack />;
}
```

### 3.4 Custom Font Loading Strategy

The app uses custom serif + sans-serif fonts. Font loading blocks rendering. Use Expo's SplashScreen to hide the app until fonts are loaded:

```tsx
// app/_layout.tsx
import { useFonts } from 'expo-font';
import * as SplashScreen from 'expo-splash-screen';

SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const [fontsLoaded] = useFonts({
    'SourceSerifPro-Regular': require('../assets/fonts/SourceSerifPro-Regular.ttf'),
    'SourceSerifPro-SemiBold': require('../assets/fonts/SourceSerifPro-SemiBold.ttf'),
    'Inter-Regular': require('../assets/fonts/Inter-Regular.ttf'),
    'Inter-Medium': require('../assets/fonts/Inter-Medium.ttf'),
  });

  useEffect(() => {
    if (fontsLoaded) {
      SplashScreen.hideAsync();
    }
  }, [fontsLoaded]);

  if (!fontsLoaded) return null;

  return <Stack />;
}
```

This prevents a flash of unstyled text and ensures the contemplative design is intact from the first frame.

---

## 4. Memory Management for Audio Recording [HIGH]

### 4.1 Audio Recording Lifecycle

The conversation mode records audio, uploads it for transcription, receives TTS audio, and plays it back. This creates significant memory pressure if not managed correctly.

**Critical Memory Leak Patterns to Avoid:**

```tsx
// hooks/useAudioConversation.ts

import { useEffect, useRef } from 'react';
import { Audio } from 'expo-av';

const useAudioConversation = () => {
  const recordingRef = useRef<Audio.Recording | null>(null);
  const soundRef = useRef<Audio.Sound | null>(null);

  // CRITICAL: Clean up recording on unmount
  useEffect(() => {
    return () => {
      // Stop and unload recording
      if (recordingRef.current) {
        recordingRef.current.stopAndUnloadAsync().catch(console.warn);
        recordingRef.current = null;
      }
      // Unload playback sound
      if (soundRef.current) {
        soundRef.current.unloadAsync().catch(console.warn);
        soundRef.current = null;
      }
    };
  }, []);

  const startRecording = async () => {
    // Unload previous recording before creating new one
    if (recordingRef.current) {
      await recordingRef.current.stopAndUnloadAsync();
      recordingRef.current = null;
    }

    const { recording } = await Audio.Recording.createAsync(
      Audio.RecordingOptionsPresets.HIGH_QUALITY,
    );
    recordingRef.current = recording;
  };

  const stopRecording = async () => {
    if (!recordingRef.current) return null;

    await recordingRef.current.stopAndUnloadAsync();
    const uri = recordingRef.current.getURI();
    recordingRef.current = null; // Release reference
    return uri;
  };

  const playResponse = async (audioUri: string) => {
    // Unload previous sound before loading new one
    if (soundRef.current) {
      await soundRef.current.unloadAsync();
      soundRef.current = null;
    }

    const { sound } = await Audio.Sound.createAsync({ uri: audioUri });
    soundRef.current = sound;

    // Auto-cleanup when playback finishes
    sound.setOnPlaybackStatusUpdate((status) => {
      if (status.isLoaded && status.didJustFinish) {
        sound.unloadAsync();
        soundRef.current = null;
      }
    });

    await sound.playAsync();
  };

  return { startRecording, stopRecording, playResponse };
};
```

### 4.2 Memory Leak Prevention Checklist

From `js-memory-leaks.md`, these are the patterns to enforce across the mobile app:

| Pattern | Risk Area | Prevention |
|---|---|---|
| Event listeners not cleaned up | Audio status callbacks, network listeners | Always return cleanup function from `useEffect` |
| Timers not cleared | Autosave debounce, animation timers | Clear intervals/timeouts in cleanup |
| Closures capturing large objects | Audio buffers captured in callbacks | Extract only needed values from closures |
| Growing global arrays | Conversation turn history accumulation | Cap array size, remove old entries |

**Audio-specific memory concerns:**

```tsx
// BAD: Accumulating audio buffers in memory
const conversationHistory = useRef<AudioBuffer[]>([]);
// Each recording adds to this, never releasing old buffers

// GOOD: Upload immediately, keep only metadata
const conversationTurns = useRef<{ timestamp: number; transcription: string }[]>([]);
// Audio files uploaded to API and local URI released
```

### 4.3 Navigation Memory — Screen Cleanup

When users navigate between assessment screens, previous screen resources must be released:

```tsx
// Expo Router handles screen lifecycle, but be explicit about cleanup
import { useFocusEffect } from 'expo-router';

const ConversationScreen = () => {
  useFocusEffect(
    useCallback(() => {
      // Screen focused — start audio session
      Audio.setAudioModeAsync({
        allowsRecordingIOS: true,
        playsInSilentModeIOS: true,
      });

      return () => {
        // Screen unfocused — release audio session
        Audio.setAudioModeAsync({
          allowsRecordingIOS: false,
        });
      };
    }, []),
  );
};
```

---

## 5. List Rendering [MEDIUM]

### 5.1 Assessment Results Document

The Results/Vocational Articulation page (`results.tsx`) displays a scrollable letter-style document. This is NOT a dynamic list of items — it is a single long document with sections. A `ScrollView` is appropriate here because:
- Content is static once loaded (not hundreds of items)
- The document is a single render, not repeated items
- The design spec explicitly says "scrollable document"

```tsx
// app/(assessment)/results.tsx
import { ScrollView } from 'react-native';

const ResultsScreen = ({ profile }: { profile: VocationalProfile }) => {
  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: '#FAFAF7' }}
      contentContainerStyle={{ paddingHorizontal: 24, paddingVertical: 48, maxWidth: 680 }}
    >
      <Typography variant="heading">{profile.openingSynthesis}</Typography>
      <Typography variant="body">{profile.vocationalOrientation}</Typography>
      {/* Additional sections... */}
    </ScrollView>
  );
};
```

**No FlashList/FlatList needed** for this use case. Per the skill's decision matrix: "< 20 static items: ScrollView OK."

### 5.2 Dashboard — Past Assessments List

The dashboard (`(dashboard)/index.tsx`) may show a list of past assessments. If this list could grow beyond 20 items (unlikely for most users, but possible for power users or organization members), use FlashList:

```tsx
import { FlashList } from '@shopify/flash-list';

const DashboardScreen = () => {
  const assessments = useAssessments(); // API hook

  if (assessments.length < 20) {
    // Simple ScrollView for small lists
    return (
      <ScrollView>
        {assessments.map((a) => (
          <AssessmentSummaryCard key={a.id} assessment={a} />
        ))}
      </ScrollView>
    );
  }

  // FlashList for larger lists (org admin viewing all members' assessments)
  return (
    <FlashList
      data={assessments}
      renderItem={({ item }) => <AssessmentSummaryCard assessment={item} />}
      estimatedItemSize={120}
      keyExtractor={(item) => item.id}
    />
  );
};
```

### 5.3 Organization Admin — Member List

If the org admin dashboard shows hundreds of members, use FlashList with `getItemType` for mixed item types:

```tsx
<FlashList
  data={members}
  renderItem={({ item }) => <MemberRow member={item} />}
  estimatedItemSize={72}
  keyExtractor={(item) => item.id}
/>
```

---

## 6. Navigation Performance [HIGH]

### 6.1 Expo Router with Native Screens

Expo Router uses `react-native-screens` under the hood for native navigation. This gives native-quality screen transitions. Verify it is enabled:

```tsx
// app/_layout.tsx
import { Stack } from 'expo-router';

export default function RootLayout() {
  return (
    <Stack
      screenOptions={{
        headerShown: false, // Custom headers per the design spec
        animation: 'fade', // Subtle transitions matching contemplative feel
      }}
    >
      <Stack.Screen name="(auth)" />
      <Stack.Screen name="(assessment)" />
      <Stack.Screen name="(dashboard)" />
    </Stack>
  );
}
```

### 6.2 Assessment Flow Navigation

The 20-question flow should NOT create 20 separate screens in the navigation stack. Instead, use a single screen with internal state transition:

```tsx
// app/(assessment)/written.tsx
// ONE screen, internal question navigation — avoids 20-deep nav stack
const WrittenAssessmentScreen = () => {
  const currentQuestion = useAssessmentStore((s) => s.currentQuestion);
  const nextQuestion = useAssessmentStore((s) => s.nextQuestion);

  return (
    <View style={styles.container}>
      <ProgressIndicator current={currentQuestion + 1} total={20} />
      <QuestionCard
        key={currentQuestion} // Force remount for clean transitions
        question={questions[currentQuestion]}
      />
      <Button title="Continue" onPress={nextQuestion} />
    </View>
  );
};
```

**Why single-screen:** 20 stacked screens would consume memory (each screen stays mounted in the stack). The design spec already calls for forward-only navigation with an optional subtle "Back" link, which maps to internal state rather than stack navigation.

### 6.3 Navigation Performance Rules

- Use `animation: 'fade'` or `animation: 'slide_from_right'` — both run on native thread
- Defer heavy work until after transition completes (InteractionManager)
- Do not pass large objects as route params — use Zustand store for shared assessment state
- Lazy-load screens that are not immediately needed

---

## 7. Offline Persistence Patterns [HIGH]

### 7.1 Written Mode — Full Offline Support

The plan specifies that written assessment mode should work offline with sync when connected. Zustand + AsyncStorage persist middleware handles this.

**Architecture:**

```
User types answer
  -> Zustand store update (immediate, in-memory)
  -> AsyncStorage persist (automatic, debounced by Zustand)
  -> Sync queue (when online, batch upload to API)
```

**Sync Queue Implementation:**

```tsx
// stores/syncStore.ts
import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';

interface SyncAction {
  id: string;
  type: 'save_answer' | 'complete_assessment';
  payload: Record<string, unknown>;
  timestamp: number;
  retryCount: number;
}

interface SyncState {
  pendingActions: SyncAction[];
  isSyncing: boolean;

  addAction: (action: Omit<SyncAction, 'id' | 'timestamp' | 'retryCount'>) => void;
  processQueue: () => Promise<void>;
  removeAction: (id: string) => void;
}

const useSyncStore = create<SyncState>()(
  persist(
    (set, get) => ({
      pendingActions: [],
      isSyncing: false,

      addAction: (action) =>
        set((state) => ({
          pendingActions: [
            ...state.pendingActions,
            {
              ...action,
              id: crypto.randomUUID(),
              timestamp: Date.now(),
              retryCount: 0,
            },
          ],
        })),

      removeAction: (id) =>
        set((state) => ({
          pendingActions: state.pendingActions.filter((a) => a.id !== id),
        })),

      processQueue: async () => {
        const { pendingActions, isSyncing } = get();
        if (isSyncing || pendingActions.length === 0) return;

        const netState = await NetInfo.fetch();
        if (!netState.isConnected) return;

        set({ isSyncing: true });

        for (const action of pendingActions) {
          try {
            await syncActionToApi(action);
            get().removeAction(action.id);
          } catch (error) {
            // Increment retry, keep in queue
            set((state) => ({
              pendingActions: state.pendingActions.map((a) =>
                a.id === action.id
                  ? { ...a, retryCount: a.retryCount + 1 }
                  : a,
              ),
            }));
          }
        }

        set({ isSyncing: false });
      },
    }),
    {
      name: 'sync-queue',
      storage: createJSONStorage(() => AsyncStorage),
    },
  ),
);
```

### 7.2 Network Listener for Auto-Sync

```tsx
// In app/_layout.tsx or a dedicated provider
import NetInfo from '@react-native-community/netinfo';

useEffect(() => {
  const unsubscribe = NetInfo.addEventListener((state) => {
    if (state.isConnected) {
      useSyncStore.getState().processQueue();
    }
  });

  return () => unsubscribe();
}, []);
```

### 7.3 Conversation Mode — Online Required

Conversation mode requires STT (Whisper) + TTS + Claude API. It cannot work offline. Provide clear indication:

```tsx
// hooks/useNetworkStatus.ts
const useNetworkStatus = () => {
  const [isConnected, setIsConnected] = useState(true);

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      setIsConnected(state.isConnected ?? false);
    });
    return () => unsubscribe();
  }, []);

  return isConnected;
};

// app/(assessment)/index.tsx
const AssessmentLanding = () => {
  const isConnected = useNetworkStatus();

  return (
    <View>
      <Button
        title="Audio Conversation"
        disabled={!isConnected}
        onPress={() => router.push('/assessment/conversation')}
      />
      {!isConnected && (
        <Typography variant="caption" style={{ color: '#C4C0B6' }}>
          Audio conversation requires an internet connection.
          You can use the written assessment offline.
        </Typography>
      )}
      <Button
        title="Written Assessment"
        onPress={() => router.push('/assessment/written')}
      />
    </View>
  );
};
```

### 7.4 AsyncStorage Size Limits

AsyncStorage has a 6 MB limit on Android by default. For an assessment with 20 text answers + metadata, this is well within limits. However, do NOT store audio files in AsyncStorage. Audio recordings should be stored as temporary files and referenced by URI.

---

## 8. TextInput Optimization [HIGH]

### 8.1 Uncontrolled TextInput for Assessment Questions

The assessment flow has 20 text inputs with autosave. Using controlled TextInput with debounced `onChangeText` that updates Zustand state on every keystroke can cause flicker on lower-end devices.

**Pattern: Uncontrolled with Debounced Sync**

```tsx
// components/assessment/QuestionCard.tsx
import { useRef, useCallback } from 'react';
import { TextInput } from 'react-native';

const QuestionCard = ({ question, initialValue, onSave }) => {
  const debounceRef = useRef<NodeJS.Timeout>();

  const handleChange = useCallback((text: string) => {
    // Debounce autosave — don't save on every keystroke
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      onSave(text);
    }, 500); // 500ms debounce per the spec's autosave requirement
  }, [onSave]);

  // Clean up timer on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  return (
    <View>
      <Text style={styles.questionText}>{question.text}</Text>
      <TextInput
        defaultValue={initialValue}      // Uncontrolled — native owns state
        onChangeText={handleChange}
        multiline
        placeholder="Take your time. Write freely."
        style={styles.input}
        textAlignVertical="top"
      />
    </View>
  );
};
```

**Key from `js-uncontrolled-components.md`:**
- Use `defaultValue` instead of `value` to let native own the input state
- This eliminates the JS-to-native round-trip that causes flicker
- On New Architecture (Expo SDK 52+), controlled inputs work better but uncontrolled still has a performance edge
- The auto-expanding behavior works with both patterns via `multiline`

---

## 9. Platform-Specific Optimizations [MEDIUM]

### 9.1 Platform Code Elimination

Use `Platform.OS` directly (not via namespace import) so tree shaking removes platform-specific code:

```tsx
// CORRECT — tree-shakeable
import { Platform } from 'react-native';

if (Platform.OS === 'ios') {
  // This block removed from Android bundle
}
```

```tsx
// INCORRECT — NOT tree-shakeable
import * as RN from 'react-native';
if (RN.Platform.OS === 'ios') {
  // NOT removed from Android bundle
}
```

### 9.2 Native Intl — No Polyfills Needed

Hermes (used by Expo SDK 52+) has native support for `Intl.DateTimeFormat`, `Intl.NumberFormat`, and `Intl.Collator`. Do NOT add `@formatjs` polyfills unless you specifically need `Intl.PluralRules`, `Intl.RelativeTimeFormat`, or `Intl.DisplayNames`.

For this app (displaying assessment dates, formatting numbers), native Hermes Intl support is sufficient. This saves 430+ KB of bundle size.

---

## 10. FPS Measurement & Profiling Workflow [MEDIUM]

### 10.1 Development Profiling Checklist

Before each release milestone:

1. **Measure FPS during AudioOrb animation:**
   - Open Dev Menu > Perf Monitor
   - Verify UI thread stays at 60fps during orb animation
   - Verify JS thread FPS does not drop below 45fps during audio processing

2. **Measure FPS during question transitions:**
   - Navigate between questions
   - Verify no dropped frames during screen transitions

3. **Memory profiling during conversation:**
   - Open React Native DevTools (press `j` in Metro)
   - Go to Memory tab > "Allocation instrumentation on timeline"
   - Start a conversation session, go through 5-10 questions
   - Check for blue bars that never turn gray (leaks)
   - Verify audio buffers are released after upload

4. **Bundle size tracking:**
   ```bash
   EXPO_UNSTABLE_ATLAS=true npx expo export --platform ios
   npx expo-atlas
   ```
   - Track total bundle size per release
   - Flag any new dependency > 20 KB gzipped

### 10.2 Android-Specific Profiling

```bash
# Install Flashlight for automated benchmarking
curl https://get.flashlight.dev | bash

# Run with release build (not dev mode!)
npx expo start --no-dev --minify
flashlight measure
```

**Target scores:**
- Overall Flashlight score: > 60/100
- Average FPS during scroll: > 54fps
- Average FPS during AudioOrb: > 58fps

### 10.3 FPS Interpretation Guide

| FPS Range | Assessment | Action |
|---|---|---|
| 55-60 | Smooth | Acceptable |
| 45-55 | Slight stutter | Investigate JS thread blocking |
| 30-45 | Noticeable jank | Profile and optimize immediately |
| < 30 | Choppy | Critical — likely blocking the UI thread |

---

## 11. Implementation Priority Matrix

| Item | Impact | Effort | Priority | Phase |
|---|---|---|---|---|
| Zustand selectors (granular subscriptions) | CRITICAL | Low | P0 | Phase 3 (Written UI) |
| React Compiler setup | HIGH | Low | P0 | Phase 3 |
| Tree shaking + Metro config | CRITICAL | Low | P0 | Phase 3 |
| No barrel exports (ESLint rule) | CRITICAL | Low | P0 | Phase 3 |
| Uncontrolled TextInput pattern | HIGH | Low | P1 | Phase 3 |
| AudioOrb Reanimated implementation | CRITICAL | Medium | P0 | Phase 4 (Audio) |
| Audio memory cleanup lifecycle | HIGH | Medium | P0 | Phase 4 |
| InteractionManager for transitions | HIGH | Low | P1 | Phase 3 |
| TTI measurement setup | HIGH | Medium | P1 | Phase 3 |
| Offline sync queue | HIGH | Medium | P1 | Phase 3 |
| R8 code shrinking (Android) | HIGH | Low | P2 | Phase 3 |
| Hermes mmap (Android) | HIGH | Low | P2 | Phase 3 |
| Bundle size analysis workflow | MEDIUM | Low | P2 | Phase 3 |
| FPS profiling workflow | MEDIUM | Low | P2 | Phase 4 |
| Font loading with SplashScreen | MEDIUM | Low | P1 | Phase 3 |
| Platform code elimination | MEDIUM | Low | P2 | Phase 3 |

---

## 12. Monitoring & Continuous Performance

### 12.1 Performance Budget

| Metric | Budget | Measured By |
|---|---|---|
| Cold start TTI | < 2s | `react-native-performance` markers |
| JS Bundle size (minified) | < 3 MB | Expo Atlas / source-map-explorer |
| App download size (iOS) | < 25 MB | App Store Connect thinning report |
| App download size (Android) | < 20 MB | Ruler / Play Console |
| AudioOrb FPS | >= 58fps | Perf Monitor / Flashlight |
| Assessment flow FPS | >= 55fps | Perf Monitor |
| Memory growth per conversation turn | < 5 MB | DevTools Memory profiler |
| Autosave latency (keystroke to persist) | < 600ms | Manual testing |

### 12.2 CI Integration (Future)

```bash
# In CI pipeline, after build:
# 1. Bundle size check
npx expo export --platform ios
# Compare against budget

# 2. Android size check via Ruler
cd android && ./gradlew analyzeReleaseBundle
# Check threshold violations

# 3. FPS regression (Android only, via Flashlight)
flashlight measure --output results.json
flashlight compare baseline.json results.json
```

---

## References

- `js-animations-reanimated.md` — AudioOrb animation patterns
- `js-atomic-state.md` — Zustand selector patterns
- `js-memory-leaks.md` — Audio recording cleanup patterns
- `js-lists-flatlist-flashlist.md` — List rendering decisions
- `js-uncontrolled-components.md` — TextInput optimization
- `js-react-compiler.md` — Automatic memoization setup
- `js-concurrent-react.md` — useDeferredValue for expensive renders
- `js-measure-fps.md` — FPS measurement workflow
- `bundle-barrel-exports.md` — Import optimization
- `bundle-tree-shaking.md` — Dead code elimination
- `bundle-analyze-js.md` — Bundle visualization
- `bundle-r8-android.md` — Android code shrinking
- `bundle-hermes-mmap.md` — Android TTI optimization
- `bundle-library-size.md` — Dependency size evaluation
- `bundle-analyze-app.md` — Full app size analysis
- `native-measure-tti.md` — TTI measurement setup
- `native-sdks-over-polyfills.md` — Native vs polyfill decisions
- `native-view-flattening.md` — View hierarchy optimization
