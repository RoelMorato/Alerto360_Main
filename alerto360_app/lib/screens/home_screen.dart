import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'dart:io';
import 'dart:async';
import '../services/api_service.dart';
import 'login_screen.dart';
import 'incident_history_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  String _selectedType = '';
  String _selectedResponderType = '';
  final TextEditingController _descriptionController = TextEditingController();
  File? _selectedImage;
  double? _latitude;
  double? _longitude;
  bool _isSubmitting = false;
  final MapController _mapController = MapController();
  LatLng _currentLocation = const LatLng(6.6833, 125.3167); // Default Kidapawan
  Timer? _onlineStatusTimer;

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
    _startOnlineStatusUpdates();
  }

  @override
  void dispose() {
    _onlineStatusTimer?.cancel();
    _descriptionController.dispose();
    super.dispose();
  }

  // Update online status periodically
  Future<void> _startOnlineStatusUpdates() async {
    // Update immediately on start
    await _updateOnlineStatus();
    
    // Then update every 2 minutes
    _onlineStatusTimer = Timer.periodic(const Duration(minutes: 2), (timer) {
      _updateOnlineStatus();
    });
  }

  Future<void> _updateOnlineStatus() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('user_id');
      if (userId != null) {
        await ApiService.updateOnlineStatus(userId, true);
      }
    } catch (e) {
      // Silently fail
    }
  }

  Future<void> _getCurrentLocation() async {
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.whileInUse || permission == LocationPermission.always) {
        Position position = await Geolocator.getCurrentPosition();
        if (mounted) {
          setState(() {
            _latitude = position.latitude;
            _longitude = position.longitude;
            _currentLocation = LatLng(position.latitude, position.longitude);
          });
          _mapController.move(_currentLocation, 15);
        }
      }
    } catch (e) {
      // Error getting location
    }
  }

  Future<void> _refreshForm() async {
    // Reset form
    setState(() {
      _selectedType = '';
      _selectedResponderType = '';
      _descriptionController.clear();
      _selectedImage = null;
    });
    
    // Refresh location
    await _getCurrentLocation();
    
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Form refreshed'),
          duration: Duration(seconds: 1),
          backgroundColor: Colors.green,
        ),
      );
    }
  }

  Future<void> _takePhoto() async {
    final ImagePicker picker = ImagePicker();
    final XFile? photo = await picker.pickImage(
      source: ImageSource.camera,
      maxWidth: 1280,
      maxHeight: 720,
      imageQuality: 80,
    );

    if (photo != null && mounted) {
      setState(() {
        _selectedImage = File(photo.path);
      });
      
      // Ask if user wants AI analysis
      final wantAI = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Row(
            children: [
              Icon(Icons.auto_awesome, color: Color(0xFF667eea)),
              SizedBox(width: 8),
              Text('AI Detection'),
            ],
          ),
          content: const Text('Do you want AI to detect the emergency type from this photo?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('No, I\'ll select manually'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF667eea),
              ),
              child: const Text('Yes, use AI'),
            ),
          ],
        ),
      );
      
      if (wantAI == true && mounted) {
        // Analyze image with AI
        _analyzeImage(File(photo.path));
      }
    }
  }

  Future<void> _pickImage() async {
    final ImagePicker picker = ImagePicker();
    final XFile? image = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1280,
      maxHeight: 720,
      imageQuality: 80,
    );

    if (image != null && mounted) {
      setState(() {
        _selectedImage = File(image.path);
      });
      
      // Ask if user wants AI analysis
      final wantAI = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Row(
            children: [
              Icon(Icons.auto_awesome, color: Color(0xFF667eea)),
              SizedBox(width: 8),
              Text('AI Detection'),
            ],
          ),
          content: const Text('Do you want AI to detect the emergency type from this photo?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('No, I\'ll select manually'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF667eea),
              ),
              child: const Text('Yes, use AI'),
            ),
          ],
        ),
      );
      
      if (wantAI == true && mounted) {
        // Analyze image with AI
        _analyzeImage(File(image.path));
      }
    }
  }

  Future<void> _analyzeImage(File imageFile) async {
    // Show analyzing dialog
    if (!mounted) return;
    
    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (context) => AlertDialog(
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(),
            const SizedBox(height: 16),
            const Text('Analyzing image with AI...'),
            const SizedBox(height: 8),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancel'),
            ),
          ],
        ),
      ),
    );

    try {
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('${ApiService.baseUrl}/api_analyze_image.php'),
      );

      var file = await http.MultipartFile.fromPath('image', imageFile.path);
      request.files.add(file);

      var streamedResponse = await request.send().timeout(
        const Duration(seconds: 10),
        onTimeout: () {
          throw Exception('AI analysis timeout. Please try again or select manually.');
        },
      );
      var response = await http.Response.fromStream(streamedResponse);

      if (mounted) {
        Navigator.pop(context); // Close analyzing dialog

        if (response.statusCode == 200) {
          final data = jsonDecode(response.body);
          if (data['success'] == true) {
            final detectedType = data['detected_type'];
            final description = data['description'];
            final suggestions = data['suggestions'] as List;

            // Show detection result
            final confirmed = await showDialog<bool>(
              context: context,
              builder: (context) => AlertDialog(
                title: Row(
                  children: [
                    const Icon(Icons.auto_awesome, color: Color(0xFF667eea)),
                    const SizedBox(width: 8),
                    const Text('AI Detection'),
                  ],
                ),
                content: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF667eea).withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.check_circle, color: Color(0xFF667eea), size: 20),
                              const SizedBox(width: 8),
                              Text(
                                'Detected: $detectedType',
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 16,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(description, style: const TextStyle(fontSize: 14)),
                    if (suggestions.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      const Text(
                        'Safety Tips:',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 4),
                      ...suggestions.map((tip) => Padding(
                        padding: const EdgeInsets.only(left: 8, top: 4),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('• ', style: TextStyle(fontSize: 14)),
                            Expanded(child: Text(tip, style: const TextStyle(fontSize: 12))),
                          ],
                        ),
                      )),
                    ],
                    const SizedBox(height: 16),
                    const Text(
                      'Use this emergency type?',
                      style: TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(context, false),
                    child: const Text('No, I\'ll select manually'),
                  ),
                  ElevatedButton(
                    onPressed: () => Navigator.pop(context, true),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF667eea),
                    ),
                    child: const Text('Yes, use this'),
                  ),
                ],
              ),
            );

            if (confirmed == true && mounted) {
              // Auto-fill emergency type
              setState(() {
                _selectedType = detectedType;
                
                // Auto-fill responder type based on emergency
                switch (detectedType.toLowerCase()) {
                  case 'fire':
                    _selectedResponderType = 'fire';
                    break;
                  case 'crime':
                    _selectedResponderType = 'police';
                    break;
                  case 'flood':
                  case 'landslide':
                  case 'accident':
                    _selectedResponderType = 'medical';
                    break;
                  default:
                    _selectedResponderType = 'medical';
                }
              });
              
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('✓ Emergency: $detectedType\n✓ Responder: ${_getResponderName(_selectedResponderType)} auto-assigned'),
                  backgroundColor: Colors.green,
                  duration: const Duration(seconds: 3),
                ),
              );
            }
          }
        }
      }
    } catch (e) {
      if (mounted) {
        Navigator.pop(context); // Close analyzing dialog
        
        // Show error with option to continue
        showDialog(
          context: context,
          builder: (context) => AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.warning, color: Colors.orange),
                SizedBox(width: 8),
                Text('AI Analysis Failed'),
              ],
            ),
            content: Text(
              'Could not analyze image automatically.\n\n'
              'Error: ${e.toString()}\n\n'
              'Please select emergency type manually.',
            ),
            actions: [
              ElevatedButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('OK'),
              ),
            ],
          ),
        );
      }
    }
  }



  Future<void> _submitIncident() async {
    if (_selectedType.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select an emergency type')),
      );
      return;
    }

    if (_latitude == null || _longitude == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please set your location on the map')),
      );
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    try {
      final result = await ApiService.reportIncident(
        type: _selectedType,
        description: _descriptionController.text,
        latitude: _latitude,
        longitude: _longitude,
        imagePath: _selectedImage?.path,
      );

      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });

        if (result['success'] == true) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Incident reported successfully! ID: ${result['incident_id']}'),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 3),
            ),
          );

          // Reset form
          setState(() {
            _selectedType = '';
            _selectedResponderType = '';
            _descriptionController.clear();
            _selectedImage = null;
          });
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(result['message'] ?? 'Failed to report incident')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  Future<void> _logout() async {
    await ApiService.logout();
    if (mounted) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (context) => const LoginScreen()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF7b7be0), Color(0xFFa18cd1)],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              _buildHeader(),
              Expanded(
                child: RefreshIndicator(
                  onRefresh: _refreshForm,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    child: Container(
                      margin: const EdgeInsets.only(top: 0),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.only(
                          topLeft: Radius.circular(30),
                          topRight: Radius.circular(30),
                        ),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _buildEmergencyTypeSection(),
                            const SizedBox(height: 20),
                            _buildResponderTypeSection(),
                            const SizedBox(height: 20),
                            _buildLocationSection(),
                            const SizedBox(height: 20),
                            _buildPhotoSection(),
                            const SizedBox(height: 20),
                            _buildDescriptionSection(),
                            const SizedBox(height: 24),
                            _buildSubmitButton(),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.all(20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: FutureBuilder<String>(
              future: SharedPreferences.getInstance().then((prefs) => prefs.getString('name') ?? 'User'),
              builder: (context, snapshot) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.shield, color: Colors.white, size: 24),
                        SizedBox(width: 8),
                        Text(
                          'Alerto360',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'Emergency Response System',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Welcome, ${snapshot.data ?? 'User'}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
          Row(
            children: [
              IconButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => const IncidentHistoryScreen()),
                  );
                },
                icon: const Icon(Icons.history, color: Colors.white),
                tooltip: 'History',
              ),
              const SizedBox(width: 4),
              Material(
                color: Colors.white.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(20),
                child: InkWell(
                  onTap: _logout,
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.logout, color: Colors.white, size: 18),
                        SizedBox(width: 6),
                        Text(
                          'Logout',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildEmergencyTypeSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.warning_amber, color: Color(0xFF667eea)),
            SizedBox(width: 8),
            Text(
              'Emergency Type',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Color(0xFF667eea),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            // Responsive grid: 2 columns for small screens, 3 for larger
            int crossAxisCount = constraints.maxWidth < 360 ? 2 : 3;
            return GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: crossAxisCount,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.0,
              children: [
                _buildEmergencyTypeCard('Fire', Icons.local_fire_department, Colors.red),
                _buildEmergencyTypeCard('Crime', Icons.shield, Colors.purple),
                _buildEmergencyTypeCard('Flood', Icons.water, Colors.blue),
                _buildEmergencyTypeCard('Landslide', Icons.landscape, Colors.brown),
                _buildEmergencyTypeCard('Accident', Icons.car_crash, Colors.orange),
                _buildEmergencyTypeCard('Other', Icons.add_circle, Colors.grey),
              ],
            );
          },
        ),
      ],
    );
  }

  Widget _buildEmergencyTypeCard(String type, IconData icon, Color color) {
    final isSelected = _selectedType == type;
    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedType = type;
        });
      },
      child: Container(
        decoration: BoxDecoration(
          color: isSelected ? color.withValues(alpha: 0.1) : Colors.white,
          border: Border.all(
            color: isSelected ? color : Colors.grey.shade300,
            width: isSelected ? 2 : 1,
          ),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 32),
            const SizedBox(height: 4),
            Text(
              type,
              style: TextStyle(
                fontSize: 12,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                color: isSelected ? color : Colors.black87,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildResponderTypeSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.people, color: Color(0xFF667eea)),
            SizedBox(width: 8),
            Text(
              'Response Team',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Color(0xFF667eea),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        DropdownButtonFormField<String>(
          value: _selectedResponderType.isEmpty ? null : _selectedResponderType,
          decoration: InputDecoration(
            hintText: 'Auto-assign based on emergency type',
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          ),
          items: const [
            DropdownMenuItem(value: 'fire', child: Text('🔥 Fire Department')),
            DropdownMenuItem(value: 'police', child: Text('👮 Police')),
            DropdownMenuItem(value: 'medical', child: Text('🚑 Medical/Disaster Response')),
          ],
          onChanged: (value) {
            setState(() {
              _selectedResponderType = value ?? '';
            });
          },
        ),
      ],
    );
  }

  Widget _buildLocationSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.location_on, color: Color(0xFF667eea)),
            SizedBox(width: 8),
            Text(
              'Location',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Color(0xFF667eea),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Container(
          height: 200,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.grey.shade300),
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: FlutterMap(
              mapController: _mapController,
              options: MapOptions(
                initialCenter: _currentLocation,
                initialZoom: 15,
                onTap: (tapPosition, point) {
                  setState(() {
                    _latitude = point.latitude;
                    _longitude = point.longitude;
                    _currentLocation = point;
                  });
                },
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.example.alerto360_app',
                ),
                if (_latitude != null && _longitude != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: LatLng(_latitude!, _longitude!),
                        width: 40,
                        height: 40,
                        child: const Icon(
                          Icons.location_on,
                          color: Colors.red,
                          size: 40,
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.blue.shade50,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              const Icon(Icons.info_outline, color: Colors.blue, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  _latitude != null && _longitude != null
                      ? 'Location: ${_latitude!.toStringAsFixed(4)}, ${_longitude!.toStringAsFixed(4)}'
                      : 'Tap on map to set location',
                  style: const TextStyle(fontSize: 12, color: Colors.blue),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildPhotoSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.camera_alt, color: Color(0xFF667eea)),
            SizedBox(width: 8),
            Text(
              'Photo Evidence',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Color(0xFF667eea),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        if (_selectedImage != null)
          Stack(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Image.file(
                  _selectedImage!,
                  height: 200,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
              ),
              Positioned(
                top: 8,
                right: 8,
                child: IconButton(
                  onPressed: () {
                    setState(() {
                      _selectedImage = null;
                    });
                  },
                  icon: const Icon(Icons.close),
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.red,
                    foregroundColor: Colors.white,
                  ),
                ),
              ),
            ],
          )
        else
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _takePhoto,
                  icon: const Icon(Icons.camera_alt),
                  label: const Text('Take Photo'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF667eea),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _pickImage,
                  icon: const Icon(Icons.photo_library),
                  label: const Text('Gallery'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF667eea),
                    side: const BorderSide(color: Color(0xFF667eea)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
            ],
          ),
      ],
    );
  }

  Widget _buildDescriptionSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.description, color: Color(0xFF667eea)),
            SizedBox(width: 8),
            Text(
              'Description',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Color(0xFF667eea),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _descriptionController,
          maxLines: 3,
          decoration: InputDecoration(
            hintText: 'Describe what happened...',
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            contentPadding: const EdgeInsets.all(16),
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Optional but recommended for faster response',
          style: TextStyle(fontSize: 11, color: Colors.grey),
        ),
      ],
    );
  }

  Widget _buildSubmitButton() {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: _isSubmitting ? null : _submitIncident,
        style: ElevatedButton.styleFrom(
          backgroundColor: Colors.green,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          disabledBackgroundColor: Colors.grey,
        ),
        child: _isSubmitting
            ? const SizedBox(
                height: 20,
                width: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                ),
              )
            : const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.send),
                  SizedBox(width: 8),
                  Text(
                    'Submit Emergency Report',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
      ),
    );
  }

  String _getResponderName(String type) {
    switch (type) {
      case 'fire':
        return 'Fire Department';
      case 'police':
        return 'Police';
      case 'medical':
        return 'Medical/Disaster Response';
      default:
        return 'Auto-assign';
    }
  }
}
