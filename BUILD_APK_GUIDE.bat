@echo off
echo ========================================
echo ALERTO360 APK BUILD GUIDE
echo ========================================
echo.
echo This will build the APK for InfinityFree hosting
echo URL: https://alerto360.infinityfreeapp.com
echo.
echo ========================================
echo STEP 1: Navigate to Flutter app folder
echo ========================================
cd alerto360_app
echo Current directory: %CD%
echo.

echo ========================================
echo STEP 2: Clean previous build
echo ========================================
call flutter clean
echo.

echo ========================================
echo STEP 3: Get dependencies
echo ========================================
call flutter pub get
echo.

echo ========================================
echo STEP 4: Build APK (Release mode)
echo ========================================
echo This will take 5-10 minutes...
echo.
call flutter build apk --release
echo.

echo ========================================
echo BUILD COMPLETE!
echo ========================================
echo.
echo APK Location:
echo %CD%\build\app\outputs\flutter-apk\app-release.apk
echo.
echo File size: 
dir build\app\outputs\flutter-apk\app-release.apk | find "app-release.apk"
echo.
echo ========================================
echo NEXT STEPS:
echo ========================================
echo 1. Transfer APK to your phone
echo 2. Install APK on phone
echo 3. Open app and test login
echo 4. Test all features
echo.
echo ========================================
pause
