/// One location the employee may check in at, plus the live GPS distance.
class AttendanceLocation {
  const AttendanceLocation({
    required this.id,
    required this.name,
    required this.latitude,
    required this.longitude,
    required this.radius,
    this.address,
    this.distance,
    this.withinRange,
  });

  factory AttendanceLocation.fromJson(Map<String, dynamic> json) {
    return AttendanceLocation(
      id: _asInt(json['id']),
      name: '${json['name'] ?? '-'}',
      address: _asText(json['address']),
      latitude: _asDouble(json['latitude']),
      longitude: _asDouble(json['longitude']),
      radius: _asInt(json['radius']),
      distance: json['distance'] == null ? null : _asDouble(json['distance']),
      withinRange: json['within_range'] as bool?,
    );
  }

  final int id;
  final String name;
  final String? address;
  final double latitude;
  final double longitude;
  final int radius;
  final double? distance;
  final bool? withinRange;

  /// True when the device is far enough from the location to be refused.
  bool get isTooFar => withinRange == false;
}

int _asInt(Object? value) {
  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.round();
  }

  return int.tryParse('$value') ?? 0;
}

double _asDouble(Object? value) {
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse('$value') ?? 0;
}

String? _asText(Object? value) {
  if (value == null) {
    return null;
  }

  final String text = '$value'.trim();

  return text.isEmpty ? null : text;
}
