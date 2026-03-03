# On-Device LLM Research for Vocational Discernment App

## Research Date: March 2026

## Executive Summary

Running LLMs on-device for a vocational discernment assessment app is technically feasible in early 2026, but **the quality gap between on-device models and cloud models (Claude Sonnet/Haiku, GPT-4o-mini) remains significant for complex analytical tasks like multi-dimensional vocational profiling**. The technology has progressed rapidly --- React Native bridges now exist, inference speeds are usable, and the Expo ecosystem has maturing options --- but the core limitation is that sub-3B parameter models cannot reliably produce the nuanced, multi-dimensional narrative analysis that this use case requires.

**Bottom line recommendation**: Use a **hybrid architecture** where on-device models handle lightweight tasks (follow-up question generation, initial response categorization) while cloud APIs (Claude Sonnet/Haiku) handle the complex vocational profile synthesis. This gives you privacy benefits for initial processing while ensuring output quality for the deliverable the user cares most about.

---

## 1. On-Device LLM Frameworks (State of Play, Early 2026)

### 1.1 ExecuTorch (Meta) --- RECOMMENDED for React Native

- **Status**: Hit 1.0 GA in October 2025, production-ready
- **Runtime footprint**: 50KB base
- **Backend support**: 12+ hardware backends (Apple Neural Engine, Qualcomm Hexagon NPU, Arm, MediaTek, Vulkan)
- **Production usage**: Powers AI features across Instagram, WhatsApp, Messenger, and Facebook (billions of users)
- **React Native bridge**: `react-native-executorch` by Software Mansion (the team behind Reanimated, Gesture Handler)
  - Expo SDK 54+ compatible (requires custom dev build, not Expo Go)
  - Supports Llama 3.2 (1B, 3B), Qwen 3 (0.6B, 1.7B, 4B), SmolLM 2, Phi 4 Mini, Hammer 2.1
  - Provides `useLLM` hook with streaming, tool calling, structured output (JSON Schema/Zod)
  - Built-in conversation management, context windowing strategies
  - Pre-exported `.pte` model files available on HuggingFace
- **Verdict**: The most mature, best-supported option for React Native / Expo apps

### 1.2 MLC LLM (Machine Learning Compilation)

- **Status**: Mature, compiles models to target diverse hardware via TVM
- **Backend**: OpenCL for Android GPUs, Metal for iOS
- **React Native bridge**: `@react-native-ai/mlc` by Callstack (Vercel AI SDK compatible)
- **Performance**: On GPU, achieved 89.2 tok/s prefill and 11.2 tok/s decode with Q4F16 quantization for Llama 3.2 3B
- **Limitation**: Requires 1-8 GB RAM depending on model; Android Mali GPU performance is significantly weaker than Adreno/Apple GPU
- **Verdict**: Strong alternative, especially if you want Vercel AI SDK compatibility. Less mature React Native integration than ExecuTorch

### 1.3 llama.cpp

- **Status**: The gold standard for CPU-based inference, continuously optimized
- **Format**: GGUF has become the de facto standard for quantized model distribution
- **Mobile ports**: Available but less optimized for mobile GPUs/NPUs than ExecuTorch
- **React Native bridge**: Indirect (via Cactus framework, see below)
- **Performance**: Peak prefill speed up to 56 tok/s with INT8 matrix multiplication on newer Arm cores
- **Verdict**: Excellent for prototyping and desktop, but ExecuTorch is recommended over raw llama.cpp for production mobile deployment

### 1.4 Cactus (Y Combinator-backed)

- **Status**: v1 released December 2025; cross-platform mobile inference engine
- **React Native SDK**: Available (v0 bindings; v1 bindings in development)
- **Performance**: Qwen3-600M at up to 75 tok/s on modern phones, 20 tok/s on older models (INT8 + NPU)
- **Features**: Tool calling, RAG support, voice transcription
- **Licensing**: Free for students, educators, non-profits, small businesses; open-source
- **Verdict**: Promising but very new; v1 React Native bindings still in development. Worth watching

### 1.5 Apple Foundation Models Framework (iOS 26)

- **Status**: Available with iOS 26 (released September 2025)
- **Model**: ~3B parameter on-device model, tightly integrated with Apple Silicon (CPU/GPU/Neural Engine)
- **Context window**: 4,096 tokens total (input + output combined) --- this is a critical limitation
- **Capabilities**: Text generation, guided generation (structured output), tool calling, streaming
- **React Native bridge**: `@react-native-ai/apple` by Callstack (preview release, requires RN 0.80+ or Expo Canary, New Architecture)
- **Cost**: Free --- no per-token billing
- **Critical limitation**: 4K context window is far too small for this use case. With 20 responses of 500-2000 words each, you would need 10,000-40,000 tokens of input alone. The model is also explicitly "not designed to be a chatbot for general world knowledge"
- **Verdict**: Not suitable for vocational profile synthesis due to context window limits. Could be used for individual response-level tasks only

### 1.6 Google Gemini Nano / ML Kit GenAI APIs

- **Status**: Available on Pixel 9+, Pixel 10, select Samsung flagships via ML Kit GenAI APIs
- **Capabilities**: Summarization, proofreading, rewriting, image description (high-level APIs only)
- **Critical limitation**: Works best for "tasks where requests can be clearly specified rather than open-ended use cases." No direct model access --- only pre-built task APIs. Not available on iOS. Limited device support
- **Verdict**: Not suitable. Too constrained, Android-only, and the API surface does not support custom prompt-driven generation

### 1.7 ONNX Runtime Mobile

- **Status**: React Native package available (`onnxruntime-react-native`), `react-native-transformers` library enables HuggingFace model usage
- **Limitation**: Pure JavaScript ONNX inference is "not recommended for production mobile apps due to performance and memory constraints." Better suited for vision/classification tasks than LLM inference
- **Verdict**: Not recommended for LLM inference on mobile. ExecuTorch and MLC LLM are superior

### 1.8 Additional React Native / Expo Libraries

| Library | Engine | Status | Notes |
|---------|--------|--------|-------|
| `react-native-executorch` (Software Mansion) | ExecuTorch | Production-ready | Best Expo support, `useLLM` hook |
| `@react-native-ai/mlc` (Callstack) | MLC LLM | Preview | Vercel AI SDK compatible |
| `@react-native-ai/apple` (Callstack) | Apple Foundation Models | Preview | iOS 26 only, 4K context limit |
| `expo-llm-mediapipe` | MediaPipe | Community | Google MediaPipe LLM Inference API |
| `expo-ai-kit` | System LLM | Community | Thin wrapper over device's built-in model |
| `cactus` | Custom (llama.cpp-based) | v0 RN bindings | Y Combinator-backed, v1 bindings in development |

---

## 2. Models Suitable for Mobile Deployment

### 2.1 Model Comparison Table

| Model | Parameters | Quantized Size (Q4) | Context Length | MMLU (approx) | Best For |
|-------|-----------|---------------------|----------------|---------------|----------|
| Llama 3.2 1B | 1B | ~0.8 GB | 128K | ~50 | Simple tasks, fast responses |
| Llama 3.2 3B | 3B | ~2.0 GB | 128K | ~63 | Best balance for mobile |
| Qwen 3 0.6B | 0.6B | ~0.4 GB | 32K | ~45 | Ultra-fast, basic tasks |
| Qwen 3 1.7B | 1.7B | ~1.1 GB | 32K | ~55 | Good speed/quality balance |
| Qwen 3 4B | 4B | ~2.5 GB | 32K | ~68 | Higher quality, flagship phones only |
| Qwen 3.5 2B | 2B | ~1.3 GB | 262K | ~58 | New; multimodal, long context |
| Phi-3.5 Mini | 3.8B | ~2.3 GB | 128K | ~69 | Strong reasoning, code |
| Phi 4 Mini | 3.8B | ~2.3 GB | 128K | ~70 | Best quality in class |
| Gemma 2 2B | 2.6B | ~1.6 GB | 8K | ~55 | Good conversation |
| SmolLM 2 1.7B | 1.7B | ~1.1 GB | 8K | ~50 | Ultra-compact |
| Hammer 2.1 3B | 3B | ~2.0 GB | 128K | ~60 | Tool calling optimized |

### 2.2 Model Recommendations for This Use Case

**For follow-up question generation (on-device):**
- **Qwen 3 1.7B** or **Llama 3.2 3B** --- good instruction following, fast enough for real-time
- These models can generate contextually relevant follow-up questions from a single response

**For vocational profile synthesis (cloud only):**
- No on-device model can match the quality needed. Use Claude Sonnet 4 / Haiku for this
- Requires processing 10,000-40,000 tokens of input and generating 1,000-2,000 words of nuanced narrative

**Why on-device models fail for profile synthesis:**
- 1B-3B models are suited for "simple Q&A and basic assistance" --- complex analysis requires 13B+
- 7B models produce "coherent stories with basic plot development"; only 13B+ produces "engaging stories with character development"
- Professional-quality narrative generation requires 13B+ models, and expert-level precision requires 30B+
- Even the best 3B models lack the reasoning depth needed for multi-dimensional vocational analysis

---

## 3. Performance Benchmarks

### 3.1 Inference Speed by Device

#### iPhone (A17 Pro / A18 / A19 Pro)

| Model | Framework | Prefill (tok/s) | Decode (tok/s) | Notes |
|-------|-----------|-----------------|----------------|-------|
| Llama 3.2 1B (Q4) | ExecuTorch | ~200-350 | ~30-50 | Neural Engine accelerated |
| Llama 3.2 3B (Q4) | ExecuTorch | ~100-200 | ~15-30 | Neural Engine accelerated |
| Llama 3.2 3B (Q4) | MLC LLM | ~89 | ~11 | GPU (Metal) |
| 125M model | Optimized | N/A | ~50 | Reference for small models |

- Apple A19 Pro Neural Engine: ~35 TOPS
- Realistic user experience for 3B model: ~15-30 tokens/second decode = readable streaming text

#### Android Flagships (Snapdragon 8 Gen3 / 8 Elite)

| Model | Framework | Prefill (tok/s) | Decode (tok/s) | Device |
|-------|-----------|-----------------|----------------|--------|
| Llama 3.2 1B (Q4) | ExecuTorch | >350 | >40 | Samsung S24+ |
| Llama 3.2 3B | ExecuTorch + KleidiAI | ~150 | ~20 | Samsung S24+ |
| Llama 2 7B (Q4) | llama.cpp (CPU) | ~10.6 | ~8.2 | Dimensity 9300 |
| Llama 2 7B (Q4) | llama.cpp (CPU) | ~8.5 | ~6.6 | Snapdragon 8 Gen3 |
| Qwen3-600M (INT8) | Cactus | N/A | ~75 | Modern flagship |

- Snapdragon 8 Elite Gen 5: ~60 TOPS (NPU)
- Snapdragon Hexagon NPU: 690 tok/s prefill (50x improvement over CPU/GPU) but decode remains comparable
- Significant variance: Mali GPUs average <3% arithmetic unit utilization vs Adreno's ~20%

### 3.2 What These Numbers Mean for Your Use Case

**Follow-up question generation (single response input, ~100-500 token output):**
- Llama 3.2 3B at ~20 tok/s decode = ~5-25 seconds for a follow-up question
- Llama 3.2 1B at ~40 tok/s decode = ~2.5-12.5 seconds
- Acceptable for interactive use with a streaming UI

**Profile synthesis (10,000-40,000 token input, 1,000-2,000 word output):**
- Prefill alone at 100 tok/s for 20,000 tokens = 200 seconds (3+ minutes just to process input)
- Then decode ~1,500 tokens at 20 tok/s = 75 seconds
- Total: ~4-5 minutes minimum, assuming the model can even handle the context
- This is impractical on-device even ignoring quality concerns

---

## 4. Memory, Storage, and Battery Impact

### 4.1 Memory (RAM) Footprint

| Model (Quantization) | RAM During Inference | Notes |
|----------------------|---------------------|-------|
| Llama 3.2 1B (Q4) | ~1.5-2.0 GB | Manageable on 6 GB+ devices |
| Llama 3.2 3B (Q4) | ~2.5-3.5 GB | Tight on 6 GB devices, comfortable on 8 GB+ |
| Llama 2 7B (Q4) | ~3.8 GB (CPU), ~4.2-4.4 GB (GPU) | Not recommended for mobile |
| Phi 4 Mini 3.8B (Q4) | ~3.0-3.5 GB | Flagship phones only |

**Critical concern**: Mobile devices have "typically <4 GB available for apps even on high-end devices." Running a 3B model alongside an audio recording pipeline (which needs its own memory for buffers, Whisper model if doing speech-to-text, etc.) will be very tight.

- iPhone 15 Pro: 8 GB total RAM, ~4-5 GB available to apps
- iPhone 16 Pro: 8 GB total RAM
- Samsung S24 Ultra: 12 GB total RAM, ~6-8 GB available
- Pixel 9 Pro: 16 GB total RAM

**Recommendation**: Llama 3.2 1B or Qwen 3 1.7B if running alongside audio pipeline. Do not attempt to load a 3B model concurrently with audio recording on devices with less than 8 GB available app memory.

### 4.2 App Size Impact

| Model | Download Size (Q4_K_M) | Impact |
|-------|----------------------|--------|
| Llama 3.2 1B | ~0.8 GB | Significant |
| Llama 3.2 3B | ~2.0 GB | Very large |
| Qwen 3 0.6B | ~0.4 GB | Moderate |
| Qwen 3 1.7B | ~1.1 GB | Significant |
| SmolLM 2 135M | ~0.1 GB | Minimal |

- iOS App Store enforces a 4 GB app size limit (though models can be side-loaded / downloaded post-install)
- Best practice: Download the model on first launch or on-demand, not bundled with the app binary
- A 3B model (6-12 GB at full precision, ~2 GB quantized) represents a significant download

### 4.3 Battery Impact

| Scenario | Power Draw | Context |
|----------|------------|---------|
| Dimensity 9300, LLM inference | 4.54 mAh per inference round | Best-case modern chip |
| Kirin 9000E, LLM inference | 8.28 mAh per inference round | Older chip |
| General GPU inference | Similar to intensive gaming | Continuous use drains rapidly |

- Running LLM inference "drains the battery rapidly, similar to playing a graphically intensive game nonstop"
- NPUs (Neural Processing Units) are more power-efficient than CPU/GPU for inference
- For your use case: Running 20 individual follow-up question generations would consume roughly 90-170 mAh total (manageable on a 4,000+ mAh battery)
- Continuous inference for a full profile synthesis would be prohibitive

---

## 5. Quantization Options and Quality Impact

### 5.1 Available Quantization Levels

| Quant Level | Size Reduction | Quality Retention | Recommended For |
|-------------|---------------|-------------------|-----------------|
| Q8_0 (8-bit) | ~2x | ~99% | Maximum quality, larger devices |
| Q5_K_M (5-bit) | ~3x | ~98% | Good balance |
| Q4_K_M (4-bit) | ~4x | ~96-98% | **Recommended for mobile** |
| Q3_K_M (3-bit) | ~5x | ~93-95% | Aggressive compression |
| Q2_K (2-bit) | ~8x | ~85-90% | Not recommended |

### 5.2 Quality Impact Specifics

- 4-bit quantization: "2-8% quality degradation" depending on model and task
- Larger models are more robust to quantization than smaller models
- Sub-7B models "experience greater accuracy losses" from aggressive quantization
- SpinQuant (Meta's method): "4-bit quantization of weights, activations, and KV-cache together, with under 3% accuracy loss"
- Numerical/math tasks degrade faster than language tasks with quantization
- For the Llama 3.1 8B model on MMLU: Q5 = 68.25%, Q3 = 66.47% (a 1.78 point drop)

### 5.3 Recommendation

Use **Q4_K_M** for the best mobile tradeoff. The 2-3% quality loss from quantization is negligible compared to the fundamental capability gap between 3B and 70B+ models for complex reasoning tasks.

---

## 6. Quality Comparison: On-Device vs. Cloud

### 6.1 The Fundamental Quality Gap

This is the most important section for your decision-making.

| Capability | 1B On-Device | 3B On-Device | Claude Haiku | Claude Sonnet |
|-----------|-------------|-------------|--------------|---------------|
| Follow-up question generation | Adequate | Good | Excellent | Excellent |
| Response categorization | Basic | Adequate | Excellent | Excellent |
| Multi-dimensional analysis | Poor | Poor | Good | Excellent |
| Narrative synthesis (1000+ words) | Very Poor | Poor | Good | Excellent |
| Nuanced psychological insight | Non-functional | Poor | Good | Excellent |
| Consistent multi-step reasoning | Poor | Basic | Good | Excellent |
| Handling 20 responses simultaneously | Impossible (context) | Impossible (context) | Yes | Yes |

### 6.2 Specific Quality Observations

**What 1B-3B models CAN do well:**
- Generate grammatically correct, coherent short text (1-3 sentences)
- Follow simple instruction templates ("Ask a follow-up question about X")
- Classify text into predefined categories
- Summarize individual paragraphs
- Basic entity extraction

**What 1B-3B models CANNOT do reliably:**
- Synthesize patterns across 20 different responses
- Produce nuanced, multi-dimensional character analysis
- Generate sophisticated 1,000-2,000 word narratives with literary quality
- Maintain consistent analytical framework across long outputs
- Make subtle psychological inferences
- Weigh competing evidence and arrive at balanced conclusions
- Handle the "multi-dimensional vocational profile" this app needs

**Research support:**
- "Simple Q&A and basic assistance" suits 1B-7B; "complex analysis and reasoning" requires 13B-30B
- "7B models produce coherent stories with basic plot development" while "13B models produce engaging stories with character development" and "70B models produce sophisticated narratives with literary techniques"
- Professional-quality output requires 13B+ models; expert-level precision requires 30B+
- GPT-4-tier LLMs "can generate causally sound stories at small scales; however, planning with character intentionality and dramatic conflict remains challenging"

### 6.3 Head-to-Head for Your Use Case

**Task: Generate a follow-up question after a user discusses their work preferences**

- **Llama 3.2 3B (on-device)**: "What aspects of working with people do you find most fulfilling?" --- Adequate, generic but functional
- **Claude Sonnet (cloud)**: "You mentioned that you feel energized after collaborative brainstorming but drained by routine meetings. Could you describe a specific moment where a team interaction made you think 'this is exactly what I should be doing'?" --- Deeply contextual, psychologically informed, specific to the user's actual response

**Task: Synthesize 20 responses into a vocational profile**

- **Llama 3.2 3B (on-device)**: Would produce a generic, surface-level summary with limited pattern recognition. Would miss contradictions, subtle themes, and cross-dimensional insights. Would likely lose coherence after 300-500 words. Cannot even fit 20 responses in context.
- **Claude Sonnet (cloud)**: Would identify recurring themes, note contradictions, weigh evidence, construct a multi-dimensional narrative with specific examples, and produce publication-quality prose with psychological depth.

---

## 7. Recommended Architecture: Hybrid Approach

### 7.1 Architecture Overview

```
User Response (text/audio)
         |
         v
[On-Device: Audio Pipeline]  <-- Whisper via react-native-executorch
         |
         v
[On-Device: LLM - Qwen 3 1.7B or Llama 3.2 1B]
    |              |
    |              v
    |    Follow-up Question Generation
    |    (single response in, short question out)
    |
    v
[Local Storage: Encrypted responses]
         |
         v (after all 20 responses collected)
[Cloud API: Claude Sonnet 4 / Haiku]
    |
    v
Vocational Profile Synthesis
(all 20 responses in, 1000-2000 word narrative out)
```

### 7.2 What Runs On-Device

| Task | Model | Expected Latency | Memory |
|------|-------|-----------------|--------|
| Follow-up question generation | Qwen 3 1.7B (Q4) | 3-8 seconds | ~1.5 GB |
| Response categorization | Qwen 3 1.7B (Q4) | 1-3 seconds | ~1.5 GB |
| Initial theme extraction | Llama 3.2 1B (Q4) | 2-5 seconds | ~1.2 GB |

### 7.3 What Runs in the Cloud

| Task | Model | Expected Latency | Cost (approx) |
|------|-------|-----------------|----------------|
| Vocational profile synthesis | Claude Sonnet 4 | 15-30 seconds | ~$0.05-0.15 per profile |
| Complex follow-up generation (optional) | Claude Haiku | 2-5 seconds | ~$0.001-0.005 per question |

### 7.4 Fallback Strategy

- If the device cannot run on-device inference (low RAM, older device), fall back to cloud for all tasks
- If no network connectivity, queue the profile synthesis and generate when online
- On-device follow-up questions ensure the assessment flow is never blocked by network issues

---

## 8. React Native / Expo Implementation Path

### 8.1 Primary Recommendation: `react-native-executorch`

```javascript
// Installation
// npx expo install react-native-executorch @react-native-executorch/expo-resource-fetcher

import { useLLM, LLAMA3_2_1B, QWEN3_1_7B } from 'react-native-executorch';
import { ExpoResourceFetcher } from '@react-native-executorch/expo-resource-fetcher';
import { initExecutorch } from 'react-native-executorch';

// Initialize with Expo resource fetcher
initExecutorch({ resourceFetcher: ExpoResourceFetcher });

function VocationalAssessment() {
  const llm = useLLM({
    model: QWEN3_1_7B, // or LLAMA3_2_1B for lighter footprint
  });

  const generateFollowUp = async (userResponse) => {
    const messages = [
      {
        role: 'system',
        content: 'You are a vocational counselor. Based on the user response, generate one insightful follow-up question that explores their interests, values, or skills more deeply. Keep it under 2 sentences.'
      },
      {
        role: 'user',
        content: userResponse
      }
    ];

    await llm.generate(messages);
    return llm.response;
  };

  // llm.isReady - model loaded
  // llm.isGenerating - currently generating
  // llm.response - streamed response text
  // llm.downloadProgress - model download progress
  // llm.interrupt() - stop generation
}
```

### 8.2 Requirements

- Expo SDK 54+
- Custom development build (not Expo Go)
- React Native New Architecture recommended
- iOS 15+ / Android API 26+
- Model downloaded on first launch (~0.8-2.0 GB depending on model)

### 8.3 Important Caveats

- "Running LLMs requires a significant amount of RAM" --- test on real devices, not just simulators
- "Dismounting during active generation causes crashes; must call interrupt() first"
- Token batching (`outputTokenBatchSize: 10`, `batchTimeInterval: 80ms`) recommended for 60+ tok/s models to avoid excessive re-renders
- "Lower-end devices might not be able to fit LLMs into memory"

---

## 9. Key Risks and Dealbreakers

### 9.1 Definite Dealbreakers for Pure On-Device

1. **Context window**: No on-device model can process all 20 responses (10,000-40,000 tokens) simultaneously. Apple's Foundation Model is limited to 4K tokens. Even Llama 3.2's 128K context cannot be used on mobile due to memory constraints --- the KV cache for 128K tokens would exceed available RAM.

2. **Narrative quality**: 1B-3B models cannot produce the caliber of multi-dimensional vocational analysis that users will expect as a deliverable they may share with advisors, mentors, or career counselors.

3. **Synthesis capability**: Cross-referencing 20 responses to identify patterns, contradictions, and emergent themes is a task that requires 13B+ model capability.

### 9.2 Significant Risks for Hybrid Approach

1. **Memory pressure**: Running an LLM alongside an audio recording pipeline on devices with 6 GB RAM may cause app termination by the OS. Must implement robust model loading/unloading.

2. **App size**: Even with on-demand download, requiring users to download 1-2 GB of model data before first use creates friction.

3. **Battery drain**: Extended inference sessions (20 questions x follow-up generation) will noticeably impact battery. Users should be warned.

4. **Device fragmentation (Android)**: Performance varies dramatically across chipsets. Mali GPUs average <3% utilization vs Adreno's ~20%. Older devices may be unusable.

5. **Framework maturity**: `react-native-executorch` is actively developed but still at v0.7.x. Breaking changes are possible. The Callstack libraries require RN 0.80+ and New Architecture.

### 9.3 Mitigations

- Use the smallest viable model (Qwen 3 1.7B or Llama 3.2 1B) for on-device tasks
- Implement device capability detection --- fall back to cloud on low-end devices
- Download models in the background after onboarding, not blocking first use
- Unload the LLM model from memory when the audio pipeline is active, and vice versa
- Cache generated follow-up questions so repeated sessions do not need re-inference

---

## 10. Emerging Options to Watch (Late 2026+)

1. **Qwen 3.5 Small Series** (released March 2026): 0.8B to 9B models, natively multimodal, 262K native context, Apache 2.0 licensed. The 2B model already outperforms many 7B-class models on benchmarks. Linear attention layers enable long context even on small models. Could be a game-changer for on-device by late 2026.

2. **Apple Foundation Models evolution**: iOS 26.4 expected to bring a revamped LLM-backed Siri with expanded capabilities. Apple-Google partnership may bring Gemini capabilities to the on-device framework. Context window may expand.

3. **Speculative decoding**: Intel/Weizmann Institute showed any small draft model can accelerate any LLM up to 2.8x faster inference. Medusa achieves 2.2-3.6x speedup.

4. **BitNet / 1-bit models**: A BitNet 2B model fits in 400MB. These ultra-compressed models could make on-device inference far more practical.

5. **Diffusion LLMs**: Non-autoregressive generation achieving 4-6x speedups over standard decoding. Could dramatically change mobile inference latency.

6. **7B models on mid-range devices**: Industry projections suggest 7B parameter models will be "standard on mid-range devices by late 2025" and 13B on flagships by 2026. If this materializes, the quality gap narrows significantly.

---

## 11. Cost Comparison: On-Device vs. Cloud

### Per-User Cost (20 questions + 1 profile synthesis)

| Approach | Inference Cost | Infrastructure | Model Hosting | Total |
|----------|---------------|----------------|---------------|-------|
| Pure Cloud (Claude Sonnet) | ~$0.15-0.30 | $0/user | $0 | ~$0.15-0.30 |
| Pure Cloud (Claude Haiku) | ~$0.02-0.05 | $0/user | $0 | ~$0.02-0.05 |
| Hybrid (on-device questions + cloud synthesis) | ~$0.05-0.10 | $0 | $0 | ~$0.05-0.10 |
| Pure On-Device | $0 | $0 | $0 | $0 (but quality insufficient) |

At scale (100,000 users), the hybrid approach saves $5,000-$20,000 vs. pure cloud while maintaining quality where it matters.

---

## 12. Summary of Recommendations

### Do This (Short-Term, 2026)

1. **Use `react-native-executorch` with Expo SDK 54+** for on-device inference
2. **Deploy Qwen 3 1.7B (Q4_K_M)** for follow-up question generation on-device (~1.1 GB download, ~1.5 GB RAM)
3. **Use Claude Sonnet 4 API** for vocational profile synthesis (cloud-based)
4. **Implement device capability detection** to gracefully degrade on low-end devices
5. **Download model in background** after onboarding, not on first launch
6. **Do not run LLM and audio pipeline simultaneously** --- load/unload as needed

### Watch and Reassess (Late 2026-2027)

1. **Qwen 3.5 2B/4B models** --- may close the quality gap enough for more on-device work
2. **7B models on mobile** --- if this becomes practical, reconsider the architecture
3. **Apple Foundation Models context expansion** --- if it goes beyond 4K tokens
4. **BitNet / 1-bit models** --- could enable much larger effective models on-device

### Do Not Do

1. Do not attempt vocational profile synthesis on-device with current models
2. Do not bundle model files with the app binary (use on-demand download)
3. Do not assume all Android devices perform equally (chipset variance is extreme)
4. Do not run inference without a visible streaming UI (users need to see progress)
5. Do not use Apple's Foundation Models framework for this use case (4K context is insufficient)

---

## Sources

- [On-Device LLMs: State of the Union, 2026](https://v-chandra.github.io/on-device-llms/)
- [Large Language Model Performance Benchmarking on Mobile Platforms](https://arxiv.org/html/2410.03613v1)
- [React Native ExecuTorch Documentation](https://docs.swmansion.com/react-native-executorch/)
- [React Native ExecuTorch GitHub](https://github.com/software-mansion/react-native-executorch)
- [Callstack: On-Device Apple LLM Support Comes to React Native](https://www.callstack.com/blog/on-device-apple-llm-support-comes-to-react-native)
- [Callstack: Are Local LLMs on Mobile a Gimmick?](https://www.callstack.com/blog/local-llms-on-mobile-are-a-gimmick)
- [Callstack React Native AI GitHub](https://github.com/callstackincubator/ai)
- [Meta: Llama 3.2 Edge AI and Vision](https://ai.meta.com/blog/llama-3-2-connect-2024-vision-edge-mobile-devices/)
- [PyTorch: Unleashing AI on Mobile with ExecuTorch and KleidiAI](https://pytorch.org/blog/unleashing-ai-mobile/)
- [Apple Foundation Models Framework](https://developer.apple.com/documentation/FoundationModels)
- [Apple Foundation Models Tech Report 2025](https://machinelearning.apple.com/research/apple-foundation-models-tech-report-2025)
- [Apple Newsroom: Foundation Models Framework](https://www.apple.com/newsroom/2025/09/apples-foundation-models-framework-unlocks-new-intelligent-app-experiences/)
- [Google: Gemini Nano on Android](https://developer.android.com/ai/gemini-nano)
- [Google: ML Kit GenAI APIs with Gemini Nano](https://android-developers.googleblog.com/2025/05/on-device-gen-ai-apis-ml-kit-gemini-nano.html)
- [Expo Blog: How to Run AI Models with React Native ExecuTorch](https://expo.dev/blog/how-to-run-ai-models-with-react-native-executorch)
- [HuggingFace: LLM Inference on Edge](https://huggingface.co/blog/llm-inference-on-edge)
- [HuggingFace: Cactus On-Device Inference](https://huggingface.co/blog/rshemet/cactus-on-device-inference)
- [Cactus Compute](https://www.cactuscompute.com/)
- [InfoQ: Cactus v1 Cross-Platform LLM Inference](https://www.infoq.com/news/2025/12/cactus-on-device-inference/)
- [HuggingFace: Llama-3.2-3B-Instruct-GGUF](https://huggingface.co/bartowski/Llama-3.2-3B-Instruct-GGUF)
- [Meta: Quantized Llama Models](https://ai.meta.com/blog/meta-llama-quantized-lightweight-models/)
- [Arm Newsroom: Llama 3.2 on Arm](https://newsroom.arm.com/news/ai-inference-everywhere-with-new-llama-llms-on-arm)
- [MobileAIBench Paper](https://openreview.net/forum?id=EEbRrNsiiD)
- [Production-Grade Local LLM Inference Comparison](https://arxiv.org/abs/2511.05502)
- [Appilian: LLMs in Mobile Apps 2026 Playbook](https://appilian.com/large-language-models-in-mobile-apps/)
- [Qwen 3.5 Small Model Series](https://awesomeagents.ai/news/qwen-3-5-small-models-series/)
- [ONNX Runtime React Native](https://onnxruntime.ai/docs/get-started/with-javascript/react-native.html)
- [Local AI Zone: Model Parameters Guide 2025](https://local-ai-zone.github.io/guides/what-is-ai-model-3b-7b-30b-parameters-guide-2025.html)
- [Ionio: Benchmarking Quantized LLMs](https://www.ionio.ai/blog/llm-quantize-analysis)
- [Callstack: Profiling MLC-LLM OpenCL Backend on Android](https://www.callstack.com/blog/profiling-mlc-llms-opencl-backend-on-android-performance-insights)
