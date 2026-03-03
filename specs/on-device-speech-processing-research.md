# On-Device Speech Processing Research (Early 2026)

## Context
Research for a vocational discernment assessment app built with Expo React Native featuring a conversational audio interface. Users speak answers and hear AI responses. This document evaluates whether on-device STT and TTS can deliver production-quality conversational experiences comparable to cloud APIs.

---

## Executive Summary

On-device speech processing has made dramatic progress through 2025, but a significant quality gap remains for conversational use cases. The recommended approach for a production conversational app is a **hybrid architecture**: use cloud APIs (Whisper API, OpenAI TTS) as the primary path for quality, with on-device processing as a fallback for offline/privacy scenarios.

Key findings:
- **On-device STT** can achieve 5-8% WER with optimized models (vs 1-3% for cloud Whisper), adequate for conversational transcription
- **On-device TTS** remains noticeably less natural than OpenAI "nova" or ElevenLabs, scoring roughly 3.5-4.0 MOS vs 4.5+ for cloud
- **React Native / Expo bindings** exist for multiple options but most require prebuild (no Expo Go)
- **Running STT + TTS + LLM simultaneously** on mobile is not practical with current hardware; streaming cloud LLM with on-device STT/TTS is the realistic architecture
- **App size increase** ranges from 26MB (Moonshine Tiny) to 466MB (Whisper Small), a meaningful consideration

---

## Part 1: On-Device Speech-to-Text (STT)

### 1.1 Apple Speech Framework / SpeechAnalyzer (iOS only)

**Overview:** Apple's built-in speech recognition. WWDC 2025 introduced `SpeechAnalyzer` as a major upgrade to `SFSpeechRecognizer`, using a new proprietary foundation model.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 7/10 |
| WER | ~8% (conversational speech); matches mid-tier Whisper models |
| Latency | Near real-time streaming; 2.2x faster than Whisper Large V3 Turbo in batch |
| Model size | 0 MB (built into OS, iOS 26+) |
| Platform | iOS only |
| RN/Expo compat | Yes, via `expo-speech-recognition` or `expo-speech-transcriber` |
| Production ready | Yes (Apple first-party) |

**Strengths:**
- Zero additional app size
- Excellent privacy (fully on-device)
- Long-form audio support optimized for lectures, meetings, conversations
- Low latency real-time streaming
- No model download required

**Weaknesses:**
- iOS only; no Android equivalent at this quality level
- SpeechAnalyzer requires iOS 26+ (fall 2025)
- Lacks custom vocabulary in SpeechAnalyzer (SFSpeechRecognizer supports it)
- 8% WER vs 1% for Whisper Large V3 Turbo on clean audio
- Accuracy degrades with accents and background noise

**React Native integration:** `@jamsch/expo-speech-recognition` wraps the native iOS Speech framework with `requiresOnDeviceRecognition: true`. Supports streaming results, audio persistence, and locale management. Works as an Expo config plugin (requires prebuild).

**Sources:**
- [Apple WWDC 2025 SpeechAnalyzer](https://developer.apple.com/videos/play/wwdc2025/277/)
- [MacStories hands-on benchmark](https://www.macstories.net/stories/hands-on-how-apples-new-speech-apis-outpace-whisper-for-lightning-fast-transcription/)
- [Apple vs Whisper accuracy analysis](https://www.communeify.com/en/blog/apple-speech-api-vs-whisper-speed-accuracy-test/)

---

### 1.2 Android SpeechRecognizer (Android only)

**Overview:** Android's built-in speech recognition via Google's on-device models. Requires offline model download per locale.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 5-6/10 |
| WER | ~12-15% (estimated, varies by device/model version) |
| Latency | Near real-time streaming |
| Model size | 0 MB bundled (user downloads ~50MB offline model) |
| Platform | Android only (API 33+ for best features) |
| RN/Expo compat | Yes, via `expo-speech-recognition` |
| Production ready | Yes, but quality varies by device |

**Strengths:**
- Zero app size impact
- Streaming results
- No additional library needed

**Weaknesses:**
- Requires Android 13+ for continuous mode and recording features
- User must manually download offline model
- Accuracy significantly lower than iOS SpeechAnalyzer or Whisper
- Struggles with accents, technical jargon, complex sentences
- Quality varies across device manufacturers
- Less reliable for conversational-length speech

**React Native integration:** Same `@jamsch/expo-speech-recognition` library. Set `requiresOnDeviceRecognition: true` and ensure offline model is downloaded via `androidTriggerOfflineModelDownload()`.

**Sources:**
- [Android SpeechRecognizer docs](https://developer.android.com/reference/android/speech/SpeechRecognizer)
- [expo-speech-recognition](https://github.com/jamsch/expo-speech-recognition)

---

### 1.3 Whisper.cpp via whisper.rn (iOS + Android)

**Overview:** Direct port of OpenAI Whisper in C/C++ with React Native bindings. Runs the actual Whisper models on-device.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 6-9/10 (depends on model size) |
| WER (tiny) | ~10-12% |
| WER (base) | ~7-8% |
| WER (small) | ~5-6% |
| Latency | Tiny: ~200-400ms; Base: ~500-800ms; Small: ~1-2s (iPhone 15 class) |
| Model size (GGML) | Tiny: 75MB, Base: 145MB, Small: 466MB |
| Platform | iOS + Android |
| RN/Expo compat | Yes, via `whisper.rn` (requires prebuild) |
| Production ready | Yes, actively maintained |

**Strengths:**
- Same Whisper architecture as the cloud API
- Configurable quality/speed tradeoff via model selection
- Supports English-only optimized models (smaller, faster)
- 38k+ GitHub stars, active community
- ARM NEON optimization for mobile

**Weaknesses:**
- Processes audio in 30-second chunks (requires chunking logic for continuous speech)
- Android performance significantly slower than iOS
- Larger models (small/medium) may cause memory pressure alongside other models
- Not streaming; must buffer audio then transcribe
- App size increase of 75-466MB depending on model

**React Native integration:** `whisper.rn` (npm) provides direct bindings. Requires Expo prebuild. For Android, recommended to use `expo-av` for recording and convert to 16kHz via `ffmpeg-kit-react-native`.

**Sources:**
- [whisper.rn GitHub](https://github.com/mybigday/whisper.rn)
- [Whisper model sizes explained](https://openwhispr.com/blog/whisper-model-sizes-explained)
- [whisper.cpp benchmark results](https://github.com/ggml-org/whisper.cpp/issues/89)

---

### 1.4 React Native ExecuTorch (Whisper + Moonshine)

**Overview:** Meta's ExecuTorch framework with React Native bindings by Software Mansion. Provides `useSpeechToText` hook supporting Whisper and Moonshine models with first-class Expo integration.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 6-8/10 |
| WER | Comparable to whisper.rn; quantized models within ~1% of full precision |
| Latency | Streaming modes: fast/balanced/quality configurable |
| Model size | Whisper Tiny EN: ~77MB; quantized: ~20MB; Moonshine Tiny: ~26MB |
| Platform | iOS + Android |
| RN/Expo compat | Yes, first-class Expo support (SDK 54+) |
| Production ready | Approaching (v0.4.x, actively developed by Software Mansion) |

**Strengths:**
- Best Expo integration of any on-device STT option
- Supports both Whisper and Moonshine models via same API
- Quantized models are 4x smaller with minimal accuracy loss
- VAD (Voice Activity Detection) built in
- Models downloaded on first use, cached locally
- Declarative React hooks API (`useSpeechToText`)

**Weaknesses:**
- Relatively new library (v0.4.x)
- Requires Expo SDK 54+
- Model download on first launch (~150MB for full Whisper Tiny EN)
- Less battle-tested than whisper.rn

**Sources:**
- [React Native ExecuTorch docs](https://docs.swmansion.com/react-native-executorch/)
- [Expo blog: on-device AI](https://expo.dev/blog/how-to-run-ai-models-with-react-native-executorch)
- [useSpeechToText API](https://docs.swmansion.com/react-native-executorch/docs/0.4.x/natural-language-processing/useSpeechToText)

---

### 1.5 Moonshine (Useful Sensors)

**Overview:** Purpose-built ASR for edge devices. Dramatically smaller and faster than Whisper with competitive accuracy. Moonshine v2 (Feb 2026) adds streaming support.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 7/10 |
| WER | 48% lower error rate than comparably-sized Whisper Tiny; matches Whisper Medium |
| Latency | Tiny: 50ms, Small: 148ms, Medium: 258ms (on Apple M3) |
| Model size | Tiny: ~26MB (quantized), Small: ~70MB, Medium: ~140MB |
| Platform | iOS + Android (via ONNX Runtime or ExecuTorch) |
| RN/Expo compat | Yes, via `react-native-executorch` useSpeechToText |
| Production ready | Yes (v2 released Feb 2026) |

**Strengths:**
- 5-44x faster than equivalent Whisper models
- Tiny model matches Whisper Medium accuracy at 1/28th the size
- Streaming support in v2 (sliding-window attention)
- Specialized language variants available (Arabic, Chinese, Japanese, Korean, etc.)
- Permissive open-source license
- Designed specifically for mobile/edge from the ground up

**Weaknesses:**
- Smaller community than Whisper
- Fewer language models than Whisper (though expanding)
- Less proven in production than Whisper

**Sources:**
- [Moonshine GitHub](https://github.com/moonshine-ai/moonshine)
- [Moonshine v2 paper](https://arxiv.org/abs/2602.12241)
- [Flavors of Moonshine paper](https://arxiv.org/html/2509.02523v1)

---

### 1.6 Vosk

**Overview:** Lightweight offline speech recognition toolkit based on Kaldi. One of the earliest offline STT solutions.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 4-5/10 |
| WER | ~15-25% (estimated, varies by model) |
| Latency | Real-time streaming |
| Model size | ~50MB (lightweight model) |
| Platform | iOS + Android |
| RN/Expo compat | Yes, via `react-native-vosk` (community maintained) |
| Production ready | Mature but dated |

**Strengths:**
- Very small model size (~50MB)
- True real-time streaming
- Supports 20+ languages
- Low RAM usage (~50MB during inference)

**Weaknesses:**
- Significantly lower accuracy than Whisper-based solutions
- Poor performance with background noise
- Community-maintained RN bindings (less reliable)
- Architecture is older (Kaldi-based, not transformer)
- Not suitable for conversational-quality transcription

**Sources:**
- [Vosk official site](https://alphacephei.com/vosk/)
- [react-native-vosk](https://github.com/riderodd/react-native-vosk)

---

### 1.7 Sherpa-ONNX (STT)

**Overview:** Comprehensive on-device speech toolkit from the next-gen Kaldi project. Supports streaming and non-streaming ASR with multiple model architectures (Zipformer, Conformer, Paraformer).

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 6-7/10 |
| WER | Varies by model; competitive with Whisper Base/Small |
| Latency | RTF ~0.05 on iPhone 15 Pro (streaming Zipformer) |
| Model size | 10-100MB depending on model |
| Platform | iOS + Android + many more |
| RN/Expo compat | Partial (community wrappers, not first-party) |
| Production ready | Yes (actively maintained, monthly model updates) |

**Strengths:**
- Unified STT + TTS + VAD in one framework
- Extremely low RTF on modern phones
- Monthly model zoo updates
- Supports streaming recognition (critical for conversation)
- Multilingual (Chinese, English, Japanese, Korean, Bengali, etc.)

**Weaknesses:**
- React Native integration is community-maintained and limited
- More complex setup than whisper.rn or ExecuTorch
- Less polished developer experience for RN/Expo
- Documentation oriented toward native developers

**Sources:**
- [sherpa-onnx GitHub](https://github.com/k2-fsa/sherpa-onnx)
- [sherpa-onnx documentation](https://k2-fsa.github.io/sherpa/onnx/index.html)

---

### 1.8 Picovoice Cheetah / Leopard (Commercial)

**Overview:** Commercial on-device STT with polished React Native SDK. Cheetah for streaming, Leopard for batch transcription.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 7/10 |
| WER | Cheetah: ~14.3%, Leopard: ~11% |
| Latency | Real-time streaming (Cheetah), Cheetah Fast for ultra-low latency |
| Model size | ~10-30MB (proprietary, optimized) |
| Platform | iOS + Android |
| RN/Expo compat | Yes, official React Native SDKs |
| Production ready | Yes (commercial, well-supported) |

**Strengths:**
- Best React Native developer experience among all options
- Official, maintained SDKs
- Small model sizes
- Custom model training available
- 12 language support

**Weaknesses:**
- Commercial licensing required (not free for production)
- 14% WER for streaming is high for conversational use
- Closed source
- 5x revenue growth in 2025 suggests pricing may increase

**Sources:**
- [Picovoice Cheetah](https://picovoice.ai/platform/cheetah/)
- [Picovoice Leopard](https://picovoice.ai/platform/leopard/)
- [React Native speech recognition guide](https://picovoice.ai/blog/react-native-speech-recognition/)

---

### 1.9 WhisperKit (iOS only, Swift-native)

**Overview:** Optimized Whisper inference framework by Argmax, specifically tuned for Apple Silicon. State-of-the-art on-device ASR on Apple devices.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 9/10 |
| WER | 2.2% (Large V3 Turbo, compressed to 0.6GB) |
| Latency | 0.45s mean per-word latency (matches cloud APIs) |
| Model size | 0.6GB (compressed Large V3 Turbo) |
| Platform | iOS / macOS only |
| RN/Expo compat | No direct bindings; would require native module |
| Production ready | Yes (backed by Argmax, Apple partnership) |

**Strengths:**
- Best on-device ASR accuracy available (2.2% WER)
- Matches cloud API latency
- Compressed models retain WER within 1% of uncompressed
- Custom vocabulary support surpasses even cloud APIs for keyword recognition
- Apple Neural Engine optimization

**Weaknesses:**
- iOS/macOS only
- No React Native bindings (Swift-native)
- 0.6GB model is large for mobile bundle
- Would require building a custom native module for RN integration

**Sources:**
- [WhisperKit paper (arXiv)](https://arxiv.org/abs/2507.10860)
- [Argmax blog on Apple partnership](https://www.argmaxinc.com/blog/apple-and-argmax)
- [WhisperKit GitHub](https://github.com/argmaxinc/WhisperKit)

---

### 1.10 Mozilla DeepSpeech / Coqui STT

**Status: Deprecated.** Coqui AI shut down in December 2025. DeepSpeech is no longer actively maintained. Not recommended for new projects.

---

## Part 2: On-Device Text-to-Speech (TTS)

### 2.1 Apple AVSpeechSynthesizer (iOS only)

**Overview:** Apple's built-in TTS with neural voices available on-device.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 5/10 (standard voices), 6.5/10 (neural voices) |
| MOS estimate | ~3.5 (neural voices) vs 4.5+ for OpenAI nova |
| Time to first audio | Near-instant for standard; 100-300ms for neural |
| Model size | 0 MB (built into OS) |
| Platform | iOS only |
| RN/Expo compat | Yes, via `expo-speech` or `@mhpdev/react-native-speech` |
| Production ready | Yes |

**Strengths:**
- Zero app size
- No setup required
- Multiple languages and voices
- Pitch and rate adjustable

**Weaknesses:**
- Cannot process streaming text input (requires full text before generating)
- Neural voices may sound choppy on older devices
- Quality gap vs cloud TTS is noticeable for conversational use
- Less expressive than OpenAI TTS or ElevenLabs
- No voice cloning capability

**React Native integration:** `expo-speech` provides direct access. Also `@mhpdev/react-native-speech` for more advanced event handling.

**Sources:**
- [AVSpeechSynthesizer docs](https://developer.apple.com/documentation/avfaudio/avspeechsynthesizer)
- [expo-speech](https://docs.expo.dev/versions/latest/sdk/speech/)

---

### 2.2 Android TextToSpeech (Android only)

**Overview:** Android's built-in TTS engine (typically Google's).

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 4-5/10 |
| MOS estimate | ~3.0-3.5 |
| Time to first audio | Near-instant |
| Model size | 0 MB (built into OS) |
| Platform | Android only |
| RN/Expo compat | Yes, via `expo-speech` |
| Production ready | Yes |

**Strengths:**
- Zero app size
- Wide language support
- No setup required

**Weaknesses:**
- Noticeably robotic compared to cloud TTS
- Quality varies significantly by device manufacturer
- Limited voice selection
- Not suitable for a premium conversational experience

**Sources:**
- [expo-speech documentation](https://docs.expo.dev/versions/latest/sdk/speech/)

---

### 2.3 Piper TTS (via Sherpa-ONNX)

**Overview:** Fast, local neural TTS built on VITS architecture, exported to ONNX. 35+ languages with 900+ pre-trained voices. The most mature open-source on-device TTS option.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 6-7/10 |
| MOS estimate | ~3.5-4.0 (high-quality voices) |
| Time to first audio | <100ms (RTF ~0.2, 5x faster than real-time) |
| Model size | 15-75MB per voice |
| Platform | iOS + Android (via ONNX Runtime) |
| RN/Expo compat | Yes, via `react-native-sherpa-onnx-offline-tts` |
| Production ready | Yes |

**Strengths:**
- RTF of 0.2 (synthesis 5x faster than real-time)
- Huge voice ecosystem (900+ voices)
- Small model sizes (15-75MB)
- Multi-speaker support
- RAM usage ~50MB during inference
- Runs on Raspberry Pi, so mobile is comfortable

**Weaknesses:**
- Quality gap vs cloud TTS is audible (less expressive prosody)
- Some voices sound better than others; quality inconsistent across voice selection
- React Native wrapper is community-maintained
- No voice cloning
- VITS architecture is older than newer approaches (Kokoro, Chatterbox)

**React Native integration:** `react-native-sherpa-onnx-offline-tts` provides async API with model path configuration. Supports Piper/VITS ONNX models. Requires prebuild.

**Sources:**
- [Piper GitHub](https://github.com/rhasspy/piper)
- [Piper voice samples](https://rhasspy.github.io/piper-samples/)
- [react-native-sherpa-onnx-offline-tts](https://github.com/kislay99/react-native-sherpa-onnx-offline-tts)

---

### 2.4 Kokoro TTS

**Overview:** 82M parameter model built on StyleTTS2 + ISTFTNet. Achieves near-cloud quality at tiny size. Apache 2.0 license.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 7.5-8/10 |
| MOS estimate | ~4.0-4.2 |
| Time to first audio | 97ms TTFB (server); ~1-2s on mobile |
| Model size | ~80MB (int8 quantized), ~350MB (full) |
| Platform | iOS + Android (via ONNX) |
| RN/Expo compat | No direct RN bindings yet |
| Production ready | Partially (needs mobile optimization) |

**Strengths:**
- Best quality among small open-source TTS models
- 96x real-time on GPU; usable on mobile with quantization
- 21 expressive voices
- Apache 2.0 license
- Competitive with cloud TTS in blind tests

**Weaknesses:**
- 10-second snippet takes ~8 seconds on smartphone (not real-time on mobile)
- No React Native bindings available
- Would require custom native module integration
- Not yet optimized for mobile inference
- 80MB+ model size

**Sources:**
- [Kokoro GitHub](https://github.com/hexgrad/kokoro)
- [Kokoro on-device deployment guide](https://www.nimbleedge.com/blog/how-to-run-kokoro-tts-model-on-device/)
- [TTS model comparison 2025](https://www.inferless.com/learn/comparing-different-text-to-speech---tts--models-part-2)

---

### 2.5 KittenTTS

**Overview:** Ultra-lightweight TTS (15-25MB) specifically designed for edge devices. Runs on CPU-only devices without memory issues.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 5-6/10 |
| MOS estimate | ~3.2-3.5 |
| Time to first audio | ~200-500ms on mobile |
| Model size | ~15-25MB |
| Platform | iOS + Android (via ONNX) |
| RN/Expo compat | No direct RN bindings |
| Production ready | Early stage (v0.8) |

**Strengths:**
- Smallest TTS model available (~15MB)
- Never OOM'd on any test device (including budget phones)
- Runs on devices where Piper fails (low-memory Android)
- 8 voice embeddings

**Weaknesses:**
- Lower quality than Piper or Kokoro
- Slower than Piper on capable hardware
- No React Native bindings
- Very new (v0.8)
- Limited voice selection

**Sources:**
- [KittenTTS benchmark vs Piper/Kokoro](https://github.com/KittenML/KittenTTS/issues/40)
- [KittenTTS overview](https://jangwook.net/en/blog/en/kitten-tts-v08-tiny-sota/)

---

### 2.6 Sherpa-ONNX TTS

**Overview:** TTS subsystem of the sherpa-onnx framework. Supports 5 model families (including Piper/VITS) across 80+ languages.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 6-7/10 (with best Piper voices) |
| MOS estimate | ~3.5-4.0 |
| Time to first audio | RTF ~0.6 on Raspberry Pi 4; ~0.1-0.2 on modern phones |
| Model size | 15-100MB per voice model |
| Platform | iOS + Android + many more |
| RN/Expo compat | Via `react-native-sherpa-onnx-offline-tts` |
| Production ready | Yes |

**Strengths:**
- Same framework handles both STT and TTS
- Monthly model zoo updates
- Multi-speaker voice selection
- Adjustable speech speed
- Most comprehensive platform support of any option

**Weaknesses:**
- React Native wrapper is limited (iOS-focused in current implementation)
- Quality ultimately depends on underlying model (Piper voices)
- Not as high quality as Kokoro or Chatterbox

**Sources:**
- [Sherpa-ONNX TTS docs](https://k2-fsa.github.io/sherpa/onnx/tts/index.html)
- [Sherpa-ONNX TTS samples](https://k2-fsa.github.io/sherpa/onnx/tts/all/)

---

### 2.7 StyleTTS2 / Coqui XTTS

**Status:** StyleTTS2 is research-quality but not optimized for mobile (large model, GPU-dependent). Coqui XTTS achieved <150ms streaming latency on GPU but Coqui AI shut down in December 2025. Both are impractical for on-device mobile use in React Native currently.

---

### 2.8 Chatterbox (Resemble AI)

**Overview:** Newer open-source TTS that wins blind tests against ElevenLabs with voice cloning from 5 seconds of audio. MIT license.

| Metric | Value |
|--------|-------|
| Quality (vs cloud) | 8/10 |
| Latency | Competitive with cloud |
| Model size | Sub-1B parameters (exact mobile size TBD) |
| Platform | Not yet optimized for mobile |
| RN/Expo compat | No |
| Production ready | For server use; not mobile |

**Note:** Promising but not yet practical for on-device mobile deployment. Worth monitoring for future mobile optimization.

**Sources:**
- [Open-source TTS comparison 2026](https://ocdevel.com/blog/20250720-tts)

---

## Part 3: Answers to Key Questions

### Q1: Realistic transcription accuracy of on-device STT vs Whisper API?

| Solution | WER | vs Whisper API (Large V3: ~2.7%) |
|----------|-----|----------------------------------|
| WhisperKit (iOS, Large V3 compressed) | 2.2% | Better (with custom vocab) |
| Apple SpeechAnalyzer (iOS 26) | ~8% | 3x worse |
| Moonshine Tiny | ~8-10% | 3-4x worse |
| Moonshine Medium | ~5% | 2x worse |
| Whisper Small (on-device) | ~5-6% | 2x worse |
| Whisper Tiny (on-device) | ~10-12% | 4x worse |
| Android SpeechRecognizer (offline) | ~12-15% | 5x worse |
| Vosk | ~15-25% | 6-9x worse |

**Bottom line:** For conversational transcription, 5-8% WER is generally acceptable (roughly 1 word wrong per 15-20 words). Users can tolerate this for conversational input, especially with confirmation UI. WhisperKit on iOS achieves cloud-equivalent accuracy but has no RN bindings.

### Q2: Can on-device STT handle conversational speech with high accuracy?

**Yes, with caveats.** Apple SpeechAnalyzer and Moonshine v2 are explicitly optimized for long-form conversational speech (not just voice commands). Key considerations:
- Background noise degrades all on-device solutions more than cloud
- Accents and dialects cause more errors on-device
- Technical or domain-specific vocabulary (religious/vocational terms) will need custom vocabulary support
- Whisper-based solutions process in 30-second chunks; Moonshine v2 and Apple SpeechAnalyzer support true streaming

**Recommendation:** For the vocational discernment use case, Moonshine v2 (via react-native-executorch) or Apple SpeechAnalyzer (via expo-speech-recognition) can handle conversational input adequately. Adding contextual strings (supported by expo-speech-recognition) for domain-specific terms would improve accuracy.

### Q3: Realistic latency for on-device STT?

| Solution | Time from end of speech to transcript |
|----------|--------------------------------------|
| Apple SpeechAnalyzer (streaming) | <100ms (interim results during speech) |
| Android SpeechRecognizer (streaming) | <100ms (interim results) |
| Moonshine v2 Tiny | ~50ms per chunk |
| Moonshine v2 Small | ~148ms per chunk |
| Whisper Tiny (batch) | 200-400ms after chunk ends |
| Whisper Base (batch) | 500-800ms after chunk ends |
| Whisper Small (batch) | 1-2s after chunk ends |

**Bottom line:** Streaming solutions (Apple SpeechAnalyzer, Android SpeechRecognizer, Moonshine v2) provide essentially real-time results. Non-streaming Whisper-based solutions add 200ms-2s after speech ends depending on model size. For conversational flow, streaming is strongly preferred.

### Q4: How natural-sounding is on-device TTS compared to OpenAI TTS "nova" or ElevenLabs?

| Solution | Estimated MOS | vs OpenAI nova (~4.5) | vs ElevenLabs (~4.6) |
|----------|--------------|----------------------|---------------------|
| Kokoro (quantized) | ~4.0-4.2 | Close but less expressive | Noticeable gap |
| Piper (best voices) | ~3.5-4.0 | Noticeable gap | Significant gap |
| Apple Neural Voices | ~3.5 | Significant gap | Significant gap |
| KittenTTS | ~3.2-3.5 | Large gap | Large gap |
| Android TTS | ~3.0-3.5 | Large gap | Large gap |

**Bottom line:** No on-device TTS currently matches the naturalness of OpenAI "nova" or ElevenLabs for conversational use. The gap is most apparent in:
- Prosody and emotional expression
- Natural pausing and emphasis
- Handling of complex sentences
- Overall "warmth" of voice

For a spiritual/vocational discernment app where voice warmth and trust matter, cloud TTS is strongly recommended as the primary option.

### Q5: Can neural TTS models run on mobile with acceptable latency?

**Partially.**
- **Piper:** Yes. RTF 0.2 means a 10-second utterance synthesizes in 2 seconds. Acceptable for pre-generating responses.
- **Kokoro:** Borderline. 10 seconds of speech takes ~8 seconds on smartphone. Not real-time but might work with streaming text + progressive synthesis.
- **KittenTTS:** Yes, but lower quality. Runs without OOM even on budget phones.
- **Chatterbox/StyleTTS2:** No. Too large for current mobile hardware.

**Key insight:** The bottleneck is not necessarily the RTF but the **time-to-first-audio-byte**. For conversational flow, users expect to hear speech begin within 200-500ms. Piper can achieve this. Kokoro cannot on mobile.

### Q6: App size increase for bundling STT/TTS models?

| Component | Size |
|-----------|------|
| **STT Models** | |
| Moonshine Tiny (quantized) | ~26MB |
| Whisper Tiny EN (quantized via ExecuTorch) | ~20-40MB |
| Whisper Tiny EN (GGML) | ~75MB |
| Whisper Base (GGML) | ~145MB |
| Whisper Small (GGML) | ~466MB |
| Vosk lightweight | ~50MB |
| **TTS Models** | |
| Piper voice (single) | ~15-75MB |
| Kokoro (int8 quantized) | ~80MB |
| KittenTTS | ~15-25MB |
| **Combined minimum (STT + TTS)** | ~40-100MB |
| **Combined recommended** | ~100-200MB |

**Strategy:** Download models on first launch rather than bundling with the app binary. React Native ExecuTorch supports this pattern natively.

### Q7: React Native / Expo bindings availability?

| Solution | Library | Expo Compatible | Maturity |
|----------|---------|----------------|----------|
| Apple/Android STT | `@jamsch/expo-speech-recognition` | Yes (config plugin) | Mature |
| Apple/Android TTS | `expo-speech` | Yes (built-in) | Mature |
| Whisper STT | `whisper.rn` | Yes (prebuild) | Mature |
| Whisper/Moonshine STT | `react-native-executorch` | Yes (SDK 54+) | Growing |
| Vosk STT | `react-native-vosk` | Prebuild only | Community |
| Piper TTS | `react-native-sherpa-onnx-offline-tts` | Prebuild only | Early |
| Sherpa-ONNX (STT+TTS) | `sherpa-onnx` (npm) + wrappers | Prebuild only | Limited |
| Picovoice STT | Official RN SDK | Yes (prebuild) | Commercial |
| Kokoro TTS | None | No | N/A |
| WhisperKit STT | None (Swift only) | No | N/A |

**Best Expo-native options:**
1. `expo-speech-recognition` (STT) + `expo-speech` (TTS) for platform-native engines
2. `react-native-executorch` for Whisper/Moonshine STT with first-class Expo support
3. `react-native-sherpa-onnx-offline-tts` for Piper TTS (requires prebuild)

### Q8: Can on-device STT, TTS, and LLM run simultaneously on mobile hardware?

**No, not practically with current hardware (as of early 2026).**

**Memory constraints:**
- Modern phones: 6-8GB RAM (iPhone 15 Pro: 8GB, typical Android: 8-12GB)
- A 3B parameter LLM (smallest useful for conversation): ~1.5-3GB RAM in 4-bit quantized form
- Whisper Tiny STT: ~150MB RAM
- Piper TTS: ~50MB RAM
- OS and app overhead: ~2-3GB
- Total needed: ~4-6GB, leaving little headroom

**Memory bandwidth:**
- Mobile: 50-90 GB/s memory bandwidth
- LLM inference is memory-bandwidth bound
- Running LLM + STT simultaneously would starve both of bandwidth

**Practical architecture for conversational voice:**
1. On-device STT transcribes user speech (runs while user talks)
2. Cloud LLM generates response via streaming API (most practical for quality)
3. TTS synthesizes response progressively as LLM tokens stream in
4. STT and TTS never run simultaneously (user talks OR app talks)

**If fully on-device is required:**
- Use a tiny LLM (Phi-3 mini, Gemma 2B) in 4-bit quantized form
- Use Moonshine Tiny for STT (~26MB)
- Use Piper for TTS (~50MB)
- Sequence operations: STT -> LLM -> TTS (not simultaneous)
- Accept 3-10 second total response latency
- This is technically possible on iPhone 15 Pro / flagship Android but will be slow

---

## Part 4: Recommended Architecture for Vocation Finder App

### Primary Path: Hybrid (Cloud + On-Device Fallback)

```
User speaks -> On-device STT (streaming) -> Cloud LLM (streaming) -> Cloud TTS (streaming) -> User hears
                                                                        |
                                              On-device TTS (fallback) <-+
```

**STT (Speech-to-Text):**
- iOS: `expo-speech-recognition` with `requiresOnDeviceRecognition: true` (uses Apple SpeechAnalyzer on iOS 26+, SFSpeechRecognizer on older)
- Android: `expo-speech-recognition` with on-device model
- Fallback: `react-native-executorch` with Moonshine Tiny for both platforms (consistent quality)

**TTS (Text-to-Speech):**
- Primary: Cloud TTS (OpenAI "nova" or ElevenLabs) for natural, warm conversational voice
- Fallback: `expo-speech` (platform native) for offline scenarios
- Optional upgrade: `react-native-sherpa-onnx-offline-tts` with Piper for better offline TTS quality

**LLM:**
- Cloud API with streaming (OpenAI, Anthropic, etc.)
- On-device LLM is not recommended for conversational quality in 2026

### Why This Architecture:

1. **STT can be on-device** because modern on-device STT (5-8% WER) is adequate for conversational input, and streaming gives real-time feedback
2. **TTS should be cloud** because the quality gap matters enormously for a spiritual/vocational discernment context where voice warmth and trustworthiness are critical
3. **LLM must be cloud** because on-device LLMs are too small for nuanced vocational guidance conversations
4. **On-device fallback** ensures the app works offline (with reduced quality) which is important for users in low-connectivity environments

### Estimated App Size Impact:
- `expo-speech-recognition`: ~0MB (uses native frameworks)
- `expo-speech`: ~0MB (uses native frameworks)
- Moonshine Tiny (download on first use): ~26MB
- Piper voice (optional, download on first use): ~20-40MB
- **Total bundled increase: near zero; ~50-70MB downloaded on first use**

---

## Part 5: Benchmark Summary Table

### STT Comparison

| Model | WER | Latency | Size | Streaming | RN/Expo | Best For |
|-------|-----|---------|------|-----------|---------|----------|
| Whisper API (cloud) | 1-3% | 500-1000ms | 0 | No | N/A | Best accuracy |
| WhisperKit (iOS) | 2.2% | 450ms | 600MB | Yes | No RN bindings | iOS-native apps |
| Apple SpeechAnalyzer | ~8% | <100ms | 0 | Yes | expo-speech-recognition | iOS default |
| Moonshine v2 Tiny | ~8-10% | 50ms | 26MB | Yes (v2) | react-native-executorch | Best edge STT |
| Moonshine v2 Medium | ~5% | 258ms | 140MB | Yes (v2) | react-native-executorch | Quality edge STT |
| Whisper Tiny (on-device) | 10-12% | 200-400ms | 75MB | No | whisper.rn | Proven solution |
| Whisper Small (on-device) | 5-6% | 1-2s | 466MB | No | whisper.rn | Higher accuracy |
| Android SpeechRecognizer | 12-15% | <100ms | 0 | Yes | expo-speech-recognition | Android default |
| Picovoice Leopard | ~11% | Batch | 10-30MB | No | Official SDK | Commercial |
| Vosk | 15-25% | <100ms | 50MB | Yes | react-native-vosk | Legacy |

### TTS Comparison

| Model | Est. MOS | TTFA | Size | Streaming | RN/Expo | Best For |
|-------|----------|------|------|-----------|---------|----------|
| OpenAI "nova" (cloud) | 4.5+ | 200ms | 0 | Yes | N/A | Best quality |
| ElevenLabs (cloud) | 4.6+ | 75ms | 0 | Yes | N/A | Best quality+speed |
| Kokoro (on-device) | 4.0-4.2 | 1-2s* | 80MB | No | None | Quality-focused |
| Piper (on-device) | 3.5-4.0 | <100ms | 15-75MB | No | sherpa-onnx-tts | Best on-device |
| Apple Neural Voice | 3.5 | 100-300ms | 0 | No | expo-speech | iOS default |
| KittenTTS | 3.2-3.5 | 200-500ms | 15-25MB | No | None | Ultra-lightweight |
| Android TTS | 3.0-3.5 | <50ms | 0 | No | expo-speech | Android default |

*Kokoro TTFA on mobile device; server TTFA is 97ms

---

## Part 6: Key Risks and Considerations

1. **Model download UX:** If models aren't bundled, first-launch experience requires downloading 50-150MB. Must handle gracefully with progress indicators and graceful fallback.

2. **Battery drain:** On-device STT inference during extended conversations will consume significant battery. Whisper-based solutions are particularly heavy.

3. **Thermal throttling:** Extended on-device inference can cause phone to heat up and throttle, degrading performance mid-conversation.

4. **Android fragmentation:** On-device performance varies wildly across Android devices. Budget Android phones may struggle with any Whisper-based solution.

5. **iOS version requirements:** Apple SpeechAnalyzer requires iOS 26 (fall 2025 release). Older devices will fall back to SFSpeechRecognizer with lower quality.

6. **Expo Go incompatibility:** All on-device STT/TTS solutions require Expo prebuild (development builds). No Expo Go support.

7. **Audio routing complexity:** Managing microphone input, speaker output, and potential Bluetooth audio simultaneously in React Native requires careful native audio session management.

---

## Sources Index

### STT
- [Northflank: Best open source STT in 2026](https://northflank.com/blog/best-open-source-speech-to-text-stt-model-in-2026-benchmarks)
- [Ionio: 2025 Edge STT Benchmark](https://www.ionio.ai/blog/2025-edge-speech-to-text-model-benchmark-whisper-vs-competitors)
- [Argmax WhisperKit](https://www.argmaxinc.com/blog/apple-and-argmax)
- [WhisperKit paper](https://arxiv.org/abs/2507.10860)
- [MacStories Apple SpeechAnalyzer test](https://www.macstories.net/stories/hands-on-how-apples-new-speech-apis-outpace-whisper-for-lightning-fast-transcription/)
- [Moonshine v2 paper](https://arxiv.org/abs/2602.12241)
- [Moonshine GitHub](https://github.com/moonshine-ai/moonshine)
- [whisper.rn](https://github.com/mybigday/whisper.rn)
- [react-native-executorch](https://docs.swmansion.com/react-native-executorch/)
- [Expo: on-device AI blog](https://expo.dev/blog/how-to-run-ai-models-with-react-native-executorch)
- [expo-speech-recognition](https://github.com/jamsch/expo-speech-recognition)
- [Vosk](https://alphacephei.com/vosk/)
- [sherpa-onnx](https://github.com/k2-fsa/sherpa-onnx)
- [Picovoice](https://picovoice.ai/blog/react-native-speech-recognition/)

### TTS
- [Piper TTS](https://github.com/rhasspy/piper)
- [Kokoro TTS](https://github.com/hexgrad/kokoro)
- [Kokoro on-device](https://www.nimbleedge.com/blog/how-to-run-kokoro-tts-model-on-device/)
- [KittenTTS benchmark](https://github.com/KittenML/KittenTTS/issues/40)
- [Open-source TTS comparison 2026](https://ocdevel.com/blog/20250720-tts)
- [TTS model comparison](https://www.inferless.com/learn/comparing-different-text-to-speech---tts--models-part-2)
- [react-native-sherpa-onnx-offline-tts](https://github.com/kislay99/react-native-sherpa-onnx-offline-tts)
- [expo-speech](https://docs.expo.dev/versions/latest/sdk/speech/)

### Architecture
- [On-Device LLMs: State of the Union 2026](https://v-chandra.github.io/on-device-llms/)
- [Callstack: Local LLMs on Mobile](https://www.callstack.com/blog/local-llms-on-mobile-are-a-gimmick)
- [iOS streaming TTS tutorial](https://picovoice.ai/blog/ios-streaming-text-to-speech/)
