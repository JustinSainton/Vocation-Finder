const { withDangerousMod, withXcodeProject } = require('expo/config-plugins');
const path = require('path');
const fs = require('fs');

function copyRecursive(src, dest) {
  if (!fs.existsSync(src)) return;
  const stat = fs.statSync(src);
  if (stat.isDirectory()) {
    fs.mkdirSync(dest, { recursive: true });
    for (const entry of fs.readdirSync(src)) {
      copyRecursive(path.join(src, entry), path.join(dest, entry));
    }
  } else {
    fs.copyFileSync(src, dest);
  }
}

function withBundledModels(config) {
  // Step 1: Copy model files into the iOS project directory
  config = withDangerousMod(config, [
    'ios',
    async (config) => {
      const projectRoot = config.modRequest.projectRoot;
      const modelsSource = path.join(projectRoot, 'assets', 'models');
      const projName = config.modRequest.projectName;
      const iosDir = config.modRequest.platformProjectRoot;
      const modelsDest = path.join(iosDir, projName, 'models');

      if (!fs.existsSync(modelsSource)) {
        console.warn('[withBundledModels] No assets/models directory, skipping.');
        return config;
      }

      console.log('[withBundledModels] Copying models...');
      copyRecursive(modelsSource, modelsDest);
      console.log('[withBundledModels] Done.');
      return config;
    },
  ]);

  // Step 2: Add a shell script build phase to copy models into the app bundle
  config = withXcodeProject(config, async (config) => {
    const project = config.modResults;
    const projName = config.modRequest.projectName;

    // Add a "Copy Models" shell script build phase
    const shellScript = `
# Copy bundled AI models into the app bundle
MODELS_SRC="$PROJECT_DIR/${projName}/models"
MODELS_DST="$BUILT_PRODUCTS_DIR/$CONTENTS_FOLDER_PATH/models"
if [ -d "$MODELS_SRC" ]; then
  echo "Copying AI models to bundle..."
  mkdir -p "$MODELS_DST"
  cp -R "$MODELS_SRC/" "$MODELS_DST/"
  echo "AI models copied: $(du -sh "$MODELS_DST" | cut -f1)"
fi
`;

    project.addBuildPhase(
      [],
      'PBXShellScriptBuildPhase',
      'Copy AI Models',
      null,
      {
        shellPath: '/bin/sh',
        shellScript,
      }
    );

    console.log('[withBundledModels] Added "Copy AI Models" build phase.');
    return config;
  });

  return config;
}

module.exports = withBundledModels;
