# On-Device AI Architecture — Consolidated Recommendation

**Date:** 2026-03-02
**Status:** Recommendation — pending decision
**Inputs:** `specs/on-device-llm-research.md`, `specs/on-device-speech-processing-research.md`, `specs/hybrid-on-device-ai-architecture-research.md`, user research (Llama 3.1 8B Instruct)

---

## Executive Summary

A fully on-device conversational AI pipeline is **technically possible on high-end devices** (iPhone 15 Pro+, flagship Android with 8GB+ RAM) but comes with real quality trade-offs for the vocational analysis — the part users care most about. The recommended architecture is a **tiered hybrid** that maximizes on-device processing while preserving output quality for the final deliverable.

The key insight: **conversation flow** (STT, follow-up generation, TTS) can run on-device with acceptable quality. **Vocational profile synthesis** — the 20-response multi-dimensional analysis — cannot, at least not at the quality level that justifies a paid product.

---

## Llama 3.1 8B Instruct — Assessment

**Source:** [meta-llama/Llama-3.1-8B-Instruct](https://huggingface.co/meta-llama/Llama-3.1-8B-Instruct)

### Specs
| Metric | Value |
|--------|-------|
| Parameters | 8B |
| Context window | 128K tokens |
| MMLU | 69.4 |
| Architecture | Llama 3.1 (GQA, RoPE) |
| Training | SFT + RLHF on 15T+ tokens |
| License | Llama 3.1 Community License (commercial OK) |

### On-Device Feasibility

| Format | Size | RAM Required | iPhone 15 Pro (A17 Pro) | iPhone 16 Pro (A18 Pro) |
|--------|------|-------------|------------------------|------------------------|
| BFloat16 (full) | ~16 GB | ~18 GB | Not possible | Not possible |
| Q8_0 (GGUF) | ~8.5 GB | ~10 GB | Not possible | Borderline |
| **Q4_K_M (GGUF)** | **~4.5 GB** | **~5-6 GB** | **15-30 tok/s** | **25-40 tok/s** |
| Q4_0 (GGUF) | ~4.3 GB | ~5.5 GB | 20-35 tok/s | 30-45 tok/s |
| Q3_K_M (GGUF) | ~3.7 GB | ~4.5 GB | 25-40 tok/s | 35-50 tok/s |

### Verdict: Excellent quality, but high-end devices only

**Pros:**
- MMLU 69.4 vs ~50 for Llama 3.2 3B — significantly better reasoning
- 128K context window easily fits all 20 assessment responses
- Quality sufficient for follow-up question generation AND potentially lightweight analysis
- Commercial license

**Cons:**
- Q4 quantization is ~4.5 GB download — large post-install model download
- Needs ~5-6 GB free RAM during inference — only works on 8GB+ RAM devices
- Cannot run concurrently with STT/TTS — must sequence pipeline steps
- 15-30 tok/s on A17 Pro means a 500-token follow-up response takes ~17-33 seconds to generate (too slow for real-time conversation)
- On 6GB RAM Android devices: will crash or be killed by OS memory pressure

**Bottom line:** Llama 3.1 8B is the right quality level for this use case, but it's **too slow for real-time conversational follow-ups** (15-30 tok/s). It could work for **background analysis** on high-end devices, but not for the interactive conversation flow where users expect sub-3-second responses.

---

## Recommended Architecture: Tiered Hybrid

### Tier 1 — Real-Time Conversation (on-device where possible)

The 20-minute conversational assessment needs sub-3-second turn latency.

| Component | Recommended | Fallback | Notes |
|-----------|-------------|----------|-------|
| **STT** | `expo-speech-recognition` (native) | Moonshine via executorch (26 MB) | Native is zero-download, ~8% WER, works on both platforms |
| **Follow-up generation** | Llama 3.2 1B via `react-native-executorch` | Cloud Claude Haiku | 1B: ~750 MB download, 30-50 tok/s, generates follow-ups in 1-2s |
| **TTS** | `expo-speech` (native) | Cloud OpenAI TTS "nova" | Native is ~3.0-3.5 MOS; cloud is ~4.5 MOS. User preference toggle? |

**End-to-end latency (on-device path):** ~2-4 seconds
- STT: ~0.5-1s (streaming, partial results)
- LLM (1B): ~0.6-1.5s for a follow-up prompt
- TTS: ~0.3-0.5s (native, begins before LLM finishes)

**End-to-end latency (cloud path):** ~3-5 seconds
- STT: ~1-2s (upload + Whisper API)
- LLM: ~1-2s (Claude Haiku, streaming)
- TTS: ~0.5-1s (OpenAI TTS, streaming)

### Tier 2 — Vocational Profile Synthesis (cloud required)

After all 20 questions are answered, the full vocational profile synthesis runs.

| Component | Recommended | Notes |
|-----------|-------------|-------|
| **Analysis engine** | Cloud Claude Sonnet/Opus via Laravel AI SDK | Two-phase prompt (Pattern Analysis → Narrative Synthesis) |
| **Fallback** | Cloud Claude Haiku (lighter analysis for free tier) | Lower quality but still dramatically better than any on-device model |

**Why cloud is required here:**
- The synthesis prompt processes ~8,000-15,000 tokens of user responses + system prompt
- It must produce a nuanced, multi-dimensional vocational profile with ministry integration
- On-device Llama 3.2 1B/3B: quality is insufficient (hallucinations, shallow analysis, poor narrative voice)
- On-device Llama 3.1 8B Q4: quality is better but still below cloud Claude, and takes 4-5 minutes to generate
- This is the deliverable users pay for — quality cannot be compromised

### Tier 3 — Optional On-Device Analysis (future, high-end only)

For users who want fully offline operation, a lower-quality on-device analysis could be offered as an explicit option.

| Component | Recommended | Devices |
|-----------|-------------|---------|
| **Analysis engine** | Llama 3.1 8B Q4_K_M via `llama.rn` | iPhone 15 Pro+ (8 GB RAM), flagship Android (8 GB+ RAM) |
| **Download size** | ~4.5 GB (post-install, Wi-Fi only) | Explicit user opt-in |
| **Generation time** | ~3-5 minutes for full profile | Must show progress indicator |
| **Quality disclaimer** | "On-device analysis may be less detailed than cloud analysis" | Set expectations |

This tier is a **future consideration**, not MVP. It requires:
- Device capability detection (RAM, chipset)
- Background model download with progress tracking
- Quality benchmarking against cloud output
- Clear UI indication of analysis mode

---

## Model Comparison for This Use Case

| Model | Size (Q4) | RAM | Speed (A17) | MMLU | Follow-ups | Full Analysis | Devices |
|-------|-----------|-----|-------------|------|------------|---------------|---------|
| Llama 3.2 1B | ~750 MB | ~1.5 GB | 30-50 tok/s | ~30 | Good | Poor | All modern |
| Llama 3.2 3B | ~2 GB | ~3 GB | 15-30 tok/s | ~50 | Good | Mediocre | 6 GB+ RAM |
| **Llama 3.1 8B** | **~4.5 GB** | **~5-6 GB** | **15-30 tok/s** | **69.4** | **Too slow** | **Decent** | **8 GB+ only** |
| Cloud Claude Haiku | N/A | N/A | ~80+ tok/s | ~75 | Excellent | Good | Any (needs network) |
| Cloud Claude Sonnet | N/A | N/A | ~60+ tok/s | ~89 | Excellent | Excellent | Any (needs network) |

**Key insight:** Llama 3.2 1B is better for real-time conversation (fast enough for follow-ups), while Llama 3.1 8B is better for batch analysis (higher quality but too slow for interactive use).

---

## React Native Integration Stack

### Primary (Recommended)

```
react-native-executorch (Software Mansion)
├── Llama 3.2 1B (.pte format, ExecuTorch optimized)
├── Uses Apple Neural Engine / Qualcomm NPU
├── useLLM() hook with streaming
├── Expo SDK 54+ (custom dev build required)
└── Pre-exported models on HuggingFace
```

### Alternative

```
llama.rn (v0.11+, MIT license)
├── Llama 3.1 8B GGUF Q4_K_M (for Tier 3 offline analysis)
├── CPU + GPU inference via llama.cpp
├── More model format flexibility (GGUF ecosystem)
├── Slightly less optimized for mobile NPUs
└── Better for larger models that need GGUF quantization
```

### Speech

```
expo-speech-recognition (STT — native platform APIs)
expo-speech (TTS — native platform APIs)
├── Zero download, zero model management
├── Works in Expo Go for development
└── Quality acceptable for conversation flow
```

---

## Implementation Phases

### Phase 1 (MVP): Cloud-Only Pipeline
- Laravel AI SDK handles all AI (STT via `Transcription::fromUpload()`, analysis via Agent classes, TTS via `Audio::of()`)
- Fastest to market, highest quality
- Cost: ~$0.10-0.50 per assessment

### Phase 2: Hybrid — On-Device STT + Cloud Analysis
- Replace cloud STT with `expo-speech-recognition` (native)
- Reduces latency for conversation flow
- Reduces API costs by eliminating STT calls
- Cloud still handles analysis + TTS

### Phase 3: Full Hybrid — On-Device Conversation
- Add Llama 3.2 1B via `react-native-executorch` for follow-up generation
- Add native TTS via `expo-speech`
- Cloud only needed for final synthesis
- Cost drops to ~$0.05-0.15 per assessment (one cloud call for synthesis)

### Phase 4 (Future): Offline Analysis Option
- Add Llama 3.1 8B via `llama.rn` for high-end device offline analysis
- Optional 4.5 GB model download
- Lower quality with explicit disclaimer
- True zero-cloud option for privacy-sensitive users

---

## Questions for Co-Founder's Academic Advisors

Based on this research, these questions would help inform the architecture:

1. **Quality threshold:** What is the minimum acceptable quality for a vocational assessment output? Would a "directionally correct but less nuanced" on-device analysis be acceptable, or does this undermine the assessment's credibility?

2. **Privacy requirements:** How important is data sovereignty / on-device processing to the target audience (churches, faith-based organizations)? Would "audio never leaves your device" be a meaningful selling point?

3. **Psychometric validity:** If on-device models produce slightly different analysis than cloud models for the same inputs, does this create psychometric validity concerns? Is the assessment validated against a specific AI model?

4. **Offline use cases:** Are there real scenarios where users would need to take the assessment offline (e.g., retreats without Wi-Fi, international missions trips)?

5. **Device demographics:** What devices does the target audience actually use? If the majority are on iPhone 12/13 (6 GB RAM), the 8B model is out. If they're on iPhone 15+ (8 GB RAM), it opens up.

---

## References

- `specs/on-device-llm-research.md` — Full LLM framework comparison
- `specs/on-device-speech-processing-research.md` — STT/TTS quality benchmarks
- `specs/hybrid-on-device-ai-architecture-research.md` — Architecture patterns and platform APIs
- [react-native-executorch](https://github.com/software-mansion/react-native-executorch) — Software Mansion's ExecuTorch bridge
- [llama.rn](https://github.com/nicepkg/llama.rn) — llama.cpp React Native bindings
- [Llama 3.1 8B Instruct](https://huggingface.co/meta-llama/Llama-3.1-8B-Instruct) — User-suggested model
- [Laravel AI SDK](https://laravel.com/ai) — Official Laravel AI integration
