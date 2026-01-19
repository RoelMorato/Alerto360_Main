import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:async';
import 'package:url_launcher/url_launcher.dart';
import '../services/api_service.dart';
import 'login_screen.dart';
import 'notifications_screen.dart';

class ResponderScreen extends StatefulWidget {
  const ResponderScreen({super.key});

  @override
  State<ResponderScreen> createState() => _ResponderScreenState();
}

class _ResponderScreenState extends State<ResponderScreen> {
  List<dynamic> _incidents = [];
  bool _isLoading = true;
  int _notificationCount = 0;
  String? _successMessage;
  int? _currentUserId;
  Timer? _onlineStatusTimer;

  @override
  void initState() {
    super.initState();
    _loadUserData();
    _startOnlineStatusUpdates();
  }

  @override
  void dispose() {
    _onlineStatusTimer?.cancel();
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

  Future<void> _loadUserData() async {
    final prefs = await SharedPreferences.getInstance();
    _currentUserId = prefs.getInt('user_id');
    await _loadIncidents();
  }

  Future<void> _loadIncidents() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
      });
    }

    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('user_id');

      final response = await http.get(
        Uri.parse('${ApiService.baseUrl}/api_responder_incidents.php?user_id=$userId'),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && mounted) {
          final incidents = data['incidents'] ?? [];
          final pendingCount = incidents.where((i) => i['status'] == 'pending').length;

          setState(() {
            _incidents = incidents;
            _notificationCount = pendingCount;
            _isLoading = false;
          });
        }
      } else if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    } catch (e) {
      // Error loading incidents
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _acceptIncident(int incidentId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('user_id');

      final response = await http.post(
        Uri.parse('${ApiService.baseUrl}/api_accept_incident.php'),
        body: {
          'incident_id': incidentId.toString(),
          'user_id': userId.toString(),
          'action': 'accept',
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (mounted) {
          if (data['success'] == true) {
            setState(() {
              _successMessage = 'Incident accepted successfully! You can now get directions to the location.';
            });
            await _loadIncidents();
            // Hide message after 5 seconds
            Future.delayed(const Duration(seconds: 5), () {
              if (mounted) {
                setState(() {
                  _successMessage = null;
                });
              }
            });
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(data['message'] ?? 'Failed to accept')),
            );
          }
        }
      }
    } catch (e) {
      // Error accepting incident
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  Future<void> _declineIncident(int incidentId) async {
    // Show dialog to get decline reason
    final TextEditingController reasonController = TextEditingController();
    String? selectedReason;
    
    final reasons = [
      'Currently responding to another incident',
      'Too far from location',
      'Not enough resources/equipment',
      'Off duty',
      'Medical/Personal emergency',
      'Other',
    ];
    
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Row(
            children: [
              Icon(Icons.cancel, color: Colors.red.shade700),
              const SizedBox(width: 8),
              const Text('Decline Incident'),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.orange.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.orange.shade200),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.warning, color: Colors.orange.shade700, size: 20),
                      const SizedBox(width: 8),
                      const Expanded(
                        child: Text(
                          'Admin will be notified and may reassign this incident.',
                          style: TextStyle(fontSize: 12),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                const Text('Select a reason:', style: TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey.shade300),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      isExpanded: true,
                      hint: const Text('-- Select reason --'),
                      value: selectedReason,
                      items: reasons.map((reason) => DropdownMenuItem(
                        value: reason,
                        child: Text(reason, style: const TextStyle(fontSize: 14)),
                      )).toList(),
                      onChanged: (value) {
                        setDialogState(() {
                          selectedReason = value;
                          if (value != 'Other') {
                            reasonController.text = value ?? '';
                          } else {
                            reasonController.text = '';
                          }
                        });
                      },
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: reasonController,
                  decoration: InputDecoration(
                    labelText: selectedReason == 'Other' ? 'Enter your reason' : 'Additional details (optional)',
                    hintText: 'Enter reason...',
                    border: const OutlineInputBorder(),
                  ),
                  maxLines: 3,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            ElevatedButton.icon(
              onPressed: () {
                if (reasonController.text.trim().isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Please enter a reason')),
                  );
                } else {
                  Navigator.pop(context, true);
                }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red,
              ),
              icon: const Icon(Icons.close, size: 18),
              label: const Text('Decline'),
            ),
          ],
        ),
      ),
    );

    if (confirmed == true && reasonController.text.trim().isNotEmpty) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final userId = prefs.getInt('user_id');

        final response = await http.post(
          Uri.parse('${ApiService.baseUrl}/api_decline_incident.php'),
          body: {
            'incident_id': incidentId.toString(),
            'user_id': userId.toString(),
            'decline_reason': reasonController.text.trim(),
          },
        );

        if (response.statusCode == 200) {
          final data = jsonDecode(response.body);
          if (mounted) {
            if (data['success'] == true) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Incident declined. Admin has been notified.'),
                  backgroundColor: Colors.orange,
                ),
              );
              await _loadIncidents();
            } else {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(data['message'] ?? 'Failed to decline')),
              );
            }
          }
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Error: $e')),
          );
        }
      }
    }
    
    reasonController.dispose();
  }

  Future<void> _completeIncident(int incidentId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Complete Incident'),
        content: const Text(
          'Mark this incident as completed?\n\n'
          'This will:\n'
          '• Mark the incident as COMPLETED\n'
          '• Notify all admins automatically\n'
          '• Show as DONE in admin dashboard\n\n'
          'Proceed?'
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF4CAF50),
            ),
            child: const Text('Complete'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final userId = prefs.getInt('user_id');

        final response = await http.post(
          Uri.parse('${ApiService.baseUrl}/api_complete_incident.php'),
          body: {
            'incident_id': incidentId.toString(),
            'user_id': userId.toString(),
          },
        );

        if (response.statusCode == 200) {
          final data = jsonDecode(response.body);
          if (mounted) {
            if (data['success'] == true) {
              setState(() {
                _successMessage = 'Incident completed successfully! Admin has been notified.';
              });
              await _loadIncidents();
              Future.delayed(const Duration(seconds: 5), () {
                if (mounted) {
                  setState(() {
                    _successMessage = null;
                  });
                }
              });
            } else {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(data['message'] ?? 'Failed to complete')),
              );
            }
          }
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Error: $e')),
          );
        }
      }
    }
  }

  // Resolve function removed - Only accept is allowed

  Future<void> _logout() async {
    await ApiService.logout();
    if (mounted) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (context) => const LoginScreen()),
      );
    }
  }

  Future<void> _openDirections(double? latitude, double? longitude) async {
    if (latitude == null || longitude == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Location not available')),
        );
      }
      return;
    }

    final googleMapsUrl = Uri.parse('google.navigation:q=$latitude,$longitude');
    final webUrl = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$latitude,$longitude');

    try {
      if (await canLaunchUrl(googleMapsUrl)) {
        await launchUrl(googleMapsUrl);
      } else if (await canLaunchUrl(webUrl)) {
        await launchUrl(webUrl, mode: LaunchMode.externalApplication);
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Could not open maps')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error opening maps: $e')),
        );
      }
    }
  }

  void _viewMap(double? latitude, double? longitude) async {
    if (latitude == null || longitude == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Location not available')),
      );
      return;
    }

    final webUrl = Uri.parse('https://www.google.com/maps?q=$latitude,$longitude');
    try {
      if (await canLaunchUrl(webUrl)) {
        await launchUrl(webUrl, mode: LaunchMode.externalApplication);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFE8E4F3),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadIncidents,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    _buildHeader(),
                    if (_successMessage != null) _buildSuccessMessage(),
                    _buildIncidentReports(),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          // Shield Icon
          Container(
            width: 60,
            height: 60,
            decoration: const BoxDecoration(
              color: Color(0xFF7B7BE0),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.shield,
              color: Colors.white,
              size: 32,
            ),
          ),
          const SizedBox(height: 12),
          // Title
          const Text(
            'Responder Dashboard',
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
              color: Colors.black87,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          // Buttons row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // Notifications button - Navigate to separate screen
              Expanded(
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    OutlinedButton.icon(
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const NotificationsScreen(),
                          ),
                        );
                      },
                      icon: const Icon(Icons.notifications_outlined, size: 18),
                      label: const Text('Notifications', style: TextStyle(fontSize: 12)),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF7B7BE0),
                        side: const BorderSide(color: Color(0xFF7B7BE0)),
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                      ),
                    ),
                    if (_notificationCount > 0)
                      Positioned(
                        right: -5,
                        top: -5,
                        child: Container(
                          padding: const EdgeInsets.all(4),
                          decoration: const BoxDecoration(
                            color: Colors.red,
                            shape: BoxShape.circle,
                          ),
                          constraints: const BoxConstraints(
                            minWidth: 18,
                            minHeight: 18,
                          ),
                          child: Text(
                            '$_notificationCount',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              // Logout button
              Expanded(
                child: OutlinedButton(
                  onPressed: _logout,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.red,
                    side: const BorderSide(color: Colors.red),
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                  ),
                  child: const Text('Logout', style: TextStyle(fontSize: 12)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSuccessMessage() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFD4EDDA),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFC3E6CB)),
      ),
      child: Row(
        children: [
          const Icon(Icons.check_circle, color: Color(0xFF155724), size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Success!',
                  style: TextStyle(
                    color: Color(0xFF155724),
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  _successMessage!,
                  style: const TextStyle(
                    color: Color(0xFF155724),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildIncidentReports() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Incident Reports',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w600,
              color: Color(0xFF7B7BE0),
            ),
          ),
          const SizedBox(height: 16),
          _isLoading
              ? const Center(
                  child: Padding(
                    padding: EdgeInsets.all(40),
                    child: CircularProgressIndicator(),
                  ),
                )
              : _incidents.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(40),
                        child: Column(
                          children: [
                            Icon(Icons.inbox_outlined, size: 64, color: Colors.grey.shade300),
                            const SizedBox(height: 16),
                            Text(
                              'No incidents found',
                              style: TextStyle(color: Colors.grey.shade600, fontSize: 16),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: _incidents.length,
                      itemBuilder: (context, index) {
                        return _buildIncidentCard(_incidents[index]);
                      },
                    ),
        ],
      ),
    );
  }

  Widget _buildIncidentCard(Map<String, dynamic> incident) {
    final status = incident['status'] ?? 'pending';
    final latitude = double.tryParse(incident['latitude']?.toString() ?? '');
    final longitude = double.tryParse(incident['longitude']?.toString() ?? '');
    final acceptedBy = incident['accepted_by'];
    final isAcceptedByMe = _currentUserId == acceptedBy;
    final isAssignedToMe = incident['is_assigned_to_me'] == 1 || incident['assigned_to'] == _currentUserId;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: BorderSide(color: Colors.grey.shade200),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Reporter and Type
            Row(
              children: [
                Expanded(
                  child: _buildInfoItem('Reporter', incident['reporter_name'] ?? 'Unknown'),
                ),
                Expanded(
                  child: _buildInfoItem('Type', incident['type'] ?? 'Unknown'),
                ),
              ],
            ),
            const Divider(height: 20),
            // Description
            _buildInfoItem('Description', incident['description'] ?? '[Auto-detected incident]'),
            const Divider(height: 20),
            // Status and Actions
            _buildStatusSection(incident, status, isAcceptedByMe, isAssignedToMe),
            const Divider(height: 20),
            // Date and Location
            Row(
              children: [
                Expanded(
                  child: _buildInfoItem('Date', incident['created_at'] ?? 'N/A'),
                ),
                Expanded(
                  child: _buildInfoItem(
                    'Location',
                    latitude != null && longitude != null
                        ? '${latitude.toStringAsFixed(4)}, ${longitude.toStringAsFixed(4)}'
                        : 'No location',
                  ),
                ),
              ],
            ),
            // Location Actions
            if (latitude != null && longitude != null) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _viewMap(latitude, longitude),
                      icon: const Icon(Icons.map, size: 16),
                      label: const Text('View Map', style: TextStyle(fontSize: 12)),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF00BCD4),
                        side: const BorderSide(color: Color(0xFF00BCD4)),
                        padding: const EdgeInsets.symmetric(vertical: 8),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => _openDirections(latitude, longitude),
                      icon: const Icon(Icons.directions, size: 16),
                      label: const Text('Directions', style: TextStyle(fontSize: 12)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF00BCD4),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 8),
                      ),
                    ),
                  ),
                ],
              ),
            ],
            // Image
            if (incident['image_path'] != null && incident['image_path'].toString().isNotEmpty) ...[
              const Divider(height: 20),
              _buildImageSection(incident['image_path']),
            ],
            // Responder
            const Divider(height: 20),
            _buildInfoItem('Responder Type', incident['responder_type']?.toString().toUpperCase() ?? 'N/A'),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 11,
            color: Colors.grey,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }

  Widget _buildStatusSection(Map<String, dynamic> incident, String status, bool isAcceptedByMe, bool isAssignedToMe) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Status / Action',
          style: TextStyle(
            fontSize: 11,
            color: Colors.grey,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 8),
        if (status == 'resolved' || status == 'completed') ...[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFF4CAF50),
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'Completed',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
            ),
          ),
          const SizedBox(height: 4),
          if (isAcceptedByMe)
            const Text(
              'by you',
              style: TextStyle(fontSize: 11, color: Colors.grey),
            ),
        ] else if (status == 'accepted') ...[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFF00BCD4),
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'Accepted',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
            ),
          ),
          const SizedBox(height: 8),
          if (isAcceptedByMe) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFF00BCD4).withOpacity(0.1),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: const Color(0xFF00BCD4)),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.check_circle, color: Color(0xFF00BCD4), size: 20),
                  SizedBox(width: 8),
                  Text(
                    'Accepted by you',
                    style: TextStyle(
                      fontSize: 14,
                      color: Color(0xFF00BCD4),
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _completeIncident(incident['id']),
                icon: const Icon(Icons.check_circle, size: 18),
                label: const Text('Mark as Complete'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF4CAF50),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
          ] else ...[
            const Text(
              'Accepted by another responder',
              style: TextStyle(fontSize: 12, color: Colors.orange, fontWeight: FontWeight.bold),
            ),
          ],
        ] else ...[
          // Pending - show assigned message if assigned to this responder
          if (isAssignedToMe) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              margin: const EdgeInsets.only(bottom: 8),
              decoration: BoxDecoration(
                color: const Color(0xFF7B7BE0).withOpacity(0.1),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: const Color(0xFF7B7BE0)),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.person_pin, color: Color(0xFF7B7BE0), size: 20),
                  SizedBox(width: 8),
                  Text(
                    'Assigned to you - Please respond',
                    style: TextStyle(
                      fontSize: 13,
                      color: Color(0xFF7B7BE0),
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          ],
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.orange,
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'Pending',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () => _acceptIncident(incident['id']),
                  icon: const Icon(Icons.check, size: 18),
                  label: const Text('Accept'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF4CAF50),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _declineIncident(incident['id']),
                  icon: const Icon(Icons.close, size: 18),
                  label: const Text('Decline'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.red,
                    side: const BorderSide(color: Colors.red),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }

  Widget _buildImageSection(String imagePath) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Image',
          style: TextStyle(
            fontSize: 11,
            color: Colors.grey,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: Image.network(
            '${ApiService.baseUrl}/$imagePath',
            height: 150,
            width: double.infinity,
            fit: BoxFit.cover,
            errorBuilder: (context, error, stackTrace) {
              return Container(
                height: 150,
                color: Colors.grey.shade200,
                child: const Center(
                  child: Icon(Icons.image_not_supported, size: 40, color: Colors.grey),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
