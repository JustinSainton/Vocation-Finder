# Conversational Audio AI Interface -- Research & Best Practices

Best practices for building a conversational audio AI interface in Expo React Native for a vocational discernment assessment where users speak answers and an AI responds with follow-up questions via text-to-speech.

**Research Date:** March 2, 2026
**Technology Stack:** Expo SDK 52+ / React Native / Laravel Backend / OpenAI

---

## Table of Contents

1. [Expo Audio Recording Best Practices](#1-expo-audio-recording-best-practices)
2. [Speech-to-Text Integration](#2-speech-to-text-integration)
3. [Text-to-Speech Integration](#3-text-to-speech-integration)
4. [Audio Visualization Components](#4-audio-visualization-components)
5. [Haptic Feedback Patterns](#5-haptic-feedback-patterns)
6. [Conversation State Management](#6-conversation-state-management)
7. [Latency Optimization Pipeline](#7-latency-optimization-pipeline)
8. [Offline Handling & Graceful Degradation](#8-offline-handling--graceful-degradation)
9. [Accessibility Considerations](#9-accessibility-considerations)
10. [Production App Examples](#10-production-app-examples)

---

## 1. Expo Audio Recording Best Practices

### Two API Options

Expo provides two audio APIs. The newer `expo-audio` module (recommended for new projects) and the legacy `expo-av` module. For this project, prefer `expo-audio` with its hooks-based API.

**Source:** [Expo Audio Documentation](https://docs.expo.dev/versions/latest/sdk/audio/)

### Recommended Recording Configuration for Speech

For speech-to-text pipelines, use a speech-optimized preset rather than `HIGH_QUALITY`. STT engines like Whisper expect 16kHz mono audio. Recording at 44.1kHz stereo wastes bandwidth and processing time.

```typescript
// Speech-optimized recording preset for STT pipelines
const SPEECH_RECORDING_PRESET = {
  extension: '.m4a',
  sampleRate: 16000,      // Whisper and most STT expect 16kHz
  numberOfChannels: 1,     // Mono is sufficient for speech
  bitRate: 64000,          // 64kbps is plenty for voice
  android: {
    outputFormat: 'mpeg4',
    audioEncoder: 'aac',
  },
  ios: {
    outputFormat: IOSOutputFormat.MPEG4AAC,
    audioQuality: AudioQuality.HIGH,
    linearPCMBitDepth: 16,
    linearPCMIsBigEndian: false,
    linearPCMIsFloat: false,
  },
  web: {
    mimeType: 'audio/webm',
    bitsPerSecond: 64000,
  },
};
```

### Using the expo-audio Hook API

```typescript
import { useAudioRecorder, useAudioPlayer, RecordingPresets } from 'expo-audio';
import { Audio } from 'expo-audio';

function VocationalAssessment() {
  const recorder = useAudioRecorder(
    SPEECH_RECORDING_PRESET,
    (status) => {
      // Use metering for audio visualization
      if (status.metering !== undefined) {
        audioLevel.value = status.metering;
      }
    }
  );

  const startListening = async () => {
    // Request permissions
    const { granted } = await Audio.requestRecordingPermissionsAsync();
    if (!granted) return;

    // Configure audio mode for recording
    await Audio.setAudioModeAsync({
      allowsRecordingIOS: true,
      playsInSilentModeIOS: true,
    });

    await recorder.prepareToRecordAsync();
    recorder.record();
  };

  const stopListening = async () => {
    await recorder.stop();

    // IMPORTANT: Reset audio mode so playback works on iOS
    await Audio.setAudioModeAsync({
      allowsRecordingIOS: false,
    });

    const uri = recorder.uri;
    // Send to STT pipeline
    return uri;
  };
}
```

### Critical iOS Audio Mode Gotcha

On iOS, you **must** toggle `allowsRecordingIOS` between recording and playback. If you leave it set to `true`, audio playback (your TTS response) will route through the earpiece speaker instead of the main speaker. Always set it to `false` after recording stops, before playing back TTS audio.

### expo-audio-stream for Real-Time Streaming

For more advanced real-time streaming (sending audio chunks to a server as the user speaks), use `@siteed/expo-audio-studio`:

```typescript
import { useAudioRecorder, RecordingConfig } from '@siteed/expo-audio-studio';

const config: RecordingConfig = {
  sampleRate: 16000,
  channels: 1,
  encoding: 'pcm_16bit',
  interval: 500,           // Emit data every 500ms
  enableProcessing: true,  // Enable audio analysis for visualization
  output: {
    primary: { enabled: true },
    compressed: {
      enabled: true,
      format: 'aac',
      bitrate: 64000,
    },
  },
  onAudioStream: async (audioData) => {
    // Stream chunks to server for real-time STT
    await sendToSTTEndpoint(audioData);
  },
  onAudioAnalysis: async (analysisEvent) => {
    // Use for waveform visualization
    updateVisualization(analysisEvent);
  },
};
```

**Source:** [expo-audio-stream / expo-audio-studio](https://github.com/deeeed/expo-audio-stream)

### Format Decision Matrix

| Format | Use Case | File Size | STT Compatibility |
|--------|----------|-----------|-------------------|
| AAC (.m4a) | Default choice, good compression | Small | Excellent (Whisper, all cloud STTs) |
| PCM/WAV | Real-time streaming, max quality | Large | Native format, no transcoding |
| Opus (.ogg) | Best compression for speech | Smallest | Good (most cloud STTs) |

**Recommendation:** Use AAC for recorded-then-uploaded flows. Use PCM for real-time streaming pipelines.

---

## 2. Speech-to-Text Integration

### Option Comparison

| Approach | Latency | Accuracy | Cost | Offline | Privacy |
|----------|---------|----------|------|---------|---------|
| **OpenAI Whisper API** | ~500ms | Best (2.2% WER) | $0.006/min | No | Data sent to cloud |
| **On-Device (expo-speech-recognition)** | ~200ms | Good | Free | Yes | Full privacy |
| **On-Device (whisper.rn)** | ~450ms | Very Good | Free | Yes | Full privacy |
| **Deepgram Nova-3** | ~300ms streaming | Excellent | $0.0043/min | No | Data sent to cloud |
| **AssemblyAI Universal** | ~90ms streaming | Excellent | $0.01/min | No | Data sent to cloud |

### Recommended: Hybrid Approach

For this vocational assessment app, a hybrid approach is ideal:

1. **Primary:** OpenAI Whisper API (best accuracy for nuanced, reflective answers)
2. **Fallback:** On-device `expo-speech-recognition` (offline/poor connectivity)

```typescript
// /services/speechToText.ts

import * as FileSystem from 'expo-file-system';

interface STTResult {
  text: string;
  confidence: number;
  source: 'whisper' | 'on-device';
}

export async function transcribeAudio(audioUri: string): Promise<STTResult> {
  try {
    return await transcribeWithWhisper(audioUri);
  } catch (error) {
    console.warn('Whisper API failed, falling back to on-device:', error);
    return await transcribeOnDevice(audioUri);
  }
}

async function transcribeWithWhisper(audioUri: string): Promise<STTResult> {
  const formData = new FormData();

  // Read the file and create form data
  const fileInfo = await FileSystem.getInfoAsync(audioUri);
  formData.append('file', {
    uri: audioUri,
    type: 'audio/m4a',
    name: 'recording.m4a',
  } as any);
  formData.append('model', 'whisper-1');
  formData.append('language', 'en');
  formData.append('response_format', 'verbose_json');

  // Use your Laravel backend as proxy to avoid exposing API keys
  const response = await fetch(`${API_BASE}/api/transcribe`, {
    method: 'POST',
    body: formData,
    headers: {
      Authorization: `Bearer ${authToken}`,
    },
  });

  const data = await response.json();
  return {
    text: data.text,
    confidence: data.segments?.[0]?.avg_logprob ?? 0,
    source: 'whisper',
  };
}
```

### On-Device Recognition with expo-speech-recognition

```typescript
// Uses iOS SFSpeechRecognizer and Android SpeechRecognizer
import {
  ExpoSpeechRecognitionModule,
  useSpeechRecognitionEvent,
} from '@jamsch/expo-speech-recognition';

function useOnDeviceSTT() {
  const [transcript, setTranscript] = useState('');
  const [isListening, setIsListening] = useState(false);

  useSpeechRecognitionEvent('result', (event) => {
    const result = event.results[event.resultIndex];
    if (result) {
      setTranscript(result[0].transcript);
    }
  });

  useSpeechRecognitionEvent('end', () => {
    setIsListening(false);
  });

  const startListening = () => {
    ExpoSpeechRecognitionModule.start({
      lang: 'en-US',
      interimResults: true,
      requiresOnDeviceRecognition: true, // Force on-device
    });
    setIsListening(true);
  };

  return { transcript, isListening, startListening };
}
```

**Key sources:**
- [expo-speech-recognition](https://github.com/jamsch/expo-speech-recognition)
- [whisper.rn -- on-device Whisper for React Native](https://github.com/mybigday/whisper.rn)
- [Software Mansion: ASR in React Native](https://blog.swmansion.com/building-an-ai-powered-note-taking-app-in-react-native-part-4-automatic-speech-recognition-a3b50e7d2245)

---

## 3. Text-to-Speech Integration

### Provider Comparison

| Provider | Latency (TTFB) | Voice Quality | Streaming | Cost | Best For |
|----------|----------------|---------------|-----------|------|----------|
| **ElevenLabs Flash v2.5** | ~75ms | Excellent (natural) | Yes | $0.30/1K chars | Premium voice quality |
| **OpenAI TTS** | ~200ms | Very Good | Yes | $15/1M chars (tts-1) | Simple integration |
| **OpenAI TTS HD** | ~400ms | Excellent | Yes | $30/1M chars | Max quality, non-realtime |
| **Cartesia Sonic** | ~90ms | Very Good | Yes | Usage-based | Ultra-low latency |
| **Expo Speech (on-device)** | ~50ms | Fair (robotic) | No | Free | Offline fallback |

**Sources:**
- [ElevenLabs vs OpenAI TTS comparison](https://vapi.ai/blog/elevenlabs-vs-openai)
- [TTS Latency Benchmark](https://github.com/Picovoice/tts-latency-benchmark)
- [TTS Voice AI Model Guide 2025](https://layercode.com/blog/tts-voice-ai-model-guide)

### Recommendation: OpenAI TTS with ElevenLabs Upgrade Path

Start with OpenAI TTS (simpler integration, same auth as your existing OpenAI usage), upgrade to ElevenLabs when voice quality becomes a differentiator.

```typescript
// /services/textToSpeech.ts

import { useAudioPlayer } from 'expo-audio';
import * as FileSystem from 'expo-file-system';

export async function synthesizeSpeech(
  text: string,
  voice: string = 'nova'  // warm, conversational voice
): Promise<string> {
  // Route through your Laravel backend
  const response = await fetch(`${API_BASE}/api/synthesize`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    body: JSON.stringify({ text, voice }),
  });

  // Save the audio response to a local file
  const audioBlob = await response.blob();
  const fileUri = `${FileSystem.cacheDirectory}tts_response_${Date.now()}.mp3`;

  // Write blob to file system
  const reader = new FileReader();
  return new Promise((resolve) => {
    reader.onload = async () => {
      const base64 = (reader.result as string).split(',')[1];
      await FileSystem.writeAsStringAsync(fileUri, base64, {
        encoding: FileSystem.EncodingType.Base64,
      });
      resolve(fileUri);
    };
    reader.readAsDataURL(audioBlob);
  });
}

// Usage in component
function AssessmentScreen() {
  const player = useAudioPlayer(null);

  const playAIResponse = async (text: string) => {
    const audioUri = await synthesizeSpeech(text);
    player.replace({ uri: audioUri });
    player.play();
  };
}
```

### Laravel Backend TTS Proxy

```php
// app/Http/Controllers/Api/SynthesizeController.php

public function __invoke(Request $request)
{
    $validated = $request->validate([
        'text' => 'required|string|max:4096',
        'voice' => 'string|in:alloy,echo,fable,onyx,nova,shimmer',
    ]);

    $response = Http::withToken(config('services.openai.key'))
        ->withHeaders(['Content-Type' => 'application/json'])
        ->post('https://api.openai.com/v1/audio/speech', [
            'model' => 'tts-1',           // Use 'tts-1-hd' for higher quality
            'input' => $validated['text'],
            'voice' => $validated['voice'] ?? 'nova',
            'response_format' => 'mp3',    // mp3 is smallest, opus for quality
            'speed' => 1.0,
        ]);

    return response($response->body(), 200, [
        'Content-Type' => 'audio/mpeg',
        'Cache-Control' => 'no-cache',
    ]);
}
```

### Streaming TTS for Lower Perceived Latency

For near-instant playback, stream TTS audio chunks and begin playback before the full response is ready:

```typescript
// Streaming approach -- begin playback on first chunk
async function streamTTSAndPlay(text: string) {
  const response = await fetch(`${API_BASE}/api/synthesize/stream`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    body: JSON.stringify({ text, voice: 'nova' }),
  });

  // Accumulate chunks into a buffer
  const reader = response.body?.getReader();
  const chunks: Uint8Array[] = [];

  while (reader) {
    const { done, value } = await reader.read();
    if (done) break;
    chunks.push(value);

    // Start playback after accumulating first ~200ms of audio
    if (chunks.length === 1) {
      // Write initial chunk and begin playback
      const initialAudio = concatenateChunks(chunks);
      await writeAndPlay(initialAudio);
    }
  }
}
```

### Voice Selection for Vocational Assessment Context

Given the design spec calls for something that feels like "letters from a wise mentor":

| Voice | Character | Suitability |
|-------|-----------|-------------|
| **nova** (OpenAI) | Warm, thoughtful female | Best match for mentoring tone |
| **onyx** (OpenAI) | Deep, authoritative male | Good for gravity/weight |
| **echo** (OpenAI) | Calm, neutral male | Good for neutral questioning |

For ElevenLabs, create a custom voice clone or use their "Daniel" or "Charlotte" voices which have a warm, contemplative quality.

---

## 4. Audio Visualization Components

### Design Alignment

Per the project's design spec: "Silence over stimulation" and "No gradients. No shadows. No cards." The visualization should be minimal and purposeful -- a single, subtle element that communicates "I am listening" without being flashy.

### Recommended: Minimal Audio Orb with React Native Skia

The best approach for this project is a GPU-accelerated shader orb using React Native Skia, driven by Reanimated shared values from microphone metering.

**Key dependencies:**
- `@shopify/react-native-skia` (v2.2.12+) -- GPU rendering
- `react-native-reanimated` (v4.x) -- animation system
- `expo-audio` -- microphone metering data

**Source:** [Orb Shader Animation with React Native Skia](https://www.animatereactnative.com/post/orb-shader-animation-with-react-native-skia)

### Simpler Alternative: Pure Reanimated Orb (No Skia Dependency)

For a lighter-weight solution that aligns with the project's minimalist ethos:

```typescript
// /components/AudioOrb.tsx

import React from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withRepeat,
  withSequence,
  withTiming,
  withSpring,
  useDerivedValue,
  interpolate,
  Easing,
} from 'react-native-reanimated';

type OrbState = 'idle' | 'listening' | 'processing' | 'speaking';

interface AudioOrbProps {
  state: OrbState;
  audioLevel: Animated.SharedValue<number>; // -160 to 0 dB metering
}

export function AudioOrb({ state, audioLevel }: AudioOrbProps) {
  // Normalize audio level from dB range to 0-1
  const normalizedLevel = useDerivedValue(() => {
    const clamped = Math.max(-60, Math.min(0, audioLevel.value));
    return interpolate(clamped, [-60, 0], [0, 1]);
  });

  // Breathing animation for idle/processing states
  const breathe = useSharedValue(1);

  React.useEffect(() => {
    if (state === 'idle' || state === 'processing') {
      breathe.value = withRepeat(
        withSequence(
          withTiming(1.05, { duration: 2000, easing: Easing.inOut(Easing.ease) }),
          withTiming(1.0, { duration: 2000, easing: Easing.inOut(Easing.ease) })
        ),
        -1,
        false
      );
    } else {
      breathe.value = withTiming(1, { duration: 300 });
    }
  }, [state]);

  // Main orb style
  const orbStyle = useAnimatedStyle(() => {
    let scale = breathe.value;
    let opacity = 0.6;

    if (state === 'listening') {
      // React to audio input
      scale = 1 + normalizedLevel.value * 0.3;
      opacity = 0.6 + normalizedLevel.value * 0.4;
    } else if (state === 'speaking') {
      // Gentle pulse while speaking
      scale = 1 + normalizedLevel.value * 0.15;
      opacity = 0.8;
    } else if (state === 'processing') {
      opacity = 0.4;
    }

    return {
      transform: [{ scale: withSpring(scale, { damping: 15, stiffness: 150 }) }],
      opacity: withTiming(opacity, { duration: 200 }),
    };
  });

  // Outer glow ring
  const glowStyle = useAnimatedStyle(() => {
    let scale = breathe.value * 1.3;
    let opacity = 0.1;

    if (state === 'listening') {
      scale = 1.3 + normalizedLevel.value * 0.4;
      opacity = 0.05 + normalizedLevel.value * 0.15;
    }

    return {
      transform: [{ scale: withSpring(scale, { damping: 12, stiffness: 100 }) }],
      opacity: withTiming(opacity, { duration: 300 }),
    };
  });

  return (
    <View style={styles.container}>
      {/* Outer glow */}
      <Animated.View style={[styles.glow, glowStyle]} />
      {/* Main orb */}
      <Animated.View style={[styles.orb, orbStyle]} />
    </View>
  );
}

const ORB_SIZE = 120;

const styles = StyleSheet.create({
  container: {
    width: ORB_SIZE * 2,
    height: ORB_SIZE * 2,
    alignItems: 'center',
    justifyContent: 'center',
  },
  orb: {
    width: ORB_SIZE,
    height: ORB_SIZE,
    borderRadius: ORB_SIZE / 2,
    backgroundColor: '#2C2C2E', // Near-black, matching design spec
    position: 'absolute',
  },
  glow: {
    width: ORB_SIZE * 1.5,
    height: ORB_SIZE * 1.5,
    borderRadius: (ORB_SIZE * 1.5) / 2,
    backgroundColor: '#3A3A3C', // Slightly lighter for glow
    position: 'absolute',
  },
});
```

### State-to-Visual Mapping

| State | Orb Behavior | Design Rationale |
|-------|-------------|------------------|
| **idle** | Slow, gentle breathing (2s cycle) | "I am here, ready when you are" |
| **listening** | Reactive to audio amplitude | "I hear you" -- validates the user is being recorded |
| **processing** | Slower breathing, reduced opacity | "I am thinking" -- creates synthesis pause feeling |
| **speaking** | Subtle pulse synced to TTS output | "I am speaking" -- provides visual audio feedback |

### Connecting Metering Data to the Orb

```typescript
import { useAudioRecorder } from 'expo-audio';
import { useSharedValue } from 'react-native-reanimated';

function AssessmentConversation() {
  const audioLevel = useSharedValue(-60);

  const recorder = useAudioRecorder(
    SPEECH_RECORDING_PRESET,
    (status) => {
      if (status.metering !== undefined) {
        audioLevel.value = status.metering; // -160 to 0 dB
      }
    }
  );

  return (
    <View style={styles.screen}>
      <AudioOrb state={conversationState} audioLevel={audioLevel} />
      {/* ... question text, controls, etc. */}
    </View>
  );
}
```

**Key animation sources:**
- [React Native Reanimated -- withRepeat, withSpring, withTiming](https://docs.swmansion.com/react-native-reanimated/)
- [ElevenLabs UI Orb Component](https://ui.elevenlabs.io/docs/components/orb)
- [React Native Audio API -- Audio Visualization](https://docs.swmansion.com/react-native-audio-api/docs/guides/see-your-sound/)

---

## 5. Haptic Feedback Patterns

### Design Philosophy for This App

Per the design spec's "Silence over stimulation" principle, haptics should be used sparingly and intentionally. Each haptic event should carry meaning, not decoration.

**Source:** [2025 Guide to Haptics](https://saropa-contacts.medium.com/2025-guide-to-haptics-enhancing-mobile-ux-with-tactile-feedback-676dd5937774), [Android Haptics Principles](https://developer.android.com/develop/ui/views/haptics/haptics-principles)

### Recommended Haptic Events

```typescript
// /services/haptics.ts

import * as Haptics from 'expo-haptics';
import { Platform } from 'react-native';

export const ConversationHaptics = {
  // User taps the orb to start speaking
  startListening: () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
  },

  // User releases / stops speaking
  stopListening: () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
  },

  // AI begins speaking its response
  aiResponseStart: () => {
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  },

  // Transition to next question
  questionTransition: () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
  },

  // Assessment complete
  assessmentComplete: () => {
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  },

  // Error occurred (network failure, STT failure)
  error: () => {
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
  },

  // Subtle selection feedback for UI elements
  selection: () => {
    Haptics.selectionAsync();
  },
};
```

### Haptic Pattern Rules

1. **Do not use haptics during speech recording** -- vibration can create audio artifacts
2. **Do not provide haptic feedback for every word** -- less is more
3. **Use platform-appropriate patterns** -- iOS Taptic Engine provides more nuance than Android
4. **Respect user preferences** -- check system haptic settings before triggering
5. **Never use continuous vibration** -- it is annoying and numbs the hands

### Centralized Haptic Service

```typescript
// /services/haptics.ts -- full service with preference management

import * as Haptics from 'expo-haptics';
import AsyncStorage from '@react-native-async-storage/async-storage';

class HapticService {
  private enabled: boolean = true;

  async initialize() {
    const pref = await AsyncStorage.getItem('haptics_enabled');
    this.enabled = pref !== 'false';
  }

  async setEnabled(value: boolean) {
    this.enabled = value;
    await AsyncStorage.setItem('haptics_enabled', value.toString());
  }

  trigger(
    type: 'impact' | 'notification' | 'selection',
    style?: Haptics.ImpactFeedbackStyle | Haptics.NotificationFeedbackType
  ) {
    if (!this.enabled) return;

    switch (type) {
      case 'impact':
        Haptics.impactAsync(style as Haptics.ImpactFeedbackStyle);
        break;
      case 'notification':
        Haptics.notificationAsync(style as Haptics.NotificationFeedbackType);
        break;
      case 'selection':
        Haptics.selectionAsync();
        break;
    }
  }
}

export const haptics = new HapticService();
```

**Source:** [Expo Haptics Documentation](https://docs.expo.dev/versions/latest/sdk/haptics/)

---

## 6. Conversation State Management

### Recommended: Zustand for Conversation State

Zustand is the best fit for this use case -- lightweight, hooks-based, no provider boilerplate, excellent TypeScript support, and performant selector-based subscriptions.

**Source:** [React State Management in 2025](https://www.developerway.com/posts/react-state-management-2025)

### Conversation State Machine

The conversational flow maps naturally to a finite state machine:

```
idle -> listening -> processing_stt -> processing_llm -> speaking -> idle
                                                                  -> listening (follow-up)
```

### Zustand Store Implementation

```typescript
// /stores/conversationStore.ts

import { create } from 'zustand';

type ConversationState =
  | 'idle'
  | 'listening'
  | 'processing_stt'
  | 'processing_llm'
  | 'speaking'
  | 'error';

interface Message {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  audioUri?: string;
  timestamp: number;
}

interface AssessmentProgress {
  currentQuestionIndex: number;
  totalQuestions: number;
  currentCategory: string;
  answeredQuestions: Set<number>;
}

interface ConversationStore {
  // State
  state: ConversationState;
  messages: Message[];
  currentTranscript: string;
  assessment: AssessmentProgress;
  error: string | null;
  isSessionActive: boolean;

  // Actions
  setState: (state: ConversationState) => void;
  addMessage: (message: Omit<Message, 'id' | 'timestamp'>) => void;
  setTranscript: (text: string) => void;
  updateTranscript: (text: string) => void; // For interim STT results
  advanceQuestion: () => void;
  setError: (error: string | null) => void;
  startSession: () => void;
  endSession: () => void;
  reset: () => void;

  // Derived
  currentQuestion: () => string | null;
  conversationHistory: () => Array<{ role: string; content: string }>;
  isComplete: () => boolean;
}

export const useConversationStore = create<ConversationStore>((set, get) => ({
  // Initial state
  state: 'idle',
  messages: [],
  currentTranscript: '',
  assessment: {
    currentQuestionIndex: 0,
    totalQuestions: 20,
    currentCategory: 'Service Orientation',
    answeredQuestions: new Set(),
  },
  error: null,
  isSessionActive: false,

  // Actions
  setState: (state) => set({ state, error: state === 'error' ? get().error : null }),

  addMessage: (message) =>
    set((prev) => ({
      messages: [
        ...prev.messages,
        {
          ...message,
          id: `msg_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
          timestamp: Date.now(),
        },
      ],
    })),

  setTranscript: (text) => set({ currentTranscript: text }),
  updateTranscript: (text) => set({ currentTranscript: text }),

  advanceQuestion: () =>
    set((prev) => ({
      assessment: {
        ...prev.assessment,
        currentQuestionIndex: prev.assessment.currentQuestionIndex + 1,
        answeredQuestions: new Set([
          ...prev.assessment.answeredQuestions,
          prev.assessment.currentQuestionIndex,
        ]),
      },
    })),

  setError: (error) => set({ error, state: error ? 'error' : get().state }),

  startSession: () => set({ isSessionActive: true, state: 'idle' }),
  endSession: () => set({ isSessionActive: false, state: 'idle' }),

  reset: () =>
    set({
      state: 'idle',
      messages: [],
      currentTranscript: '',
      assessment: {
        currentQuestionIndex: 0,
        totalQuestions: 20,
        currentCategory: 'Service Orientation',
        answeredQuestions: new Set(),
      },
      error: null,
      isSessionActive: false,
    }),

  // Derived state
  currentQuestion: () => {
    const { currentQuestionIndex } = get().assessment;
    return QUESTIONS[currentQuestionIndex] ?? null;
  },

  conversationHistory: () => {
    return get().messages.map(({ role, content }) => ({ role, content }));
  },

  isComplete: () => {
    const { currentQuestionIndex, totalQuestions } = get().assessment;
    return currentQuestionIndex >= totalQuestions;
  },
}));
```

### Orchestrator Hook

```typescript
// /hooks/useConversationOrchestrator.ts

import { useCallback, useRef } from 'react';
import { useConversationStore } from '@/stores/conversationStore';
import { transcribeAudio } from '@/services/speechToText';
import { synthesizeSpeech } from '@/services/textToSpeech';
import { generateFollowUp } from '@/services/llm';
import { ConversationHaptics } from '@/services/haptics';

export function useConversationOrchestrator() {
  const store = useConversationStore();
  const recorderRef = useRef<AudioRecorder | null>(null);
  const playerRef = useRef<AudioPlayer | null>(null);

  const handleUserFinishedSpeaking = useCallback(async (audioUri: string) => {
    try {
      // Phase 1: STT
      store.setState('processing_stt');
      const { text } = await transcribeAudio(audioUri);
      store.addMessage({ role: 'user', content: text, audioUri });

      // Phase 2: LLM
      store.setState('processing_llm');
      const history = store.conversationHistory();
      const aiResponse = await generateFollowUp(history, store.currentQuestion());
      store.addMessage({ role: 'assistant', content: aiResponse });

      // Phase 3: TTS
      store.setState('speaking');
      ConversationHaptics.aiResponseStart();
      const ttsUri = await synthesizeSpeech(aiResponse);

      // Phase 4: Playback
      playerRef.current?.replace({ uri: ttsUri });
      playerRef.current?.play();

      // When playback finishes, return to idle or advance
      // (handled by player status listener)

    } catch (error) {
      store.setError(error.message);
      ConversationHaptics.error();
      store.setState('error');
    }
  }, []);

  const startListening = useCallback(() => {
    ConversationHaptics.startListening();
    store.setState('listening');
    // Start recording...
  }, []);

  const stopListening = useCallback(async () => {
    ConversationHaptics.stopListening();
    const uri = await stopRecording();
    if (uri) {
      await handleUserFinishedSpeaking(uri);
    }
  }, []);

  return {
    state: store.state,
    startListening,
    stopListening,
    messages: store.messages,
    assessment: store.assessment,
    error: store.error,
  };
}
```

### Why Zustand Over XState

XState is excellent for complex state machines with guards, actions, and nested states. However, for this use case:

- Zustand is simpler to integrate with React hooks
- The conversation flow is relatively linear (not deeply nested)
- Zustand's selector-based subscriptions prevent unnecessary re-renders
- The team likely already uses Zustand or similar lightweight stores

If the conversation logic becomes significantly more complex (parallel states, complex guard conditions, undo/redo), consider migrating to XState.

**Reference:** [XState for React Native](https://x-team.com/blog/react-native-xstate)

---

## 7. Latency Optimization Pipeline

### Target: Under 3 Seconds End-to-End

The voice AI pipeline has four sequential stages. Each must be optimized independently and then parallelized where possible.

```
User speaks -> STT -> LLM -> TTS -> Playback
              ~300ms  ~500ms  ~150ms  ~50ms  = ~1000ms theoretical minimum
```

**Sources:**
- [Solving Voice AI Latency: Sub-1 Second Responses](https://medium.com/@reveorai/solving-voice-ai-latency-from-5-seconds-to-sub-1-second-responses-d0065e520799)
- [Voice AI Infrastructure Guide 2025](https://introl.com/blog/voice-ai-infrastructure-real-time-speech-agents-asr-tts-guide-2025)
- [Achieving ~465ms end-to-end latency](https://www.assemblyai.com/blog/how-to-build-lowest-latency-voice-agent-vapi)
- [Engineering for Real-Time Voice Agent Latency](https://cresta.com/blog/engineering-for-real-time-voice-agent-latency)
- [How to optimise latency for voice agents](https://rnikhil.com/2025/05/18/how-to-reduce-latency-voice-agents)

### Optimization Strategy 1: Streaming Pipeline (Most Important)

Instead of waiting for each stage to complete, stream partial results:

```
Audio chunks -> Streaming STT (partial text) -> Streaming LLM (token by token) -> Streaming TTS -> Playback
```

```typescript
// /services/streamingPipeline.ts

export async function processUserSpeechStreaming(
  audioUri: string,
  onPartialTranscript: (text: string) => void,
  onAITokens: (text: string) => void,
  onAudioChunk: (chunk: ArrayBuffer) => void
) {
  // Step 1: Start STT (can be streaming)
  const transcript = await transcribeAudio(audioUri);
  onPartialTranscript(transcript.text);

  // Step 2: Stream LLM response
  const response = await fetch(`${API_BASE}/api/conversation/stream`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${authToken}`,
    },
    body: JSON.stringify({
      messages: conversationHistory,
      userMessage: transcript.text,
    }),
  });

  // Step 3: Read LLM tokens and start TTS on first sentence
  const reader = response.body?.getReader();
  const decoder = new TextDecoder();
  let accumulatedText = '';
  let sentenceBuffer = '';

  while (reader) {
    const { done, value } = await reader.read();
    if (done) break;

    const chunk = decoder.decode(value);
    accumulatedText += chunk;
    sentenceBuffer += chunk;
    onAITokens(accumulatedText);

    // When we have a complete sentence, start TTS immediately
    const sentenceEnd = sentenceBuffer.match(/[.!?]\s/);
    if (sentenceEnd) {
      const sentence = sentenceBuffer.slice(0, sentenceEnd.index! + 1);
      sentenceBuffer = sentenceBuffer.slice(sentenceEnd.index! + 2);

      // Fire-and-forget TTS for this sentence
      synthesizeSpeechChunk(sentence).then(onAudioChunk);
    }
  }

  // Handle remaining text
  if (sentenceBuffer.trim()) {
    const finalAudio = await synthesizeSpeechChunk(sentenceBuffer);
    onAudioChunk(finalAudio);
  }
}
```

### Optimization Strategy 2: Endpoint Detection (VAD)

Detect when the user stops speaking as fast as possible to begin processing:

```typescript
// Use Voice Activity Detection to quickly detect end-of-speech
// WebRTC VAD (30-80ms detection) -> Silero VAD confirmation

// Adaptive silence threshold based on conversation context
function getEndpointTimeout(context: 'question' | 'reflection'): number {
  // Shorter timeout for quick acknowledgments
  if (context === 'question') return 800;
  // Longer for reflective answers (users pause to think)
  if (context === 'reflection') return 2000;
  return 1200;
}
```

### Optimization Strategy 3: LLM Response Optimization

```typescript
// Laravel backend: optimize LLM calls

// 1. Use streaming responses
$stream = OpenAI::chat()->createStreamed([
    'model' => 'gpt-4o-mini',  // Faster than gpt-4o for follow-ups
    'messages' => $messages,
    'max_tokens' => 150,        // Keep follow-ups concise
    'temperature' => 0.7,
    'stream' => true,
]);

// 2. Cache system prompt (OpenAI supports prompt caching)
// The system prompt describing the assessment framework stays constant

// 3. Use rolling context window
// Summarize older messages instead of sending full history
$optimizedMessages = array_merge(
    [$systemPrompt],
    $this->summarizeOlderMessages($messages),
    array_slice($messages, -6)  // Keep last 3 exchanges in full
);
```

### Optimization Strategy 4: Warm-Up and Pre-Loading

```typescript
// Pre-warm connections when the assessment screen loads
async function warmUpPipeline() {
  // 1. Pre-establish WebSocket/HTTP connections
  await fetch(`${API_BASE}/api/health`, { method: 'HEAD' });

  // 2. Pre-load TTS for the first question
  const firstQuestion = QUESTIONS[0];
  const audioUri = await synthesizeSpeech(firstQuestion);
  preloadedAudio.set(0, audioUri);

  // 3. Pre-load next question TTS while user is answering current
  // (happens in background during recording)
}
```

### Latency Budget Breakdown

| Stage | Unoptimized | Optimized | Technique |
|-------|-------------|-----------|-----------|
| End-of-speech detection | 1000ms | 300ms | WebRTC VAD + adaptive threshold |
| STT transcription | 500ms | 300ms | Whisper API with short audio, no streaming needed for turn-based |
| LLM processing (TTFT) | 800ms | 200ms | gpt-4o-mini, streaming, prompt caching, short max_tokens |
| TTS synthesis (TTFB) | 300ms | 75-150ms | ElevenLabs Flash or OpenAI tts-1, streaming |
| Audio playback start | 100ms | 50ms | Pre-buffered player |
| **Total** | **2700ms** | **925-1000ms** | |

---

## 8. Offline Handling & Graceful Degradation

### Tiered Degradation Strategy

For a vocational assessment, losing a user's thoughtful, vulnerable answers is unacceptable. The offline strategy must protect user data above all else.

```typescript
// /services/networkStatus.ts

import NetInfo from '@react-native-community/netinfo';
import { create } from 'zustand';

type ConnectivityLevel = 'online' | 'degraded' | 'offline';

interface NetworkStore {
  connectivity: ConnectivityLevel;
  setConnectivity: (level: ConnectivityLevel) => void;
}

export const useNetworkStore = create<NetworkStore>((set) => ({
  connectivity: 'online',
  setConnectivity: (connectivity) => set({ connectivity }),
}));

// Monitor network quality
NetInfo.addEventListener((state) => {
  if (!state.isConnected) {
    useNetworkStore.getState().setConnectivity('offline');
  } else if (state.isInternetReachable === false) {
    useNetworkStore.getState().setConnectivity('degraded');
  } else {
    useNetworkStore.getState().setConnectivity('online');
  }
});
```

### Feature Degradation Matrix

| Feature | Online | Degraded | Offline |
|---------|--------|----------|---------|
| **Audio recording** | Full quality | Full quality | Full quality |
| **STT** | Whisper API | Whisper API (retry) | On-device STT |
| **AI follow-ups** | Streaming LLM | LLM with retry | Pre-scripted follow-ups |
| **TTS** | Cloud TTS | Cloud TTS (retry) | Expo Speech (on-device) |
| **Answer saving** | Real-time sync | Queue for sync | Local storage |

### Offline Queue System

```typescript
// /services/offlineQueue.ts

import AsyncStorage from '@react-native-async-storage/async-storage';

interface QueuedItem {
  id: string;
  type: 'transcription' | 'answer' | 'tts_request';
  payload: any;
  audioUri?: string;
  createdAt: number;
  retryCount: number;
}

class OfflineQueue {
  private queue: QueuedItem[] = [];
  private maxRetries = 3;

  async enqueue(item: Omit<QueuedItem, 'id' | 'createdAt' | 'retryCount'>) {
    const queueItem: QueuedItem = {
      ...item,
      id: `queue_${Date.now()}`,
      createdAt: Date.now(),
      retryCount: 0,
    };
    this.queue.push(queueItem);
    await this.persist();
  }

  async processQueue() {
    const pending = [...this.queue];
    for (const item of pending) {
      try {
        await this.processItem(item);
        this.queue = this.queue.filter((q) => q.id !== item.id);
      } catch (error) {
        item.retryCount++;
        if (item.retryCount >= this.maxRetries) {
          // Move to dead letter queue for manual review
          await this.moveToDeadLetter(item);
          this.queue = this.queue.filter((q) => q.id !== item.id);
        }
      }
    }
    await this.persist();
  }

  private async persist() {
    await AsyncStorage.setItem('offline_queue', JSON.stringify(this.queue));
  }

  private async processItem(item: QueuedItem) {
    switch (item.type) {
      case 'answer':
        await syncAnswerToServer(item.payload);
        break;
      case 'transcription':
        await transcribeQueuedAudio(item.audioUri!, item.payload);
        break;
    }
  }
}

export const offlineQueue = new OfflineQueue();
```

### Pre-Scripted Fallback Follow-Ups

When the LLM is unreachable, use thoughtful, pre-written follow-up prompts:

```typescript
// /data/fallbackFollowUps.ts

export const FALLBACK_FOLLOW_UPS: Record<number, string[]> = {
  // Question 1: Service Orientation
  0: [
    'Thank you for sharing that. Can you tell me more about what drew you to help in that particular way?',
    'That is meaningful. What do you think it reveals about what matters most to you?',
  ],
  // Question 2
  1: [
    'I appreciate your honesty. What kind of problem would you not want to help with, and why?',
    'Interesting. Does that desire to help come naturally, or is it something you have chosen?',
  ],
  // ... for all 20 questions
};
```

### Local Audio Storage for Data Protection

```typescript
// Always save recordings locally FIRST, then sync
async function saveRecordingLocally(
  audioUri: string,
  questionIndex: number,
  sessionId: string
): Promise<string> {
  const localDir = `${FileSystem.documentDirectory}recordings/${sessionId}/`;
  await FileSystem.makeDirectoryAsync(localDir, { intermediates: true });

  const localPath = `${localDir}q${questionIndex}_${Date.now()}.m4a`;
  await FileSystem.copyAsync({ from: audioUri, to: localPath });

  return localPath;
}
```

---

## 9. Accessibility Considerations

### Core Principle

An audio-first interface must not become an audio-only interface. Every interaction must have a non-audio alternative.

**Sources:**
- [Voice Interfaces, AI, and Accessibility](https://medium.com/@rounakbajoriastar/voice-interfaces-ai-and-accessibility-a-new-era-of-inclusive-design-bdae509c0318)
- [Voice-First AI for Blind and Low-Vision People](https://toptechtidbits.com/how-voice-first-ai-could-soon-change-everything-for-blv-people/)

### Accessibility Requirements Checklist

**1. Text Input Alternative**
Users who cannot speak (deaf, mute, or in noisy environments) must be able to type their answers:

```typescript
function QuestionScreen({ question }: { question: string }) {
  const [inputMode, setInputMode] = useState<'voice' | 'text'>('voice');

  return (
    <View>
      <Text
        accessibilityRole="header"
        accessibilityLabel={`Question ${questionNumber} of ${total}`}
      >
        {question}
      </Text>

      {/* Mode toggle */}
      <View accessibilityRole="radiogroup" accessibilityLabel="Answer input method">
        <Pressable
          accessibilityRole="radio"
          accessibilityState={{ checked: inputMode === 'voice' }}
          onPress={() => setInputMode('voice')}
        >
          <Text>Speak your answer</Text>
        </Pressable>
        <Pressable
          accessibilityRole="radio"
          accessibilityState={{ checked: inputMode === 'text' }}
          onPress={() => setInputMode('text')}
        >
          <Text>Type your answer</Text>
        </Pressable>
      </View>

      {inputMode === 'voice' ? (
        <VoiceInput />
      ) : (
        <TextInput
          multiline
          placeholder="Take your time. Write freely."
          accessibilityLabel="Your answer"
          accessibilityHint="Type your response to the question"
        />
      )}
    </View>
  );
}
```

**2. Visual Transcript of All Audio**
Every TTS response must have a visible text equivalent:

```typescript
// Show AI responses as text alongside audio playback
<View accessibilityRole="log" accessibilityLabel="Conversation transcript">
  {messages.map((msg) => (
    <View
      key={msg.id}
      accessibilityRole="text"
      accessibilityLabel={`${msg.role === 'assistant' ? 'AI' : 'You'}: ${msg.content}`}
    >
      <Text style={styles.messageRole}>
        {msg.role === 'assistant' ? 'Assessment Guide' : 'Your Response'}
      </Text>
      <Text style={styles.messageContent}>{msg.content}</Text>
    </View>
  ))}
</View>
```

**3. Screen Reader (VoiceOver/TalkBack) Compatibility**

```typescript
// Audio orb must have accessible label describing its state
<AudioOrb
  state={conversationState}
  audioLevel={audioLevel}
  accessibilityRole="progressbar"
  accessibilityLabel={getOrbAccessibilityLabel(conversationState)}
  accessibilityHint="Double tap to start or stop speaking"
/>

function getOrbAccessibilityLabel(state: ConversationState): string {
  switch (state) {
    case 'idle': return 'Ready to listen. Double tap to start speaking.';
    case 'listening': return 'Recording your response. Double tap to stop.';
    case 'processing_stt': return 'Processing your response.';
    case 'processing_llm': return 'Preparing next question.';
    case 'speaking': return 'The assessment guide is speaking.';
    default: return 'Audio interaction area.';
  }
}
```

**4. Pause and Replay Controls**

```typescript
// Users must be able to replay AI questions and pause the assessment
<View accessibilityRole="toolbar" accessibilityLabel="Audio controls">
  <Pressable
    accessibilityRole="button"
    accessibilityLabel="Replay last question"
    onPress={replayLastResponse}
  >
    <Text>Replay</Text>
  </Pressable>

  <Pressable
    accessibilityRole="button"
    accessibilityLabel="Pause assessment"
    onPress={pauseAssessment}
  >
    <Text>Pause</Text>
  </Pressable>
</View>
```

**5. Timing Accommodations**
Do not auto-advance or impose time limits. The design spec already says "Depth over speed" -- this naturally supports accessibility:

- No countdown timers on responses
- No auto-stop on silence (use manual stop)
- Allow users to take as long as they need
- Provide a "Take a break" option

**6. Captions/Subtitles During TTS Playback**
Display real-time captions synchronized with TTS audio for deaf/hard-of-hearing users.

**7. Reduced Motion Support**

```typescript
import { AccessibilityInfo } from 'react-native';
import { useEffect, useState } from 'react';

function useReducedMotion(): boolean {
  const [reduceMotion, setReduceMotion] = useState(false);

  useEffect(() => {
    AccessibilityInfo.isReduceMotionEnabled().then(setReduceMotion);
    const sub = AccessibilityInfo.addEventListener(
      'reduceMotionChanged',
      setReduceMotion
    );
    return () => sub.remove();
  }, []);

  return reduceMotion;
}

// In AudioOrb component
const reduceMotion = useReducedMotion();

// If reduced motion, replace animations with static opacity changes
const orbStyle = useAnimatedStyle(() => {
  if (reduceMotion) {
    return {
      opacity: state === 'listening' ? 1 : 0.6,
      transform: [{ scale: 1 }], // No animation
    };
  }
  // ... normal animated styles
});
```

---

## 10. Production App Examples

### Pi by Inflection AI

**Architecture:** Cloud-first, proprietary fine-tuned Llama-based models, session-based conversation without persistent context carryover.

**Voice Implementation:**
- Two-way voice interaction on iOS and Android
- Calm, human-like voice tone optimized for empathetic conversation
- Mobile app optimized for hands-free voice conversations
- Consistency across platforms (mobile, web, iMessage)

**Relevance to This Project:** Pi's emotional intelligence and empathetic conversational design is the closest analog to a vocational discernment assessment. Study how Pi creates a "safe space" for vulnerable self-disclosure through voice tone and conversational pacing.

**Source:** [Pi AI Mobile vs Web 2025](https://www.datastudios.org/post/pi-ai-mobile-vs-web-features-differences-and-performance-in-2025)

### ChatGPT Voice Mode (OpenAI)

**Architecture:** Uses the OpenAI Realtime API for native speech-to-speech without intermediate STT/TTS steps. WebRTC for client-side media handling.

**Key Technical Details:**
- GPT-4o responds to audio inputs in 232-320ms
- WebRTC connection handles media transport
- No longer limits simultaneous sessions (as of February 2025)
- Uses native multimodal model (not cascaded STT -> LLM -> TTS)

**Relevance to This Project:** The Realtime API is the lowest-latency option but sacrifices control over individual pipeline stages. For a vocational assessment where you need to save transcripts, analyze answers, and maintain conversation history, the cascaded approach (STT -> LLM -> TTS) provides better control.

**Source:** [OpenAI Realtime API Documentation](https://platform.openai.com/docs/guides/realtime)

### ElevenLabs Conversational AI Agents

**Architecture:** WebRTC via LiveKit, React Native SDK with native hooks.

**Implementation Pattern:**

```typescript
import { ElevenLabsProvider, useConversation } from '@elevenlabs/react-native';

function App() {
  return (
    <ElevenLabsProvider audioSessionConfig={{ allowMixingWithOthers: false }}>
      <ConversationScreen />
    </ElevenLabsProvider>
  );
}

function ConversationScreen() {
  const conversation = useConversation({
    onConnect: ({ conversationId }) => {
      console.log('Session started:', conversationId);
    },
    onMessage: (message) => {
      // Both user and AI messages come through here
      updateTranscript(message);
    },
    onModeChange: ({ mode }) => {
      // 'speaking' or 'listening'
      updateOrbState(mode);
    },
    onError: (error) => {
      handleError(error);
    },
  });

  return (
    <Pressable onPress={() => conversation.startSession({ agentId: AGENT_ID })}>
      <AudioOrb state={conversation.isSpeaking ? 'speaking' : 'listening'} />
    </Pressable>
  );
}
```

**Key SDK Properties:**
- `status` -- "connecting" | "connected" | "disconnected"
- `isSpeaking` -- whether the AI is currently outputting audio
- `canSendFeedback` -- whether feedback is available
- `sendUserMessage(text)` -- inject text as if user spoke it
- `sendContextualUpdate(text)` -- send context without triggering a response
- `setMicMuted(boolean)` -- control microphone state

**Dependencies:** `@elevenlabs/react-native`, `@livekit/react-native`, `@livekit/react-native-webrtc`, `livekit-client`

**Limitation:** Requires Expo development builds (not Expo Go).

**Source:** [ElevenLabs React Native SDK](https://github.com/elevenlabs/packages/tree/main/packages/react-native), [Expo + ElevenLabs Guide](https://expo.dev/blog/how-to-build-universal-app-voice-agents-with-expo-and-elevenlabs)

### SayPi (Open Source Voice Interface for AI)

**Architecture:** Browser extension using XState state machine, Silero VAD for speech detection, OpenAI Whisper for transcription, ElevenLabs for TTS.

**Key Patterns:**
- XState-based conversation state machine for complex flow management
- Silero VAD for real-time voice activity detection
- Multi-language support
- Progressive enhancement across browsers

**Relevance to This Project:** SayPi demonstrates the full cascaded pipeline (VAD -> STT -> LLM -> TTS) with XState managing the conversation flow. Good reference for state machine design.

**Source:** [SayPi on GitHub](https://github.com/Pedal-Intelligence/saypi-userscript)

### Speechmatics Flow + React Native

**Architecture:** Real-time audio communication using the Speechmatics Flow API, designed specifically for React Native.

**Source:** [Build a conversational AI app with React Native and Flow](https://docs.speechmatics.com/voice-agents/flow/guides/react-native)

---

## Architecture Decision: Cascaded vs. End-to-End

For the Vocation Finder assessment, **use the cascaded pipeline** (STT -> LLM -> TTS), not an end-to-end solution like OpenAI Realtime API. Here is why:

| Requirement | Cascaded | End-to-End (Realtime API) |
|------------|----------|--------------------------|
| Save user transcripts for analysis | Yes, naturally | Requires additional extraction |
| Custom LLM system prompt per question | Full control | Limited control |
| Send answers to Laravel backend | Easy | Additional plumbing |
| Swap TTS provider | Yes | No (locked to OpenAI voices) |
| Offline fallback at each stage | Yes, per-stage | All-or-nothing |
| Latency | ~1-2s optimized | ~300ms |
| Cost control | Mix providers | Single provider |
| Answer persistence for vocational report | Built-in | Must extract from session |

The assessment report requires analyzing each answer individually and synthesizing patterns across all 20 responses. The cascaded approach gives you clean text at every stage for storage, analysis, and report generation.

---

## Recommended Technology Stack Summary

| Component | Primary Choice | Fallback |
|-----------|---------------|----------|
| **Audio Recording** | `expo-audio` (useAudioRecorder) | `@siteed/expo-audio-studio` for streaming |
| **STT** | OpenAI Whisper API (via Laravel proxy) | `@jamsch/expo-speech-recognition` (on-device) |
| **LLM** | GPT-4o-mini streaming (via Laravel) | Pre-scripted follow-ups |
| **TTS** | OpenAI TTS (tts-1, voice: nova) | `expo-speech` (on-device) |
| **Audio Visualization** | Reanimated animated orb | Static opacity indicator |
| **State Management** | Zustand | -- |
| **Haptics** | expo-haptics | -- |
| **Offline Queue** | AsyncStorage + retry queue | -- |

---

## Implementation Priority Order

1. **Core audio loop** -- Record -> Stop -> Playback (prove the audio round-trip works)
2. **STT integration** -- Whisper API via Laravel proxy
3. **LLM follow-up generation** -- Streaming responses via Laravel
4. **TTS playback** -- OpenAI TTS via Laravel proxy
5. **Conversation state management** -- Zustand store + orchestrator hook
6. **Audio visualization** -- Animated orb component
7. **Haptic feedback** -- Event-based haptic triggers
8. **Offline handling** -- Queue system + on-device fallbacks
9. **Accessibility** -- Text alternatives, screen reader support, reduced motion
10. **Latency optimization** -- Streaming pipeline, VAD, warm-up, prompt caching
