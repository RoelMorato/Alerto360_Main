# App Icon Setup

## Instructions:

1. Save your purple shield icon as `app_icon.png` in this folder
   - Size: 1024x1024 pixels (recommended)
   - Format: PNG with transparency

2. Also save a foreground-only version as `app_icon_foreground.png`
   - This should be just the shield icon without the circle background
   - The background color (#7b7be0) will be added automatically

3. Run these commands in terminal:
   ```bash
   cd alerto360_app
   flutter pub get
   dart run flutter_launcher_icons
   ```

4. Rebuild your APK:
   ```bash
   flutter build apk --release
   ```

## File Checklist:
- [ ] app_icon.png (1024x1024, full icon with purple background)
- [ ] app_icon_foreground.png (1024x1024, just the shield, transparent background)
