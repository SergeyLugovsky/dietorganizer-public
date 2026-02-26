# Diet Organizer Mobile (React Native)

Native mobile shell for the Diet Organizer web app:
- `react-native-webview` renders the existing website.
- `@react-native-firebase/messaging` receives push notifications.
- FCM token is registered in backend via `window.DietOrganizer.registerMobilePushToken(...)`.

## 1. Backend prerequisites

From the PHP project root:

1. Apply migration:
   - `sql/migrations/20260222_add_mobile_push_tokens.sql`
2. Configure `private/.env.php`:
   - `mobile_push.enabled = true`
   - `mobile_push.provider = 'fcm'`
   - `mobile_push.project_id = ...`
   - `mobile_push.service_account_json = APP_PRIVATE . '/firebase-service-account.json'`
3. Ensure cron is running:
   - `private/cron/send_meal_reminders.php`

## 2. Mobile configuration

Edit `src/config.ts`:
- `WEB_APP_URL` must point to your real HTTPS domain.

```ts
export const WEB_APP_URL = 'https://dietorganizer.your-domain.com';
```

## 3. Firebase setup

### Android

1. Copy template and fill with your Firebase project values:
   - `cp android/app/google-services.json.example android/app/google-services.json`
2. Place your real Firebase config in:
   - `android/app/google-services.json`
3. Build files are already configured:
   - `android/build.gradle` includes Google Services classpath.
   - `android/app/build.gradle` applies `com.google.gms.google-services`.

### iOS

1. Place `GoogleService-Info.plist` in `ios/` via Xcode.
2. Run:
   - `bundle install`
   - `bundle exec pod install` (inside `ios/`)
3. In Firebase console configure APNs key/certificate for iOS push delivery.

## 4. Install and run

```bash
npm install
npm run start
```

In another terminal:

```bash
npm run android
```

or:

```bash
npm run ios
```

## 5. Push flow

1. App gets FCM token.
2. App loads website in WebView.
3. App injects JS and calls:
   - `window.DietOrganizer.registerMobilePushToken(token, platform, deviceId, appVersion)`
4. Backend stores token in `mobile_push_tokens`.
5. Cron sends reminders through FCM.

## Notes

- Android 13+ permission `POST_NOTIFICATIONS` is requested at runtime.
- Foreground messages are shown as an in-app alert.
- On notification tap the app navigates WebView to `data.url`.
