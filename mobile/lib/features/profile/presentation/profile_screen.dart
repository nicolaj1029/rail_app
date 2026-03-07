import 'package:flutter/material.dart';

import 'package:mobile/features/profile/data/commuter_profile_store.dart';

class ProfileScreen extends StatefulWidget {
  final CommuterProfile commuterProfile;
  final ValueChanged<CommuterProfile> onSaveProfile;

  const ProfileScreen({
    super.key,
    required this.commuterProfile,
    required this.onSaveProfile,
  });

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late bool enabled;
  late TextEditingController operatorCtrl;
  late TextEditingController countryCtrl;
  late TextEditingController productCtrl;
  late TextEditingController routeCtrl;

  @override
  void initState() {
    super.initState();
    enabled = widget.commuterProfile.enabled;
    operatorCtrl = TextEditingController(
      text: widget.commuterProfile.operatorName,
    );
    countryCtrl = TextEditingController(
      text: widget.commuterProfile.operatorCountry,
    );
    productCtrl = TextEditingController(
      text: widget.commuterProfile.productName,
    );
    routeCtrl = TextEditingController(text: widget.commuterProfile.routeName);
  }

  @override
  void dispose() {
    operatorCtrl.dispose();
    countryCtrl.dispose();
    productCtrl.dispose();
    routeCtrl.dispose();
    super.dispose();
  }

  void _save() {
    final profile = CommuterProfile(
      enabled: enabled,
      operatorName: operatorCtrl.text.trim(),
      operatorCountry: countryCtrl.text.trim().toUpperCase(),
      productName: productCtrl.text.trim(),
      routeName: routeCtrl.text.trim(),
    );
    widget.onSaveProfile(profile);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('Profil gemt')));
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Profil', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 8),
        const Text(
          'Samme app kan håndtere både almindelige rejser og pendler-mode. Pendler-mode aktiveres her.',
        ),
        const SizedBox(height: 16),
        SwitchListTile(
          value: enabled,
          onChanged: (value) => setState(() => enabled = value),
          title: const Text('Aktivér pendler-mode'),
          subtitle: const Text(
            'Brug faste ruter, produkt og hurtig claim-assist som default.',
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: operatorCtrl,
          decoration: const InputDecoration(
            labelText: 'Operatør',
            hintText: 'Fx DSB, Deutsche Bahn, NS',
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: countryCtrl,
          decoration: const InputDecoration(
            labelText: 'Land',
            hintText: 'Fx DK, DE, NL',
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: productCtrl,
          decoration: const InputDecoration(
            labelText: 'Pendlerprodukt',
            hintText: 'Fx Pendlerkort, BahnCard 100, NS Flex',
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: routeCtrl,
          decoration: const InputDecoration(
            labelText: 'Typisk rute',
            hintText: 'Fx København → Roskilde',
          ),
        ),
        const SizedBox(height: 16),
        Card(
          color: Colors.blue.shade50,
          child: const ListTile(
            leading: Icon(Icons.info_outline),
            title: Text('Tracking og privacy'),
            subtitle: Text(
              'I fase 1 ændrer denne skærm kun profil og mode. Geofencing og native tracking testes senere på device.',
            ),
          ),
        ),
        const SizedBox(height: 16),
        ElevatedButton.icon(
          onPressed: _save,
          icon: const Icon(Icons.save_outlined),
          label: const Text('Gem profil'),
        ),
      ],
    );
  }
}
