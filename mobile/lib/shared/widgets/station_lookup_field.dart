import 'package:flutter/material.dart';

import 'package:mobile/services/stations_service.dart';

class StationLookupField extends StatefulWidget {
  final TextEditingController controller;
  final String label;
  final StationsService stationsService;
  final String? Function(String?)? validator;
  final String? Function()? countryProvider;

  const StationLookupField({
    super.key,
    required this.controller,
    required this.label,
    required this.stationsService,
    this.validator,
    this.countryProvider,
  });

  @override
  State<StationLookupField> createState() => _StationLookupFieldState();
}

class _StationLookupFieldState extends State<StationLookupField> {
  List<Map<String, dynamic>> _results = const [];
  bool _loading = false;
  String _lastQuery = '';

  Future<void> _search(String value) async {
    final query = value.trim();
    _lastQuery = query;

    if (query.length < 2) {
      if (mounted) {
        setState(() {
          _results = const [];
          _loading = false;
        });
      }
      return;
    }

    setState(() {
      _loading = true;
    });

    try {
      final results = await widget.stationsService.searchStations(
        query,
        country: widget.countryProvider?.call(),
      );
      if (!mounted || _lastQuery != query) {
        return;
      }
      setState(() {
        _results = results;
      });
    } catch (_) {
      if (!mounted || _lastQuery != query) {
        return;
      }
      setState(() {
        _results = const [];
      });
    } finally {
      if (mounted && _lastQuery == query) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  void _selectStation(Map<String, dynamic> station) {
    widget.controller.text = (station['name'] ?? '').toString();
    setState(() {
      _results = const [];
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextFormField(
          controller: widget.controller,
          decoration: InputDecoration(
            labelText: widget.label,
            suffixIcon: _loading
                ? const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : null,
          ),
          validator: widget.validator,
          onChanged: _search,
        ),
        if (_results.isNotEmpty)
          Card(
            margin: const EdgeInsets.only(top: 8, bottom: 8),
            child: Column(
              children: _results.take(6).map((station) {
                final name = (station['name'] ?? '').toString();
                final country = (station['country'] ?? '').toString();
                return ListTile(
                  dense: true,
                  leading: const Icon(Icons.location_on_outlined),
                  title: Text(name),
                  subtitle: country.isEmpty ? null : Text(country),
                  onTap: () => _selectStation(station),
                );
              }).toList(),
            ),
          ),
      ],
    );
  }
}
