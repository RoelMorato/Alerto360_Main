@echo off
echo Downloading ngrok...
echo.
echo Please follow these steps:
echo 1. Go to: https://ngrok.com/download
echo 2. Click "Windows (64-bit)"
echo 3. Download the zip file
echo 4. Extract ngrok.exe to C:\ngrok\
echo 5. Run this script again to continue setup
echo.
pause

if exist "C:\ngrok\ngrok.exe" (
    echo ngrok found! Setting up authtoken...
    C:\ngrok\ngrok.exe authtoken 36kGAQfpUlNGDuptedziARhZBns_5sm9PWcV2F4ZaoLz2egKo
    echo.
    echo Authtoken setup complete!
    echo Now you can start ngrok with: C:\ngrok\ngrok.exe http 80
    echo.
    pause
) else (
    echo ngrok.exe not found in C:\ngrok\
    echo Please download and extract ngrok first.
    pause
)