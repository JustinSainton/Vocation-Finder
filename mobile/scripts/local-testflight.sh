#!/usr/bin/env bash
#
# Local TestFlight pipeline — NO EAS. Archives, exports, and uploads the iOS
# app to TestFlight using the local Apple Distribution cert + an App Store
# Connect API key.
#
# Usage:
#   bash scripts/local-testflight.sh <ASC_ISSUER_ID> [ASC_KEY_ID]
#
# Find the Issuer ID at App Store Connect -> Users and Access -> Integrations
# -> App Store Connect API, shown above the key list.
#
# Deliberately does NOT run `expo prebuild`. Unlike the sibling projects this
# was adapted from, ios/VocationFinder.xcodeproj/project.pbxproj is COMMITTED
# here — prebuild --clean would regenerate it and discard the build number
# along with any manual project settings.
set -euo pipefail

ISSUER_ID="${1:?Pass the App Store Connect Issuer ID as arg 1}"
KEY_ID="${2:-KFC5KARQZR}"
KEY_PATH="$HOME/.appstoreconnect/private_keys/AuthKey_${KEY_ID}.p8"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORKSPACE="$ROOT/ios/VocationFinder.xcworkspace"
SCHEME="VocationFinder"
BUNDLE_ID="io.vocationfinder.app"
TEAM_ID="6CJ76J3665"
BUILD_DIR="$ROOT/build"
ARCHIVE="$BUILD_DIR/VocationFinder.xcarchive"
EXPORT_DIR="$BUILD_DIR/export"
EXPORT_PLIST="$BUILD_DIR/ExportOptions.plist"

# Baked into the JS bundle at build time. These mirror the "production" build
# profile in eas.json; omitting EXPO_PUBLIC_API_URL ships an app pointed at the
# wrong API, which no signing or upload step would catch.
export EXPO_PUBLIC_API_URL="${EXPO_PUBLIC_API_URL:-https://vocation-finder-main-f14jpf.laravel.cloud}"
export SHERPA_ONNX_DISABLE_FFMPEG="${SHERPA_ONNX_DISABLE_FFMPEG:-1}"

[ -f "$KEY_PATH" ] || { echo "ASC key not found: $KEY_PATH"; exit 1; }
[ -d "$ROOT/ios" ] || { echo "ios/ missing"; exit 1; }

mkdir -p "$BUILD_DIR"

# The build number lives in ios/VocationFinder/Info.plist as a LITERAL. That
# plist does not reference $(CURRENT_PROJECT_VERSION), so editing the pbxproj
# changes nothing that ships — a build bumped there archives at the old number
# and App Store Connect rejects it as a duplicate. Bump here, not there.
INFO_PLIST="$ROOT/ios/VocationFinder/Info.plist"
CURRENT_BUILD="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleVersion' "$INFO_PLIST")"
if [ "${BUMP_BUILD:-1}" = "1" ]; then
  NEXT_BUILD=$((CURRENT_BUILD + 1))
  /usr/libexec/PlistBuddy -c "Set :CFBundleVersion $NEXT_BUILD" "$INFO_PLIST"
  echo "==> Build number $CURRENT_BUILD -> $NEXT_BUILD"
else
  echo "==> Build number $CURRENT_BUILD (BUMP_BUILD=0, not bumping)"
fi
echo "==> Version: $(/usr/libexec/PlistBuddy -c 'Print :CFBundleShortVersionString' "$INFO_PLIST")"
echo "==> API the build will talk to: $EXPO_PUBLIC_API_URL"

echo "==> Installing CocoaPods"
( cd "$ROOT/ios" && pod install )

echo "==> Archiving (Release)"
xcodebuild \
  -workspace "$WORKSPACE" \
  -scheme "$SCHEME" \
  -configuration Release \
  -archivePath "$ARCHIVE" \
  -destination 'generic/platform=iOS' \
  -allowProvisioningUpdates \
  -authenticationKeyPath "$KEY_PATH" \
  -authenticationKeyID "$KEY_ID" \
  -authenticationKeyIssuerID "$ISSUER_ID" \
  DEVELOPMENT_TEAM="$TEAM_ID" \
  archive

echo "==> Writing ExportOptions.plist"
cat > "$EXPORT_PLIST" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>method</key><string>app-store-connect</string>
  <key>teamID</key><string>${TEAM_ID}</string>
  <key>signingStyle</key><string>automatic</string>
  <key>uploadSymbols</key><true/>
  <key>destination</key><string>export</string>
</dict>
</plist>
PLIST

echo "==> Exporting signed .ipa"
xcodebuild -exportArchive \
  -archivePath "$ARCHIVE" \
  -exportPath "$EXPORT_DIR" \
  -exportOptionsPlist "$EXPORT_PLIST" \
  -allowProvisioningUpdates \
  -authenticationKeyPath "$KEY_PATH" \
  -authenticationKeyID "$KEY_ID" \
  -authenticationKeyIssuerID "$ISSUER_ID"

IPA="$(ls "$EXPORT_DIR"/*.ipa | head -1)"
echo "==> Built: $IPA"

echo "==> Validating"
xcrun altool --validate-app -f "$IPA" -t ios --apiKey "$KEY_ID" --apiIssuer "$ISSUER_ID"

echo "==> Uploading to TestFlight"
xcrun altool --upload-app -f "$IPA" -t ios --apiKey "$KEY_ID" --apiIssuer "$ISSUER_ID"

echo "==> Done. Processing in App Store Connect / TestFlight (allow a few minutes)."
