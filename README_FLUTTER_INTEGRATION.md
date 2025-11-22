# Flutter Integration Guide for Alerto360

This guide explains how to connect your Flutter mobile app to the Alerto360 PHP backend.

## Backend API Setup

Your PHP backend now includes a REST API (`api.php`) that provides endpoints for:

- User authentication (login/register)
- Incident reporting and management
- Responder actions
- Admin functions

### API Endpoints

#### Authentication
- `POST /api.php/login` - User login
- `POST /api.php/register` - User registration

#### Incidents
- `GET /api.php/incidents` - Get incidents (filtered by user role)
- `POST /api.php/incidents` - Report new incident
- `GET /api.php/incidents/{id}` - Get specific incident
- `PUT /api.php/incidents/{id}` - Update incident status

#### Admin
- `GET /api.php/responders` - Get all responders

## Flutter Setup

### 1. Create Flutter Project

```bash
flutter create alerto360_mobile
cd alerto360_mobile
```

### 2. Add Dependencies

Add these to your `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter
  http: ^1.1.0
  shared_preferences: ^2.2.2
  provider: ^6.0.5
  image_picker: ^1.0.4  # For camera/gallery
  geolocator: ^10.1.0   # For location
```

### 3. Configure API Base URL

Update the base URL in your API service to match your server:

```dart
static const String baseUrl = 'http://YOUR_SERVER_IP/Alerto360/api.php';
```

For local development with Android emulator, use:
```dart
static const String baseUrl = 'http://10.0.2.2/Alerto360/api.php';
```

### 4. Android Permissions

Add to `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
```

### 5. iOS Permissions

Add to `ios/Runner/Info.plist`:

```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>This app needs location access to report incidents</string>
<key>NSCameraUsageDescription</key>
<string>This app needs camera access to take incident photos</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>This app needs photo library access to select incident photos</string>
```

## API Usage Examples

### Login
```dart
final result = await ApiService.login('user@example.com', 'password');
final token = result['token'];
final user = result['user'];
```

### Report Incident
```dart
final result = await ApiService.reportIncident(
  type: 'Fire',
  description: 'Building on fire',
  latitude: 14.5995,
  longitude: 120.9842,
  imagePath: '/uploads/incident_123.jpg'
);
```

### Get Incidents
```dart
final incidents = await ApiService.getIncidents();
// Returns list of incident objects
```

### Update Incident Status (Responder)
```dart
await ApiService.updateIncidentStatus(incidentId, 'accepted');
```

## Security Notes

1. **HTTPS**: Use HTTPS in production for secure communication
2. **Token Storage**: The example uses SharedPreferences, but consider more secure storage
3. **Input Validation**: Always validate user inputs on both client and server
4. **Error Handling**: Implement proper error handling for network failures

## Testing the API

You can test the API endpoints using tools like Postman or curl:

```bash
# Login
curl -X POST http://localhost/Alerto360/api.php/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Get incidents (include token)
curl -X GET http://localhost/Alerto360/api.php/incidents \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## File Upload for Images

For image uploads, you'll need to modify the incident reporting to handle multipart form data. The current API expects image_path as a string (URL to uploaded image).

Consider implementing a separate file upload endpoint if you want to upload images directly from the mobile app.

## Next Steps

1. Set up your Flutter development environment
2. Create the basic app structure (login, home, incident reporting screens)
3. Implement the API service class
4. Add state management with Provider
5. Implement camera and location features
6. Test with your PHP backend

The `flutter_example.dart` file contains sample code to get you started.