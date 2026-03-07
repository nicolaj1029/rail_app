import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/chat/data/chat_service.dart';
import 'package:mobile/features/profile/data/commuter_profile_store.dart';

class ChatScreen extends StatefulWidget {
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
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  late final ChatService _service;
  final TextEditingController _messageController = TextEditingController();
  final ImagePicker _picker = ImagePicker();

  bool _loading = true;
  bool _sending = false;
  bool _uploading = false;
  String? _error;
  Map<String, dynamic>? _payload;

  @override
  void initState() {
    super.initState();
    _service = ChatService(baseUrl: apiBaseUrl);
    _bootstrap();
  }

  @override
  void dispose() {
    _messageController.dispose();
    _service.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final payload = await _service.bootstrap();
      if (!mounted) return;
      setState(() {
        _payload = payload;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  Future<void> _sendMessage(String message) async {
    final trimmed = message.trim();
    if (trimmed.isEmpty || _sending) {
      return;
    }

    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final payload = await _service.sendMessage(trimmed);
      if (!mounted) return;
      _messageController.clear();
      setState(() {
        _payload = payload;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _sending = false;
        });
      }
    }
  }

  Future<void> _resetChat() async {
    if (_sending || _uploading) {
      return;
    }
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final payload = await _service.reset();
      if (!mounted) return;
      _messageController.clear();
      setState(() {
        _payload = payload;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _sending = false;
        });
      }
    }
  }

  Future<void> _uploadTicket(ImageSource source) async {
    if (_uploading) {
      return;
    }
    final file = await _picker.pickImage(source: source, imageQuality: 85);
    if (file == null) {
      return;
    }

    setState(() {
      _uploading = true;
      _error = null;
    });
    try {
      final payload = await _service.upload(file);
      if (!mounted) return;
      setState(() {
        _payload = payload;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _uploading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final payload = _payload ?? const <String, dynamic>{};
    final history =
        (payload['history'] as List?)?.cast<Map<String, dynamic>>() ??
        const <Map<String, dynamic>>[];
    final question =
        (payload['question'] as Map?)?.cast<String, dynamic>() ??
        const <String, dynamic>{};
    final choices =
        (question['choices'] as List?)?.cast<Map<String, dynamic>>() ??
        const <Map<String, dynamic>>[];
    final uploadHint = (payload['upload_hint'] as Map?)
        ?.cast<String, dynamic>();
    final explanation =
        (payload['explanation'] as Map?)?.cast<String, dynamic>() ??
        const <String, dynamic>{};
    final visibleSteps =
        (payload['visible_steps'] as List?)?.cast<Map<String, dynamic>>() ??
        const <Map<String, dynamic>>[];
    final summary =
        (payload['summary'] as Map?)?.cast<String, dynamic>() ??
        const <String, dynamic>{};

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          widget.commuterMode ? 'Pendler-chat' : 'Chat',
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: 8),
        Text(
          widget.commuterMode
              ? 'Chatten er nu koblet på backend og spørger kun om de manglende felter i din sag.'
              : 'Chatten er nu koblet på backend og bruges som hjælpelag oven på claims-flowet.',
        ),
        const SizedBox(height: 12),
        Card(
          child: ListTile(
            leading: const Icon(Icons.phone_android_outlined),
            title: const Text('Device ID'),
            subtitle: Text(widget.deviceId ?? 'ikke registreret endnu'),
            trailing: TextButton(
              onPressed: _sending || _uploading ? null : _resetChat,
              child: const Text('Nulstil'),
            ),
          ),
        ),
        if (widget.commuterMode)
          Card(
            child: ListTile(
              leading: const Icon(Icons.directions_railway_outlined),
              title: const Text('Pendlerprofil'),
              subtitle: Text(
                '${widget.commuterProfile.operatorName} • ${widget.commuterProfile.productName} • ${widget.commuterProfile.routeName}',
              ),
            ),
          ),
        if (_error != null)
          Card(
            color: Colors.red.shade50,
            child: ListTile(
              leading: const Icon(Icons.error_outline),
              title: const Text('Chatten kunne ikke hente svar'),
              subtitle: Text(_error!),
              trailing: TextButton(
                onPressed: _loading ? null : _bootstrap,
                child: const Text('Prøv igen'),
              ),
            ),
          ),
        if (_loading)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
          )
        else ...[
          if (uploadHint != null)
            Card(
              color: Colors.blue.shade50,
              child: ListTile(
                leading: const Icon(Icons.upload_file_outlined),
                title: Text((uploadHint['title'] ?? 'Upload').toString()),
                subtitle: Text((uploadHint['text'] ?? '').toString()),
              ),
            ),
          if ((explanation['status'] ?? '').toString() == 'ok' &&
              (explanation['text'] ?? '').toString().trim().isNotEmpty)
            Card(
              color: Colors.amber.shade50,
              child: ListTile(
                leading: const Icon(Icons.smart_toy_outlined),
                title: const Text('Forklaring'),
                subtitle: Text((explanation['text'] ?? '').toString()),
              ),
            ),
          if (summary.isNotEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Sagsoversigt',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    _SummaryRow(
                      label: 'Rejsestatus',
                      value: (summary['travel_state'] ?? 'ukendt').toString(),
                    ),
                    _SummaryRow(
                      label: 'Billetmode',
                      value: (summary['ticket_mode'] ?? 'ukendt').toString(),
                    ),
                    _SummaryRow(
                      label: 'Rute',
                      value: (summary['route'] ?? 'mangler').toString(),
                    ),
                    _SummaryRow(
                      label: 'Forsinkelse',
                      value: '${(summary['delay_minutes'] ?? 0)} min',
                    ),
                  ],
                ),
              ),
            ),
          if (visibleSteps.isNotEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Synlige trin',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: visibleSteps.map((step) {
                        final uiNum = step['ui_num'];
                        final title = (step['title'] ?? '').toString();
                        return Chip(
                          label: Text(
                            uiNum == null ? title : 'Trin $uiNum: $title',
                          ),
                        );
                      }).toList(),
                    ),
                  ],
                ),
              ),
            ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Samtale',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  if (history.isEmpty)
                    const Text('Ingen beskeder endnu.')
                  else
                    ...history.map(_buildMessageBubble),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (question.isNotEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Næste spørgsmål',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    Text((question['prompt'] ?? '').toString()),
                    if (choices.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: choices.map((choice) {
                          final value = (choice['value'] ?? '').toString();
                          final label = (choice['label'] ?? value).toString();
                          return ChoiceChip(
                            label: Text(label),
                            selected: false,
                            onSelected: _sending
                                ? null
                                : (_) => _sendMessage(value),
                          );
                        }).toList(),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _messageController,
                  minLines: 1,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Skriv dit svar',
                    border: OutlineInputBorder(),
                  ),
                  onSubmitted: _sending ? null : _sendMessage,
                ),
              ),
              const SizedBox(width: 8),
              FilledButton(
                onPressed: _sending
                    ? null
                    : () => _sendMessage(_messageController.text),
                child: _sending
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Send'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: _uploading
                    ? null
                    : () => _uploadTicket(ImageSource.camera),
                icon: const Icon(Icons.photo_camera_outlined),
                label: Text(_uploading ? 'Uploader...' : 'Foto upload'),
              ),
              OutlinedButton.icon(
                onPressed: _uploading
                    ? null
                    : () => _uploadTicket(ImageSource.gallery),
                icon: const Icon(Icons.upload_file_outlined),
                label: const Text('Fil upload'),
              ),
            ],
          ),
        ],
      ],
    );
  }

  Widget _buildMessageBubble(Map<String, dynamic> message) {
    final role = (message['role'] ?? 'assistant').toString();
    final content = (message['content'] ?? '').toString();
    final isUser = role == 'user';

    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        constraints: const BoxConstraints(maxWidth: 520),
        decoration: BoxDecoration(
          color: isUser ? Colors.blue.shade50 : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(content),
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final String label;
  final String value;

  const _SummaryRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
