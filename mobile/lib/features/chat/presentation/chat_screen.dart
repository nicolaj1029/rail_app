import 'package:flutter/material.dart';

import 'package:mobile/features/profile/data/commuter_profile_store.dart';

class ChatScreen extends StatelessWidget {
  final bool commuterMode;
  final String? deviceId;
  final CommuterProfile commuterProfile;

  const ChatScreen({
    super.key,
    required this.commuterMode,
    required this.deviceId,
    required this.commuterProfile,
  });

  @override
  Widget build(BuildContext context) {
    final suggestions = commuterMode
        ? [
            'Vi mangler kun ét svar om din pendlerrejse',
            'Forklar hvorfor denne sag er claim-assist',
            'Hjælp med season-produkt og data-pack',
          ]
        : [
            'Hjælp mig med denne rejse',
            'Hvorfor får jeg ikke kompensation endnu?',
            'Hvilket dokument mangler vi?',
          ];

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          commuterMode ? 'Pendler-chat' : 'Chat',
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: 8),
        Text(
          commuterMode
              ? 'Chatten bør bruges som hjælp og claim-assist oven på dine rejser — ikke som hovednavigation.'
              : 'Chatten bør bruges til forklaringer og manglende oplysninger, ikke som eneste flow.',
        ),
        const SizedBox(height: 16),
        Card(
          child: ListTile(
            leading: const Icon(Icons.smart_toy_outlined),
            title: const Text('Kontekstuel chat anbefales'),
            subtitle: Text(
              commuterMode
                  ? 'Brug quick replies, upload og trip-context. Profil: ${commuterProfile.operatorName} • ${commuterProfile.productName}'
                  : 'Brug quick replies og trip-context frem for fri tekst som primær UX.',
            ),
          ),
        ),
        Card(
          child: ListTile(
            leading: const Icon(Icons.phone_android_outlined),
            title: const Text('Device ID'),
            subtitle: Text(deviceId ?? 'ikke registreret endnu'),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Foreslåede startprompts',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 8),
        ...suggestions.map(
          (text) => Card(
            child: ListTile(
              leading: const Icon(Icons.chat_bubble_outline),
              title: Text(text),
              trailing: const Icon(Icons.chevron_right),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Card(
          color: Colors.amber.shade50,
          child: const ListTile(
            leading: Icon(Icons.info_outline),
            title: Text('Phase 1 placeholder'),
            subtitle: Text(
              'Den egentlige passager-chat bør kobles på backend senere via /api/chat og /api/chat/upload.',
            ),
          ),
        ),
      ],
    );
  }
}
