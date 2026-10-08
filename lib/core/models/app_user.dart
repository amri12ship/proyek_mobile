/// The signed in employee account as returned by the API.
class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.nik,
    this.phone,
    this.department,
    this.position,
    this.photo,
  });

  factory AppUser.fromJson(Map<String, dynamic> json) {
    final Object? employee = json['employee'];
    final Map<String, dynamic> profile = employee is Map<String, dynamic>
        ? employee
        : const <String, dynamic>{};

    return AppUser(
      id: _toInt(json['id']) ?? 0,
      name: '${json['name'] ?? '-'}',
      email: '${json['email'] ?? '-'}',
      role: '${json['role'] ?? 'employee'}',
      nik: _nullableText(profile['nik']),
      phone: _nullableText(profile['phone'] ?? json['phone']),
      department: _nullableText(profile['department']),
      position: _nullableText(profile['position']),
      photo: _nullableText(profile['photo']),
    );
  }

  final int id;
  final String name;
  final String email;
  final String role;
  final String? nik;
  final String? phone;
  final String? department;
  final String? position;
  final String? photo;

  bool get isEmployee => role == 'employee';

  String get initials {
    final List<String> parts = name
        .split(' ')
        .where((String part) => part.isNotEmpty)
        .toList();

    if (parts.isEmpty) {
      return '?';
    }

    if (parts.length == 1) {
      return parts.first.substring(0, 1).toUpperCase();
    }

    return (parts.first.substring(0, 1) + parts[1].substring(0, 1))
        .toUpperCase();
  }
}

int? _toInt(Object? value) {
  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.toInt();
  }

  return int.tryParse('$value');
}

String? _nullableText(Object? value) {
  if (value == null) {
    return null;
  }

  final String text = '$value'.trim();

  return text.isEmpty ? null : text;
}
