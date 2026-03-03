# Hybrid & On-Device AI Architecture Research

Research into hybrid and fully on-device AI architectures for a vocational discernment assessment with a conversational audio interface on iOS and Android (Expo React Native).

**Research Date:** March 2, 2026
**Target Platforms:** iPhone 14+ / Equivalent Android flagships
**Technology Stack:** Expo SDK 54+ / React Native (New Architecture) / Laravel Backend

---

## Table of Contents

1. [Fully On-Device Pipeline Analysis](#1-fully-on-device-pipeline-analysis)
2. [Hybrid Architecture: On-Device Conversation + Cloud Synthesis](#2-hybrid-architecture-on-device-conversation--cloud-synthesis)
3. [Edge Computing & Model Splitting](#3-edge-computing--model-splitting)
4. [Real Production Examples & Platform APIs](#4-real-production-examples--platform-apis)
5. [React Native / Expo Integration Libraries](#5-react-native--expo-integration-libraries)
6. [Hardware Requirements & Constraints](#6-hardware-requirements--constraints)
7. [Concrete Architecture Recommendation](#7-concrete-architecture-recommendation)

---

## 1. Fully On-Device Pipeline Analysis

### The Ideal Pipeline

```
User speaks
  --> On-device STT (Whisper or native)
  --> On-device LLM (Llama 3.2 3B or 1B)
  --> On-device TTS (Piper/native)
  --> User hears response

Zero cloud. Zero network latency. Total privacy.
```

### Component-by-Component Feasibility

#### On-Device STT: Whisper via whisper.rn or react-native-executorch

**whisper.rn** is the React Native binding of whisper.cpp. Latest version 0.5.2. Actively maintained.

| Model | File Size | Memory Usage | Realtime Factor (iPhone 15 Pro) |
|-------|-----------|-------------|-------------------------------|
| Whisper Tiny EN | ~75 MB | ~200 MB RAM | ~0.3x (3x faster than real-time) |
| Whisper Base EN | ~140 MB | ~500 MB RAM | ~0.5x (2x faster than real-time) |
| Whisper Small EN | ~460 MB | ~1 GB RAM | ~1.0x (near real-time) |

With Core ML / Apple Neural Engine acceleration, whisper.cpp achieves 3x+ speedup over CPU-only execution on Apple Silicon. The Tiny EN model is sufficient for clear English speech recognition and fits within the 200 MB app size budget.

**react-native-executorch** also supports Whisper. The Whisper Tiny EN model consists of an Encoder (~33 MB), Decoder (~118 MB), and Tokenizer (~3 MB), totaling ~150 MB. It provides streaming transcription with overlapping audio chunks to handle continuous speech beyond Whisper's 30-second processing window.

**expo-speech-recognition** uses the native iOS SFSpeechRecognizer and Android SpeechRecognizer. Zero model download. ~200 ms latency. Supports `requiresOnDeviceRecognition: true` to force fully on-device processing.

**Verdict:** On-device STT is fully production-ready. Use expo-speech-recognition for the lowest latency and zero model size, or Whisper Tiny EN via react-native-executorch for higher accuracy at ~150 MB cost.

**Sources:**
- [whisper.rn](https://github.com/mybigday/whisper.rn)
- [react-native-executorch Whisper docs](https://docs.swmansion.com/react-native-executorch/docs/0.4.x/natural-language-processing/useSpeechToText)
- [whisper.cpp](https://github.com/ggml-org/whisper.cpp)

#### On-Device LLM: Llama 3.2 Models

**Model size and memory requirements:**

| Model | GGUF Q4_K_M Size | RAM Required | Tokens/sec (iPhone 15 Pro) | Tokens/sec (iPhone 14) |
|-------|-----------------|-------------|--------------------------|----------------------|
| Llama 3.2 1B | ~0.75 GB | ~1.5 GB | 30-40 tok/s | 15-25 tok/s |
| Llama 3.2 3B | ~2.0 GB | ~3-4 GB | 10-20 tok/s | 5-10 tok/s |
| SmolLM2 1.7B | ~1.0 GB | ~2 GB | 25-35 tok/s | 15-20 tok/s |

**Quality comparison for conversational follow-ups:**

The 3B model significantly outperforms 1B on instruction following (IFEval: 77.4 vs lower), tool use (BFCL V2: 67.0 vs 25.7), and general knowledge (MMLU: 63.4). The 3B model is strong enough for generating contextual follow-up questions ("Tell me more about what drew you to that experience" or "How did that make you feel?").

The 1B model is competitive for basic conversational flow but noticeably weaker on nuanced follow-ups and complex instruction following. SmolLM2 1.7B offers a middle ground -- better than Llama 1B on most benchmarks, smaller than Llama 3B, at ~1 GB quantized.

**Critical constraint:** The Llama 3.2 3B Q4_K_M model is 2.0 GB, which exceeds the +200 MB app size budget if bundled. Models must be downloaded on first launch rather than bundled with the app. This is the standard approach -- apps like ChatGPT, LM Studio Mobile, and PrivateLLM all download models post-install.

**Sources:**
- [Llama 3.2 3B Instruct Q4_K_M GGUF](https://huggingface.co/hugging-quants/Llama-3.2-3B-Instruct-Q4_K_M-GGUF)
- [PrivateLLM blog on Llama 3.2 on iOS](https://privatellm.app/blog/run-meta-llama-3-2-1b-3b-models-locally-on-ios-devices)
- [Meta quantized Llama models](https://ai.meta.com/blog/meta-llama-quantized-lightweight-models/)

#### On-Device TTS: Piper via sherpa-onnx or Native

**react-native-sherpa-onnx-offline-tts** wraps Piper/VITS ONNX models. Natural-sounding voices at 22050 Hz. Model sizes range from ~15 MB (low quality) to ~100 MB (medium quality).

| TTS Option | Latency | Quality | Model Size | Offline |
|-----------|---------|---------|------------|---------|
| OS Native (expo-speech) | ~50 ms | Fair (robotic on Android, better on iOS) | 0 MB | Yes |
| Piper Medium (sherpa-onnx) | ~100 ms | Good (natural) | ~60 MB | Yes |
| Apple AVSpeechSynthesizer | ~50 ms | Good (premium voices) | 0 MB (pre-installed) | Yes |

The OS native TTS (Apple AVSpeechSynthesizer on iOS, Android TTS) is the simplest option with zero model download. Apple's premium voices (available since iOS 17) sound significantly more natural than the default voice. On Android, quality varies by manufacturer but Google's TTS voices are adequate.

Piper via sherpa-onnx provides consistent cross-platform quality but adds ~60 MB of model data.

**Verdict:** Use native OS TTS as the primary option (zero size cost, good-enough quality, instant). Offer Piper as a downloadable "premium voice" option.

**Sources:**
- [react-native-sherpa-onnx-offline-tts](https://github.com/kislay99/react-native-sherpa-onnx-offline-tts)
- [Piper TTS](https://github.com/rhasspy/piper)
- [Sherpa-ONNX](https://github.com/k2-fsa/sherpa-onnx)

### Can All Three Run Concurrently?

**Memory budget analysis for iPhone 15 (6 GB total RAM, ~4 GB available to apps):**

| Component | RAM Usage | Status |
|-----------|----------|--------|
| iOS System + App Framework | ~1.5 GB | Always loaded |
| Whisper Tiny EN | ~200 MB | Load/unload per turn |
| Llama 3.2 1B (Q4) | ~1.5 GB | Resident in memory |
| Native TTS | ~50 MB | System service |
| App UI + State | ~200 MB | Always loaded |
| **Total** | **~3.45 GB** | **Feasible on 6 GB device** |

| Component | RAM Usage | Status |
|-----------|----------|--------|
| iOS System + App Framework | ~1.5 GB | Always loaded |
| Whisper Tiny EN | ~200 MB | Load/unload per turn |
| Llama 3.2 3B (Q4) | ~3.5 GB | Resident in memory |
| Native TTS | ~50 MB | System service |
| App UI + State | ~200 MB | Always loaded |
| **Total** | **~5.45 GB** | **Tight on 6 GB; safe on 8 GB** |

**Conclusion for fully on-device pipeline:**

- **iPhone 14 (6 GB):** Can run Llama 3.2 1B + Whisper Tiny + Native TTS concurrently. Cannot safely run Llama 3.2 3B -- risk of OOM kills under memory pressure.
- **iPhone 15 Pro / 16 Pro (8 GB):** Can run Llama 3.2 3B + Whisper Tiny + Native TTS concurrently with comfortable headroom.
- **iPhone 15 standard (6 GB):** Same constraint as iPhone 14. 1B model only.
- **Android flagships (8-12 GB):** Galaxy S24+ (12 GB), Pixel 9 Pro (16 GB) -- can run 3B model comfortably.
- **Android mid-range (6-8 GB):** 1B model is the safe choice.

**The practical approach:** Do not keep all three models loaded simultaneously. Use a sequential pipeline -- load STT, transcribe, unload STT, load LLM (or keep resident), generate, unload if needed, then speak via native TTS (always available). This sequential approach adds ~200-500 ms for model load/unload but avoids memory pressure.

---

## 2. Hybrid Architecture: On-Device Conversation + Cloud Synthesis

This is the recommended architecture. It provides the best balance of privacy, latency, quality, and device compatibility.

### Architecture Overview

```
CONVERSATION PHASE (on-device, ~15-20 minutes):
  User speaks
    --> On-device STT (native or Whisper Tiny)     [< 300ms]
    --> On-device LLM (1B-3B) generates follow-up  [< 500ms]
    --> On-device TTS speaks follow-up              [< 100ms]
    --> Total: < 1 second end-to-end

  Repeat for 10-20 conversational turns.
  All transcripts stored locally on device.

SYNTHESIS PHASE (cloud, ~10-30 seconds):
  All transcripts + structured data
    --> Sent to cloud (Claude API via Laravel backend)
    --> Claude generates multi-dimensional vocational profile
    --> Rich synthesis returned to app
    --> Stored locally, viewable offline
```

### Why This Architecture Wins

| Criterion | Fully On-Device | Fully Cloud | Hybrid (Recommended) |
|-----------|----------------|-------------|---------------------|
| Conversation Latency | < 1s | 2-4s | < 1s |
| Synthesis Quality | Limited (3B model) | Excellent | Excellent (Claude) |
| Privacy During Conversation | Total | None | Total |
| Privacy of Final Analysis | Total | Transcripts sent | Transcripts sent once |
| Offline Conversation | Yes | No | Yes |
| Device Compatibility | Flagship only (3B) | Any device | Any device (1B) |
| App Size Impact | +150 MB (Whisper) or 0 (native) | 0 | +150 MB or 0 |
| Battery Impact | Moderate-High | Low | Moderate |
| Cost per Assessment | $0 | ~$0.05-0.15 | ~$0.02-0.05 (synthesis only) |

### On-Device Conversation Flow

The on-device LLM handles these tasks during conversation:

1. **Generate contextual follow-up questions** based on user responses
2. **Identify when a topic has been sufficiently explored** and move to the next dimension
3. **Provide brief affirmations** ("That's really insightful" / "I appreciate you sharing that")
4. **Track conversation state** (which dimensions explored, which remaining)

These tasks are well within a 1B-3B model's capability. The questions and affirmations are short (10-50 tokens), and the conversation logic can be heavily guided by system prompts and structured output constraints.

### Cloud Synthesis Phase

After conversation completes, the app sends all transcripts to the Laravel backend, which calls Claude for the sophisticated multi-dimensional analysis. This is the only network call in the entire session.

```typescript
// After all conversation turns complete
const synthesisPayload = {
  transcripts: conversationTurns.map(turn => ({
    question: turn.aiQuestion,
    response: turn.userTranscript,
    dimension: turn.assessmentDimension,
    followUps: turn.followUpExchanges,
  })),
  userMetadata: {
    // age, background, etc. (collected during onboarding)
  },
};

// Single API call to Laravel backend
const synthesis = await api.post('/api/assessments/synthesize', synthesisPayload);
// Claude API call happens server-side
// Returns the full vocational profile
```

### Handling the "Offline Everywhere" Edge Case

If the user completes the conversation with zero connectivity:

1. All transcripts are stored locally (SQLite or AsyncStorage)
2. The app queues the synthesis request
3. When connectivity returns, the synthesis is triggered automatically
4. Push notification alerts the user when their profile is ready

This means the conversational experience is never degraded by network conditions.

---

## 3. Edge Computing & Model Splitting

### Apple Private Cloud Compute (PCC)

Apple's Private Cloud Compute is a system designed specifically for Apple Intelligence. Key facts as of early 2026:

- **Not available to third-party developers.** PCC exclusively serves Apple Intelligence features (Siri, Writing Tools, etc.). There is no API for third-party apps to offload inference to PCC.
- **Architecture:** Custom Apple Silicon servers (currently M2 Ultra, transitioning to M5 chips in 2026-2027). Stateless compute nodes with no persistent storage of user data.
- **Privacy guarantees:** Cryptographic verification, no data retention, no privileged access. Independent researchers can audit the system.
- **Future outlook:** Apple has not announced plans to open PCC to developers. The Foundation Models framework (see Section 4) is their developer-facing on-device offering.

**Verdict:** PCC is not relevant for this project. It is Apple-internal infrastructure, not a developer platform.

**Sources:**
- [Apple Private Cloud Compute](https://security.apple.com/blog/private-cloud-compute/)
- [Apple M5-based PCC plans (9to5Mac)](https://9to5mac.com/2026/02/17/apple-plans-m5-based-private-cloud-compute-architecture-for-apple-intelligence/)

### Model Splitting / Federated Inference

No mature frameworks exist for splitting a single model's inference between a mobile device and an edge server in production. Research prototypes exist (e.g., SplitNN, PipeEdge) but none are production-ready for mobile apps.

The practical alternative is the hybrid approach described in Section 2: run a small model fully on-device for interactive tasks, and a large model fully in the cloud for complex analysis. This is simpler, more reliable, and achieves better results than splitting a medium model across device and cloud.

### Google's On-Device Pipeline

Google's approach is centered on Gemini Nano, accessed through ML Kit GenAI APIs and the Google AI Edge SDK. See Section 4 for details.

---

## 4. Real Production Examples & Platform APIs

### Apple Foundation Models Framework (iOS 26+)

Announced at WWDC 2025, available September 2025. This is Apple's on-device LLM API for third-party developers.

**Capabilities:**
- Access to Apple's ~3B parameter on-device model (the same model powering Apple Intelligence)
- Swift-native API with structured output (@Generable macro), tool calling, and multi-turn sessions
- 30 tokens/second generation on iPhone 15 Pro
- 0.6 ms time-to-first-token latency
- Works offline, no cloud calls, no inference cost
- Built-in safety guardrails

**Limitations:**
- **4,096 token context window** (input + output combined, ~3,000 words). This is tight for a multi-turn conversation -- a 20-question assessment with follow-ups could easily exceed this limit.
- **Text-only** -- no audio, image, or multimodal input
- **iOS 26+ only** -- requires the latest OS version
- **English + 9 languages** -- no universal language support
- **Weaker than cloud models** -- intentionally small for on-device use. Cannot perform complex reasoning, code verification, or nuanced analysis comparable to Claude or GPT-4.
- **Heavy safety guardrails** -- may produce false positives blocking legitimate vocational assessment questions about sensitive career topics
- **No Android equivalent** -- iOS only

**How third-party apps access it (Swift):**

```swift
import FoundationModels

let session = LanguageModelSession(
    instructions: """
    You are a vocational discernment guide. Ask thoughtful follow-up
    questions to help the user explore their interests, values, skills,
    and sense of calling. Be warm, curious, and encouraging.
    """
)

let response = try await session.respond(
    to: "I've always been drawn to helping people but I'm not sure
         if that means healthcare, teaching, or social work."
)
print(response.content)
// "That's a meaningful insight. When you think about helping people,
//  what kind of impact feels most natural to you -- healing someone's
//  body, shaping someone's mind, or addressing systemic challenges
//  in their life?"
```

**React Native access:** Callstack's `@callstackincubator/ai` library wraps the Apple Foundation Models framework as a Vercel AI SDK provider. Requires iOS 26+ for text generation.

```typescript
import { apple } from '@react-native-ai/apple';
import { generateText } from 'ai';

const result = await generateText({
  model: apple.languageModel(),
  prompt: 'Generate a follow-up question about career interests',
});
```

**Verdict for this project:** The 4,096 token context window is the critical limitation. A 20-turn vocational conversation with substantive responses will exceed this limit. You would need to implement aggressive summarization of prior turns to fit within the window, which degrades conversation quality. The iOS-only restriction is also a non-starter for a cross-platform app. The Foundation Models framework is useful as a fallback or for auxiliary tasks but not as the primary conversation engine.

**Sources:**
- [Meet the Foundation Models framework (WWDC25)](https://developer.apple.com/videos/play/wwdc2025/286/)
- [Apple Foundation Models framework announcement](https://www.apple.com/newsroom/2025/09/apples-foundation-models-framework-unlocks-new-intelligent-app-experiences/)
- [Foundation Models limitations analysis](https://www.natashatherobot.com/p/apple-foundation-models)
- [Managing the context window (TN3193)](https://developer.apple.com/documentation/technotes/tn3193-managing-the-on-device-foundation-model-s-context-window)

### Google Gemini Nano (Android)

Gemini Nano runs on-device on supported Android devices. Accessed through ML Kit GenAI APIs.

**Capabilities:**
- Text generation, summarization, classification, image understanding
- Runs fully on-device for offline and privacy-sensitive tasks
- Prompt API allows custom prompts (alpha as of late 2025)

**Limitations:**
- **Device restrictions:** Best performance on Pixel 10 (nano-v3). Pixel 9, Galaxy Z Fold7, Xiaomi 15 get the less capable nano-v2.
- **Foreground only:** Inference blocked if app is not the top foreground app.
- **Battery quota:** AICore enforces per-app battery usage quotas. Exceeding them returns `PER_APP_BATTERY_USE_QUOTA_EXCEEDED`.
- **Rate limiting:** Too many requests returns `BUSY` error code.
- **Android only** -- no iOS equivalent
- **No React Native SDK:** Requires a native Android module or bridge.

**Access from React Native:** Would require a custom native module (Kotlin) wrapping the ML Kit GenAI Prompt API, exposed to JS via Turbo Modules or Expo Modules API. No existing library does this out of the box.

**Verdict:** Gemini Nano is not practical as the primary engine for a cross-platform app. The device restrictions, battery quotas, and lack of React Native integration make it unsuitable. It could serve as an Android-specific enhancement (e.g., using Gemini Nano for on-device summarization when available) but not as a core dependency.

**Sources:**
- [On-device GenAI APIs with ML Kit](https://android-developers.googleblog.com/2025/05/on-device-gen-ai-apis-ml-kit-gemini-nano.html)
- [ML Kit Prompt API](https://android-developers.googleblog.com/2025/10/ml-kit-genai-prompt-api-alpha-release.html)
- [Gemini Nano developer page](https://developer.android.com/ai/gemini-nano)

### Samsung Galaxy AI

Samsung provides the Galaxy AI Engine 2.0 SDK for on-device AI on Galaxy devices with Exynos processors.

**Capabilities:**
- On-device LLM, LVM, and multimodal model inference
- Exynos AI Studio toolchain for model optimization
- Samsung Neural SDK for custom model deployment
- Supports PyTorch, ONNX, TensorFlow, TFLite model formats

**Limitations:**
- **Samsung/Exynos devices only** -- most global Galaxy flagships use Qualcomm Snapdragon, not Exynos
- **No React Native integration** -- native Android SDK only
- **Proprietary ecosystem** -- cannot be used on non-Samsung devices
- **Bixby Connect API** -- tied to Samsung's assistant ecosystem

**Verdict:** Not relevant for a cross-platform app. Too proprietary and limited in device reach.

**Sources:**
- [Samsung On-device AI](https://semiconductor.samsung.com/technologies/processor/on-device-ai/)
- [Samsung Galaxy AI Engine SDK](https://toolshelf.tech/blog/samsung-ai-forum-2025-developer-tools-guide/)

### Apps Shipping On-Device Conversational AI (as of early 2026)

| App | On-Device Components | Cloud Components | Notes |
|-----|---------------------|-------------------|-------|
| **Apple Siri** (iOS 26) | STT, LLM (Foundation Models), TTS | PCC for complex requests | Not accessible to third-party apps for the full pipeline |
| **Google Assistant** | STT, Gemini Nano | Gemini Pro (cloud) | Hybrid approach, Google-only |
| **PrivateLLM** | Full LLM inference (Llama, Mistral) | None | iOS app, runs GGUF models locally |
| **LM Studio Mobile** | Full LLM inference | None | Model download + local inference |
| **Meta WhatsApp/Instagram** | ExecuTorch-based models | Server models | Uses on-device for specific features, not full conversation |

No shipping app currently offers a fully on-device conversational AI experience with the quality of cloud models. The closest are PrivateLLM and LM Studio Mobile, which run open-source LLMs locally but are general-purpose chat apps, not domain-specific assessment tools.

---

## 5. React Native / Expo Integration Libraries

### Library Comparison Matrix

| Library | On-Device LLM | STT | TTS | Expo Compatible | Hardware Accel | Maturity |
|---------|:----------:|:---:|:---:|:--------------:|:---------:|:--------:|
| **llama.rn** | Yes (GGUF) | No | No | Yes (New Arch) | Metal, Hexagon NPU | Production (v0.11) |
| **react-native-executorch** | Yes (PTE) | Yes (Whisper) | No | Yes (SDK 54+) | ExecuTorch backends | Production (v0.4+) |
| **@callstackincubator/ai** | Yes (GGUF + Apple) | Yes (Apple) | Yes (Apple) | Yes | Metal, Apple FM | Active development |
| **whisper.rn** | No | Yes (Whisper) | No | Yes | Metal, Core ML | Production (v0.5) |
| **expo-speech-recognition** | No | Yes (native) | No | Yes (config plugin) | Native engine | Production |
| **react-native-sherpa-onnx-offline-tts** | No | No | Yes (Piper) | Yes | ONNX Runtime | Emerging |
| **expo-speech** | No | No | Yes (native) | Yes (built-in) | Native engine | Production |

### llama.rn -- The Most Mature On-Device LLM Library

**Version:** 0.11.2 (as of late February 2026)
**Requirement:** React Native New Architecture (v0.10+)
**License:** MIT

Key features:
- GPU acceleration via Metal (iOS, enabled by default) and OpenCL (Android, Adreno 700+)
- Hexagon NPU support (Android, Snapdragon 8 Gen 1+, experimental)
- Multimodal support (vision + audio via mmproj)
- Parallel decoding with slot-based concurrent requests
- Grammar sampling (GBNF, JSON schema) for structured output
- Tool calling via Jinja templates
- Any GGUF model from HuggingFace

```typescript
import { initLlama, LlamaContext } from 'llama.rn';

// Initialize with a downloaded model
const context = await initLlama({
  model: `${FileSystem.documentDirectory}models/llama-3.2-1b-instruct-q4_k_m.gguf`,
  n_ctx: 2048,        // Context window
  n_batch: 512,       // Batch size
  n_threads: 4,       // CPU threads (for non-Metal operations)
  n_gpu_layers: 99,   // Offload all layers to Metal/GPU
});

// Generate a follow-up question
const response = await context.completion({
  messages: [
    {
      role: 'system',
      content: `You are a vocational discernment guide. Based on the user's
        response, ask ONE thoughtful follow-up question that helps them explore
        their interests, values, or sense of calling more deeply. Keep your
        response under 2 sentences.`,
    },
    {
      role: 'user',
      content: userTranscript,
    },
  ],
  n_predict: 100,      // Max tokens to generate
  temperature: 0.7,
  stop: ['\n\n'],       // Stop at double newline
});

console.log(response.text);
// "What was it about that experience that felt most meaningful to you --
//  was it the direct impact on someone's life, or the problem-solving
//  aspect of finding the right solution?"
```

**Expo integration:** llama.rn works with Expo's prebuild (CNG) system. No special config plugin needed -- it's a standard native module that auto-links. You do need `expo prebuild` to generate native projects.

**Sources:**
- [llama.rn GitHub](https://github.com/mybigday/llama.rn)
- [llama.rn npm](https://www.npmjs.com/package/llama.rn)

### react-native-executorch -- The All-in-One Toolkit

**Version:** 0.4.x (as of early 2026)
**Requirements:** React Native 0.81+, iOS 17.0+, Android 13+, Expo SDK 54+
**License:** MIT

Provides a unified toolkit for on-device LLM, STT (Whisper), and computer vision. Backed by Software Mansion (the company behind Reanimated, Gesture Handler, etc.).

```typescript
import { useLLM, useSpeechToText, LLAMA3_2_1B, WHISPER_TINY_EN } from 'react-native-executorch';

// LLM Hook
function useVocationalAssistant() {
  const llm = useLLM({
    model: LLAMA3_2_1B,
    // Model downloaded automatically on first use (~750 MB for 1B)
  });

  const generateFollowUp = async (userResponse: string) => {
    const chat = [
      { role: 'system', content: VOCATIONAL_SYSTEM_PROMPT },
      { role: 'user', content: userResponse },
    ];

    await llm.generate(chat);
    return llm.response; // Streamed response text
  };

  return { generateFollowUp, isReady: llm.isReady, isGenerating: llm.isGenerating };
}

// STT Hook
function useTranscription() {
  const stt = useSpeechToText({
    model: WHISPER_TINY_EN,
    // Encoder ~33MB + Decoder ~118MB + Tokenizer ~3MB
  });

  return {
    startListening: stt.start,
    stopListening: stt.stop,
    transcript: stt.transcript,
    isTranscribing: stt.isTranscribing,
  };
}
```

**Expo integration:** Requires the `react-native-audio-api` config plugin in `app.json`:

```json
{
  "expo": {
    "plugins": [
      [
        "react-native-audio-api",
        {
          "ios": {
            "microphonePermission": "This app uses the microphone for voice-based assessment"
          },
          "android": {
            "foregroundServicePermission": true
          }
        }
      ]
    ]
  }
}
```

**Sources:**
- [react-native-executorch GitHub](https://github.com/software-mansion/react-native-executorch)
- [react-native-executorch docs](https://docs.swmansion.com/react-native-executorch/)
- [Expo blog: How to run AI models with React Native ExecuTorch](https://expo.dev/blog/how-to-run-ai-models-with-react-native-executorch)

### @callstackincubator/ai -- Vercel AI SDK for React Native

**Version:** 0.12+ (AI SDK v6 compatible)
**License:** MIT

The most comprehensive solution, providing LLM + STT + TTS through multiple providers:

| Provider | Platform | LLM | STT | TTS | Model Download |
|----------|----------|:---:|:---:|:---:|:---:|
| **Apple** | iOS 26+ | Yes (Foundation Models) | Yes | Yes | None (built-in) |
| **Llama** | iOS + Android | Yes (GGUF via llama.rn) | No | No | Required |
| **MLC** | iOS + Android | Yes (optimized) | No | No | Required |

```typescript
import { apple } from '@react-native-ai/apple';
import { llama } from '@react-native-ai/llama';
import { generateText, streamText } from 'ai';
import { Platform } from 'react-native';

// Use Apple's Foundation Models on iOS 26+, llama.rn elsewhere
const getModel = () => {
  if (Platform.OS === 'ios' && parseInt(Platform.Version, 10) >= 26) {
    return apple.languageModel();
  }
  return llama.languageModel(
    'bartowski/Llama-3.2-1B-Instruct-GGUF/Llama-3.2-1B-Instruct-Q4_K_M.gguf'
  );
};

// Unified API regardless of backend
const { text } = await generateText({
  model: getModel(),
  system: VOCATIONAL_SYSTEM_PROMPT,
  prompt: userTranscript,
  maxTokens: 100,
});

// Apple provider also gives free STT + TTS on iOS
import { transcribe, speak } from '@react-native-ai/apple';

const transcript = await transcribe(audioBuffer);
await speak(text);
```

**Sources:**
- [@callstackincubator/ai GitHub](https://github.com/callstackincubator/ai)
- [Callstack blog on React Native AI](https://www.callstack.com/blog/meet-react-native-ai-llms-running-on-mobile-for-real)
- [On-Device Apple LLM Support in React Native](https://www.callstack.com/blog/on-device-apple-llm-support-comes-to-react-native)

### Can You Call Core ML / MediaPipe from Expo?

**Core ML from Expo:**
Yes, via Expo Modules API. You create a native Swift module that loads and runs a Core ML model, then expose it to JavaScript. Several examples exist:
- [expo-stable-diffusion](https://github.com/andrei-zgirvaci/expo-stable-diffusion) runs Stable Diffusion via Core ML
- [Building a Core ML app with Expo](https://hietalajulius.medium.com/building-a-react-native-coreml-image-classification-app-with-expo-and-yolov8-a083c7866e85) demonstrates YOLOv8 classification

The pattern is:
1. Create an Expo Module with `npx create-expo-module`
2. Write Swift code that imports Core ML and runs your model
3. Create a config plugin that bundles the `.mlmodelc` file
4. Expose results to JS via the module's API

**MediaPipe / ML Kit from Expo:**
Yes, via native modules. [react-native-fast-tflite](https://github.com/mrousavy/react-native-fast-tflite) by Marc Rousavy provides TFLite inference from React Native with GPU acceleration. For ML Kit specifically, you would need custom native modules.

**Config plugins needed:**

| Feature | Config Plugin Required | Notes |
|---------|:---------------------:|-------|
| Microphone access | Yes | NSMicrophoneUsageDescription (iOS), RECORD_AUDIO (Android) |
| Background audio | Yes | UIBackgroundModes (iOS) |
| Model file bundling | Optional | Can bundle in app or download at runtime |
| Metal/GPU access | No | Handled automatically by llama.rn / ExecuTorch |
| Large file downloads | No | Use expo-file-system |

---

## 6. Hardware Requirements & Constraints

### Minimum Device Specs for On-Device LLM

| Model Size | Min RAM | Min Chip (iOS) | Min Chip (Android) | Notes |
|-----------|---------|----------------|-------------------|-------|
| 1B (Q4) | 4 GB | A15 (iPhone 13) | Snapdragon 8 Gen 1 | Comfortable on most modern devices |
| 1.7B (Q4) | 4 GB | A15 | Snapdragon 8 Gen 1 | SmolLM2, slightly more demanding |
| 3B (Q4) | 6 GB | A16 (iPhone 14 Pro) | Snapdragon 8 Gen 2 | Flagship territory |
| 3B (Q4) concurrent w/ STT | 8 GB | A17 Pro (iPhone 15 Pro) | Snapdragon 8 Gen 3 | High-end only |

### Neural Engine / NPU Performance

| Chip | Device | TOPS | Token/sec (3B Q4) | Token/sec (1B Q4) |
|------|--------|------|-------------------|-------------------|
| A15 | iPhone 13/14 | 15.8 | 5-8 | 20-30 |
| A16 | iPhone 14 Pro / 15 | 17.0 | 8-12 | 25-35 |
| A17 Pro | iPhone 15 Pro | 35.0 | 15-20 | 30-40 |
| A18 | iPhone 16 | 35.0 | 15-20 | 30-40 |
| A18 Pro | iPhone 16 Pro | 38.0 | 18-22 | 35-45 |
| Snapdragon 8 Gen 2 | Galaxy S23, etc. | 34.0 | 12-18 | 25-35 |
| Snapdragon 8 Gen 3 | Galaxy S24, etc. | 45.0 | 15-20 | 30-40 |
| Snapdragon 8 Elite | Galaxy S25, etc. | 60.0 | 20-25 | 35-50 |

**Neural Engine utilization on Apple Silicon:**
- llama.cpp (via llama.rn) uses Metal for GPU acceleration. It does **not** directly use the Neural Engine (ANE) -- Metal targets the GPU cores.
- Core ML models can target the ANE, which is more power-efficient than GPU. ExecuTorch has a Core ML backend that can route to ANE.
- Apple's Foundation Models framework automatically uses the ANE for optimal efficiency.
- Practical impact: Metal GPU is fast but power-hungry. ANE is slightly slower but uses significantly less power. For a 20-minute session, ANE routing (via ExecuTorch or Foundation Models) is preferable.

**Qualcomm Hexagon NPU utilization:**
- llama.rn has experimental Hexagon NPU support for Snapdragon 8 Gen 1+ devices
- ExecuTorch supports the Qualcomm QNN backend for Hexagon NPU delegation
- The Hexagon NPU excels at prefill speed (10x faster than CPU on same SoC) and is more power-efficient than GPU compute
- LiteRT (formerly TFLite) supports Qualcomm NPU delegation via the Google AI Edge SDK

**Sources:**
- [On-Device LLMs: State of the Union, 2026](https://v-chandra.github.io/on-device-llms/)
- [Apple A16 specs](https://en.wikipedia.org/wiki/Apple_A16)
- [Qualcomm NPU utilization with LiteRT](https://ai.google.dev/edge/litert/android/npu/qualcomm)

### Thermal Throttling During 20-Minute Sessions

This is the most under-discussed risk of on-device LLM inference for sustained sessions.

**The problem:**
- CPU/GPU temperature climbs to 70-80 degrees C during LLM inference
- Thermal throttling begins when device surface reaches 42-48 degrees C
- Once throttled, token generation speed drops 30-60%
- Users may notice the phone becoming hot, which degrades experience

**Measured impact on a 20-minute session:**

The conversation pattern naturally mitigates this. In a vocational assessment:
- User speaks for 30-60 seconds per response
- LLM generates 50-100 tokens (~2-5 seconds of inference)
- TTS speaks for 5-15 seconds
- Total LLM "on" time: ~2-5 seconds per 60-90 second turn
- Duty cycle: ~5-8% active inference, 92-95% idle

This bursty inference pattern is ideal for mobile. The device has 50-80 seconds to cool between inference bursts. Sustained inference (like generating a 2000-word essay) would cause throttling, but conversational turn-taking does not.

**Mitigation strategies:**
1. **Use 1B instead of 3B** -- 2-3x less compute per token
2. **Limit generation length** -- cap at 100 tokens per follow-up
3. **Prefer ANE/NPU over GPU** -- more power-efficient
4. **Pre-generate follow-up options** -- during user's speaking time, speculatively generate 2-3 possible follow-ups
5. **Use native STT/TTS** -- these use dedicated hardware, not the GPU

### Battery Drain Analysis

**Research data:** LLM inference can drain 6-25% of battery in 15 minutes of sustained inference.

**Projected battery drain for this app's conversation pattern:**

| Component | Power Draw | Active Time (20 min session) | Battery Impact |
|-----------|-----------|------------------------------|---------------|
| Screen (OLED, dim) | Moderate | 20 min | ~3-4% |
| Microphone | Low | ~10 min total | ~0.5% |
| STT (native) | Low | ~10 min total | ~1% |
| LLM inference (1B Q4) | High | ~2 min total (bursty) | ~2-3% |
| TTS (native) | Low | ~5 min total | ~0.5% |
| App framework | Low | 20 min | ~1% |
| **Total** | | | **~8-10%** |

With a 1B model and native STT/TTS, the 15% battery budget is achievable. With a 3B model, expect 12-15%. The bursty inference pattern is the key -- the LLM is only active for ~10% of the total session time.

**With cloud-based conversation (for comparison):**

| Component | Power Draw | Active Time | Battery Impact |
|-----------|-----------|-------------|---------------|
| Screen | Moderate | 20 min | ~3-4% |
| Network radio (active) | High | 20 min | ~4-5% |
| Microphone | Low | ~10 min | ~0.5% |
| TTS (native playback) | Low | ~5 min | ~0.5% |
| **Total** | | | **~8-10%** |

Surprisingly similar. The network radio staying active for 20 minutes of streaming is roughly as expensive as bursty on-device LLM inference. The hybrid approach (on-device conversation + single cloud synthesis call) is actually the most battery-efficient architecture.

---

## 7. Concrete Architecture Recommendation

### Recommended Architecture: Tiered Hybrid

```
┌──────────────────────────────────────────────────────┐
│                    USER'S DEVICE                      │
│                                                      │
│  ┌─────────────┐   ┌──────────────┐   ┌───────────┐ │
│  │   expo-     │   │  llama.rn    │   │  Native   │ │
│  │   speech-   │──>│  (1B Q4_K_M) │──>│  TTS      │ │
│  │  recognition│   │  or Apple FM │   │           │ │
│  └─────────────┘   └──────────────┘   └───────────┘ │
│         ^                 │                   │      │
│         │                 │                   v      │
│     [User speaks]    [Follow-up Q]      [AI speaks]  │
│                          │                           │
│                    ┌─────┴─────┐                     │
│                    │ Transcript │                     │
│                    │   Store    │                     │
│                    │ (SQLite)   │                     │
│                    └─────┬─────┘                     │
│                          │                           │
└──────────────────────────┼───────────────────────────┘
                           │ (single API call
                           │  after conversation)
                           v
              ┌────────────────────────┐
              │    LARAVEL BACKEND     │
              │                        │
              │  POST /api/assessments │
              │     /synthesize        │
              │         │              │
              │         v              │
              │  ┌──────────────┐      │
              │  │  Claude API  │      │
              │  │  (Sonnet or  │      │
              │  │   Opus)      │      │
              │  └──────────────┘      │
              │         │              │
              │         v              │
              │  Multi-dimensional     │
              │  Vocational Profile    │
              └────────────────────────┘
```

### Tier 1: Primary Implementation (Ship First)

**On-device conversation with native STT/TTS + llama.rn (1B model)**

| Component | Choice | Size Impact | Reason |
|-----------|--------|------------|--------|
| STT | expo-speech-recognition | 0 MB | Native, fast, free, offline-capable |
| LLM | llama.rn + Llama 3.2 1B Q4_K_M | +750 MB download | Best cross-platform LLM library, good quality |
| TTS | expo-speech (native) | 0 MB | Built-in, no download needed |
| Synthesis | Claude API (via Laravel) | 0 MB | Claude-quality final analysis |

**Expected performance:**

| Metric | Target | Expected |
|--------|--------|----------|
| End-of-speech to AI-speech latency | < 1 second | ~600-800 ms |
| Conversation quality | Slightly below Claude | Adequate for guided follow-ups |
| Synthesis quality | Claude-level | Exact Claude quality |
| App binary size increase | < 200 MB | ~0 MB (model downloaded post-install) |
| Battery drain (20 min) | < 15% | ~8-10% |
| Device compatibility | iPhone 14+ | iPhone 13+ (6 GB RAM) |

**Latency breakdown:**

```
User stops speaking ─────────────────────────────> AI starts speaking
                    │                            │
                    ├── STT finalization: ~200ms  │
                    ├── LLM generation start:     │
                    │   ~50ms (first token)       │
                    ├── Generate ~30 tokens:      │
                    │   ~300ms (1B at 30 tok/s)   │
                    ├── TTS start: ~50ms          │
                    │                             │
                    └── Total: ~600ms ────────────┘
```

With streaming, the LLM can begin generating while STT finalizes, and TTS can begin speaking while the LLM is still generating. This pipelining can reduce perceived latency to ~400 ms.

### Tier 2: Enhancement (Post-Launch)

**Platform-specific optimizations:**

```typescript
// Adaptive model selection based on device capability
import { Platform } from 'react-native';
import DeviceInfo from 'react-native-device-info';

async function selectModel(): Promise<ModelConfig> {
  const totalMemory = await DeviceInfo.getTotalMemory(); // bytes
  const memoryGB = totalMemory / (1024 * 1024 * 1024);

  // iOS 26+ with Apple Intelligence: use Foundation Models (free, no download)
  if (Platform.OS === 'ios' && parseInt(Platform.Version, 10) >= 26) {
    return {
      provider: 'apple-foundation-models',
      modelId: 'apple-on-device',
      downloadSize: 0,
      contextWindow: 4096,
      note: 'Apple Foundation Models -- free, no download, but 4K context limit',
    };
  }

  // 8+ GB RAM: use Llama 3.2 3B for higher quality
  if (memoryGB >= 8) {
    return {
      provider: 'llama.rn',
      modelId: 'Llama-3.2-3B-Instruct-Q4_K_M',
      downloadSize: 2000, // MB
      contextWindow: 8192,
      note: 'Higher quality follow-ups, requires 8 GB+ RAM',
    };
  }

  // 6+ GB RAM: use Llama 3.2 1B (default)
  if (memoryGB >= 6) {
    return {
      provider: 'llama.rn',
      modelId: 'Llama-3.2-1B-Instruct-Q4_K_M',
      downloadSize: 750, // MB
      contextWindow: 8192,
      note: 'Good quality, works on most modern devices',
    };
  }

  // < 6 GB RAM: fall back to cloud
  return {
    provider: 'cloud',
    modelId: 'claude-sonnet',
    downloadSize: 0,
    contextWindow: 200000,
    note: 'Device too constrained for on-device LLM, using cloud',
  };
}
```

### Tier 3: Future Architecture (When iOS 26 Adoption Hits 60%+)

**Full Callstack AI SDK integration:**

```typescript
import { apple } from '@react-native-ai/apple';
import { llama } from '@react-native-ai/llama';
import { generateText } from 'ai';

// iOS 26+: Zero-download, zero-cost, instant
// Apple Foundation Models handles LLM + STT + TTS natively
const iosModel = apple.languageModel();
const iosTTS = apple.speechModel();
const iosSTT = apple.transcriptionModel();

// Android: llama.rn with downloaded model
const androidModel = llama.languageModel(
  'bartowski/Llama-3.2-1B-Instruct-GGUF/Llama-3.2-1B-Instruct-Q4_K_M.gguf'
);

// Unified conversation loop using Vercel AI SDK
async function conversationTurn(audioBuffer: ArrayBuffer) {
  // STT
  const transcript = Platform.OS === 'ios'
    ? await transcribe(iosSTT, audioBuffer)
    : await nativeSTT(audioBuffer); // expo-speech-recognition

  // LLM
  const model = Platform.OS === 'ios' ? iosModel : androidModel;
  const { text } = await generateText({
    model,
    system: VOCATIONAL_SYSTEM_PROMPT,
    prompt: transcript,
    maxTokens: 100,
  });

  // TTS
  if (Platform.OS === 'ios') {
    await speak(iosTTS, text);
  } else {
    await nativeTTS(text); // expo-speech
  }

  return { transcript, followUp: text };
}
```

### Model Download & Management Strategy

Since models cannot be bundled in the app binary (2 GB model vs. 200 MB budget), implement a first-launch download flow:

```typescript
import * as FileSystem from 'expo-file-system';

const MODEL_URL = 'https://huggingface.co/bartowski/Llama-3.2-1B-Instruct-GGUF/resolve/main/Llama-3.2-1B-Instruct-Q4_K_M.gguf';
const MODEL_PATH = `${FileSystem.documentDirectory}models/llama-3.2-1b-q4.gguf`;

async function ensureModelDownloaded(
  onProgress: (progress: number) => void
): Promise<string> {
  const fileInfo = await FileSystem.getInfoAsync(MODEL_PATH);

  if (fileInfo.exists) {
    return MODEL_PATH;
  }

  // Create models directory
  await FileSystem.makeDirectoryAsync(
    `${FileSystem.documentDirectory}models/`,
    { intermediates: true }
  );

  // Download with progress tracking
  const downloadResumable = FileSystem.createDownloadResumable(
    MODEL_URL,
    MODEL_PATH,
    {},
    (downloadProgress) => {
      const progress = downloadProgress.totalBytesWritten / downloadProgress.totalBytesExpectedToWrite;
      onProgress(progress);
    }
  );

  const result = await downloadResumable.downloadAsync();
  if (!result?.uri) throw new Error('Model download failed');

  return result.uri;
}
```

### What to Tell Users About the Model Download

Present this as a one-time setup during onboarding:

```
"To give you the fastest, most private experience, we'll download
a small AI assistant to your device. This takes about 750 MB and
happens only once. After this, your conversations happen entirely
on your device -- nothing is sent to the cloud until your final
assessment."
```

### Summary of Key Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Primary STT | expo-speech-recognition (native) | Zero size, fast, offline, good accuracy for English |
| Primary LLM | llama.rn + Llama 3.2 1B Q4_K_M | Best React Native LLM library, 1B fits all target devices |
| Primary TTS | expo-speech (native) | Zero size, instant, adequate quality |
| Synthesis | Claude API via Laravel backend | Claude-quality analysis for the final vocational profile |
| Model format | GGUF | Industry standard for on-device LLM, supported by llama.rn |
| Quantization | Q4_K_M | Best quality-to-size ratio for mobile |
| Context management | Rolling summary | Summarize earlier turns to fit context window |
| Fallback (low-RAM devices) | Cloud-based conversation | Graceful degradation for older devices |
| Fallback (iOS 26+) | Apple Foundation Models | Free, zero-download alternative on latest iOS |
| Android STT | expo-speech-recognition | Uses Android SpeechRecognizer, supports on-device mode |

### Risk Matrix

| Risk | Probability | Impact | Mitigation |
|------|:-----------:|:------:|------------|
| 1B model quality too low for follow-ups | Medium | High | Fine-tune with vocational Q&A data; upgrade to 3B on capable devices |
| Thermal throttling during long sessions | Low | Medium | Bursty inference pattern; 1B model; native STT/TTS use dedicated hardware |
| Battery drain exceeds 15% | Low | Medium | 1B model + native STT/TTS keeps duty cycle under 10% |
| Model download friction (750 MB) | Medium | Medium | Progressive download during onboarding; clear UX messaging |
| expo-speech-recognition accuracy insufficient | Low | Medium | Fall back to whisper.rn or react-native-executorch Whisper |
| llama.rn breaking changes (New Architecture) | Low | Low | Library is actively maintained; pin version |
| Apple Foundation Models 4K context too small | High | Low | FM is a Tier 2 enhancement, not primary engine |
| User on device with < 6 GB RAM | Low | Medium | Automatic fallback to cloud-based conversation |

### Development Priority Order

1. **Week 1-2:** Implement cloud-based conversation pipeline (STT -> Claude API -> TTS) as the baseline
2. **Week 3-4:** Add expo-speech-recognition + expo-speech for on-device STT/TTS
3. **Week 5-6:** Integrate llama.rn with Llama 3.2 1B for on-device follow-up generation
4. **Week 7-8:** Build model download/management system, device capability detection
5. **Week 9-10:** Optimize latency pipeline (streaming STT -> streaming LLM -> streaming TTS)
6. **Post-launch:** Add Apple Foundation Models provider for iOS 26+, explore 3B model for high-end devices

This tiered approach lets you ship with a working cloud pipeline first, then progressively move intelligence on-device. Every tier improves privacy, reduces latency, and lowers operating costs.

---

## Appendix: Key Library Links

| Library | URL | Purpose |
|---------|-----|---------|
| llama.rn | https://github.com/mybigday/llama.rn | On-device LLM (GGUF) |
| react-native-executorch | https://github.com/software-mansion/react-native-executorch | On-device LLM + STT (ExecuTorch) |
| @callstackincubator/ai | https://github.com/callstackincubator/ai | Vercel AI SDK + on-device providers |
| whisper.rn | https://github.com/mybigday/whisper.rn | On-device Whisper STT |
| expo-speech-recognition | https://github.com/jamsch/expo-speech-recognition | Native on-device STT |
| react-native-sherpa-onnx-offline-tts | https://github.com/kislay99/react-native-sherpa-onnx-offline-tts | Offline Piper TTS |
| Llama 3.2 1B GGUF | https://huggingface.co/bartowski/Llama-3.2-1B-Instruct-GGUF | Model weights |
| Llama 3.2 3B GGUF | https://huggingface.co/bartowski/Llama-3.2-3B-Instruct-GGUF | Model weights |
| On-Device LLMs 2026 Survey | https://v-chandra.github.io/on-device-llms/ | Hardware/performance reference |
| Apple Foundation Models | https://developer.apple.com/documentation/FoundationModels | Apple on-device LLM API |
| Gemini Nano ML Kit | https://developer.android.com/ai/gemini-nano | Google on-device LLM API |
