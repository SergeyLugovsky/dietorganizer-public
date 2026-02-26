<!-- README.md -->
# diet_organizer

Diet organizer and calorie calculator.

## Local setup (OSPanel)
1. Copy `private/.env.php.example` to `private/.env.php` and update DB credentials.
2. Import `sql/schema.sql` into MySQL.
3. In OSPanel, create the domain `dietorganizer` with document root `public_html`.
4. Open `http://dietorganizer/` in the browser.

## Project layout
- `public_html/` - web root with `index.php`, `.htaccess`, `css/`, `includes/`, `pages/`, `storage/`, `uploads/`.
- `private/` - private config files.
- `sql/` - database schema.
- `mobile-app/` - React Native WebView + FCM mobile app.

## Database migrations
- Apply `sql/migrations/20260109_add_meal_reminders.sql` for reminder and push tables/columns.
- Apply `sql/migrations/20260222_add_mobile_push_tokens.sql` for mobile push tokens.

## Push notifications (Web + Mobile)
1. Generate VAPID keys:
   `php scripts/generate_vapid_keys.php`
2. Add keys to `private/.env.php`:
   ```php
   'vapid' => [
       'public_key' => 'YOUR_PUBLIC_KEY',
       'private_key' => 'YOUR_PRIVATE_KEY',
       'subject' => 'mailto:admin@example.com',
   ],
   ```
3. Configure mobile push (FCM) for React Native app:
   ```php
   'mobile_push' => [
       'enabled' => true,
       'provider' => 'fcm',
       'project_id' => 'your-firebase-project-id',
       'service_account_json' => APP_PRIVATE . '/firebase-service-account.json',
   ],
   ```
4. Register/unregister mobile tokens from WebView:
   - `POST /api/push/mobile_register.php`
   - `POST /api/push/mobile_unregister.php`
5. From WebView JS, call bridge helpers:
   - `window.DietOrganizer.registerMobilePushToken(token, 'android', deviceId, appVersion)`
   - `window.DietOrganizer.unregisterMobilePushToken(token, deviceId, 'android')`
6. Legacy browser Web Push remains available via `/profile` as fallback.

## Meal reminders
1. Open `/meal_categories`.
2. Enable reminder, set time and days.

## Cron
Run every minute:
```
* * * * * php /path/to/dietorganizer/private/cron/send_meal_reminders.php
```

## Before making repository public
1. Rotate credentials that were ever committed (DB password, VAPID private key, Firebase keys/service account).
2. Verify local secret files are ignored:
   - `private/.env.php`
   - `private/firebase-service-account.json`
   - `mobile-app/android/app/google-services.json`
3. Ensure `public_html/storage/logs/*.log` is not tracked.
4. Run a quick scan:
   - `rg -n --hidden -S "(API_KEY|SECRET|TOKEN|PASSWORD|PRIVATE_KEY|BEGIN PRIVATE|ghp_)" .`
