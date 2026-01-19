import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  // UPDATE THIS URL:
  // - For local WiFi: use your IP like 'http://192.168.5.10/Alerto360-main'
  // - For internet access: use ngrok or hosting URL like 'https://abc123.ngrok-free.app/Alerto360-main'
  // - For cloud hosting: use your domain like 'https://alerto360.infinityfreeapp.com'
  static const String baseUrl = 'https://alerto360.infinityfreeapp.com';

  static Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api_login.php'),
        body: {
          'email': email,
          'password': password,
        },
      );

      final data = jsonDecode(response.body);
      
      if (response.statusCode == 200) {
        if (data['success'] == true) {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setInt('user_id', data['user']['id']);
          await prefs.setString('user_name', data['user']['name']);
          await prefs.setString('user_email', data['user']['email']);
          await prefs.setString('user_role', data['user']['role']);
          
          // Update online status
          updateOnlineStatus(data['user']['id'], true);
          
          return data;
        } else {
          throw Exception(data['message'] ?? 'Login failed');
        }
      } else if (response.statusCode == 403 && data['requires_verification'] == true) {
        // Return the data with requires_verification flag instead of throwing
        return data;
      } else {
        throw Exception(data['message'] ?? 'Server error');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Connection error: Unable to reach server');
    }
  }

  static Future<Map<String, dynamic>> register(String name, String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api_register.php'),
        body: {
          'name': name,
          'email': email,
          'password': password,
          'role': 'citizen',
        },
      );

      if (response.statusCode == 200 || response.statusCode == 201) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          return data;
        } else {
          throw Exception(data['message'] ?? 'Registration failed');
        }
      } else {
        final data = jsonDecode(response.body);
        throw Exception(data['message'] ?? 'Server error');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Connection error: Unable to reach server');
    }
  }

  static Future<Map<String, dynamic>> verifyEmail(String email, String code) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api_verify_email.php'),
        body: {
          'email': email,
          'code': code,
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data;
      } else {
        final data = jsonDecode(response.body);
        throw Exception(data['message'] ?? 'Verification failed');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Connection error: Unable to reach server');
    }
  }

  static Future<Map<String, dynamic>> resendVerificationCode(String email) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api_resend_verification.php'),
        body: {
          'email': email,
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data;
      } else {
        final data = jsonDecode(response.body);
        throw Exception(data['message'] ?? 'Failed to resend code');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Connection error: Unable to reach server');
    }
  }

  static Future<List<dynamic>> getUserIncidents() async {
    final prefs = await SharedPreferences.getInstance();
    final userId = prefs.getInt('user_id');

    final response = await http.get(
      Uri.parse('$baseUrl/api.php?action=get_user_incidents&user_id=$userId'),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['incidents'] ?? [];
    } else {
      throw Exception('Failed to load incidents');
    }
  }

  static Future<Map<String, dynamic>> analyzeImage(dynamic imageFile) async {
    try {
      print('AI Detection: Starting image analysis...');
      
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/api_analyze_image.php'),
      );

      // Add image file
      String imagePath;
      if (imageFile is String) {
        imagePath = imageFile;
      } else {
        imagePath = imageFile.path;
      }
      
      print('AI Detection: Image path: $imagePath');

      var file = await http.MultipartFile.fromPath('image', imagePath);
      request.files.add(file);

      print('AI Detection: Sending request to $baseUrl/api_analyze_image.php');
      
      // Send request with timeout - increased for AI processing
      var streamedResponse = await request.send().timeout(
        const Duration(seconds: 60),
        onTimeout: () {
          throw Exception('AI analysis timeout. Please try again or select manually.');
        },
      );
      var response = await http.Response.fromStream(streamedResponse);

      print('AI Detection: Response status: ${response.statusCode}');
      print('AI Detection: Response body: ${response.body}');

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        print('AI Detection: Parsed data: $data');
        return data;
      } else {
        print('AI Detection: Server error ${response.statusCode}');
        throw Exception('Server error: ${response.statusCode}');
      }
    } catch (e) {
      print('AI Detection ERROR: $e');
      if (e is Exception) rethrow;
      throw Exception('Error analyzing image: $e');
    }
  }

  static Future<Map<String, dynamic>> reportIncident({
    required String type,
    required String description,
    double? latitude,
    double? longitude,
    String? imagePath,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('user_id');

      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/api_upload_incident.php'),
      );

      // Add form fields
      request.fields['user_id'] = userId.toString();
      request.fields['type'] = type;
      request.fields['description'] = description;
      if (latitude != null) request.fields['latitude'] = latitude.toString();
      if (longitude != null) request.fields['longitude'] = longitude.toString();
      
      // Add accurate device tracking info
      String deviceType = 'Mobile';
      String osName = Platform.operatingSystem; // android, ios, windows, macos, linux
      String osVersion = Platform.operatingSystemVersion;
      
      // Determine device type based on platform
      if (Platform.isAndroid || Platform.isIOS) {
        deviceType = 'Mobile';
      } else if (Platform.isWindows || Platform.isMacOS || Platform.isLinux) {
        deviceType = 'Desktop';
      }
      
      String deviceInfo = 'Alerto360 App | $osName $osVersion';
      
      request.fields['device_type'] = deviceType;
      request.fields['device_info'] = deviceInfo;

      // Add image file if provided
      if (imagePath != null && imagePath.isNotEmpty) {
        var file = await http.MultipartFile.fromPath('image', imagePath);
        request.files.add(file);
      }

      // Send request
      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          return data;
        } else {
          throw Exception(data['message'] ?? 'Failed to report incident');
        }
      } else {
        throw Exception('Server error: ${response.statusCode}');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Error reporting incident: $e');
    }
  }

  // Update online status
  static Future<void> updateOnlineStatus(int userId, bool isOnline) async {
    try {
      await http.post(
        Uri.parse('$baseUrl/api_update_status.php'),
        body: {
          'user_id': userId.toString(),
          'is_online': isOnline ? '1' : '0',
          'device_info': 'Flutter Mobile App',
        },
      );
    } catch (e) {
      // Silently fail
    }
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    final userId = prefs.getInt('user_id');
    
    // Set offline before logout
    if (userId != null) {
      await updateOnlineStatus(userId, false);
    }
    
    await prefs.clear();
  }

  static Future<bool> isLoggedIn() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.containsKey('user_id');
  }

  // Forgot Password - Send reset code
  static Future<Map<String, dynamic>> forgotPassword(String email) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api_forgot_password.php'),
        body: {'email': email},
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final data = jsonDecode(response.body);
        throw Exception(data['message'] ?? 'Failed to send reset code');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Connection error: Unable to reach server');
    }
  }

  // Verify reset code
  static Future<Map<String, dynamic>> verifyResetCode(String email, String code) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api_verify_reset_code.php'),
        body: {
          'email': email,
          'code': code,
        },
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final data = jsonDecode(response.body);
        throw Exception(data['message'] ?? 'Invalid code');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Connection error: Unable to reach server');
    }
  }

  // Reset password with email and code
  static Future<Map<String, dynamic>> resetPassword(String email, String code, String newPassword) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api_reset_password.php'),
        body: {
          'email': email,
          'code': code,
          'password': newPassword,
        },
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final data = jsonDecode(response.body);
        throw Exception(data['message'] ?? 'Failed to reset password');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Connection error: Unable to reach server');
    }
  }
}
