import express from "express";
import cors from "cors";
import path from "node:path";
import { fileURLToPath } from "node:url";
import fs from "node:fs";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
app.use(cors());
app.use(express.json());

function loadJson(relPath: string) {
  const abs = path.join(__dirname, "data", relPath);
  const raw = fs.readFileSync(abs, "utf8");
  return JSON.parse(raw);
}

// ---- SNCF ----
app.post("/api/providers/sncf/booking/validate", (req, res) => {
  return res.json(loadJson("sncf/booking_validate.json"));
});
app.get("/api/providers/sncf/trains/:trainNo", (req, res) => {
  return res.json(loadJson("sncf/trip.json"));
});
app.get("/api/providers/sncf/realtime/:trainUid", (req, res) => {
  return res.json(loadJson("sncf/realtime.json"));
});

// ---- DB (Deutsche Bahn) ----
app.get("/api/providers/db/lookup", (req, res) => {
  return res.json(loadJson("db/lookup.json"));
});
app.get("/api/providers/db/trip", (req, res) => {
  return res.json(loadJson("db/trip.json"));
});
app.get("/api/providers/db/realtime", (req, res) => {
  return res.json(loadJson("db/realtime.json"));
});

// ---- DSB ----
app.get("/api/providers/dsb/trip", (req, res) => {
  return res.json(loadJson("dsb/trip.json"));
});
app.get("/api/providers/dsb/realtime", (req, res) => {
  return res.json(loadJson("dsb/realtime.json"));
});

// ---- RNE ----
app.get("/api/providers/rne/realtime", (req, res) => {
  return res.json(loadJson("rne/realtime.json"));
});

const port = process.env.MOCKS_PORT || 5555;
app.listen(port, () => console.log(`Mock server running on :${port}`));
