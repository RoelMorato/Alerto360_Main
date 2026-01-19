@echo off
echo ========================================
echo VERCEL DEPLOYMENT - ALERTO360
echo ========================================
echo.
echo This will deploy your API to Vercel automatically.
echo Make sure you've completed the Vercel login first!
echo.
pause

echo Deploying to Vercel...
echo.

echo Y | vercel --prod --confirm

echo.
echo ========================================
echo DEPLOYMENT COMPLETE!
echo ========================================
echo.
echo Your API should now be available at the URL shown above.
echo Copy that URL and update your Flutter app!
echo.
pause