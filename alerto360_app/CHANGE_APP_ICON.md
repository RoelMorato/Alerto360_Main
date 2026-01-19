# How to Change Alerto360 App Icon

## Quick Steps:

### Step 1: Prepare Your Icon
- Your icon should be a square PNG image (at least 512x512 pixels)
- The purple shield icon you have is perfect!

### Step 2: Generate Icon Sizes
Go to one of these free tools:
- https://www.appicon.co/ (recommended)
- https://icon.kitchen/
- https://makeappicon.com/

Upload your icon and download the Android icons.

### Step 3: Replace Icon Files
Copy the generated icons to these folders:

```
alerto360_app/android/app/src/main/res/
├── mipmap-mdpi/ic_launcher.png      (48x48 px)
├── mipmap-hdpi/ic_launcher.png      (72x72 px)
├── mipmap-xhdpi/ic_launcher.png     (96x96 px)
├── mipmap-xxhdpi/ic_launcher.png    (144x144 px)
└── mipmap-xxxhdpi/ic_launcher.png   (192x192 px)
```

### Step 4: (Optional) Add Adaptive Icon for Android 8+
Create these additional files for modern Android:
- `mipmap-anydpi-v26/ic_launcher.xml`
- `ic_launcher_foreground.png` (in each mipmap folder)
- `ic_launcher_background.png` (in each mipmap folder)

### Step 5: Rebuild APK
Run in terminal:
```bash
cd alerto360_app
flutter clean
flutter build apk --release
```

### Alternative: Using Flutter Launcher Icons Package

1. Add to `pubspec.yaml`:
```yaml
dev_dependencies:
  flutter_launcher_icons: ^0.13.1

flutter_launcher_icons:
  android: true
  ios: true
  image_path: "assets/icon/app_icon.png"
```

2. Put your icon at `assets/icon/app_icon.png`

3. Run:
```bash
flutter pub get
dart run flutter_launcher_icons
```

## Your Icon Specs:
- Purple gradient circle (#7b7be0 to #a18cd1)
- White shield outline in center
- Matches Alerto360 branding perfectly!
