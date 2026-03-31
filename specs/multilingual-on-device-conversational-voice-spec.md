# Multilingual On-Device Conversational Voice Interface -- Technical Spec

**Date:** 2026-03-30
**Status:** Recommended implementation spec
**Applies to:** `mobile/` Expo React Native app + Laravel conversation backend
**Supersedes:** Parts of `specs/on-device-ai-architecture-recommendation.md` and related March 2026 voice/on-device research where they assume fully local reasoning on baseline devices or treat `react-native-executorch` as the turnkey multilingual TTS answer.

---

## Executive Decision

The recommended architecture for Vocation Finder is:

1. **Local STT on device**
2. **Server-side conversation reasoning and assessment logic**
3. **Local TTS on device**
4. **Optional local LLM later, only for supported higher-memory devices**

This is the best path to the actual product goal:

- warm, human-feeling conversation
- English, Spanish, and Portuguese at launch
- good performance on iPhone 15-class devices and recent Android devices
- preservation of the current server-side assessment logic
- lower latency than the current upload-audio pipeline
- better privacy than the current upload-audio pipeline

This spec intentionally does **not** recommend a fully local speech-to-speech LLM for the launch path. That is the wrong baseline for this product and this device target.

---

## 1. Product Goals

### Primary goals

- Deliver a conversational assessment experience that feels calm, human, and responsive.
- Support **English**, **Spanish**, and **Portuguese** at launch.
- Work well on:
  - iPhone 15 baseline
  - recent iPhones newer than iPhone 15
  - recent Android devices from the last two years, especially 6 GB to 12 GB RAM devices
- Preserve the quality and consistency of the current assessment logic.
- Reduce dependence on cloud STT and cloud TTS during the conversation itself.
- Keep the architecture compatible with Expo React Native and native builds.

### Secondary goals

- Improve privacy by avoiding raw audio upload in the primary path.
- Make the conversation usable under weak network conditions.
- Create a foundation for later optional offline or semi-offline conversation modes.

### Non-goals for launch

- Fully local vocational synthesis and report generation.
- Voice cloning.
- Continuous full-duplex voice assistant behavior across all devices.
- Bundling large local LLMs as a required install-time dependency.
- A single inference framework for every stage regardless of fit.

---

## 2. Corrections to the Previous Spec

The earlier Grok-generated direction was useful as exploration, but it should not be used as the implementation spec without correction.

### 2.1 This is not a single "speech-to-speech LLM" decision

For mobile, the practical architecture is still a pipeline:

- microphone capture
- VAD / turn detection
- STT
- dialogue decisioning
- TTS

Trying to collapse this into one local "speech-to-speech LLM" is not how the current React Native mobile ecosystem actually ships reliable products.

### 2.2 `react-native-executorch` is not the launch TTS answer for en/es/pt

`react-native-executorch` is credible for local STT and later local LLM experimentation, but its official built-in TTS story is currently not a fit for this launch because the documented built-in TTS support is Kokoro and the official docs currently list that support in English only.

Implication:

- do not base the multilingual TTS plan on ExecuTorch's current built-in TTS layer
- use a separate TTS stack for launch

### 2.3 The earlier spec mixed incompatible model/runtime assumptions

The earlier spec casually mixed:

- ExecuTorch
- GGUF local LLMs
- Sherpa
- Kokoro

Those can coexist in a product, but they are not interchangeable runtime assumptions. If local reasoning is added later and the team wants GGUF models, `llama.rn` is a better fit than forcing GGUF expectations onto ExecuTorch.

### 2.4 iPhone 15 is not a safe baseline for fully local 3B conversation reasoning

The earlier spec treated 3B local reasoning as if it were a normal baseline for an iPhone 15 class device. That is too aggressive.

Why this matters:

- iPhone 15 has 6 GB RAM.
- Official ExecuTorch benchmarks show meaningful memory usage even before the app's own UI, audio, and runtime overhead are counted.
- Local STT plus local LLM plus local TTS becomes memory-sensitive quickly, especially if any two stages overlap.

Conclusion:

- do not make a 3B local LLM the baseline path
- do not make fully local reasoning the required path for iPhone 15-class devices

### 2.5 The current project is not set up for multilingual conversation yet

Today the repo still has multiple English-only assumptions:

- backend transcription is hardcoded to English in `app/Http/Controllers/Api/V1/AudioConversationController.php`
- mobile fallback TTS is hardcoded to `en-US` in `mobile/hooks/useConversationFlow.ts`
- question content has no locale-aware storage model in `app/Models/Question.php`

If the product goal is English, Spanish, and Portuguese, localization has to be part of the technical plan, not an afterthought.

---

## 3. Current State in This Repo

### 3.1 Current conversation path

The mobile app currently:

1. records a full audio clip
2. uploads the clip to the backend
3. waits for backend transcription
4. waits for backend reasoning
5. plays either local TTS, remote TTS, or `expo-speech`

Relevant code:

- `mobile/hooks/useConversationFlow.ts`
- `mobile/stores/assessmentStore.ts`
- `app/Http/Controllers/Api/V1/AudioConversationController.php`

### 3.2 Current assessment logic is server-side and domain-specific

The backend conversation agent is not a generic assistant. It has explicit rules around:

- sufficiency of responses
- follow-up generation
- synthesized answer structure
- tone and constraints

That logic lives in:

- `app/Ai/Agents/ConversationAgent.php`

That is product logic. It should stay centralized for launch.

### 3.3 Existing local TTS work is partial

The repo already contains a `mobile/services/localTts.ts` module using `react-native-sherpa-onnx`, which is directionally good. However:

- it currently prefers English models
- its referenced native dependencies are not currently declared in `mobile/package.json`
- it is not yet a complete multilingual production path

### 3.4 Existing March 2026 on-device docs should be treated as research, not final implementation guidance

The existing documents in `specs/` are still valuable background, but the new implementation should follow this spec where they conflict.

---

## 4. Recommended Launch Architecture

### 4.1 Launch architecture summary

**Launch path:**

- local VAD
- local STT
- server-side conversation reasoning
- local TTS

**Not launch path:**

- fully local dialogue reasoning on all devices
- raw audio upload as primary path
- multilingual TTS via ExecuTorch built-ins

### 4.2 Why this architecture is right for Vocation Finder

This architecture preserves the most important asset in the system:

- the server-side reasoning rules that determine whether a response is sufficient
- the structured assessment flow
- the consistency of synthesized answers that feed later analysis

It also improves the parts that actually drive conversational feel:

- local transcription latency
- local speech playback latency
- lower jitter than cloud TTS
- fewer full round-trips involving audio file upload

### 4.3 High-level data flow

```text
User speaks
  -> local mic capture
  -> local VAD determines end of utterance
  -> local STT produces transcript + confidence + locale
  -> mobile sends transcript payload to backend
  -> backend decides: follow-up or advance
  -> backend returns assistant text + target locale + speech hints
  -> mobile runs local TTS and plays response
```

### 4.4 Raw audio policy

**Default launch policy:**

- do not upload raw audio in the primary path
- upload transcript, locale, confidence, and timing metadata only

**Optional support path:**

- allow raw audio upload only behind explicit opt-in for debugging, quality review, or support escalation

This improves privacy, reduces payload size, and makes the product claim cleaner.

---

## 5. Device Tiers

### Tier A -- Launch baseline

**Target devices:**

- iPhone 15
- iPhone 15 Plus
- iPhone 16 line
- Pixel 8 and newer
- Galaxy S24 and newer
- comparable Android devices with at least 6 GB RAM

**Required behavior:**

- local STT
- server reasoning
- local TTS

This is the baseline tier the product must feel good on.

### Tier B -- Higher-memory devices

**Target devices:**

- iPhone Pro devices with more headroom
- Android devices with 8 GB to 12 GB RAM

**Optional future behavior:**

- local STT
- local TTS
- optional local follow-up generation in offline mode

This tier is not required for launch.

### Tier C -- Degraded fallback

**Target devices:**

- older devices
- low-storage devices
- devices where local model download fails
- builds where native AI modules are unavailable

**Fallback behavior:**

- existing cloud-assisted path remains available
- OS-native speech APIs may be used where local model path is unavailable

This keeps the app usable without blocking the launch architecture on perfect device support.

---

## 6. Technology Choices

## 6.1 Speech-to-text recommendation

### Recommended launch choice: `whisper.rn`

Use `whisper.rn` with a **multilingual Whisper Tiny** model for launch.

Why:

- battle-tested mobile Whisper binding
- works on iOS and Android
- Expo prebuild compatible
- stronger STT quality story than relying on device-native Android speech quality
- more predictable multilingual performance across English, Spanish, and Portuguese

### Build requirement

All local AI paths in this spec require:

- native modules
- Expo prebuild / custom development build
- real-device testing

They should not be planned around Expo Go.

### Secondary option: `react-native-executorch` STT

`react-native-executorch` remains a credible alternative for local STT and future local LLM work. It should be considered if the team later wants:

- one Meta-centric on-device runtime for STT and some future local reasoning experiments
- built-in VAD integration
- tighter alignment with `.pte`-based mobile AI flows

It is **not** the primary launch recommendation because:

- it does not solve multilingual TTS for launch
- it adds runtime complexity without reducing the need for a separate TTS stack

### STT launch rules

- Default model: multilingual Whisper Tiny
- Evaluate Whisper Base only if benchmarks on iPhone 15 and recent Android devices stay within latency targets
- Input locales supported at launch:
  - `en-US`
  - `es-ES` or `es-419` depending on voice availability
  - `pt-BR`

### STT requirements

- support partial text updates internally if available
- final transcript must include:
  - transcript text
  - locale
  - confidence
  - utterance duration
  - local processing engine identifier

---

## 6.2 Text-to-speech recommendation

### Recommended launch choice: `react-native-sherpa-onnx`

Use `react-native-sherpa-onnx` as the launch TTS stack.

Why:

- it is already partially scaffolded in this repo
- it provides a practical path for multilingual local TTS on iOS and Android
- it is a better fit for en/es/pt launch than relying on current ExecuTorch TTS docs

### TTS model family guidance

Use language-specific local voices chosen by benchmark and subjective review. Do not lock the spec to one brand name unless it passes evaluation on both platforms.

Initial shortlist:

- Sherpa-compatible Kokoro variants where language support is confirmed
- Sherpa-compatible VITS or MeloTTS voices for Spanish and Portuguese
- Piper-compatible voices only if they meet the subjective naturalness bar

### Future premium candidate: Supertonic

Supertonic looks promising as a future premium local TTS option for:

- English
- Spanish
- Portuguese

However, it should **not** be the launch dependency because:

- there is no turnkey React Native integration path
- it would likely require custom iOS and Android bridging
- it would slow down delivery materially

Keep it as a future upgrade path if Sherpa-based voices fail the quality bar.

### Do not use NeuTTS for launch

NeuTTS is not a strong fit for this launch because:

- Portuguese is not the right launch fit based on the official project
- its core flow is more reference-audio-oriented than this product needs
- voice cloning is explicitly not a product requirement

### TTS launch rules

- one default voice per launch locale
- no voice marketplace at launch
- all voices must support calm, warm assistant-style delivery
- TTS output must begin quickly enough to preserve conversational feel

---

## 6.3 Local reasoning recommendation

### Launch: no local reasoning in the primary path

Do **not** put follow-up generation or sufficiency logic on-device in the launch path.

Reasons:

- current assessment logic already exists server-side
- that logic is part of the product, not generic assistant behavior
- duplicating it locally introduces drift
- multilingual launch is already a large scope increase
- iPhone 15-class devices should not be forced into a local LLM baseline

### Future optional local reasoning

If later added, local reasoning should be:

- optional
- device-tiered
- offline-mode-only or privacy-mode-only
- explicitly benchmarked against backend output

### Future local reasoning runtime

If the team adds local reasoning later, prefer:

- `llama.rn` for GGUF-based experimentation

Candidate model class:

- Qwen 2.5 1.5B or another strong multilingual 1B to 1.5B instruct model

Do not baseline:

- 3B local reasoning on iPhone 15 class devices
- full-local reasoning overlap with local TTS on 6 GB devices without benchmarking

---

## 7. Localization Requirements

Multilingual conversation cannot be treated as just a speech-layer problem. The app and backend need a localization model.

### 7.1 Launch locales

- English: `en-US`
- Spanish: choose one canonical launch locale, likely `es-419` if targeting Latin American usage
- Portuguese: `pt-BR`

### 7.2 Required content localization

The following content must exist per launch locale:

- question text
- conversation prompt
- follow-up prompt suggestions
- orientation copy
- microphone permission copy
- interruption / retry copy
- final synthesis output, if the product is marketed as multilingual end-to-end

### 7.3 Data model change

The current `questions` table is not locale-aware enough for multilingual launch.

Recommended addition:

```text
question_translations
- id
- question_id
- locale
- question_text
- conversation_prompt
- follow_up_prompts (json)
- created_at
- updated_at
```

### 7.4 Session locale fields

Add locale-aware fields to the assessment and conversation layer:

- `assessments.locale`
- `conversation_sessions.locale`
- `conversation_turns.content_locale`

Optional but useful:

- `conversation_turns.stt_engine`
- `conversation_turns.stt_confidence`

### 7.5 Analysis behavior

Backend analysis must either:

- analyze the original-language responses directly and return results in the user's locale

or:

- store original-language responses plus normalized English translations for internal analysis

Recommended launch path:

- keep original-language responses
- let the backend LLM handle multilingual analysis
- render final outputs in the user's selected locale

Do not force English-only synthesis if multilingual conversation is a launch promise.

---

## 8. API Contract Changes

## 8.1 `POST /conversations/start`

### Request

```json
{
  "assessment_id": "uuid",
  "locale": "es-419",
  "speech_locale": "es-419",
  "tts_voice_id": "es_default_01",
  "device_capabilities": {
    "local_stt": true,
    "local_tts": true,
    "local_llm": false
  }
}
```

### Response

```json
{
  "session_id": "uuid",
  "current_question_index": 0,
  "status": "active",
  "locale": "es-419",
  "question": {
    "text": "localized prompt text",
    "locale": "es-419"
  }
}
```

## 8.2 `POST /conversations/{session}/turn`

Replace the current "upload audio then transcribe on server" assumption with transcript-first processing.

### Request

```json
{
  "transcript": "localized transcript text",
  "transcript_locale": "pt-BR",
  "transcript_confidence": 0.91,
  "duration_ms": 5400,
  "audio_storage_path": null,
  "client_processing": {
    "stt_engine": "whisper.rn",
    "tts_engine": "sherpa-onnx",
    "app_version": "1.0.0"
  }
}
```

### Response

```json
{
  "response": "assistant reply text",
  "response_locale": "pt-BR",
  "response_kind": "follow_up",
  "current_question_index": 3,
  "is_follow_up": true,
  "is_complete": false,
  "speech": {
    "voice_id": "pt_default_01",
    "style": "warm_calm",
    "allow_barge_in": true
  }
}
```

## 8.3 Optional raw-audio diagnostic endpoint

Keep `POST /conversations/{session}/audio` only for:

- support debugging
- QA comparison
- explicit user opt-in diagnostics

It should no longer be the primary dependency for the conversation path.

---

## 9. Mobile Application Architecture Changes

## 9.1 Introduce a dedicated voice engine abstraction

Do not keep piling new logic directly into `useConversationFlow.ts`.

Create a voice subsystem with clear boundaries:

- `MicCapture`
- `TurnDetector`
- `SpeechRecognizer`
- `SpeechSynthesizer`
- `ConversationTransport`
- `ConversationOrchestrator`

### Suggested responsibility split

- `MicCapture`: platform audio session, mic permission, PCM or record buffers
- `TurnDetector`: VAD, speech start/end detection, timeout behavior
- `SpeechRecognizer`: local STT
- `SpeechSynthesizer`: local TTS, voice selection, warmup
- `ConversationTransport`: transcript-first backend API layer
- `ConversationOrchestrator`: state machine and interruption rules

## 9.1.1 Audio capture requirement

The implementation must support STT-ready audio capture. Do not assume the current "record a full file, then upload it" pattern is sufficient.

Requirements:

- capture speech in a format suitable for local STT
- support utterance-finalization without requiring a long saved file
- avoid designing around only the current `HIGH_QUALITY` file-recording preset

If `expo-audio` can be adapted cleanly for the chosen STT path, keep it. If not, introduce a dedicated PCM-capable audio layer rather than forcing the local STT path through the old file-upload architecture.

## 9.2 State machine

The conversation state machine should become:

- `idle`
- `priming`
- `listening`
- `speech_detected`
- `transcribing`
- `submitting`
- `awaiting_reply`
- `speaking`
- `interrupted`
- `error`

This is better than overloading a smaller set of states.

## 9.3 Replace full-file turn assumption

Current behavior depends on:

- recording a file
- stopping recording
- uploading that file

Launch behavior should instead depend on:

- VAD detecting end-of-turn
- local STT finalizing transcript
- sending transcript payload

The app may still persist local audio clips temporarily for debugging, but it should not require that file path for normal turn submission.

## 9.4 TTS warmup and caching

The TTS engine should be warmed during orientation or intro.

Requirements:

- preload the selected voice before first assistant speech
- keep the voice resident for the session if memory allows
- cache rendered audio only if the engine benefits from it

---

## 10. Audio UX Requirements

Human feel comes from turn-taking and timing more than from model marketing.

## 10.1 Launch interaction model

Launch should be **half-duplex with fast interruptibility**, not true full-duplex.

Meaning:

- user speaks while assistant is silent
- assistant speaks after transcript is processed
- user can interrupt assistant quickly

This is much safer than promising full-duplex echo-cancelled assistant behavior across all devices at launch.

## 10.2 Interruption

Launch requirements:

- manual interrupt gesture must exist
- TTS must stop immediately on interrupt
- if speech barge-in is attempted, it must only be enabled on devices/routes where it passes QA

If speech-based barge-in is unreliable at launch, do **not** force it. A manual interrupt is better than flaky acoustic interruption.

## 10.3 Audio session rules

The app must manage:

- microphone permission
- speaker playback routing
- silent-mode playback on iOS
- switching between recording and playback modes
- headphone / Bluetooth behavior

The current iOS audio mode management already matters here and should be preserved as the voice subsystem is refactored.

---

## 11. Performance Targets

These are the launch performance goals on an iPhone 15-class device with good network conditions.

### 11.1 Latency targets

- Time from end-of-user-speech to final local transcript:
  - p50: <= 700 ms
  - p90: <= 1200 ms
- Time from transcript submit to backend reply:
  - p50: <= 900 ms
  - p90: <= 1800 ms
- Time from backend reply receipt to first audible assistant audio:
  - p50: <= 250 ms
  - p90: <= 500 ms
- End-to-end time from end-of-user-speech to first assistant audio:
  - p50: <= 1800 ms
  - p90: <= 3000 ms

### 11.2 Download size targets

- Initial required model download for a single launch locale:
  - target <= 350 MB
- Optional download for all three launch locales:
  - target <= 550 MB

### 11.3 Stability targets

- no memory-related crash regression on iPhone 15 baseline
- no fatal initialization failures when local voice models are missing
- graceful fallback to server/OS path if local modules fail

---

## 12. Failure Modes and Fallbacks

## 12.1 Local STT unavailable

Fallback order:

1. retry local recognizer
2. use device-native speech recognizer if configured
3. fall back to current upload-audio backend path

## 12.2 Local TTS unavailable

Fallback order:

1. retry Sherpa voice load
2. use OS-native TTS for the selected locale
3. use current backend TTS endpoint

## 12.3 Missing locale resources

Fallback behavior:

- fall back to English only if the locale is missing and the product has not promised that locale
- do **not** silently fall back to English for an advertised launch locale

## 12.4 Storage pressure

If the user lacks storage for local voice models:

- explain the issue clearly
- offer cloud fallback
- do not leave the conversation flow blocked

---

## 13. Privacy and Security

### Launch privacy position

The product should be able to say:

- speech is transcribed locally on your device
- your typed or transcribed answers are sent to the Vocation Finder backend for assessment logic and final analysis
- raw audio is not uploaded by default

### Required privacy behavior

- raw audio upload off by default
- transcripts and locale metadata stored securely
- local model downloads validated and versioned
- support logs must not accidentally contain full transcript content unless explicitly intended

---

## 14. Rollout Plan

## Phase 0 -- Benchmark and wiring spike

Goals:

- prove `whisper.rn` on iPhone 15 and one recent Android device
- prove `react-native-sherpa-onnx` TTS for en/es/pt
- verify custom dev client / native build path

Deliverables:

- benchmark table
- selected STT model
- selected TTS voices
- final locale naming decision

## Phase 1 -- Local TTS production path

Goals:

- finish the existing `localTts.ts` path
- add multilingual voice selection
- keep current backend reasoning

Deliverables:

- local TTS default path
- OS-native and backend TTS fallbacks
- analytics around startup and playback timing

## Phase 2 -- Local STT and transcript-first turn API

Goals:

- remove raw audio upload as the primary path
- submit transcripts directly
- keep optional audio diagnostic upload

Deliverables:

- new mobile voice subsystem
- new backend transcript-first endpoint contract
- confidence and locale propagation

## Phase 3 -- Localization completion

Goals:

- localized questions
- localized follow-up prompts
- localized assistant replies and final synthesis output

Deliverables:

- locale-aware question storage
- session locale persistence
- QA for English, Spanish, and Portuguese

## Phase 4 -- Optional advanced offline mode

Goals:

- benchmark `llama.rn` local reasoning only on higher-memory devices
- enable it behind capability detection and feature flag

Deliverables:

- offline mode flag
- device gating
- quality comparison against server reasoning

---

## 15. Testing Plan

## 15.1 Device matrix

Minimum:

- iPhone 15
- iPhone 16 class device
- Pixel 8 or 9
- Galaxy S24 class device
- one 6 GB RAM Android device

## 15.2 Language matrix

Test across:

- English
- Spanish
- Portuguese

For each:

- quiet indoor speech
- mild background noise
- different accents
- short answers
- long reflective answers

## 15.3 Functional scenarios

- intro question playback
- standard answer and follow-up
- answer sufficient, advance to next question
- interruption during assistant speech
- offline after local STT but before backend submit
- local TTS model missing
- local STT failure

## 15.4 Metrics to capture

- local STT latency
- backend reasoning latency
- time to first audio
- fallback rate
- interruption frequency
- crash rate
- memory pressure warnings

---

## 16. Definition of Done

The launch implementation is done when all of the following are true:

- English, Spanish, and Portuguese are supported end-to-end for the conversational assessment
- iPhone 15-class devices meet the performance targets in this document
- recent Android devices meet acceptable latency and stability thresholds
- server-side assessment logic remains the source of truth for follow-up and sufficiency decisions
- local STT is the default path
- local TTS is the default path
- raw audio upload is no longer the primary dependency
- clear fallbacks exist for unsupported or degraded devices

---

## 17. Implementation Ownership Recommendation

### Recommendation

**Use GPT-5.4 Codex as the primary implementer.**

### Why

This implementation is not mainly a brainstorming problem anymore. It is a repo-specific delivery problem involving:

- Expo React Native app surgery
- native module integration
- audio-state refactors
- Laravel API contract changes
- data model and localization changes
- careful migration from the current voice flow

That favors the model that is strongest at:

- inspecting the live repo
- editing multiple files coherently
- keeping implementation aligned to actual code
- running local verification loops
- managing incremental migration risk

That is the stronger fit for GPT-5.4 Codex in this environment.

### Where Claude is still useful

Claude Opus 4.6 is useful as a **secondary reviewer** for:

- prompt wording
- conversation tone review
- localization copy review
- critique of the final architecture or product behavior

### Final call

If you want one primary implementation model, choose:

- **GPT-5.4 Codex for implementation**

If you want a two-model workflow, use:

- **GPT-5.4 Codex for implementation**
- **Claude Opus 4.6 for review and prompt-language critique**

---

## 18. References

### Internal repo references

- `mobile/hooks/useConversationFlow.ts`
- `mobile/services/localTts.ts`
- `mobile/stores/assessmentStore.ts`
- `mobile/package.json`
- `app/Http/Controllers/Api/V1/AudioConversationController.php`
- `app/Ai/Agents/ConversationAgent.php`
- `app/Models/Question.php`
- `specs/on-device-ai-architecture-recommendation.md`
- `specs/hybrid-on-device-ai-architecture-research.md`
- `specs/on-device-speech-processing-research.md`

### External references

- [React Native ExecuTorch docs](https://docs.swmansion.com/react-native-executorch/)
- [ExecuTorch useSpeechToText](https://docs.swmansion.com/react-native-executorch/docs/hooks/natural-language-processing/useSpeechToText)
- [ExecuTorch useTextToSpeech](https://docs.swmansion.com/react-native-executorch/docs/next/hooks/natural-language-processing/useTextToSpeech)
- [ExecuTorch memory benchmarks](https://docs.swmansion.com/react-native-executorch/docs/benchmarks/memory-usage)
- [ExecuTorch inference benchmarks](https://docs.swmansion.com/react-native-executorch/docs/benchmarks/inference-time)
- [whisper.rn](https://github.com/mybigday/whisper.rn)
- [llama.rn](https://github.com/mybigday/llama.rn)
- [React Native Sherpa-ONNX docs](https://xdcobra-react-native-sherpa-onnx.mintlify.app/introduction)
- [Supertonic](https://github.com/supertone-inc/supertonic)
- [NeuTTS](https://github.com/neuphonic/neutts)
