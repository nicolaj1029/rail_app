import 'package:http/http.dart' as http;

import 'persistent_http_client_stub.dart'
    if (dart.library.html) 'persistent_http_client_web.dart'
    as impl;

http.Client createPersistentHttpClient() => impl.createPersistentHttpClient();
