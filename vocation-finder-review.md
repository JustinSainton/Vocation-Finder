# Vocation Finder — release review

Shipped 2026-09-22 from this Mac. No EAS.

## What the app is

Vocation Finder is a student vocational product: an assessment, an honest result, and a coach that leaves the student with one concrete next action. The repo is a Laravel 13 / PHP 8.4 API and Inertia React web app, plus an Expo SDK 55 / React Native 0.83 app in `mobile/` (Expo Router, Zustand). Production API baked into this build: `https://vocation-finder-main-f14jpf.laravel.cloud`.

iOS identity:

- Display name: Vocation Finder
- Bundle ID: `io.vocationfinder.app`
- Team: `6CJ76J3665` (Justin Sainton)
- Marketing version: `1.0.0`
- App Store Connect app id: `6760142511`
- App Store Connect name: Vocation Finder (70a08e)
- Android package (not shipped here): `com.vocationfinder.app`

The 1.0.0 App Store version is still `PREPARE_FOR_SUBMISSION`. This release uploads a TestFlight build. It does not submit for App Review.

## How it ships today

The local path is `mobile/scripts/local-testflight.sh`. It does not run `expo prebuild` or EAS.

1. Increment `CFBundleVersion` in `mobile/ios/VocationFinder/Info.plist` (literal, not `$(CURRENT_PROJECT_VERSION)`).
2. `pod install`
3. `xcodebuild archive` (Release, automatic signing, App Store Connect API key)
4. Export an App Store IPA
5. `xcrun altool --validate-app` then `--upload-app`

Credentials already on this machine, and used by that script:

- Issuer id file: `~/.appstoreconnect/issuer_id`
- Private key: `~/.appstoreconnect/private_keys/`, the key id hardcoded as the script default
- Signing identity: Apple Distribution: Justin Sainton (6CJ76J3665)
- Store provisioning profile for `io.vocationfinder.app` is installed

`eas.json` still has a production profile (`autoIncrement`, submit `ascAppId` 6760142511). That path was not used. `asc` is installed, but its default keychain profile is a different app and cannot see Vocation Finder. The ship script passes the correct key explicitly.

`mobile/ios` is gitignored, except `project.pbxproj`, which is tracked. `Info.plist` is not in git. `CURRENT_PROJECT_VERSION` in the pbxproj is 46 and does not affect the binary. The number that ships is the plist literal.

## Risks

- The plist build number drifts from App Store Connect. It was 49 locally while Connect’s highest build was 70. A plain +1 would have been rejected as a duplicate.
- The next local run only does +1 from whatever plist is on disk. Regenerating `ios/` (prebuild, or a clean checkout that drops the ignored plist) will resurrect an old build number.
- The script does not commit. The bump lives only in the ignored plist.
- This archive bundled the dirty working tree: modified `mobile/app/(dashboard)/coach.tsx` and `mobile/components/ui/SingleLineInput.tsx`, plus untracked `mobile/components/ui/TypingIndicator.tsx`.
- `Info.plist` still carries Expo dev-client surface (local-network usage string, `exp+vocation-finder` URL scheme). Prior builds were accepted this way; build 71 was too.
- `asc` without the script’s key id lists the wrong account’s apps.

## What changed in order to ship

No application source was edited for the release, and nothing was committed or pushed.

`Info.plist` `CFBundleVersion` was set from 49 to 70 so the script’s existing +1 landed on 71, above Connect build 70. The script then set it to 71, archived, validated, and uploaded. `altool` reported `UPLOAD SUCCEEDED`.

## App Store Connect result

| Field | Value |
|---|---|
| App | Vocation Finder (70a08e) |
| App id | 6760142511 |
| Bundle | io.vocationfinder.app |
| Version | 1.0.0 |
| Build number | 71 |
| Build id | dea2217e-2c92-43ea-9194-70cbd4616efd |
| Processing state | VALID |
| Upload | COMPLETE, no errors |
| Uploaded | 2026-09-22 20:53:54 -0700 |
