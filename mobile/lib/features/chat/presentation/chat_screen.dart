import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/chat/data/chat_context.dart';
import 'package:mobile/features/chat/data/chat_service.dart';
import 'package:mobile/features/profile/data/commuter_profile_store.dart';

class ChatScreen extends StatefulWidget {
  final bool commuterMode;
  final String? deviceId;
  final CommuterProfile commuterProfile;
  final ChatContext? initialContext;

  const ChatScreen({
    super.key,
    required this.commuterMode,
    required this.deviceId,
    required this.commuterProfile,
    this.initialContext,
  });

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  late final ChatService _service;
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final ImagePicker _picker = ImagePicker();

  bool _loading = true;
  bool _sending = false;
  bool _uploading = false;
  bool _applyingContext = false;
  bool _contextApplied = false;
  String? _error;
  Map<String, dynamic>? _payload;

  String get _stageTone => widget.initialContext?.stageTone ?? 'general';

  Color _stageColor(String tone) {
    switch (tone) {
      case 'live':
        return Colors.green;
      case 'review':
        return Colors.orange;
      case 'submitted':
        return Colors.indigo;
      case 'completed':
        return Colors.teal;
      default:
        return Colors.blueGrey;
    }
  }

  String _uploadTitle() {
    switch (_stageTone) {
      case 'live':
        return 'Dokumentér hændelsen nu';
      case 'review':
        return 'Upload billet eller season-dokument';
      case 'submitted':
        return 'Tilføj ekstra dokumentation';
      case 'completed':
        return 'Gem dokumentation';
      default:
        return 'Upload dokumentation';
    }
  }

  String _uploadSubtitle() {
    switch (_stageTone) {
      case 'live':
        return 'Prioritér kvitteringer, fotos og anden dokumentation fra den aktuelle rejse.';
      case 'review':
        return 'Prioritér billet, season-pass eller andet der stabiliserer review og eligibility.';
      case 'submitted':
        return 'Upload kun ekstra dokumentation hvis sagen skal styrkes eller opdateres.';
      case 'completed':
        return 'Gem kun materiale hvis det er nyttigt som reference til senere sager.';
      default:
        return 'Upload relevant dokumentation til den aktuelle sag.';
    }
  }

  String _cameraUploadLabel() {
    switch (_stageTone) {
      case 'live':
        return _uploading ? 'Uploader...' : 'Foto af kvittering';
      case 'review':
        return _uploading ? 'Uploader...' : 'Foto af billet';
      case 'submitted':
        return _uploading ? 'Uploader...' : 'Foto af dokumentation';
      default:
        return _uploading ? 'Uploader...' : 'Foto upload';
    }
  }

  String _fileUploadLabel() {
    switch (_stageTone) {
      case 'review':
        return 'Billet / season-fil';
      case 'submitted':
        return 'Ekstra bilag';
      case 'completed':
        return 'Gem bilag';
      default:
        return 'Fil upload';
    }
  }

  @override
  void initState() {
    super.initState();
    _service = ChatService(baseUrl: apiBaseUrl);
    _bootstrap();
  }

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    _service.dispose();
    super.dispose();
  }

  void _scrollToBottom({bool animated = true}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scrollController.hasClients) {
        return;
      }
      final offset = _scrollController.position.maxScrollExtent;
      if (animated) {
        _scrollController.animateTo(
          offset,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
        return;
      }
      _scrollController.jumpTo(offset);
    });
  }

  Future<void> _bootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
      _applyingContext = widget.initialContext != null && !_contextApplied;
    });
    try {
      var payload = await _service.bootstrap();
      if (widget.initialContext != null && !_contextApplied) {
        payload = await _service.applyContext(widget.initialContext!.payload);
        _contextApplied = true;
      }
      if (!mounted) return;
      setState(() {
        _payload = payload;
      });
      _scrollToBottom(animated: false);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _applyingContext = false;
        });
      }
    }
  }

  Future<void> _applyContext() async {
    if (widget.initialContext == null || _loading || _sending || _uploading) {
      return;
    }

    setState(() {
      _applyingContext = true;
      _error = null;
    });
    try {
      final payload = await _service.applyContext(
        widget.initialContext!.payload,
      );
      if (!mounted) return;
      setState(() {
        _payload = payload;
        _contextApplied = true;
      });
      _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _applyingContext = false;
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
      _scrollToBottom();
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
      _scrollToBottom(animated: false);
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
      _scrollToBottom();
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
    final contextSuggestions =
        widget.initialContext?.suggestions ?? const <String>[];
    final recommendedSuggestion = contextSuggestions.isNotEmpty
        ? contextSuggestions.first
        : null;
    final secondarySuggestions = contextSuggestions.length > 1
        ? contextSuggestions.sublist(1)
        : const <String>[];

    return ListView(
      controller: _scrollController,
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
        if (widget.initialContext != null)
          Card(
            color: Colors.blue.shade50,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.initialContext!.title,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 6),
                  Text(widget.initialContext!.subtitle),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      FilledButton.icon(
                        onPressed: _applyingContext ? null : _applyContext,
                        icon: const Icon(Icons.sync),
                        label: Text(
                          _contextApplied
                              ? 'Anvend rejsekontekst igen'
                              : 'Brug denne rejse i chatten',
                        ),
                      ),
                      if (recommendedSuggestion != null)
                        FilledButton.tonalIcon(
                          onPressed: _sending
                              ? null
                              : () => _sendMessage(recommendedSuggestion),
                          icon: const Icon(Icons.auto_awesome),
                          label: Text(recommendedSuggestion),
                        ),
                      ...secondarySuggestions.map(
                        (suggestion) => ActionChip(
                          label: Text(suggestion),
                          onPressed: () {
                            _messageController.text = suggestion;
                            _messageController.selection =
                                TextSelection.collapsed(
                                  offset: _messageController.text.length,
                                );
                          },
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        if (widget.initialContext != null)
          Card(
            color: _stageColor(
              widget.initialContext!.stageTone,
            ).withValues(alpha: 0.10),
            child: ListTile(
              leading: Icon(switch (widget.initialContext!.stageTone) {
                'live' => Icons.play_circle_outline,
                'review' => Icons.assignment_outlined,
                'submitted' => Icons.outbox_outlined,
                'completed' => Icons.check_circle_outline,
                _ => Icons.info_outline,
              }, color: _stageColor(widget.initialContext!.stageTone)),
              title: Text('Fase: ${widget.initialContext!.stageLabel}'),
              subtitle: Text(widget.initialContext!.stageDescription),
            ),
          ),
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
          Card(
            color: _stageColor(_stageTone).withValues(alpha: 0.08),
            child: ListTile(
              leading: Icon(
                Icons.attach_file_outlined,
                color: _stageColor(_stageTone),
              ),
              title: Text(_uploadTitle()),
              subtitle: Text(_uploadSubtitle()),
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
                  if (_applyingContext) ...[
                    const SizedBox(height: 8),
                    const LinearProgressIndicator(),
                  ],
                  const SizedBox(height: 12),
                  if (history.isEmpty && !_sending)
                    const Text('Ingen beskeder endnu.'),
                  if (history.isNotEmpty) ...[
                    ...history.map(_buildMessageBubble),
                  ],
                  if (_sending) _buildTypingBubble(),
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
                label: Text(_cameraUploadLabel()),
              ),
              OutlinedButton.icon(
                onPressed: _uploading
                    ? null
                    : () => _uploadTicket(ImageSource.gallery),
                icon: const Icon(Icons.upload_file_outlined),
                label: Text(_fileUploadLabel()),
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

  Widget _buildTypingBubble() {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        constraints: const BoxConstraints(maxWidth: 520),
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(
              width: 16,
              height: 16,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
            const SizedBox(width: 10),
            Text(
              'Assistenten svarer...',
              style: TextStyle(color: Colors.grey.shade700),
            ),
          ],
        ),
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
