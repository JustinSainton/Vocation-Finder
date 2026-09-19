# AI Model Landscape — September 2026 Refresh

**Date:** 2026-09-19. **Supersedes:** `specs/on-device-ai-architecture-recommendation.md` (April 2026) where they disagree.
**Method:** primary sources and independent benchmarks only; vendor claims are labeled as such.
**Budget constraint:** ~$2–3/month per user — inference must round to zero.

---

## 1. What changed since April

1. **Jev (TypeSafe AI, released Sep 15 2026)** — a non-generative "System One" model: typed decisions (boolean / choice / score) with calibrated confidence in 70–500 ms, ~$0.042/MTok input, output free. Early access. Sources: [jevapi.dev](https://jevapi.dev/), [LangChain guide](https://www.langchain.com/blog/building-a-harness-with-jev), [heise](https://www.heise.de/en/news/AI-model-Jev-to-make-machines-decide-faster-11457071.html). Vendor speed/cost multiples (up to 200×/400×) are **unverified by third parties**; constrained shape ≠ correct answer.
2. **Server small models got frontier-cheap:** DeepSeek V3.2 Exp ($0.27/$0.41), Qwen3 32B ($0.08/$0.28), Gemma 3 27B ($0.08/$0.16), GLM-5.3 Flash (promo $0.075/$0.25) — all via OpenRouter with `:floor` / `max_price` routing and 20+ free models. Sources: [pricepertoken IFEval](https://pricepertoken.com/leaderboards/benchmark/ifeval), [OpenRouter lowest-cost guide](https://openrouter.ai/blog/tutorials/how-to-get-the-lowest-cost-llm-inference-on-openrouter), [Sept 2026 model roundup](https://www.teamday.ai/blog/best-ai-models-2026). Prices move weekly — re-check before pinning.
3. **On-device speech lapped Whisper:** Parakeet TDT-0.6B (~32–103× realtime, 465–500 MB, CoreML/ANE) and streaming Parakeet-EOU-120M (232 MB, end-of-utterance head, no separate turn detector); SenseVoice Small dominates CJK. Whisper Large V3 Turbo manages ~13×. Sources: [WhisperNotes benchmark](https://whispernotes.app/blog/sensevoice-fastest-cjk-transcription) (M4 Pro, same files, same conditions), [Soniqo Parakeet guide](https://cloud.soniqo.audio/guides/parakeet), [parakeet-coreml-swift](https://github.com/mweinbach/parakeet-coreml-swift). Parakeet covers 25 European languages **including Spanish and Portuguese** — both locales we ship.
4. **On-device TTS shrank:** Supertonic-3 (99M, 31 langs), Chatterbox Flash (0.27s TTFT), CosyVoice 3 (cloning) vs our bundled Kokoro-82M (329 MB).
5. **On-device LLMs improved but still can't do our analysis:** Artificial Analysis (iPhone 17 Pro, Aug 2026) puts the best phone models at score ~63 with 8–21 s per 1K prompt tokens and multi-GB RAM; MoE (LFM2.5-8B-A1B, Ling Tiny) is the efficiency frontier. Our analysis needs 17-category reasoning plus an 8K-token narrative — minutes on-phone with heavy battery cost. Sources: [Artificial Analysis](https://artificialanalysis.ai/articles/mobile-phone-intelligence-inference), [Apple-silicon bench](https://github.com/john-rocky/apple-silicon-llm-bench). **The April tiered-hybrid stance holds.**

---

## 2. Cost math for OUR pipeline (per assessment)

Measured from `AnalyzeAssessmentJob`: ~4 sequential calls (signals → analysis → conditional disambiguation → narrative, ≤2 attempts). Estimated tokens: **~30–45K in / ~8–12K out.**

| Model (OpenRouter, Sep 2026) | Est. cost/assessment |
|---|---|
| DeepSeek V3.2 Exp ($0.27/$0.41) | ~1.5¢ |
| Qwen3 32B ($0.08/$0.28) | ~0.6¢ |
| Gemma 3 27B ($0.08/$0.16) | ~0.5¢ |
| GLM-5.3 Flash promo ($0.075/$0.25) | ~0.5¢ |
| Jev, closed-vocab calls ($0.042 in, out free) | fractions of a cent per decision |

Coach turns dominate monthly cost, not assessments: ~2–4K context in + ~300 out per turn ≈ 0.1¢/turn on DeepSeek-tier → 50 turns ≈ a nickel. Prompt caching is the force multiplier — our taxonomy prompt is **static per engine version**, and DeepSeek off-peak cache reads are ~$0.007–0.022/MTok. Assessments land **under a cent**; a chatty month stays cents. $2–3/user/mo has roughly 100× headroom.

---

## 3. Recommendations

### R1. Evaluate cheap server models against the golden corpus (highest leverage)
We own the perfect harness: `tests/Fixtures/Engine` + `EngineFixtureLiveTest`. Run the corpus against DeepSeek V3.2 / Qwen3-32B / GLM Flash tier and compare: provenance intact, lint catches, confidence calibration, dual-track gap. If a 0.5¢ model holds the line, portrait cost and latency both fall ~10× with zero architecture change. Keep the deterministic red-team lint regardless — it's free and model-independent.

### R2. Apply for Jev early access now; trial it on closed vocabularies
Our engine's governing rule — *never ask a model for a value we can compute; constrain every closed vocabulary with an enum* — is Jev's entire product thesis. Candidate decisions: gap-type classification (`GapType`), evidence standing (`EvidenceStanding`), readiness factors, clarity standings. 70–500 ms + free output would take the snappiest parts of the pipeline off the generative path entirely. Gates: early access, unverified vendor numbers, and shape-guarantees-don't-equal-correctness — every Jev call needs a confidence threshold with a generative fallback, exactly as jevapi.dev itself advises.

### R3. Replace whisper-tiny with Parakeet (STT)
Parakeet-EOU-120M for streaming conversation turns (232 MB, EOU head removes our turn detector), TDT-0.6B CoreML for accuracy, SenseVoice Small for CJK if we expand beyond es/pt. Bonus: CoreML/ANE execution leaves the GPU free for TTS — no contention. Trial via `parakeet-coreml-swift` (iOS 17+, ANE/GPU/CPU selectable).

### R4. Trial a smaller TTS to reclaim ~200+ MB
Supertonic-3 (99M) or Chatterbox Flash first; keep Kokoro as fallback. Compare MOS on coach-voice samples, not benchmarks.

### R5. Do NOT move analysis on-device
The August iPhone data says our workload would take minutes and meaningful battery. Hybrid stands: on-device for turns, server for the portrait.

### R6. Portrait latency wins, in order
1. Faster/cheaper analysis model (R1) — same calls, less time each.
2. Prompt caching on the static taxonomy (off-peak cache ≈ free).
3. Streamed narrative sections rendered progressively (real project; biggest perceived win).
4. Notify-on-complete so waiting is never blocking (cheap; pairs with the elapsed-timer wait screen).

---

## 4. What this means for the $2–3 seat

Nothing in the current pipeline threatens the budget even before optimization: assessments ≈ 1–2¢ on mid-tier models, coach ≈ 5¢/heavy month. After R1+R2+cache: assessments < 0.5¢, decisions ≈ free, coach ≈ 2–3¢. Inference is not the margin risk — SMS, app-store fees, and support are.
