# Provider Mock Server

Run a local JSON-backed mock server for SNCF, DB, DSB and RNE so you can test upload→parse→enrich→reconcile without external keys.

## Install & Run (Windows PowerShell)

```powershell
cd mocks
npm install
npm run mocks
```

Server starts at http://localhost:5555. Point your client at:

- http://localhost:5555/api/providers/sncf/booking/validate
- http://localhost:5555/api/providers/sncf/trains/{trainNo}
- http://localhost:5555/api/providers/sncf/realtime/{trainUid}
- http://localhost:5555/api/providers/db/lookup
- http://localhost:5555/api/providers/db/trip
- http://localhost:5555/api/providers/db/realtime
- http://localhost:5555/api/providers/dsb/trip
- http://localhost:5555/api/providers/dsb/realtime
- http://localhost:5555/api/providers/rne/realtime

You can customize payloads under mocks/data/*.
