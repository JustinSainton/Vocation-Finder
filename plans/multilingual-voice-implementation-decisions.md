# Multilingual Voice Implementation Decisions

Working log for implementation decisions made without waiting for user input.

**Started:** 2026-03-30
**Related spec:** `specs/multilingual-on-device-conversational-voice-spec.md`

---

## Decision 1: Implement backend-first and keep compatibility during migration

**Decision**

Implement the backend transcript-first, locale-aware API before switching the mobile app fully to local STT.

**Why**

- The current mobile flow depends on the backend contract.
- A backward-compatible backend lets the app migrate incrementally.
- This reduces the risk of breaking the existing conversation feature while native speech work is in progress.

---

## Decision 2: Treat the current upload-audio path as a compatibility fallback, not the primary path

**Decision**

Keep the existing `/conversations/{session}/audio` route available for fallback and diagnostics while moving the primary path to transcript submission.

**Why**

- It preserves a recoverable path if local STT is unavailable on a given build or device.
- It avoids forcing a full cutover before native speech modules are verified in this repo.

---

## Decision 3: Re-evaluate local STT runtime based on installability in this repo

**Decision**

Although the spec recommends `whisper.rn` as the default STT direction, implementation is allowed to consolidate on `react-native-sherpa-onnx` for both STT and TTS if that produces a cleaner and more reliable integration in this Expo/native setup.

**Why**

- The repo already contains a partial Sherpa TTS integration.
- Sherpa's current package supports both streaming STT and TTS.
- Reducing the number of native runtimes lowers integration complexity.

**Status**

Still being evaluated during implementation. If the final code path deviates from the spec here, it will be called out again at the end.

---

## Decision 4: Use per-turn offline STT first, not live streaming STT

**Decision**

Implement local STT by transcribing each completed recorded turn on-device with Sherpa Whisper Tiny after recording stops, instead of rebuilding the conversation UI around fully streaming microphone transcription in this pass.

**Why**

- The current mobile flow already records discrete turns with `expo-audio`.
- This keeps the interaction stable while removing the primary cloud transcription dependency.
- It is a smaller and safer integration step than replacing the recorder with a fully streaming PCM pipeline immediately.

**Trade-off**

- It does not yet provide true live partial captions.
- The app still feels conversational because the reasoning and TTS stages are now transcript-first and locale-aware, but there is still a short post-recording transcription step.

---

## Decision 5: Keep server audio upload as an automatic fallback, not a second-class manual mode

**Decision**

If local STT fails on a given device or build, the mobile app automatically falls back to the existing audio upload transcription path for that turn.

**Why**

- It avoids bricking the voice experience on unsupported or misconfigured native builds.
- It gives the new local pipeline a safe failure mode while still shipping the transcript-first architecture.

---

## Decision 6: Use locale-specific Piper-style TTS defaults instead of a single multilingual TTS model

**Decision**

Default local TTS to one explicit model per launch locale:

- `en-US` → `vits-piper-en_US-lessac-medium-int8`
- `es-419` → `vits-piper-es_MX-ald-medium-int8`
- `pt-BR` → `vits-piper-pt_BR-cadu-medium-int8`

**Why**

- This is more deterministic than relying on auto-selection across mixed voice inventories.
- It gives better immediate coverage for English, Spanish, and Brazilian Portuguese than the current React Native ExecuTorch Kokoro path.
- It keeps voice selection simple while still allowing env overrides later.

---

## Decision 7: Default local STT/TTS to enabled when native modules are available

**Decision**

Treat local STT and local TTS as the default production path in native builds, with env vars available to disable them, rather than making them opt-in.

**Why**

- The spec goal is on-device speech, not a hidden experimental mode.
- Expo Go still naturally falls back because the native Sherpa modules are unavailable there.
- This lets release/dev-client builds behave like the intended product without extra operator steps.

---

## Decision 8: Use a lightweight locale copy map on mobile instead of a full i18n framework

**Decision**

Implement mobile UI localization for the assessment flow with a typed locale helper and bounded copy maps, rather than introducing a general-purpose i18n package during this migration.

**Why**

- The current scope is intentionally narrow: three locales and one bounded user journey.
- A full i18n framework would add migration complexity across the app without materially improving this feature delivery.
- The helper keeps locale normalization, speech-language mapping, and assessment copy in one place.

---

## Decision 9: Keep narrative parsing headers in English while localizing the prose

**Decision**

For generated vocational profiles, require the model to write the body text, bullets, and steps in the selected locale while preserving the exact English markdown section headers.

**Why**

- The existing parser and validation logic depend on those headers.
- This localizes the user-facing content without forcing a risky rewrite of the narrative extraction pipeline.

---

## Decision 10: Localize downstream result delivery, not only the conversation loop

**Decision**

Apply locale-aware copy to results polling messages, API responses, email subject lines, PDF/email section labels, and mobile results chrome in the same implementation pass.

**Why**

- A multilingual conversation that ends in English-only results would feel unfinished.
- The assessment experience is one flow; the locale should carry through to synthesis and delivery, not stop at the microphone.

---

## Decision 11: Keep local speech runtime operator-controllable through env toggles

**Decision**

Preserve environment-variable escape hatches for local speech:

- `EXPO_PUBLIC_LOCAL_STT_ENABLED=false`
- `EXPO_PUBLIC_LOCAL_TTS_ENABLED=false`

Optional model overrides remain supported through env vars.

**Why**

- This gives a safe rollback path if a native build exposes device-specific issues.
- It allows QA to compare local vs. fallback behavior without code changes.
