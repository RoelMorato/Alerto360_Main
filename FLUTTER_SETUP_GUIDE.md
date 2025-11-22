# Flutter Installation Guide for Windows

This guide will help you install Flutter on your Windows system to develop the Alerto360 mobile app.

## Prerequisites

Before installing Flutter, you need:
- Windows 7 SP1 or later (64-bit)
- At least 400 MB of disk space
- Windows PowerShell 5.0 or newer
- Git for Windows

## Step 1: Download Flutter SDK

1. Go to the official Flutter website: https://flutter.dev/docs/get-started/install/windows
2. Click on "Download Flutter SDK" (latest stable version)
3. Download the `flutter_windows_x.x.x-stable.zip` file

## Step 2: Extract Flutter SDK

1. Create a folder called `C:\src\` (or any location you prefer)
2. Extract the downloaded ZIP file to `C:\src\flutter`
3. The path should be: `C:\src\flutter\bin\flutter.bat`

## Step 3: Update Environment Variables

### Option A: Using Windows Settings (Recommended)

1. Press `Win + R`, type `sysdm.cpl`, and press Enter
2. Go to "Advanced" tab → "Environment Variables"
3. Under "User variables", find "Path" and click "Edit"
4. Click "New" and add: `C:\src\flutter\bin`
5. Click OK on all windows

### Option B: Using Command Prompt

1. Open Command Prompt as Administrator
2. Run: `setx /M PATH "%PATH%;C:\src\flutter\bin"`

## Step 4: Verify Installation

1. Open a new Command Prompt (important!)
2. Run: `flutter --version`
3. You should see Flutter version information
4. Run: `flutter doctor`

## Step 5: Install Android Studio (for Android development)

1. Download Android Studio: https://developer.android.com/studio
2. Install Android Studio
3. During installation, make sure to install:
   - Android SDK
   - Android SDK Platform
   - Android Virtual Device

## Step 6: Configure Android Studio

1. Open Android Studio
2. Go to "SDK Manager" (Configure → SDK Manager)
3. Install SDK Platforms for Android API 30+ (Android 11.0+)
4. Install SDK Tools:
   - Android SDK Build-Tools
   - Android Emulator
   - Android SDK Platform-Tools

## Step 7: Accept Android Licenses

1. Open Command Prompt
2. Run: `flutter doctor --android-licenses`
3. Accept all licenses by typing `y` for each

## Step 8: Install VS Code Extensions (Optional but Recommended)

1. Open VS Code
2. Go to Extensions (Ctrl+Shift+X)
3. Install:
   - Flutter
   - Dart

## Step 9: Create Your First Flutter Project

1. Open Command Prompt
2. Navigate to your projects folder: `cd C:\xampp4\htdocs\Alerto360`
3. Run: `flutter create alerto360_mobile`
4. Navigate to project: `cd alerto360_mobile`
5. Run the app: `flutter run`

## Step 10: Test with Android Emulator

1. Open Android Studio
2. Go to AVD Manager (Configure → AVD Manager)
3. Create a new virtual device (Pixel 4 recommended)
4. Start the emulator
5. In your Flutter project terminal, run: `flutter run`

## Troubleshooting

### Common Issues:

1. **"flutter command not found"**
   - Make sure you added Flutter to PATH correctly
   - Restart Command Prompt/Terminal
   - Try using full path: `C:\src\flutter\bin\flutter.bat`

2. **Android license issues**
   - Run: `flutter doctor --android-licenses`
   - Accept all licenses

3. **SDK location issues**
   - Run: `flutter config --android-sdk "C:\Users\[USERNAME]\AppData\Local\Android\Sdk"`

4. **Emulator not starting**
   - Make sure virtualization is enabled in BIOS
   - Try different emulator (Pixel 3a works well)

### Check Installation Status

Run `flutter doctor -v` to see detailed status of all components.

## Alternative: Flutter Installation Script

You can also use this PowerShell script to automate the installation:

```powershell
# Download Flutter
Invoke-WebRequest -Uri "https://storage.googleapis.com/flutter_infra_release/releases/stable/windows/flutter_windows_3.13.6-stable.zip" -OutFile "flutter.zip"

# Extract to C:\src
New-Item -ItemType Directory -Path "C:\src" -Force
Expand-Archive -Path "flutter.zip" -DestinationPath "C:\src"

# Add to PATH
$env:Path += ";C:\src\flutter\bin"
[Environment]::SetEnvironmentVariable("Path", $env:Path, [EnvironmentVariableTarget]::Machine)
```

## Next Steps

Once Flutter is installed:
1. Copy the API service code from `flutter_example.dart`
2. Add dependencies to `pubspec.yaml`
3. Configure Android permissions
4. Start building your Alerto360 mobile app!

## Resources

- Official Flutter Docs: https://flutter.dev/docs
- Flutter Cookbook: https://flutter.dev/docs/cookbook
- Dart Language Tour: https://dart.dev/guides/language/language-tour

## Need Help?

If you encounter issues:
1. Check `flutter doctor` output
2. Visit Flutter Discord or Stack Overflow
3. Refer to the troubleshooting section above