# Framework & Library Documentation Reference

Comprehensive documentation for the Vocation-Finder SaaS application technology stack.
Gathered from official sources, Context7, and community best practices.

---

## Table of Contents

1. [Laravel 11+ (Backend API)](#1-laravel-11-backend-api)
2. [Expo SDK 52+ (React Native Frontend)](#2-expo-sdk-52-react-native-frontend)
3. [AI/ML Integration Libraries](#3-aiml-integration-libraries)
4. [Supporting Libraries](#4-supporting-libraries)

---

## 1. Laravel 11+ (Backend API)

### 1.1 API Resource Routes and Controllers

Laravel 11 provides streamlined API resource routing that automatically excludes HTML-template routes (`create`, `edit`):

```php
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\OrganizationController;

// Single API resource
Route::apiResource('assessments', AssessmentController::class);

// Multiple API resources registered at once
Route::apiResources([
    'assessments'   => AssessmentController::class,
    'questions'     => QuestionController::class,
    'answers'       => AnswerController::class,
    'results'       => ResultController::class,
    'organizations' => OrganizationController::class,
]);
```

**Generated Routes per `apiResource`:**

| Verb   | URI                        | Action  | Route Name            |
|--------|----------------------------|---------|-----------------------|
| GET    | /assessments               | index   | assessments.index     |
| POST   | /assessments               | store   | assessments.store     |
| GET    | /assessments/{assessment}  | show    | assessments.show      |
| PUT    | /assessments/{assessment}  | update  | assessments.update    |
| DELETE | /assessments/{assessment}  | destroy | assessments.destroy   |

**Nested Resources:**

```php
Route::apiResource('assessments.questions', QuestionController::class);
// Generates: /assessments/{assessment}/questions/{question}
```

**API Controller Stub Generation:**

```bash
php artisan make:controller AssessmentController --api
```

**Source:** https://laravel.com/docs/11.x/controllers

---

### 1.2 Eloquent Relationships for Complex Data Models

#### One-to-Many (User -> Assessments)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    /**
     * Get all assessments for the user.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
```

#### Inverse BelongsTo (Assessment -> User)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    /**
     * Get the user that owns the assessment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

#### Many-to-Many (Users <-> Organizations)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    /**
     * The organizations the user belongs to.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role')
            ->withTimestamps();
    }
}
```

#### Polymorphic Relationships (Answers can be text, audio, or multiple-choice)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Question extends Model
{
    /**
     * Get all answers for this question (polymorphic).
     */
    public function answers(): MorphMany
    {
        return $this->morphMany(Answer::class, 'answerable');
    }
}
```

#### Has-Many-Through (Organization -> Users -> Assessments)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Organization extends Model
{
    /**
     * Get all assessments for the organization through its users.
     */
    public function assessments(): HasManyThrough
    {
        return $this->hasManyThrough(Assessment::class, User::class);
    }
}
```

**Source:** https://laravel.com/docs/11.x/eloquent-relationships

---

### 1.3 Laravel Sanctum for Mobile API Authentication

Sanctum is the recommended authentication system for mobile apps, SPAs, and simple token-based APIs.

#### Installation and Configuration

```bash
php artisan install:api
```

This command publishes the Sanctum migration and creates the `api.php` routes file.

#### Issuing API Tokens

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create token with specific abilities
        $token = $user->createToken($request->device_name, [
            'assessment:create',
            'assessment:read',
            'results:read',
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'user'  => $user,
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
```

#### Protecting Routes

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('assessments', AssessmentController::class);
    Route::apiResource('results', ResultController::class);
});
```

#### Checking Token Abilities

```php
if ($request->user()->tokenCan('assessment:create')) {
    // User has the ability to create assessments
}
```

**Best Practices for Mobile:**
- Use personal access tokens (not cookie/session-based auth)
- Send tokens via `Authorization: Bearer {token}` header
- Store tokens in the mobile device's secure storage (Expo SecureStore)
- Implement token rotation for enhanced security
- Define granular token abilities/scopes
- Set token expiration in `config/sanctum.php`

**Source:** https://laravel.com/docs/11.x/sanctum

---

### 1.4 Queue System for AI Processing Jobs

#### Creating a Job

```bash
php artisan make:job ProcessAssessmentWithAI
```

```php
<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Services\ClaudeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAssessmentWithAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Assessment $assessment,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ClaudeService $claude): void
    {
        $answers = $this->assessment->answers()
            ->with('question')
            ->get();

        $result = $claude->analyzeAssessment($answers);

        $this->assessment->result()->create([
            'analysis'        => $result['analysis'],
            'recommendations' => $result['recommendations'],
            'scores'          => $result['scores'],
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->assessment->update(['status' => 'failed']);
        // Notify user of failure
    }
}
```

#### Dispatching Jobs

```php
// Standard dispatch
ProcessAssessmentWithAI::dispatch($assessment);

// Dispatch with delay
ProcessAssessmentWithAI::dispatch($assessment)
    ->delay(now()->addSeconds(10));

// Dispatch on a specific queue
ProcessAssessmentWithAI::dispatch($assessment)
    ->onQueue('ai-processing');

// Dispatch with chaining
Bus::chain([
    new TranscribeAudio($assessment),
    new ProcessAssessmentWithAI($assessment),
    new GeneratePDFReport($assessment),
    new NotifyUser($assessment),
])->dispatch();
```

#### Preventing Relationship Serialization Overhead

```php
public function __construct(
    #[\Illuminate\Queue\Attributes\WithoutRelations]
    public Assessment $assessment,
) {}
```

**Source:** https://laravel.com/docs/11.x/queues

---

### 1.5 Laravel Events and Listeners

#### Defining Events

```php
<?php

namespace App\Events;

use App\Models\Assessment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class AssessmentCompleted implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public Assessment $assessment,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('assessments.'.$this->assessment->id),
        ];
    }
}
```

#### Queued Event Listener

```php
<?php

namespace App\Listeners;

use App\Events\AssessmentCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAssessmentNotification implements ShouldQueue
{
    public function handle(AssessmentCompleted $event): void
    {
        $event->assessment->user->notify(
            new AssessmentReadyNotification($event->assessment)
        );
    }
}
```

#### Dispatching Events

```php
use App\Events\AssessmentCompleted;

AssessmentCompleted::dispatch($assessment);
```

#### Queueable Anonymous Listeners

```php
use function Illuminate\Events\queueable;

Event::listen(queueable(function (AssessmentCompleted $event) {
    // Process in background queue
})->onQueue('notifications')->catch(function (AssessmentCompleted $event, \Throwable $e) {
    // Handle failure
}));
```

**Source:** https://laravel.com/docs/11.x/events, https://laravel.com/docs/11.x/broadcasting

---

### 1.6 Database Migrations Best Practices

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'failed'])
                  ->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index('organization_id');
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // text, audio, multiple_choice
            $table->text('content');
            $table->json('options')->nullable(); // For multiple choice
            $table->integer('order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['assessment_id', 'order']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('text_answer')->nullable();
            $table->string('audio_path')->nullable();
            $table->text('transcription')->nullable();
            $table->json('selected_options')->nullable();
            $table->integer('duration_seconds')->nullable(); // Audio duration
            $table->timestamps();

            $table->unique(['question_id', 'user_id']);
        });

        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('analysis');
            $table->json('recommendations');
            $table->json('scores');
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('assessments');
    }
};
```

**Migration Best Practices:**
- Always define `down()` methods for rollback capability
- Use foreign key constraints with appropriate cascade actions
- Add composite indexes for frequently queried column combinations
- Use `softDeletes()` for data that should be recoverable
- Use `json` columns for flexible/schemaless data
- Use `enum` for columns with a fixed set of values
- Group related table creations in logical migration files

**Source:** https://laravel.com/docs/11.x/migrations

---

### 1.7 Laravel Sail / Docker Setup

```bash
# Create a new Laravel project with Sail
curl -s "https://laravel.build/vocation-finder?with=mysql,redis,meilisearch" | bash

# Start the development environment
cd vocation-finder
./vendor/bin/sail up -d

# Run artisan commands
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan queue:work

# Install Sail into an existing project
composer require laravel/sail --dev
php artisan sail:install
```

**Custom docker-compose.yml services** for AI processing:

```yaml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.3
            dockerfile: Dockerfile
        ports:
            - '${APP_PORT:-80}:80'
        environment:
            ANTHROPIC_API_KEY: '${ANTHROPIC_API_KEY}'
            OPENAI_API_KEY: '${OPENAI_API_KEY}'
            ELEVENLABS_API_KEY: '${ELEVENLABS_API_KEY}'
        volumes:
            - '.:/var/www/html'
        depends_on:
            - mysql
            - redis

    mysql:
        image: 'mysql/mysql-server:8.0'
        ports:
            - '${FORWARD_DB_PORT:-3306}:3306'
        environment:
            MYSQL_ROOT_PASSWORD: '${DB_PASSWORD}'
            MYSQL_DATABASE: '${DB_DATABASE}'

    redis:
        image: 'redis:alpine'
        ports:
            - '${FORWARD_REDIS_PORT:-6379}:6379'

    queue-worker:
        build:
            context: ./vendor/laravel/sail/runtimes/8.3
            dockerfile: Dockerfile
        command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
        volumes:
            - '.:/var/www/html'
        depends_on:
            - mysql
            - redis
```

**Source:** https://laravel.com/docs/11.x/sail, https://laravel.com/docs/11.x/installation

---

## 2. Expo SDK 52+ (React Native Frontend)

### 2.1 Expo Router File-Based Routing

Expo Router uses file-based routing where file paths map directly to URL routes.

#### File Structure Convention

```
app/
  _layout.tsx          -> Root layout
  index.tsx            -> / (home)
  (auth)/
    _layout.tsx        -> Auth layout group
    login.tsx          -> /login
    register.tsx       -> /register
  (tabs)/
    _layout.tsx        -> Tab layout group
    index.tsx          -> / (tab home)
    assessments.tsx    -> /assessments
    results.tsx        -> /results
    profile.tsx        -> /profile
  assessment/
    [id].tsx           -> /assessment/:id (dynamic)
    [id]/
      question/[qid].tsx -> /assessment/:id/question/:qid
  +not-found.tsx       -> 404 handler
  +api.ts              -> API route (server)
```

#### Root Layout with Authentication

```tsx
// app/_layout.tsx
import { Stack } from 'expo-router';
import { useAuth } from '../hooks/useAuth';

export default function RootLayout() {
  const { isLoggedIn } = useAuth();

  return (
    <Stack>
      <Stack.Protected guard={isLoggedIn}>
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="assessment/[id]" options={{ title: 'Assessment' }} />
      </Stack.Protected>

      <Stack.Protected guard={!isLoggedIn}>
        <Stack.Screen name="(auth)" options={{ headerShown: false }} />
      </Stack.Protected>

      <Stack.Screen name="+not-found" />
    </Stack>
  );
}
```

#### Tab Layout

```tsx
// app/(tabs)/_layout.tsx
import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

export default function TabLayout() {
  return (
    <Tabs screenOptions={{ tabBarActiveTintColor: '#6366f1' }}>
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarIcon: ({ color }) => <Ionicons name="home" size={24} color={color} />,
        }}
      />
      <Tabs.Screen
        name="assessments"
        options={{
          title: 'Assessments',
          tabBarIcon: ({ color }) => <Ionicons name="clipboard" size={24} color={color} />,
        }}
      />
      <Tabs.Screen
        name="results"
        options={{
          title: 'Results',
          tabBarIcon: ({ color }) => <Ionicons name="bar-chart" size={24} color={color} />,
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profile',
          tabBarIcon: ({ color }) => <Ionicons name="person" size={24} color={color} />,
        }}
      />
    </Tabs>
  );
}
```

#### Protected Routes (Expo SDK 53+)

Expo Router introduced `Stack.Protected` and `Tabs.Protected` components that accept a `guard` prop. When `guard={true}`, the screen is accessible; when `guard={false}`, it is not. This simplifies authentication flows and role-based routing.

```tsx
<Tabs>
  <Tabs.Protected guard={isVIP}>
    <Tabs.Screen name="vip" options={{ title: 'VIP' }} />
  </Tabs.Protected>
</Tabs>
```

**Sources:**
- https://docs.expo.dev/router/introduction/
- https://docs.expo.dev/router/basics/core-concepts/
- https://docs.expo.dev/router/advanced/authentication/
- https://docs.expo.dev/router/advanced/protected/
- https://expo.dev/blog/simplifying-auth-flows-with-protected-routes

---

### 2.2 Expo Audio Module (Recording and Playback)

#### Audio Playback with `useAudioPlayer`

```tsx
import { useAudioPlayer } from 'expo-audio';
import { View, Button } from 'react-native';

function AudioPlayback() {
  // Local asset
  const player = useAudioPlayer(require('../assets/question-audio.mp3'));

  // Remote URL with options
  const remotePlayer = useAudioPlayer('https://api.example.com/audio/tts-output.mp3', {
    updateInterval: 1000,  // Update interval in ms
    downloadFirst: true,   // Download before playing
  });

  return (
    <View>
      <Button title="Play Local" onPress={() => player.play()} />
      <Button title="Play Remote" onPress={() => remotePlayer.play()} />
      <Button title="Pause" onPress={() => remotePlayer.pause()} />
    </View>
  );
}
```

#### Audio Recording with `useAudioRecorder`

```tsx
import { useState, useEffect } from 'react';
import { View, Button, Alert } from 'react-native';
import {
  useAudioRecorder,
  AudioModule,
  RecordingPresets,
  setAudioModeAsync,
  useAudioRecorderState,
} from 'expo-audio';

export default function VoiceRecorder() {
  const audioRecorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const recorderState = useAudioRecorderState(audioRecorder);

  const record = async () => {
    await audioRecorder.prepareToRecordAsync();
    audioRecorder.record();
  };

  const stopRecording = async () => {
    await audioRecorder.stop();
    // The recorded file URI is available at audioRecorder.uri
    console.log('Recording saved to:', audioRecorder.uri);
    // Upload to backend for Whisper transcription
    await uploadAudioForTranscription(audioRecorder.uri);
  };

  useEffect(() => {
    (async () => {
      const status = await AudioModule.requestRecordingPermissionsAsync();
      if (!status.granted) {
        Alert.alert('Permission to access microphone was denied');
      }
      setAudioModeAsync({
        playsInSilentMode: true,
        allowsRecording: true,
      });
    })();
  }, []);

  return (
    <View>
      <Button
        title={recorderState.isRecording ? 'Stop Recording' : 'Start Recording'}
        onPress={recorderState.isRecording ? stopRecording : record}
      />
    </View>
  );
}
```

**Key API surface:**
- `useAudioPlayer(source, options)` -- Creates a managed AudioPlayer instance
- `useAudioRecorder(preset)` -- Creates a managed AudioRecorder instance
- `useAudioRecorderState(recorder)` -- Returns reactive recording state
- `AudioModule.requestRecordingPermissionsAsync()` -- Requests mic permission
- `setAudioModeAsync(options)` -- Configures global audio mode
- `RecordingPresets.HIGH_QUALITY` -- Preset recording configuration

**Source:** https://docs.expo.dev/versions/latest/sdk/audio

---

### 2.3 Expo Haptics

Provides access to the system vibration (Android), haptics engine (iOS), and Web Vibration API.

```bash
npx expo install expo-haptics
```

#### API Methods

```tsx
import * as Haptics from 'expo-haptics';

// Selection feedback -- for UI selection changes (lightest)
await Haptics.selectionAsync();

// Impact feedback -- for UI element interactions
await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Heavy);

// Notification feedback -- for operation outcomes
await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
```

#### Usage in Assessment App

```tsx
import * as Haptics from 'expo-haptics';

function QuestionCard({ onAnswer }) {
  const handleAnswerSelect = (option) => {
    Haptics.selectionAsync(); // Light feedback on selection
    onAnswer(option);
  };

  const handleSubmit = () => {
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  };

  return (/* ... */);
}
```

**Platform Notes:**
- iOS: No haptics when Low Power Mode is enabled or during Camera/Dictation use
- Android: VIBRATE permission is added automatically
- Web: Limited support via Vibration API

**Source:** https://docs.expo.dev/versions/latest/sdk/haptics/

---

### 2.4 Expo Speech (Text-to-Speech)

```bash
npx expo install expo-speech
```

#### Basic Usage

```tsx
import * as Speech from 'expo-speech';

// Simple speak
Speech.speak('Welcome to your vocational assessment.');

// Speak with options
Speech.speak('Please answer the following question.', {
  language: 'en-US',
  pitch: 1.0,     // 0.5 to 2.0
  rate: 0.9,      // 0.1 to 2.0 (speed)
  onStart: () => console.log('Speech started'),
  onDone: () => console.log('Speech finished'),
  onError: (error) => console.error('Speech error:', error),
});

// Check if currently speaking
const isSpeaking = await Speech.isSpeakingAsync();

// Stop all speech
Speech.stop();

// Pause current speech (iOS only)
Speech.pause();

// Resume paused speech (iOS only)
Speech.resume();
```

#### Assessment Question Reader

```tsx
import * as Speech from 'expo-speech';
import { useState } from 'react';

function QuestionReader({ questionText }) {
  const [isSpeaking, setIsSpeaking] = useState(false);

  const readQuestion = () => {
    Speech.speak(questionText, {
      language: 'en-US',
      rate: 0.85,
      onStart: () => setIsSpeaking(true),
      onDone: () => setIsSpeaking(false),
      onError: () => setIsSpeaking(false),
    });
  };

  const stopReading = () => {
    Speech.stop();
    setIsSpeaking(false);
  };

  return (
    <Button
      title={isSpeaking ? 'Stop Reading' : 'Read Aloud'}
      onPress={isSpeaking ? stopReading : readQuestion}
    />
  );
}
```

**Note:** On iOS physical devices, speech will not produce sound if the device is in silent mode.

**Source:** https://docs.expo.dev/versions/latest/sdk/speech/

---

### 2.5 expo-dev-client for Custom Native Modules

```bash
npx expo install expo-dev-client
```

#### Development Build vs Expo Go

expo-dev-client enables "development builds" -- custom debug builds of your app that include native modules not available in Expo Go. This is required when using libraries like `expo-audio` recording features.

#### Creating a Development Build

```bash
# Start the JS bundler (auto-detects expo-dev-client)
npx expo start

# Build with EAS for device testing
eas build --profile development --platform ios
eas build --profile development --platform android
```

#### EAS Build Configuration (eas.json)

```json
{
  "cli": {
    "version": ">= 12.0.0"
  },
  "build": {
    "development": {
      "developmentClient": true,
      "distribution": "internal",
      "ios": {
        "simulator": true
      }
    },
    "preview": {
      "distribution": "internal"
    },
    "production": {}
  },
  "submit": {
    "production": {}
  }
}
```

**Source:** https://docs.expo.dev/develop/development-builds/create-a-build/

---

### 2.6 NativeWind / Tailwind CSS for Styling

NativeWind allows Tailwind CSS utility classes on React Native components, compiling to `StyleSheet.create` on native and CSS on web.

#### Installation

```bash
npx expo install nativewind tailwindcss react-native-reanimated react-native-safe-area-context
# or create a new project with NativeWind pre-configured:
npx rn-new --nativewind
```

#### Tailwind Configuration

```javascript
// tailwind.config.js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/**/*.{js,jsx,ts,tsx}",
    "./components/**/*.{js,jsx,ts,tsx}",
  ],
  presets: [require("nativewind/preset")],
  theme: {
    extend: {
      colors: {
        primary: '#6366f1',
        secondary: '#8b5cf6',
        accent: '#f59e0b',
      },
    },
  },
  plugins: [],
};
```

#### Global CSS Import

```css
/* global.css */
@tailwind base;
@tailwind components;
@tailwind utilities;
```

#### Usage Example

```tsx
import "./global.css";
import { Text, View } from "react-native";

export default function AssessmentCard({ title, status }) {
  return (
    <View className="flex-1 items-center justify-center bg-white p-4 rounded-xl shadow-md">
      <Text className="text-xl font-bold text-primary">
        {title}
      </Text>
      <Text className="text-sm text-gray-500 mt-2">
        Status: {status}
      </Text>
    </View>
  );
}
```

**Key Concepts:**
- Use `className` prop instead of `style` on React Native components
- `presets: [require("nativewind/preset")]` is required in tailwind.config.js
- Import `global.css` at the root of your app
- Supports dark mode, media queries, and platform-specific styles
- NativeWind v4 is stable; v5 is in pre-release

**Source:** https://www.nativewind.dev/docs/getting-started/installation

---

### 2.7 Expo EAS Build and Deployment

```bash
# Install EAS CLI
npm install -g eas-cli

# Log in to Expo
eas login

# Configure EAS Build
eas build:configure

# Build for platforms
eas build --platform ios
eas build --platform android
eas build --platform all

# Submit to stores
eas submit --platform ios
eas submit --platform android

# Over-the-air updates
eas update --branch production --message "Bug fix"
```

**Source:** https://docs.expo.dev/build/introduction/

---

## 3. AI/ML Integration Libraries

### 3.1 Anthropic Claude API (PHP SDK for Laravel)

#### Installation

```bash
composer require claude-php/claude-php-sdk-laravel
```

Alternative community packages:
- `mozex/anthropic-laravel` -- Anthropic PHP for Laravel
- `goldenpathdigital/laravel-claude` -- Laravel wrapper with MCP connector support

#### Direct HTTP Integration (Framework-Agnostic)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ClaudeService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
    }

    /**
     * Send a message to Claude and get a response.
     */
    public function sendMessage(string $prompt, array $systemPrompt = []): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post("{$this->baseUrl}/messages", [
            'model'      => 'claude-sonnet-4-5-20250514',
            'max_tokens' => 4096,
            'system'     => $systemPrompt ?: 'You are a vocational assessment expert.',
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return $response->json();
    }

    /**
     * Analyze assessment answers with structured tool use.
     */
    public function analyzeAssessment(array $answers): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->post("{$this->baseUrl}/messages", [
            'model'      => 'claude-sonnet-4-5-20250514',
            'max_tokens' => 8192,
            'system'     => 'You are an expert vocational psychologist. Analyze the assessment responses and provide structured career recommendations.',
            'tools'      => [
                [
                    'name'         => 'vocational_analysis',
                    'description'  => 'Structured vocational assessment analysis result',
                    'input_schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'career_matches' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'career'      => ['type' => 'string'],
                                        'match_score' => ['type' => 'number'],
                                        'reasoning'   => ['type' => 'string'],
                                    ],
                                ],
                            ],
                            'strengths'       => [
                                'type'  => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'growth_areas'    => [
                                'type'  => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'summary'         => ['type' => 'string'],
                        ],
                        'required'   => ['career_matches', 'strengths', 'growth_areas', 'summary'],
                    ],
                ],
            ],
            'tool_choice' => ['type' => 'tool', 'name' => 'vocational_analysis'],
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => "Analyze these assessment responses:\n\n" . json_encode($answers),
                ],
            ],
        ]);

        $result = $response->json();
        $toolUse = collect($result['content'])
            ->firstWhere('type', 'tool_use');

        return $toolUse['input'] ?? [];
    }

    /**
     * Stream a response from Claude.
     */
    public function streamMessage(string $prompt, callable $onChunk): void
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->withOptions([
            'stream' => true,
        ])->post("{$this->baseUrl}/messages", [
            'model'      => 'claude-sonnet-4-5-20250514',
            'max_tokens' => 4096,
            'stream'     => true,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $body = $response->getBody();
        while (!$body->eof()) {
            $line = $body->read(1024);
            $onChunk($line);
        }
    }
}
```

#### Claude API Key Endpoints

| Method | Endpoint             | Description                    |
|--------|----------------------|--------------------------------|
| POST   | /v1/messages         | Create a message               |
| POST   | /v1/messages (stream)| Create a streamed message      |
| POST   | /v1/messages/batches | Create a message batch         |

#### API Parameters

- `model` (required): e.g., `claude-sonnet-4-5-20250514`, `claude-haiku-35-20250929`
- `max_tokens` (required): Maximum tokens to generate
- `messages` (required): Array of message objects with `role` and `content`
- `system` (optional): System prompt string
- `tools` (optional): Array of tool definitions
- `tool_choice` (optional): Force specific tool usage
- `stream` (optional): Enable streaming response

**Sources:**
- https://docs.anthropic.com/en/api/messages
- https://github.com/claude-php/Claude-PHP-SDK
- https://github.com/claude-php/Claude-PHP-SDK-Laravel
- https://github.com/mozex/anthropic-laravel

---

### 3.2 OpenAI Whisper API for Speech-to-Text

#### Using openai-php/laravel Package

```bash
composer require openai-php/laravel
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

```env
OPENAI_API_KEY=sk-...
OPENAI_ORGANIZATION=org-...
```

#### Transcription Service

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WhisperService
{
    /**
     * Transcribe an audio file using OpenAI Whisper.
     */
    public function transcribe(string $audioPath): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
        ])->attach(
            'file',
            Storage::get($audioPath),
            basename($audioPath)
        )->post('https://api.openai.com/v1/audio/transcriptions', [
            'model'           => 'whisper-1',
            'response_format' => 'json',
            'language'        => 'en',
        ]);

        return $response->json('text');
    }

    /**
     * Transcribe with timestamps (verbose output).
     */
    public function transcribeWithTimestamps(string $audioPath): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
        ])->attach(
            'file',
            Storage::get($audioPath),
            basename($audioPath)
        )->post('https://api.openai.com/v1/audio/transcriptions', [
            'model'           => 'whisper-1',
            'response_format' => 'verbose_json',
            'timestamp_granularities' => ['word', 'segment'],
        ]);

        return $response->json();
    }
}
```

**Whisper API Details:**
- Endpoint: `POST https://api.openai.com/v1/audio/transcriptions`
- Supported formats: mp3, mp4, mpeg, mpga, m4a, wav, webm
- Maximum file size: 25 MB
- Cost: $0.006 per minute
- Model: `whisper-1`

**Sources:**
- https://platform.openai.com/docs/guides/speech-to-text
- https://github.com/openai-php/laravel

---

### 3.3 Text-to-Speech (ElevenLabs and OpenAI TTS)

#### ElevenLabs TTS Service (PHP)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ElevenLabsService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.elevenlabs.io/v1';

    public function __construct()
    {
        $this->apiKey = config('services.elevenlabs.api_key');
    }

    /**
     * Convert text to speech and return audio file path.
     */
    public function textToSpeech(
        string $text,
        string $voiceId = 'pMsXgVXv3BLzUgSXRplE', // Default voice
        string $modelId = 'eleven_multilingual_v2'
    ): string {
        $response = Http::withHeaders([
            'xi-api-key'   => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'audio/mpeg',
        ])->post("{$this->baseUrl}/text-to-speech/{$voiceId}", [
            'text'     => $text,
            'model_id' => $modelId,
            'voice_settings' => [
                'stability'        => 0.5,
                'similarity_boost' => 0.75,
                'style'            => 0.0,
                'use_speaker_boost' => true,
            ],
        ]);

        $filename = 'tts/' . uniqid() . '.mp3';
        Storage::disk('public')->put($filename, $response->body());

        return $filename;
    }

    /**
     * Stream text to speech audio.
     */
    public function streamTextToSpeech(string $text, string $voiceId): \Generator
    {
        $response = Http::withHeaders([
            'xi-api-key'   => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->withOptions([
            'stream' => true,
        ])->post("{$this->baseUrl}/text-to-speech/{$voiceId}/stream", [
            'text'     => $text,
            'model_id' => 'eleven_multilingual_v2',
        ]);

        $body = $response->getBody();
        while (!$body->eof()) {
            yield $body->read(4096);
        }
    }
}
```

**ElevenLabs API Details:**
- Convert endpoint: `POST /v1/text-to-speech/{voice_id}`
- Stream endpoint: `POST /v1/text-to-speech/{voice_id}/stream`
- Authentication: `xi-api-key` header
- Models: `eleven_multilingual_v2`, `eleven_v3`
- Output: audio/mpeg

#### OpenAI TTS Service (PHP)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OpenAITTSService
{
    /**
     * Generate speech from text.
     */
    public function speak(
        string $text,
        string $voice = 'nova',
        string $model = 'tts-1'
    ): string {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.openai.com/v1/audio/speech', [
            'model'           => $model,         // tts-1, tts-1-hd, gpt-4o-mini-tts
            'input'           => $text,
            'voice'           => $voice,          // alloy, echo, fable, onyx, nova, shimmer
            'response_format' => 'mp3',           // mp3, opus, aac, flac, wav, pcm
            'speed'           => 1.0,             // 0.25 to 4.0
        ]);

        $filename = 'tts/' . uniqid() . '.mp3';
        Storage::disk('public')->put($filename, $response->body());

        return $filename;
    }

    /**
     * Stream speech using the openai-php/client package.
     * Returns chunks of audio data as they arrive.
     */
    public function streamSpeech(string $text, string $voice = 'nova'): \Generator
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.api_key'),
            'Content-Type'  => 'application/json',
        ])->withOptions([
            'stream' => true,
        ])->post('https://api.openai.com/v1/audio/speech', [
            'model' => 'tts-1',
            'input' => $text,
            'voice' => $voice,
        ]);

        $body = $response->getBody();
        while (!$body->eof()) {
            yield $body->read(4096);
        }
    }
}
```

**OpenAI TTS API Details:**
- Endpoint: `POST https://api.openai.com/v1/audio/speech`
- Models: `tts-1` (low latency), `tts-1-hd` (high quality), `gpt-4o-mini-tts`
- Voices: alloy, ash, ballad, coral, echo, fable, onyx, nova, sage, shimmer, verse
- Formats: mp3, opus, aac, flac, wav, pcm
- Supports realtime streaming via chunk transfer encoding

**Sources:**
- https://elevenlabs.io/docs/api-reference/text-to-speech/convert
- https://elevenlabs.io/docs/api-reference/text-to-speech/stream
- https://platform.openai.com/docs/guides/text-to-speech
- https://platform.openai.com/docs/api-reference/audio/createSpeech

---

### 3.4 Streaming Responses from AI APIs

#### Server-Sent Events (SSE) from Laravel

```php
<?php

namespace App\Http\Controllers;

use App\Services\ClaudeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIStreamController extends Controller
{
    public function streamAnalysis(Request $request, ClaudeService $claude): StreamedResponse
    {
        return response()->stream(function () use ($request, $claude) {
            $claude->streamMessage(
                $request->input('prompt'),
                function ($chunk) {
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                }
            );
            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection'    => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

#### Consuming SSE on React Native

```tsx
import { useCallback, useState } from 'react';

function useStreamingAI() {
  const [text, setText] = useState('');
  const [isStreaming, setIsStreaming] = useState(false);

  const startStream = useCallback(async (prompt: string) => {
    setIsStreaming(true);
    setText('');

    const response = await fetch('https://api.example.com/ai/stream', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({ prompt }),
    });

    const reader = response.body?.getReader();
    const decoder = new TextDecoder();

    while (reader) {
      const { done, value } = await reader.read();
      if (done) break;

      const chunk = decoder.decode(value);
      const lines = chunk.split('\n');

      for (const line of lines) {
        if (line.startsWith('data: ') && line !== 'data: [DONE]') {
          const data = JSON.parse(line.slice(6));
          setText(prev => prev + data.text);
        }
      }
    }

    setIsStreaming(false);
  }, []);

  return { text, isStreaming, startStream };
}
```

---

## 4. Supporting Libraries

### 4.1 Stripe PHP SDK for Subscriptions

#### Installation

```bash
composer require stripe/stripe-php
```

#### Configuration

```php
// config/services.php
'stripe' => [
    'key'    => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

#### Subscription Management Service

```php
<?php

namespace App\Services;

use App\Models\User;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Checkout\Session;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Checkout Session for a subscription.
     */
    public function createCheckoutSession(User $user, string $priceId): Session
    {
        return Session::create([
            'mode'         => 'subscription',
            'success_url'  => config('app.frontend_url') . '/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'   => config('app.frontend_url') . '/subscription/canceled',
            'customer_email' => $user->email,
            'line_items'   => [
                [
                    'price'    => $priceId,
                    'quantity' => 1,
                ],
            ],
            'subscription_data' => [
                'trial_period_days' => 14,
                'metadata'          => [
                    'user_id' => $user->id,
                    'plan'    => 'premium',
                ],
            ],
            'allow_promotion_codes' => true,
        ]);
    }

    /**
     * Create a subscription directly with payment.
     */
    public function createSubscription(string $customerId, string $priceId): Subscription
    {
        return Subscription::create([
            'customer'         => $customerId,
            'items'            => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'expand'           => ['latest_invoice.payment_intent'],
            'metadata'         => ['plan_name' => 'Premium Monthly'],
        ]);
    }

    /**
     * Cancel subscription at end of billing period.
     */
    public function cancelAtPeriodEnd(string $subscriptionId): Subscription
    {
        return Subscription::update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }
}
```

#### Webhook Handler

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload    = $request->getContent();
        $sigHeader  = $request->header('Stripe-Signature');
        $secret     = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            return response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                // Provision the subscription
                $this->provisionSubscription($session);
                break;

            case 'invoice.paid':
                $invoice = $event->data->object;
                // Continue provisioning, record payment
                $this->recordPayment($invoice);
                break;

            case 'invoice.payment_failed':
                $invoice = $event->data->object;
                // Notify user to update payment method
                $this->handleFailedPayment($invoice);
                break;

            case 'customer.subscription.updated':
                $subscription = $event->data->object;
                $this->handleSubscriptionUpdate($subscription);
                break;

            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
                $this->handleSubscriptionCancellation($subscription);
                break;

            default:
                // Unhandled event type
        }

        return response('OK', 200);
    }
}
```

**Sources:**
- https://github.com/stripe/stripe-php
- https://docs.stripe.com/billing/subscriptions/build-subscriptions
- https://docs.stripe.com/billing/quickstart

---

### 4.2 Laravel DomPDF for PDF Generation

#### Installation

```bash
composer require barryvdh/laravel-dompdf
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

#### PDF Generation Service

```php
<?php

namespace App\Services;

use App\Models\Result;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PDFReportService
{
    /**
     * Generate an assessment results PDF.
     */
    public function generateReport(Result $result): string
    {
        $data = [
            'user'            => $result->user,
            'assessment'      => $result->assessment,
            'analysis'        => $result->analysis,
            'recommendations' => $result->recommendations,
            'scores'          => $result->scores,
            'generated_at'    => now()->format('F j, Y'),
        ];

        $pdf = Pdf::loadView('pdf.assessment-report', $data);

        // Set PDF options
        $pdf->setOption([
            'dpi'         => 150,
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => true,
        ]);

        $pdf->setPaper('A4', 'portrait');

        // Save to storage
        $filename = "reports/assessment-{$result->assessment_id}-{$result->user_id}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        // Update result record
        $result->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Stream PDF directly to browser.
     */
    public function streamReport(Result $result)
    {
        $pdf = Pdf::loadView('pdf.assessment-report', [
            'result' => $result->load(['user', 'assessment']),
        ]);

        return $pdf->stream("assessment-report-{$result->id}.pdf");
    }

    /**
     * Download PDF.
     */
    public function downloadReport(Result $result)
    {
        $pdf = Pdf::loadView('pdf.assessment-report', [
            'result' => $result->load(['user', 'assessment']),
        ]);

        return $pdf->download("assessment-report-{$result->id}.pdf");
    }
}
```

**DomPDF Constraints:**
- Only supports CSS 2.1 and limited CSS3 properties
- Write dedicated PDF-specific CSS (not reuse app CSS)
- Place CSS files in `/public` and load with `asset()` helper
- Use DejaVu Sans or other Unicode-safe fonts
- Use `page-break-before` / `page-break-after` for multi-page layouts
- For complex layouts, consider Spatie Laravel PDF (uses Browsershot/Chrome)

**Sources:**
- https://github.com/barryvdh/laravel-dompdf
- https://laraveldaily.com/post/laravel-dompdf-generate-simple-invoice-pdf-with-images-css

---

### 4.3 React Native Reanimated for Animations

React Native Reanimated provides performant animations that run on the UI thread.

#### Installation

```bash
npx expo install react-native-reanimated
```

#### Add Babel Plugin

```javascript
// babel.config.js
module.exports = function (api) {
  api.cache(true);
  return {
    presets: ['babel-preset-expo'],
    plugins: ['react-native-reanimated/plugin'], // MUST be last
  };
};
```

#### Core Animation Patterns

##### useSharedValue + useAnimatedStyle

```tsx
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withSpring,
  withTiming,
  Easing,
} from 'react-native-reanimated';
import { Button, View, StyleSheet } from 'react-native';

function AnimatedCard() {
  const offset = useSharedValue(0);
  const scale = useSharedValue(1);

  const animatedStyles = useAnimatedStyle(() => ({
    transform: [
      { translateX: offset.value },
      { scale: scale.value },
    ],
  }));

  const handlePress = () => {
    offset.value = withSpring(offset.value + 50);
    scale.value = withSpring(1.1, { damping: 10, stiffness: 100 }, () => {
      scale.value = withSpring(1);
    });
  };

  return (
    <View style={styles.container}>
      <Animated.View style={[styles.card, animatedStyles]}>
        {/* Card content */}
      </Animated.View>
      <Button title="Animate" onPress={handlePress} />
    </View>
  );
}
```

##### withTiming (Duration-Based)

```tsx
const expand = () => {
  width.value = withTiming(300, {
    duration: 500,
    easing: Easing.bezier(0.25, 0.1, 0.25, 1),
  });
};
```

##### withSpring (Physics-Based)

```tsx
const bounce = () => {
  translateY.value = withSpring(100, {
    damping: 10,       // Lower = more bouncy
    stiffness: 100,    // Higher = faster
    mass: 1,
  });
};
```

##### Entering and Exiting Layout Animations

```tsx
import Animated, { SlideInRight, SlideOutLeft, FadeIn, FadeOut } from 'react-native-reanimated';

function QuestionTransition({ question }) {
  return (
    <Animated.View entering={SlideInRight} exiting={SlideOutLeft}>
      <Text>{question.text}</Text>
    </Animated.View>
  );
}

function ResultCard({ visible }) {
  if (!visible) return null;
  return (
    <Animated.View entering={FadeIn.duration(300)} exiting={FadeOut.duration(200)}>
      {/* Result content */}
    </Animated.View>
  );
}
```

**Sources:**
- https://docs.swmansion.com/react-native-reanimated/
- https://github.com/software-mansion/react-native-reanimated

---

### 4.4 Zustand for State Management (React Native)

Zustand is a small, fast state management solution for React with a hooks-based API and zero boilerplate.

#### Installation

```bash
npm install zustand
```

#### Assessment Store

```typescript
import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { devtools } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Types
interface Assessment {
  id: number;
  title: string;
  status: 'draft' | 'in_progress' | 'completed' | 'failed';
}

interface Question {
  id: number;
  type: 'text' | 'audio' | 'multiple_choice';
  content: string;
  options?: string[];
}

interface Answer {
  questionId: number;
  textAnswer?: string;
  audioUri?: string;
  selectedOptions?: string[];
}

interface AssessmentStore {
  // State
  currentAssessment: Assessment | null;
  questions: Question[];
  answers: Record<number, Answer>;
  currentQuestionIndex: number;
  loading: boolean;
  error: string | null;

  // Actions
  setAssessment: (assessment: Assessment) => void;
  setQuestions: (questions: Question[]) => void;
  saveAnswer: (questionId: number, answer: Answer) => void;
  nextQuestion: () => void;
  previousQuestion: () => void;
  resetAssessment: () => void;
  submitAssessment: () => Promise<void>;
}

export const useAssessmentStore = create<AssessmentStore>()(
  devtools(
    persist(
      (set, get) => ({
        // Initial state
        currentAssessment: null,
        questions: [],
        answers: {},
        currentQuestionIndex: 0,
        loading: false,
        error: null,

        // Actions
        setAssessment: (assessment) =>
          set({ currentAssessment: assessment }, undefined, 'assessment/set'),

        setQuestions: (questions) =>
          set({ questions }, undefined, 'questions/set'),

        saveAnswer: (questionId, answer) =>
          set(
            (state) => ({
              answers: { ...state.answers, [questionId]: answer },
            }),
            undefined,
            'answer/save'
          ),

        nextQuestion: () =>
          set(
            (state) => ({
              currentQuestionIndex: Math.min(
                state.currentQuestionIndex + 1,
                state.questions.length - 1
              ),
            }),
            undefined,
            'question/next'
          ),

        previousQuestion: () =>
          set(
            (state) => ({
              currentQuestionIndex: Math.max(state.currentQuestionIndex - 1, 0),
            }),
            undefined,
            'question/previous'
          ),

        resetAssessment: () =>
          set(
            {
              currentAssessment: null,
              questions: [],
              answers: {},
              currentQuestionIndex: 0,
              error: null,
            },
            undefined,
            'assessment/reset'
          ),

        submitAssessment: async () => {
          const { currentAssessment, answers } = get();
          if (!currentAssessment) return;

          set({ loading: true, error: null }, undefined, 'assessment/submitStart');

          try {
            const response = await fetch(
              `${API_URL}/assessments/${currentAssessment.id}/submit`,
              {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify({ answers }),
              }
            );

            if (!response.ok) throw new Error('Submission failed');

            set(
              {
                currentAssessment: { ...currentAssessment, status: 'completed' },
                loading: false,
              },
              undefined,
              'assessment/submitSuccess'
            );
          } catch (error) {
            set(
              { error: (error as Error).message, loading: false },
              undefined,
              'assessment/submitError'
            );
          }
        },
      }),
      {
        name: 'assessment-storage',
        storage: createJSONStorage(() => AsyncStorage),
        partialize: (state) => ({
          currentAssessment: state.currentAssessment,
          answers: state.answers,
          currentQuestionIndex: state.currentQuestionIndex,
        }),
      }
    ),
    { name: 'AssessmentStore' }
  )
);
```

#### Auth Store

```typescript
import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';

interface User {
  id: number;
  name: string;
  email: string;
}

interface AuthStore {
  user: User | null;
  token: string | null;
  isLoggedIn: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  setToken: (token: string) => void;
}

export const useAuthStore = create<AuthStore>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      isLoggedIn: false,

      login: async (email, password) => {
        const response = await fetch(`${API_URL}/auth/login`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, password, device_name: 'mobile' }),
        });

        const data = await response.json();

        // Store token securely
        await SecureStore.setItemAsync('auth_token', data.token);

        set({
          user: data.user,
          token: data.token,
          isLoggedIn: true,
        });
      },

      logout: async () => {
        await SecureStore.deleteItemAsync('auth_token');
        set({ user: null, token: null, isLoggedIn: false });
      },

      setToken: (token) => set({ token }),
    }),
    {
      name: 'auth-storage',
      storage: createJSONStorage(() => AsyncStorage),
      partialize: (state) => ({ user: state.user, isLoggedIn: state.isLoggedIn }),
      // Note: token is stored in SecureStore, not AsyncStorage
    }
  )
);
```

#### Using Stores in Components

```tsx
import { useAssessmentStore } from '../stores/assessmentStore';

function QuestionScreen() {
  // Select only what you need (automatic re-render optimization)
  const question = useAssessmentStore(
    (state) => state.questions[state.currentQuestionIndex]
  );
  const saveAnswer = useAssessmentStore((state) => state.saveAnswer);
  const nextQuestion = useAssessmentStore((state) => state.nextQuestion);

  return (/* ... */);
}
```

**Key Zustand Features:**
- No providers or context needed
- Automatic selector optimization (only re-renders when selected state changes)
- Built-in `persist` middleware with custom storage (AsyncStorage for React Native)
- `devtools` middleware for Redux DevTools integration
- `partialize` option to persist only specific state slices
- Supports async actions natively within the store

**Sources:**
- https://github.com/pmndrs/zustand
- https://zustand.docs.pmnd.rs/

---

## Version Compatibility Matrix

| Library                   | Recommended Version | PHP / Node Requirement |
|---------------------------|--------------------|-----------------------|
| Laravel                   | 11.x or 12.x      | PHP 8.2+             |
| Laravel Sanctum           | 4.x (bundled)     | PHP 8.2+             |
| Expo SDK                  | 52 or 53           | Node 18+             |
| Expo Router               | v4                 | --                    |
| expo-audio                | 15.x              | --                    |
| expo-haptics              | 14.x              | --                    |
| expo-speech               | 14.x              | --                    |
| NativeWind                | 4.x               | --                    |
| React Native Reanimated   | 3.x or 4.x        | --                    |
| Zustand                   | 5.x               | --                    |
| stripe/stripe-php         | 16.x+             | PHP 8.1+             |
| barryvdh/laravel-dompdf   | 3.x               | PHP 8.1+             |
| openai-php/laravel        | 0.10+              | PHP 8.1+             |
| claude-php/claude-php-sdk | Latest             | PHP 8.1+             |

---

## Quick Reference: Key Shell Commands

```bash
# Laravel Setup
curl -s "https://laravel.build/vocation-finder?with=mysql,redis" | bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan make:model Assessment -mfcr  # model + migration + factory + controller + resource
./vendor/bin/sail artisan make:job ProcessAssessmentWithAI
./vendor/bin/sail artisan make:event AssessmentCompleted
./vendor/bin/sail artisan make:listener SendAssessmentNotification --event=AssessmentCompleted
./vendor/bin/sail artisan queue:work redis --queue=ai-processing

# Expo Setup
npx create-expo-app vocation-finder --template blank-typescript
npx expo install expo-router expo-audio expo-haptics expo-speech expo-dev-client expo-secure-store
npx expo install nativewind tailwindcss react-native-reanimated
npx expo install @react-native-async-storage/async-storage
npm install zustand

# EAS Build
eas build --profile development --platform all
eas build --profile production --platform all
eas submit --platform ios
eas submit --platform android
```
