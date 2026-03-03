# App Store Compliance Review -- Vocational Discernment Assessment Platform

**Date:** 2026-03-02
**Scan Tool:** Greenlight (Revyl) preflight scanner
**Status:** NOT READY -- 1 critical finding from automated scan + 12 manual findings requiring action

---

## Greenlight Automated Scan Results

```
greenlight preflight .
```

### Findings

| # | Severity | Guideline | Finding | Status |
|---|----------|-----------|---------|--------|
| 1 | CRITICAL | 5.1.1 | No `PrivacyInfo.xcprivacy` found in project. Privacy manifests required since May 2024. Missing triggers ITMS-91061. | OPEN |

The automated scan only found one issue because the project is in the planning stage with no source code yet. The manual analysis below covers the remaining compliance risks that will surface as development proceeds.

---

## Manual Compliance Analysis

Based on thorough review of the platform plan, specs, and Apple App Store Review Guidelines, the following issues are identified and organized by severity.

---

### CRITICAL -- Will Be Rejected

---

#### C1. In-App Purchase Requirement (Guideline 3.1.1)

**Risk:** The plan specifies "Billing (Laravel Cashier + Stripe)" for both individual assessments and organization subscriptions. The vocational assessment result is **digital content** -- it is generated within the app and consumed digitally (vocational profile, PDF download). Under Apple's Guideline 3.1.1, digital content, subscriptions, and features unlocked within an app **must** use Apple In-App Purchase (StoreKit), not Stripe or any external payment processor.

**What must change:**

- Individual assessment purchases inside the iOS app MUST go through Apple IAP (StoreKit 2). Apple takes a 30% commission (15% for small businesses under $1M/year).
- Auto-renewable subscriptions for organizations accessed via the iOS app MUST also use Apple IAP.
- Stripe may ONLY be used for: (a) the web SaaS application (not subject to App Store rules), (b) organization billing that is initiated and managed entirely via the web dashboard (never surfaced in the iOS app), (c) physical goods or real-world services (not applicable here).
- The app MUST NOT contain any links, buttons, or text directing users to an external website for purchasing digital content (Guideline 3.1.1 "anti-steering" provisions). Note: US-only external link entitlement exists but has specific requirements and still requires Apple IAP as primary option.

**Architecture impact:** You will need a dual billing system:
- StoreKit 2 integration in the Expo app for iOS purchases (use `expo-in-app-purchases` or `react-native-iap`)
- Stripe via Laravel Cashier for the web SaaS and potentially Android (Google Play also requires their billing for digital goods)
- Server-side receipt validation to unify subscription state across platforms
- Webhook handling for both Apple App Store Server Notifications and Stripe webhooks

**Severity: CRITICAL** -- Apps that bypass IAP for digital content are rejected immediately.

---

#### C2. Privacy Manifest (PrivacyInfo.xcprivacy) (Guideline 5.1.1)

**Risk:** Found by greenlight scan. Since May 2024, all apps submitted to the App Store must include a `PrivacyInfo.xcprivacy` file declaring:
- Required Reason APIs used (e.g., `NSUserDefaults`, `fileTimestamp`, disk space APIs)
- Data types collected
- Tracking domains (if any)
- Third-party SDK privacy manifests

**What must change:**

Create `mobile/ios/PrivacyInfo.xcprivacy` with declarations for:
- `NSPrivacyAccessedAPITypes`: UserDefaults (Zustand/AsyncStorage uses this), file timestamp APIs
- `NSPrivacyCollectedDataTypes`: Must list every data type collected (see Privacy Nutrition Labels section below)
- `NSPrivacyTracking`: `false` (assuming no cross-app tracking)
- `NSPrivacyTrackingDomains`: empty array (assuming no tracking)

Additionally, all third-party SDKs (Expo modules, analytics, etc.) must include their own privacy manifests or you must declare their API usage in yours.

**Severity: CRITICAL** -- Triggers ITMS-91061 rejection.

---

#### C3. Account Deletion Requirement (Guideline 5.1.1)

**Risk:** The plan includes account creation (Sanctum auth, registration, organization invitations, guest-to-account conversion) but does not mention account deletion functionality anywhere.

**What must change:**

- Add a "Delete Account" option in the app's profile/settings screen.
- Account deletion must delete or disassociate ALL user data: assessment responses, vocational profiles, audio recordings, conversation transcripts, organization memberships, and billing information.
- Deletion must be easily discoverable (not hidden behind support emails).
- If you cannot delete data immediately (e.g., legal/financial retention requirements), clearly disclose the retention period and what data is retained.
- The deletion flow should work from within the app, not redirect to a website.

**Severity: CRITICAL** -- Apple has been rejecting apps without account deletion since June 2022.

---

### WARN -- High Rejection Risk

---

#### W1. Sign in with Apple (Guideline 4.8)

**Risk:** The plan mentions Laravel Sanctum for auth with registration and login but does not mention Sign in with Apple. If the app offers ANY third-party or social login (Google, Facebook, email/password with social login options), it MUST also offer Sign in with Apple as an equivalent option.

**What must change:**

- Implement Sign in with Apple alongside any other authentication methods in the iOS app.
- Use `expo-apple-authentication` for the Expo app.
- Server-side: handle Apple identity tokens in Laravel, map to user accounts.
- Sign in with Apple must be presented as an equally prominent option (not hidden or de-emphasized).

**Note:** If the app ONLY uses email/password registration (no social login), Sign in with Apple is technically not required. But adding it is strongly recommended regardless because: (a) reviewers sometimes reject apps they believe should have it, and (b) it improves conversion.

**Severity: WARN** -- Rejection very likely if social login exists without Apple option.

---

#### W2. Microphone Permission Purpose String (Guideline 5.1.1, 5.1.2)

**Risk:** The audio conversation interface requires microphone access. Apple requires a specific, descriptive `NSMicrophoneUsageDescription` that explains WHY the app needs the microphone and HOW the audio will be used. Vague strings like "Microphone access needed" will be rejected.

**What must change:**

In `mobile/app.json` (Expo config), set:
```json
{
  "ios": {
    "infoPlist": {
      "NSMicrophoneUsageDescription": "Vocation Finder uses your microphone to record your spoken responses during the vocational discernment conversation. Your audio is transcribed to text for analysis and is not shared with third parties beyond the transcription service."
    }
  }
}
```

Key requirements for the purpose string:
- Must explain the specific feature that uses the microphone (vocational conversation)
- Must disclose that audio is sent to external APIs (Whisper/OpenAI for transcription)
- Must be honest about data handling
- Should mention that audio recording is optional (written mode exists as alternative)

**Severity: WARN** -- Vague purpose strings cause rejection.

---

#### W3. Audio Data Transmission Disclosure (Guideline 5.1.1, 5.1.2)

**Risk:** The app records user audio and transmits it to multiple external APIs:
1. Your Laravel API server
2. OpenAI Whisper API (for transcription)
3. Claude/Anthropic API (text analysis of transcripts)
4. ElevenLabs/OpenAI TTS (for voice synthesis)

Users must be clearly informed before audio leaves their device.

**What must change:**

- Display a clear disclosure BEFORE the first audio recording that explains: what is recorded, where it is sent, how long it is stored, and who has access.
- This disclosure must appear in-app (not just buried in a privacy policy).
- Consider adding a consent flow at the start of conversation mode.
- The privacy policy must enumerate all third-party services that receive user data.

**Severity: WARN** -- Undisclosed data transmission to third parties is grounds for rejection.

---

#### W4. AI-Generated Content Moderation (Guideline 1.1, 1.2)

**Risk:** The app uses Claude API to generate personalized vocational guidance. AI-generated content that is objectionable, inappropriate, or harmful can cause rejection under Guideline 1.1 (Objectionable Content) and 1.2 (User-Generated Content).

Apple's stance on AI-generated content (updated 2024):
- Apps that generate content using AI must implement content filtering/moderation.
- Apps must have mechanisms to report objectionable AI output.
- Apps that provide personalized advice (especially in sensitive domains like career/life guidance) face elevated scrutiny.

**What must change:**

- Implement server-side output filtering on Claude responses before they reach the user. Check for: harmful advice, discriminatory content, content inappropriate for minors, clinically diagnostic language.
- Add a "Report an issue with this result" mechanism in the results view.
- Include a clear disclaimer that the vocational profile is AI-generated guidance, not professional counseling, career advising, or clinical assessment.
- The disclaimer must be visible on the results page, not hidden in terms of service.
- Consider human review for the initial launch period (the plan already mentions this in the risk analysis).

**Severity: WARN** -- Apple has increased AI content scrutiny significantly since 2024.

---

#### W5. Age Rating and Minors (Guideline 1.3, 5.1.4)

**Risk:** The assessment is "Revised for Ages 17-21." This means 17-year-olds (minors) are in the target audience. The app also collects highly personal data including suffering/limitation narratives, family situations, and religious reflections. Combined with AI-generated life guidance, this creates multiple compliance vectors.

**What must change:**

**Age Rating:** The app should be rated 17+ in App Store Connect due to:
- Unrestricted web access (API calls to external services)
- Mature/suggestive themes (suffering, limitation, family hardship questions)
- AI-generated personalized content

**Do NOT list in the Kids Category.** The Kids Category (Guideline 1.3) has extremely strict requirements (no external links, no data transmission to third parties, no account creation) that are incompatible with this app's architecture.

**COPPA / Children's Privacy:**
- If users under 13 could potentially use the app: you must comply with COPPA and Apple's Guideline 5.1.4.
- Since the target starts at 17, implement an age gate at registration. Require users to confirm they are 17+ before creating an account.
- Do NOT collect ages under 13. If you do, you must comply with COPPA which would require parental consent and severely restrict data collection.

**Parental consent for 17-year-olds:**
- 17 is a minor in most US states. Consider whether COPPA/FERPA/state privacy laws require parental consent for data collection from 17-year-olds.
- If organizations include high schools, FERPA compliance becomes relevant.

**Severity: WARN** -- Incorrect age rating or missing age gate can cause rejection.

---

#### W6. Sensitive Data Collection Scope (Guideline 5.1.1, 5.1.2)

**Risk:** The assessment collects extraordinarily sensitive personal data:
- Descriptions of personal suffering and failure (Category 5)
- Family financial situations and limitations (Question 15)
- Religious beliefs and spiritual reflections (throughout, plus ministry integration)
- Career anxieties and personal values (Categories 4, 6)
- Voice recordings of all of the above
- AI analysis of personality and calling

This data is far more sensitive than typical app data. Much of it could qualify as "sensitive personal information" under GDPR, CCPA, and Apple's own privacy standards.

**What must change:**

- Implement encryption at rest for all assessment data (the plan mentions this but it must be verified).
- Implement encryption in transit (HTTPS only -- no HTTP endpoints anywhere).
- Minimize data retention: define and enforce retention periods for audio files, transcripts, and raw AI analysis data.
- Do NOT use this data for advertising, analytics beyond the product's core function, or any secondary purpose.
- Clearly categorize this data in your privacy policy as sensitive personal information.
- Consider whether this data triggers "health" or "sensitive info" categories in Apple's privacy nutrition labels.

**Severity: WARN** -- Mishandling sensitive data leads to rejection and regulatory risk.

---

### INFO -- Best Practice Recommendations

---

#### I1. Privacy Nutrition Labels (App Store Connect)

When submitting to the App Store, you must complete Apple's "App Privacy" section (privacy nutrition labels). Based on the plan, you will need to declare the following data types:

| Data Type | Category | Linked to Identity | Used for Tracking |
|-----------|----------|-------------------|-------------------|
| Name | Contact Info | Yes | No |
| Email Address | Contact Info | Yes | No |
| Audio Data | User Content | Yes | No |
| Other User Content (assessment responses) | User Content | Yes | No |
| Photos or Videos (if PDF includes images) | User Content | Yes | No |
| Payment Info | Financial Info | Yes | No |
| Purchase History | Purchases | Yes | No |
| Product Interaction (assessment progress) | Usage Data | Yes | No |
| Other Diagnostic Data | Diagnostics | No | No |
| Religious or Philosophical Beliefs | Sensitive Info | Yes | No |
| Health & Fitness (if suffering/limitation data is categorized here) | Health & Fitness | Yes | No |
| User ID | Identifiers | Yes | No |
| Device ID | Identifiers | No | No |

**Important:** If you use ANY analytics SDK (even crash reporting), you must also declare Diagnostics and possibly Identifiers data types. If you use App Tracking Transparency, declare tracking usage.

---

#### I2. App Capabilities Declarations (Entitlements)

The iOS app will need the following capabilities and entitlements:

| Capability | Reason |
|------------|--------|
| Microphone (NSMicrophoneUsageDescription) | Audio conversation interface |
| Background Audio (UIBackgroundModes: audio) | If audio playback continues in background |
| In-App Purchase (StoreKit) | Required for digital content purchases |
| Sign in with Apple | Authentication |
| Push Notifications | Assessment completion notifications, results ready |
| Network/Internet Access | API communication, audio streaming |
| Keychain Sharing (optional) | Secure token storage |

Entitlements to configure in `app.json` / Xcode:
- `com.apple.developer.in-app-payments` (StoreKit)
- `com.apple.developer.applesignin` (Sign in with Apple)

---

#### I3. HTTPS Enforcement

All API endpoints must use HTTPS. The plan references a Laravel API but does not specify TLS requirements. Ensure:
- No hardcoded `http://` URLs anywhere in the codebase.
- No hardcoded IP addresses (use hostnames).
- App Transport Security (ATS) is not disabled in Info.plist (Expo default is fine).
- All third-party API connections (Claude, Whisper, ElevenLabs) use HTTPS (they do by default).

---

#### I4. Console Logs and Debug Output

When building for production/App Store submission:
- Remove or gate all `console.log` statements behind `__DEV__` checks.
- Remove any debug views or developer menus.
- Ensure no API keys or secrets are logged.
- Use Expo's release build configuration to strip development code.

---

#### I5. Placeholder Content

The plan references example copy patterns ("Most people are taught to choose a career...") and placeholder text. Before submission:
- Replace ALL placeholder text, "TBD", "Coming soon", "Lorem ipsum" content.
- Ensure the orientation page, landing page, and all screens have final copy.
- Remove any "Bubble.io" references from code, comments, and visible UI.

---

#### I6. Platform References

Do not include references to competing platforms in any user-facing text:
- No "Also available on Android" or "Google Play" references in the iOS app.
- No "Download on the App Store" references in the Android app.
- This applies to: UI text, images, metadata, and App Store description.

---

#### I7. Offline Functionality Declaration

The plan mentions "Written mode supports offline with Zustand persistence." If the app requires network connectivity for core features (audio conversation mode, AI analysis, results generation), it must:
- Clearly indicate when features are unavailable offline.
- Not crash or show blank screens when offline.
- The plan already addresses this ("Clear UI indication when offline") -- ensure this is implemented.

---

#### I8. Background Audio Handling

If the TTS audio playback (AI voice responses during conversation) continues when the app is backgrounded:
- You must declare `UIBackgroundModes: audio` in your app configuration.
- The audio must be genuine user-initiated content (not background tracking).
- If audio should stop when backgrounded, handle the audio session lifecycle correctly.

---

#### I9. Data Export and Portability

While not strictly an App Store requirement, GDPR (relevant for EU users) requires data portability. The plan includes PDF export and email of results. Consider also:
- A full data export option (all assessment responses, transcripts, profiles).
- This supports the account deletion requirement (users can export before deleting).

---

#### I10. Disclaimer for AI-Generated Guidance

Add a visible, non-dismissable disclaimer on the results page:

> "This vocational profile was generated by artificial intelligence based on your responses. It is intended as a tool for reflection and discernment, not as professional career counseling, psychological assessment, or clinical diagnosis. Please consult with mentors, counselors, or advisors for comprehensive guidance."

This protects against:
- Apple rejection for apps that appear to provide medical/psychological advice without proper disclaimers.
- Liability if users make life decisions based solely on AI output.
- Reviewer concerns about the app practicing counseling without credentials.

---

## Summary: Required Actions Before Submission

### Must Fix (CRITICAL)

| # | Issue | Plan Section Affected |
|---|-------|----------------------|
| C1 | Replace Stripe with Apple IAP for in-app digital purchases | Phase 5 Billing |
| C2 | Create `PrivacyInfo.xcprivacy` privacy manifest | Phase 3 Expo Setup |
| C3 | Implement account deletion flow | Phase 1 Authentication |

### Should Fix (WARN)

| # | Issue | Plan Section Affected |
|---|-------|----------------------|
| W1 | Add Sign in with Apple to authentication | Phase 1 Authentication |
| W2 | Write specific microphone purpose string | Phase 4 Audio Pipeline |
| W3 | Add audio data transmission consent flow | Phase 4 Audio Pipeline |
| W4 | Implement AI content moderation and disclaimers | Phase 2 AI Analysis |
| W5 | Set 17+ age rating, add age gate at registration | Phase 1 Authentication |
| W6 | Encrypt sensitive assessment data, define retention policy | Phase 1 Database, Phase 2 |

### Consider (INFO)

| # | Issue | Plan Section Affected |
|---|-------|----------------------|
| I1 | Prepare privacy nutrition label declarations | Pre-submission |
| I2 | Configure required app capabilities/entitlements | Phase 3 Expo Setup |
| I3 | Enforce HTTPS everywhere | Phase 1 API |
| I4 | Gate console logs behind __DEV__ | Phase 3 Mobile |
| I5 | Replace all placeholder content before submission | All phases |
| I6 | Remove cross-platform references | All phases |
| I7 | Implement offline state handling | Phase 3, Phase 4 |
| I8 | Handle background audio lifecycle | Phase 4 Audio |
| I9 | Add full data export for GDPR portability | Phase 5 |
| I10 | Add AI-generated content disclaimer to results | Phase 2 Results |

---

## Billing Architecture Recommendation

Given the IAP requirement, the recommended billing architecture is:

```
iOS App (StoreKit 2)                Web SaaS (Stripe)
        |                                   |
        v                                   v
  Apple Receipt -----> Laravel API <----- Stripe Webhook
                           |
                    Unified Subscription
                        State (DB)
                           |
               +-----------+-----------+
               |                       |
        Has Active Sub?         Has Active Sub?
        (check Apple OR         (check Stripe OR
         Stripe receipts)        Apple receipts)
```

- Use `react-native-iap` or `expo-in-app-purchases` for StoreKit 2 integration.
- Laravel receives and validates receipts from both Apple and Stripe.
- A single `subscriptions` table tracks the canonical subscription state.
- Server-side receipt validation ensures users cannot spoof purchase status.
- Apple App Store Server Notifications v2 for real-time subscription lifecycle events.

**Individual one-time assessments:** If sold as a one-time purchase in the iOS app, use a non-consumable or consumable IAP (depending on whether one assessment = one purchase or a permanent unlock).

**Organization subscriptions:** If organization admins ONLY manage billing via the web dashboard (never through the iOS app), you can use Stripe for org billing. But the iOS app must never display org subscription pricing, purchase buttons, or links to the web billing page.

---

## Re-scan Instructions

After implementing the fixes above and writing source code, re-run:

```bash
greenlight preflight .
```

This will catch additional issues in actual code (hardcoded secrets, HTTP URLs, console logs, missing purpose strings, etc.). Keep iterating until the output shows **GREENLIT** status with zero critical findings.
