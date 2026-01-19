import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:image_picker/image_picker.dart';
import '../services/api_service.dart';

class ReportIncidentScreen extends StatefulWidget {
  const ReportIncidentScreen({super.key});

  @override
  State<ReportIncidentScreen> createState() => _ReportIncidentScreenState();
}

class _ReportIncidentScreenState extends State<ReportIncidentScreen> {
  final _descriptionController = TextEditingController();
  String? _selectedType;
  bool _isLoading = false;
  bool _isAnalyzing = false;
  String _errorMessage = '';
  String _userName = 'User';
  bool _locationDetected = false;
  File? _selectedImage;
  final ImagePicker _picker = ImagePicker();
  bool _autoFilled = false;

  final List<Map<String, dynamic>> _incidentTypes = [
    {'name': 'Fire', 'icon': Icons.local_fire_department, 'color': Colors.red},
    {'name': 'Crime', 'icon': Icons.person, 'color': Colors.purple},
    {'name': 'Flood', 'icon': Icons.water, 'color': Colors.blue},
    {'name': 'Landslide', 'icon': Icons.landscape, 'color': Colors.brown},
    {'name': 'Accident', 'icon': Icons.car_crash, 'color': Colors.orange},
    {'name': 'Other', 'icon': Icons.more_horiz, 'color': Colors.grey},
  ];

  @override
  void initState() {
    super.initState();
    _loadUserData();
  }

  Future<void> _loadUserData() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _userName = prefs.getString('user_name') ?? 'User';
    });
  }

  Future<void> _submitReport() async {
    if (_selectedType == null) {
      setState(() {
        _errorMessage = 'Please select emergency type';
      });
      return;
    }

    if (_descriptionController.text.isEmpty) {
      setState(() {
        _errorMessage = 'Please provide a description';
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final result = await ApiService.reportIncident(
        type: _selectedType!,
        description: _descriptionController.text.trim(),
        imagePath: _selectedImage?.path,
      );

      if (mounted) {
        String message = 'Incident reported successfully!';
        
        // Show auto-assignment info if available
        if (result['auto_assigned'] == true) {
          message += '\n\nAutomatically assigned to: ${result['assigned_to']}';
        }
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(message),
            backgroundColor: Colors.green,
            duration: const Duration(seconds: 4),
          ),
        );
        Navigator.of(context).pop(true);
      }
    } catch (e) {
      setState(() {
        _errorMessage = e.toString().replaceAll('Exception: ', '');
        _isLoading = false;
      });
    }
  }

  void _detectLocation() {
    setState(() {
      _locationDetected = true;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Location detected'),
        duration: Duration(seconds: 2),
      ),
    );
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? image = await _picker.pickImage(
        source: source,
        maxWidth: 800,
        maxHeight: 600,
        imageQuality: 70,
      );

      if (image != null) {
        setState(() {
          _selectedImage = File(image.path);
          _isAnalyzing = true;
          _errorMessage = '';
        });

        // Automatically analyze the image
        await _analyzeImage();
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Failed to pick image: $e';
      });
    }
  }

  Future<void> _analyzeImage() async {
    if (_selectedImage == null) return;

    try {
      setState(() {
        _isAnalyzing = true;
        _errorMessage = '';
      });

      // Add timeout for response (increased to 15 seconds for slower connections)
      final result = await ApiService.analyzeImage(_selectedImage!).timeout(
        const Duration(seconds: 15),
        onTimeout: () {
          throw Exception('Analysis timeout - please select type manually');
        },
      );

      if (result['success'] == true) {
        // Auto-fill the form with AI results
        final detectedType = result['detected_type'];
        final aiDescription = result['description'] ?? '';
        
        setState(() {
          // IMPORTANT: Set the detected type from AI
          _selectedType = detectedType;
          _descriptionController.text = aiDescription;
          _autoFilled = true;
          _isAnalyzing = false;
        });

        // Show success message with suggestions
        if (mounted) {
          final suggestions = result['suggestions'] as List<dynamic>?;
          final suggestionText = suggestions?.join('\n• ') ?? '';
          
          showDialog(
            context: context,
            builder: (context) => AlertDialog(
              title: Row(
                children: [
                  const Icon(Icons.check_circle, color: Colors.green),
                  const SizedBox(width: 8),
                  const Text('AI Analysis Complete'),
                ],
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Detected: ${result['detected_type']}',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  if (suggestionText.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    const Text(
                      'Safety Tips:',
                      style: TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    Text('• $suggestionText'),
                  ],
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.blue.shade50,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text(
                      'Form has been auto-filled. Review and click Submit to report.',
                      style: TextStyle(fontSize: 13),
                    ),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('OK'),
                ),
              ],
            ),
          );
        }
      } else {
        setState(() {
          _isAnalyzing = false;
          _errorMessage = result['message'] ?? 'AI analysis failed';
        });
      }
    } catch (e) {
      print('AI Analysis Error: $e');
      setState(() {
        _isAnalyzing = false;
        // Don't show error - just let user select manually
        // The image is still uploaded, just AI detection failed
      });
      
      // Show a simple snackbar instead of error message
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('AI detection unavailable. Please select emergency type manually.'),
            backgroundColor: Colors.orange,
            duration: Duration(seconds: 3),
          ),
        );
      }
    }
  }

  Future<void> _logout() async {
    await ApiService.logout();
    if (mounted) {
      Navigator.of(context).pushNamedAndRemoveUntil('/login', (route) => false);
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
              // Header
              Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.shield, color: Colors.white, size: 24),
                            const SizedBox(width: 8),
                            const Text(
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
                        Text(
                          'Welcome, $_userName',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                    ElevatedButton.icon(
                      onPressed: _logout,
                      icon: const Icon(Icons.logout, size: 16),
                      label: const Text('Logout'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.white.withAlpha(51),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(20),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              // Main Content
              Expanded(
                child: Container(
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.only(
                      topLeft: Radius.circular(30),
                      topRight: Radius.circular(30),
                    ),
                  ),
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Emergency Type Section
                        Row(
                          children: [
                            const Icon(Icons.warning_amber, color: Color(0xFF7b7be0), size: 20),
                            const SizedBox(width: 8),
                            const Text(
                              'Emergency Type',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Wrap(
                          spacing: 12,
                          runSpacing: 12,
                          children: _incidentTypes.map((type) {
                            final isSelected = _selectedType == type['name'];
                            return GestureDetector(
                              onTap: () {
                                setState(() {
                                  _selectedType = type['name'];
                                  _errorMessage = '';
                                });
                              },
                              child: Container(
                                width: 100,
                                padding: const EdgeInsets.all(16),
                                decoration: BoxDecoration(
                                  color: isSelected
                                      ? type['color'].withAlpha(26)
                                      : Colors.grey.shade100,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: isSelected
                                        ? type['color']
                                        : Colors.grey.shade300,
                                    width: isSelected ? 2 : 1,
                                  ),
                                ),
                                child: Column(
                                  children: [
                                    Icon(
                                      type['icon'],
                                      color: isSelected ? type['color'] : Colors.grey.shade600,
                                      size: 32,
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      type['name'],
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                                        color: isSelected ? type['color'] : Colors.grey.shade700,
                                      ),
                                      textAlign: TextAlign.center,
                                    ),
                                  ],
                                ),
                              ),
                            );
                          }).toList(),
                        ),
                        const SizedBox(height: 24),
                        // Response Team Section
                        Row(
                          children: [
                            const Icon(Icons.groups, color: Color(0xFF7b7be0), size: 20),
                            const SizedBox(width: 8),
                            const Text(
                              'Response Team',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const Spacer(),
                            const Icon(Icons.lightbulb, color: Colors.amber, size: 20),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                          decoration: BoxDecoration(
                            border: Border.all(color: Colors.grey.shade300),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Row(
                            children: [
                              const Text(
                                'Auto-assign based on emergency type',
                                style: TextStyle(fontSize: 14, color: Colors.grey),
                              ),
                              const Spacer(),
                              Icon(Icons.arrow_drop_down, color: Colors.grey.shade600),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),
                        // Location Section
                        Row(
                          children: [
                            const Icon(Icons.location_on, color: Color(0xFF7b7be0), size: 20),
                            const SizedBox(width: 8),
                            const Text(
                              'Location',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Container(
                          height: 180,
                          decoration: BoxDecoration(
                            color: Colors.grey.shade200,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.grey.shade300),
                          ),
                          child: Stack(
                            children: [
                              Center(
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(Icons.map, size: 48, color: Colors.grey.shade400),
                                    const SizedBox(height: 8),
                                    Text(
                                      'Map view',
                                      style: TextStyle(color: Colors.grey.shade600),
                                    ),
                                  ],
                                ),
                              ),
                              Positioned(
                                top: 8,
                                left: 8,
                                child: Container(
                                  padding: const EdgeInsets.all(4),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: const Icon(Icons.add, size: 20),
                                ),
                              ),
                              Positioned(
                                top: 40,
                                left: 8,
                                child: Container(
                                  padding: const EdgeInsets.all(4),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: const Icon(Icons.remove, size: 20),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: _detectLocation,
                            icon: Icon(
                              _locationDetected ? Icons.check_circle : Icons.my_location,
                              size: 18,
                            ),
                            label: Text(
                              _locationDetected ? 'Location detected' : 'Current location detected',
                              style: const TextStyle(fontSize: 14),
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: _locationDetected
                                  ? Colors.green.shade50
                                  : Colors.grey.shade100,
                              foregroundColor: _locationDetected
                                  ? Colors.green.shade700
                                  : Colors.grey.shade700,
                              elevation: 0,
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 24),
                        // Photo Evidence Section
                        Row(
                          children: [
                            const Icon(Icons.photo_camera, color: Color(0xFF7b7be0), size: 20),
                            const SizedBox(width: 8),
                            const Text(
                              'Photo Evidence',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            if (_autoFilled) ...[
                              const SizedBox(width: 8),
                              const Icon(Icons.auto_awesome, color: Colors.amber, size: 18),
                              const Text(
                                'Auto-filled',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: Colors.amber,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (_selectedImage != null) ...[
                          // Show selected image
                          Container(
                            height: 200,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: Colors.grey.shade300),
                            ),
                            child: Stack(
                              children: [
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(12),
                                  child: Image.file(
                                    _selectedImage!,
                                    width: double.infinity,
                                    height: 200,
                                    fit: BoxFit.cover,
                                  ),
                                ),
                                if (_isAnalyzing)
                                  Container(
                                    decoration: BoxDecoration(
                                      color: Colors.black54,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: const Center(
                                      child: Column(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          CircularProgressIndicator(color: Colors.white),
                                          SizedBox(height: 16),
                                          Text(
                                            'Analyzing image with AI...',
                                            style: TextStyle(
                                              color: Colors.white,
                                              fontSize: 14,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                Positioned(
                                  top: 8,
                                  right: 8,
                                  child: IconButton(
                                    onPressed: () {
                                      setState(() {
                                        _selectedImage = null;
                                        _autoFilled = false;
                                      });
                                    },
                                    icon: const Icon(Icons.close),
                                    style: IconButton.styleFrom(
                                      backgroundColor: Colors.white,
                                      foregroundColor: Colors.black,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        Container(
                          padding: const EdgeInsets.all(24),
                          decoration: BoxDecoration(
                            color: _selectedImage != null
                                ? Colors.green.shade50
                                : Colors.grey.shade50,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: _selectedImage != null
                                  ? Colors.green.shade300
                                  : Colors.grey.shade300,
                            ),
                          ),
                          child: Column(
                            children: [
                              Icon(
                                _selectedImage != null
                                    ? Icons.check_circle
                                    : Icons.auto_awesome,
                                color: _selectedImage != null
                                    ? Colors.green
                                    : const Color(0xFF7b7be0),
                                size: 32,
                              ),
                              const SizedBox(height: 12),
                              Text(
                                _selectedImage != null
                                    ? 'Image uploaded! AI will auto-fill the form.'
                                    : 'Take a photo and AI will auto-fill everything!',
                                style: TextStyle(
                                  fontSize: 13,
                                  color: _selectedImage != null
                                      ? Colors.green.shade700
                                      : Colors.grey.shade600,
                                  fontWeight: _selectedImage != null
                                      ? FontWeight.w600
                                      : FontWeight.normal,
                                ),
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 16),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  ElevatedButton.icon(
                                    onPressed: _isAnalyzing
                                        ? null
                                        : () => _pickImage(ImageSource.camera),
                                    icon: const Icon(Icons.camera_alt, size: 18),
                                    label: const Text('Take Photo'),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: const Color(0xFF7b7be0),
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 16,
                                        vertical: 12,
                                      ),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  ElevatedButton.icon(
                                    onPressed: _isAnalyzing
                                        ? null
                                        : () => _pickImage(ImageSource.gallery),
                                    icon: const Icon(Icons.upload, size: 18),
                                    label: const Text('Upload'),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: Colors.grey.shade700,
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 16,
                                        vertical: 12,
                                      ),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),
                        // Description Section
                        Row(
                          children: [
                            const Icon(Icons.description, color: Color(0xFF7b7be0), size: 20),
                            const SizedBox(width: 8),
                            const Text(
                              'Description',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _descriptionController,
                          maxLines: 5,
                          decoration: InputDecoration(
                            hintText: 'Describe what happened, how many people are affected, and any other important details...',
                            hintStyle: TextStyle(fontSize: 13, color: Colors.grey.shade500),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                              borderSide: BorderSide(color: Colors.grey.shade300),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                              borderSide: BorderSide(color: Colors.grey.shade300),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                              borderSide: const BorderSide(color: Color(0xFF7b7be0), width: 2),
                            ),
                            contentPadding: const EdgeInsets.all(16),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Optional but recommended for faster response',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.grey.shade600,
                          ),
                        ),
                        if (_errorMessage.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.red.shade50,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: Colors.red.shade200),
                            ),
                            child: Row(
                              children: [
                                Icon(Icons.error_outline, color: Colors.red.shade700, size: 20),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    _errorMessage,
                                    style: TextStyle(color: Colors.red.shade700, fontSize: 13),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                        const SizedBox(height: 24),
                        // Submit Button
                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: ElevatedButton(
                            onPressed: (_isLoading || _isAnalyzing) ? null : _submitReport,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: _selectedType == null
                                  ? Colors.grey.shade400
                                  : (_autoFilled ? Colors.green : const Color(0xFF7b7be0)),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            child: _isLoading
                                ? const SizedBox(
                                    width: 24,
                                    height: 24,
                                    child: CircularProgressIndicator(
                                      color: Colors.white,
                                      strokeWidth: 2,
                                    ),
                                  )
                                : _isAnalyzing
                                    ? const Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          SizedBox(
                                            width: 20,
                                            height: 20,
                                            child: CircularProgressIndicator(
                                              color: Colors.white,
                                              strokeWidth: 2,
                                            ),
                                          ),
                                          SizedBox(width: 12),
                                          Text(
                                            'Analyzing image...',
                                            style: TextStyle(
                                              fontSize: 16,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ],
                                      )
                                    : Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Icon(_autoFilled ? Icons.check_circle : Icons.warning_amber),
                                          const SizedBox(width: 8),
                                          Text(
                                            _selectedType == null
                                                ? 'Please select emergency type'
                                                : (_autoFilled
                                                    ? 'Complete Report'
                                                    : 'Submit Emergency Report'),
                                            style: const TextStyle(
                                              fontSize: 16,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ],
                                      ),
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],
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

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }
}
