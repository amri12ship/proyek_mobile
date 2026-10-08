/// The single use ticket published when a location QR code is scanned.
class QrTicket {
  const QrTicket({
    required this.ticket,
    required this.expiresAt,
    required this.expiresInSeconds,
    required this.locationId,
    required this.locationName,
    required this.locationRadius,
  });

  factory QrTicket.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> location =
        json['location'] is Map<String, dynamic>
        ? json['location'] as Map<String, dynamic>
        : const <String, dynamic>{};

    return QrTicket(
      ticket: '${json['ticket'] ?? ''}',
      expiresAt:
          DateTime.tryParse('${json['expires_at'] ?? ''}')?.toLocal() ??
          DateTime.now(),
      expiresInSeconds: _int(json['expires_in_seconds']),
      locationId: _int(location['id']),
      locationName: '${location['name'] ?? '-'}',
      locationRadius: _int(location['radius']),
    );
  }

  final String ticket;
  final DateTime expiresAt;
  final int expiresInSeconds;
  final int locationId;
  final String locationName;
  final int locationRadius;

  bool isValidAt(DateTime moment) => moment.isBefore(expiresAt);

  Duration remainingAt(DateTime moment) {
    final Duration remaining = expiresAt.difference(moment);

    return remaining.isNegative ? Duration.zero : remaining;
  }
}

int _int(Object? value) {
  if (value is num) {
    return value.round();
  }

  return int.tryParse('$value') ?? 0;
}
